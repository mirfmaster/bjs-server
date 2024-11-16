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

    public static function __IGGetInfo($username)
    {
        $resp = [
            'username' => $username,
            'found' => false,
            'processed' => false,
            'retry' => false,
            'probably_banned' => false,
            'data' => [],
            'debug' => [],
            'counter' => 1,
        ];

        for ($i = 1; $i <= round(3 * 1.5); $i++) {
            try {
                $resp['counter'] = $i;
                $proxy = Util::generateProxyUrl();
                $response = Http::withHeaders([
                    'X-IG-App-ID' => '936619743392459',
                    'Origin' => 'https://www.instagram.com',
                    'Referer' => 'https://www.instagram.com',
                    "X-ASBD-ID" => 198387,
                ])
                    ->timeout(6)
                    ->withOptions([
                        'proxy' => $proxy,
                    ])
                    ->get('https://www.instagram.com/api/v1/users/web_profile_info/', [
                        'username' => $username,
                    ]);

                $resp['processed'] = true;
                if ($response->status() === 404) {
                    return (object) $resp;
                } elseif ($response->successful() && isset($response['data'])) {
                    $data = $response['data']['user'];
                    if (empty($data)) {
                        $resp['probably_banned'] = true;
                        break;
                    }

                    $resp['found'] = true;
                    $resp['data'] = (object) $response['data']['user'];

                    $total_media = intval($data['edge_owner_to_timeline_media']['count']);
                    $anonym = strpos($data['profile_pic_url'], '2446069589734326272') !== false;

                    $resp['pk'] = $data['id'];
                    $resp['is_private'] = $data['is_private'];
                    $resp['has_anonymous_profile_picture'] = $anonym;
                    $resp['total_media_timeline'] = $total_media;
                    $resp['follower_count'] = $data['edge_followed_by']['count'];
                    $resp['following_count'] = $data['edge_follow']['count'];
                    break;
                } else {
                    // Login • Instagram
                    $message = $response->body();
                    if ($response->status() == 200 && str_contains($message, "Login • Instagram")) {
                        dump("Got redirected to login page");
                        $resp['retry'] = true;
                    } elseif ($message == "") {
                        dump("Got empty response");
                        $resp['retry'] = true;
                    } else {
                        dump($message);
                    }
                    return (object) $resp;
                }
            } catch (\Illuminate\Http\Client\RequestException $e) {
                if ($e->response && $e->response->status() == 404) {
                    $resp['debug'][] = $e->getMessage();
                    break;
                } else {
                    dump("requexcept " . $e->getMessage());
                    $resp['debug'][] = 'got error ' . $e->response->status() . ", try again... ($proxy)";
                }
            } catch (\Exception $e) {
                $timedOut = str_contains($e->getMessage(), "timed out");
                $sslError = str_contains($e->getMessage(), "unexpected eof while");
                if ($timedOut || $sslError) {
                    $resp['retry'] = true;
                    $resp['debug'][] = $e->getMessage();
                } else {
                    dump("except " . $e->getMessage());
                }
                break;
            }
        }

        return (object) $resp;
    }
}
