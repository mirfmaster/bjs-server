<?php

namespace App\Client;

use App\Utils\InstagramID;
use App\Utils\Util;
use Illuminate\Support\Facades\Http;
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

    public static function withOrderMargin(float $value, float $bonusPercent = 10): int
    {
        $bonusValue = $value * ($bonusPercent / 100);
        $bonusValue = min(100, $bonusValue);
        $bonusValue = max(10, $bonusValue);

        $newValue = $value + $bonusValue;

        return round($newValue);
    }
}
