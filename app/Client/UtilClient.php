<?php

namespace App\Client;

use App\Utils\InstagramID;
use App\Utils\Util;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UtilClient
{
    public function isValidIGUrl($url)
    {
        $sanitized_url = filter_var($url, FILTER_SANITIZE_URL);

        // If the sanitized URL is different from the original, it had unsafe characters
        if ($sanitized_url !== $url) {
            return false;
        }

        // Parse the URL to retrieve the host
        $parsed_url = parse_url($sanitized_url);
        if (empty($parsed_url['host'])) {
            return false;
        }

        // Check if the host is an Instagram domain (allowing for subdomains)
        return preg_match('/(?:^|\.)instagram\.com$/', $parsed_url['host']) === 1;
    }

    public static function getIgMediaId($link, $returnShortcode = false)
    {
        if (! self::isValidIGUrl($link)) {
            return null;
        }

        // Check if the necessary paths are present in the URL
        if (! Str::contains($link, ['/tv/', '/reel/', '/p/'])) {
            return null;
        }

        $link = Str::replaceFirst('/tv/', '/p/', $link);
        $link = Str::replaceFirst('/reel/', '/p/', $link);

        // Extract the shortcode from the URL
        $parts = explode('/p/', $link);
        if (isset($parts[1])) {
            $shortcode = explode('/', $parts[1])[0];
            try {
                if ($returnShortcode) {
                    InstagramID::fromCode($shortcode);

                    return $shortcode;
                }

                return InstagramID::fromCode($shortcode);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public static function getMediaCode($link)
    {
        if (! self::isValidIGUrl($link)) {
            return null;
        }

        // Check if the necessary paths are present in the URL
        if (! Str::contains($link, ['/tv/', '/reel/', '/p/'])) {
            return null;
        }

        $link = Str::replaceFirst('/tv/', '/p/', $link);
        $link = Str::replaceFirst('/reel/', '/p/', $link);

        // Extract the shortcode from the URL
        $parts = explode('/p/', $link);
        if (isset($parts[1])) {
            $shortcode = explode('/', $parts[1])[0];
            try {
                InstagramID::fromCode($shortcode);

                return $shortcode;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Fetches Instagram user information using their public profile API.
     * This function attempts to retrieve user profile data with retry mechanism and proxy support.
     *
     * @param  string  $username  The Instagram username to fetch information for
     * @return object {
     *                username: string              - The requested Instagram username
     *                found: boolean               - Whether the user was found
     *                retry: boolean               - Whether the request should be retried (e.g., due to rate limiting)
     *                pk?: string                  - User's Instagram ID (only if found)
     *                is_private?: boolean         - Whether the account is private (only if found)
     *                has_anonymous_profile_picture?: boolean - Whether user has default profile picture (only if found)
     *                total_media_timeline?: int   - Count of user's posts (only if found)
     *                follower_count?: int         - Count of user's followers (only if found)
     *                following_count?: int        - Count of users this account follows (only if found)
     *                }
     *
     * @throws \Illuminate\Http\Client\RequestException When the HTTP request fails
     * @throws \Exception For general errors (timeout, SSL issues, etc.)
     */
    public function __IGGetInfo($username)
    {
        $maxRetries = 4; // 3 * 1.5 rounded down for simplicity

        $resp = [
            'username' => $username,
            'found' => false,
            'retry' => false,
        ];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Generate a new proxy URL for each attempt to avoid IP blocks
                $proxy = Util::generateProxyUrl();

                // Make request to Instagram's public profile API
                $response = Http::withHeaders([
                    'X-IG-App-ID' => '936619743392459',
                    'Origin' => 'https://www.instagram.com',
                    'Referer' => 'https://www.instagram.com',
                    'X-ASBD-ID' => '129477',
                ])
                    ->timeout(6)
                    ->withOptions(['proxy' => $proxy])
                    ->get('https://www.instagram.com/api/v1/users/web_profile_info/', [
                        'username' => $username,
                    ]);

                // Handle user not found case
                if ($response->status() === 404) {
                    return (object) $resp;
                }

                // Process successful response
                if ($response->successful() && isset($response['data']['user'])) {
                    $user = $response['data']['user'];
                    if (empty($user)) {
                        break;
                    }

                    // Return full user profile data
                    return (object) [
                        'username' => $username,
                        'found' => true,
                        'pk' => $user['id'],
                        'is_private' => $user['is_private'],
                        'has_anonymous_profile_picture' => strpos($user['profile_pic_url'], '2446069589734326272') !== false,
                        'total_media_timeline' => intval($user['edge_owner_to_timeline_media']['count']),
                        'follower_count' => $user['edge_followed_by']['count'],
                        'following_count' => $user['edge_follow']['count'],
                    ];
                }

                // Handle cases where we need to retry
                $body = $response->body();
                if ($response->status() === 200 && str_contains($body, 'Login • Instagram')) {
                    // We got redirected to login page - likely rate limited
                    $resp['retry'] = true;

                    return (object) $resp;
                }

                if (empty($body)) {
                    // Empty response usually indicates a temporary error
                    $resp['retry'] = true;

                    return (object) $resp;
                }
            } catch (\Exception $e) {
                // Network timeouts and SSL errors should trigger a retry
                if (
                    str_contains($e->getMessage(), 'timed out') ||
                    str_contains($e->getMessage(), 'unexpected eof while')
                ) {
                    $resp['retry'] = true;

                    continue;
                }

                // For 404 responses, we can stop trying
                if (
                    $e instanceof \Illuminate\Http\Client\RequestException &&
                    $e->response?->status() === 404
                ) {
                    break;
                }
            }
        }

        // If we've exhausted all retries without success, return base response
        return (object) $resp;
    }

    /**
     * Fetches Instagram media information using a shortcode from Instagram's GraphQL API.
     * Makes multiple attempts with proxy support to handle rate limiting and temporary failures.
     *
     * @param  string  $shortcode  The Instagram media shortcode (from the URL)
     * @return object {
     *                found: boolean               - Whether the media was found
     *                is_video?: boolean          - Whether the media is a video
     *                is_view_count_disabled?: boolean - Whether view count display is disabled
     *                comments_disabled?: boolean  - Whether comments are disabled for the post
     *                commenting_disabled_for_viewer?: boolean - Whether the current viewer can't comment
     *                like_count?: int            - Number of likes on the post
     *                comment_count?: int         - Number of comments on the post
     *                owner_id?: string           - Instagram ID of the post owner
     *                owner_username?: string     - Username of the post owner
     *                owner_is_private?: boolean  - Whether the owner's account is private
     *                media_id?: string          - Instagram's internal ID for this media
     *                }
     *
     * @throws \Illuminate\Http\Client\RequestException When the HTTP request fails
     * @throws \Exception For network or other errors
     */
    public function __IGGetInfoMedia(string $shortcode)
    {
        $maxRetries = 3;
        $graphqlVariables = [
            'shortcode' => $shortcode,
            'child_comment_count' => 0,
            'fetch_comment_count' => 0,
            'parent_comment_count' => 0,
            'has_threaded_comments' => true,
        ];

        $resp = ['found' => false];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $headers = [
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Origin' => 'https://www.instagram.com',
                    'Referer' => 'https://www.instagram.com',
                    'X-IG-App-ID' => '936619743392459',
                    'X-ASBD-ID' => '129477',
                    'X-IG-WWW-Claim' => '0',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'X-Bloks-Version-Id' => 'abaff5d09a530689e609e838538ae53475ff0cac083a548efad6633e0e625cf',
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                    // 'Sec-Fetch-Site' => 'same-origin',
                    // 'Sec-Fetch-Mode' => 'cors',
                    // 'Sec-Ch-Ua-Platform' => '"Linux"',
                    // 'Sec-Ch-Ua-Platform-Version' => '"6.8.0"',
                ];
                $proxy = Util::generateProxyUrl();
                $response = Http::withHeaders($headers)
                    ->timeout(5)
                    ->withOptions(['proxy' => $proxy])
                    ->get('https://www.instagram.com/graphql/query/', [
                        // 'query_hash' => 'b3055c01b4b222b8a47dc12b090e4e64',
                        'variables' => json_encode($graphqlVariables),
                    ]);

                // Handle successful response
                if ($response->successful() && isset($response['data']['shortcode_media'])) {
                    $media = $response['data']['shortcode_media'];

                    return (object) [
                        'found' => true,
                        'is_video' => $media['is_video'],
                        'is_view_count_disabled' => ! $media['like_and_view_counts_disabled'],
                        'comments_disabled' => $media['comments_disabled'],
                        'commenting_disabled_for_viewer' => $media['commenting_disabled_for_viewer'],
                        'like_count' => $media['edge_media_preview_like']['count'],
                        'comment_count' => $media['edge_media_preview_comment']['count'],
                        'owner_id' => $media['owner']['id'],
                        'owner_username' => $media['owner']['username'],
                        'owner_is_private' => $media['owner']['is_private'],
                        'media_id' => $media['id'],
                    ];
                }
            } catch (\Illuminate\Http\Client\RequestException $e) {
                // If media not found, stop retrying
                if ($e->response?->status() === 404) {
                    break;
                }

                // For other request errors, continue retrying
                if ($attempt < $maxRetries) {
                    continue;
                }

                // Log the final attempt error
                Log::warning("Instagram media fetch failed after {$maxRetries} attempts", [
                    'shortcode' => $shortcode,
                    'error' => $e->getMessage(),
                    'status' => $e->response?->status(),
                ]);
            } catch (\Exception $e) {
                // Log unexpected errors and stop retrying
                Log::warning('Unexpected error fetching Instagram media', [
                    'shortcode' => $shortcode,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        return (object) $resp;
    }

    /**
     * Fetches detailed information about an Instagram media post using its ID.
     *
     * @param  string  $mediaId  The Instagram media ID
     * @return object {
     *                found: boolean               - Whether the media was found
     *                pk?: string                  - Media's ID
     *                code?: string               - Media shortcode
     *                media_type?: int            - Type of media (1=photo, 2=video, etc)
     *                taken_at?: int              - Timestamp when media was posted
     *                like_count?: int            - Number of likes
     *                comment_count?: int         - Number of comments
     *                caption_text?: string       - Caption text if any
     *                owner_id?: string           - Media owner's Instagram ID
     *                owner_username?: string     - Media owner's username
     *                is_video?: boolean          - Whether media is a video
     *                comments_disabled?: boolean - Whether comments are disabled
     *                image_versions?: array      - Available image versions with urls
     *                }
     */
    public function __IGGetMediaInfo(string $mediaId)
    {
        $maxRetries = 3;

        $mediaId = '3524457040519166164';
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $proxy = Util::generateProxyUrl();
                $response = Http::withHeaders([
                    // 'Accept-Language' => 'en-US,en;q=0.9',
                    // 'Origin' => 'https://www.instagram.com',
                    // 'Referer' => 'https://www.instagram.com',
                    // 'X-IG-App-ID' => '936619743392459',
                    // 'X-ASBD-ID' => '129477',
                    // 'X-IG-WWW-Claim' => '0',
                    // 'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                    // 'Sec-Fetch-Site' => 'same-origin',
                    // 'Sec-Ch-Ua-Platform' => '"Linux"',
                    'X-IG-App-ID' => '936619743392459',
                    'Origin' => 'https://www.instagram.com',
                    'Referer' => 'https://www.instagram.com/justinbieber/p/DDpYwFGRPjU/',
                    'X-ASBD-ID' => '129477',
                    'x-ig-www-claim' => 'hmac.AR0EZAiQYyWysVSFbh83N1yCSXWzI_X7M81FXlJxVLuZ-MGu',
                    'X-Requested-With' => 'XMLHttpRequest',
                    // 'X-Web-Session-Id' => 'haflhv:as7nf6:es4c5y',
                ])
                    ->timeout(5)
                    ->withOptions(['proxy' => $proxy])
                    ->get("https://www.instagram.com/api/v1/media/{$mediaId}/info/");

                if ($response->successful() && isset($response['items'][0])) {
                    $media = $response['items'][0];

                    return (object) [
                        'found' => true,
                        'pk' => $media['pk'],
                        'code' => $media['code'],
                        'media_type' => $media['media_type'],
                        'taken_at' => $media['taken_at'],
                        'like_count' => $media['like_count'] ?? 0,
                        'comment_count' => $media['comment_count'] ?? 0,
                        'caption_text' => $media['caption']['text'] ?? null,
                        'owner_id' => $media['user']['pk'],
                        'owner_username' => $media['user']['username'],
                        'is_private' => $media['user']['is_private'],
                        'is_video' => $media['media_type'] !== 1,
                        'comments_disabled' => $media['comments_disabled'] ?? false,
                        'like_and_view_counts_disabled' => $media['like_and_view_counts_disabled'] ?? false,
                        'image_versions' => $media['image_versions2']['candidates'] ?? [],
                    ];
                }
                dd((string) $response->body(), $response->reason());

                // Non-200 response or missing data
                Log::warning('Failed to fetch Instagram media info', [
                    'media_id' => $mediaId,
                    'status' => $response->status(),
                    'attempt' => $attempt,
                ]);
            } catch (\Exception $e) {
                // Log error but continue retrying if attempts remain
                Log::error('Error fetching Instagram media info', [
                    'media_id' => $mediaId,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt === $maxRetries) {
                    break;
                }
            }
        }

        // Return base response if all attempts fail
        return (object) ['found' => false];
    }

    /**
     * Fetches Instagram media data via BJS proxy service.
     *
     * @param  string  $code  The Instagram media shortcode
     * @param  bool  $verbose  Whether to output detailed info (for CLI)
     * @return object {
     *                error: boolean               - Whether there was an error
     *                code: string                - Media shortcode
     *                pk: string                  - Media ID
     *                owner_username: string      - Media owner's username
     *                owner_pk_id: string         - Media owner's ID
     *                like_and_view_counts_disabled: boolean - Whether like/view counts are hidden
     *                comment_count: int          - Number of comments
     *                like_count: int             - Number of likes
     *                found: boolean              - Whether the media was found
     *                }
     *
     * @throws \Exception When configuration is missing or API request fails
     */
    public function BJSGetMediaData(string $code)
    {
        // Validate auth configuration
        $auth = config('app.redispo_auth');
        if (empty($auth)) {
            throw new \Exception('Redispo auth configuration is missing');
        }

        try {
            // Make API request
            $response = Http::withHeaders([
                'authorization' => $auth,
            ])->get('http://172.104.183.180:12091/v2/proxy-ig/media-info-proxyv2', [
                'media_shortcode' => $code,
                'source' => 'belanjasosmed',
            ]);

            // Handle errors
            if (! $response->successful() || $response->json('error')) {
                throw new \Exception($response->json('message') ?? 'Error response from server');
            }

            $data = $response->json('data');
            if (empty($data['media'])) {
                throw new \Exception('Media data not found');
            }

            $media = $data['media'];

            // Construct response
            $result = [
                'error' => false,
                'found' => true,
                'code' => $code,
                'media_id' => $media['pk'],
                'owner_id' => $media['owner']['id'],
                'owner_username' => $media['owner']['username'],
                'owner_pk_id' => $media['owner']['pk_id'],
                'owner_is_private' => $media['owner']['is_private'],
                'like_and_view_counts_disabled' => $media['like_and_view_counts_disabled'],
                'comment_count' => $media['comment_count'],
                'like_count' => $media['like_count'],
            ];

            Log::info('Media data fetched successfully');

            return (object) $result;
        } catch (\Exception $e) {
            Log::error('Failed to fetch media data', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'error' => true,
                'found' => false,
                'code' => $code,
                'message' => $e->getMessage(),
            ];
        }
    }

    public static function withOrderMargin(float $value, float $bonusPercent = 10): int
    {
        $bonusValue = $value * ($bonusPercent / 100);
        $bonusValue = min(100, $bonusValue);
        $bonusValue = max(10, $bonusValue);

        $newValue = $value + $bonusValue;

        return round($newValue);
    }
}
