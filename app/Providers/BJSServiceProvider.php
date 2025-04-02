<?php

namespace App\Providers;

use App\Client\BJSClient;
use App\Client\RedisCookieJar;
use App\Repository\RedisTiktokRepository;
use App\Services\BJSService;
use App\Services\BJSTiktokService;
use Illuminate\Support\ServiceProvider;

class BJSServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Bind the RedisCookieJar as a singleton
        $this->app->singleton(RedisCookieJar::class, function ($app) {
            return new RedisCookieJar();
        });

        // Bind BJSClient as a singleton with the cookie jar
        $this->app->singleton(BJSClient::class, function ($app) {
            return new BJSClient();
        });

        // Bind BJSService with the client dependency
        $this->app->bind(BJSService::class, function ($app) {
            return new BJSService(
                $app->make(BJSClient::class)
            );
        });

        // Bind Redis repository
        $this->app->singleton(RedisTiktokRepository::class, function ($app) {
            return new RedisTiktokRepository();
        });

        // Bind BJSTiktokService with dependencies
        $this->app->bind(BJSTiktokService::class, function ($app) {
            return new BJSTiktokService(
                $app->make(BJSClient::class),
                $app->make(RedisTiktokRepository::class)
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
