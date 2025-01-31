<?php

namespace App\Utils;

class Util
{
    public static function generateProxyUrl($length = 12)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $random_string = substr(str_shuffle($characters), 0, $length);
        $username = config('app.pyproxy_username');
        $password = config('app.pyproxy_password');

        $proxy = "http://{$username}-zone-resi-region-id-st-jakarta-city-jakarta-session-$random_string-sessTime-10:{$password}@d169f2e23873ee25.tuf.as.pyproxy.io:16666";

        return $proxy;
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
