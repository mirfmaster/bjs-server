<?php

namespace App\Client;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Cache;

class RedisCookieJar extends CookieJar
{
    /**
     * @var SetCookie[] Loaded cookie data
     */
    private $cookies = [];

    private const CACHE_KEY = 'system:bjs:cookies';

    private const CACHE_TTL = 86400; // 24 hours

    public function __construct(bool $strictMode = false)
    {
        parent::__construct($strictMode);
        $this->load();
    }

    protected function load(): void
    {
        $cookies = Cache::get(self::CACHE_KEY, []);

        if (! empty($cookies)) {
            foreach ($cookies as $cookie) {
                $this->setCookie(new SetCookie($cookie));
            }
        }
    }

    public function persist(): void
    {
        $cookies = [];
        foreach ($this->cookies as $cookie) {
            $cookies[] = $cookie->toArray();
        }

        Cache::put(self::CACHE_KEY, $cookies, self::CACHE_TTL);
    }

    public function clear(?string $domain = null, ?string $path = null, ?string $name = null): void
    {
        parent::clear($domain, $path, $name);

        // Only clear Redis cache if we're clearing everything (no specific domain/path/name)
        if ($domain === null) {
            Cache::forget(self::CACHE_KEY);
        } else {
            // If clearing specific cookies, update the cache with remaining cookies
            $this->persist();
        }
    }

    public function setCookie(SetCookie $cookie): bool
    {
        $result = parent::setCookie($cookie);
        $this->persist();

        return $result;
    }
}
