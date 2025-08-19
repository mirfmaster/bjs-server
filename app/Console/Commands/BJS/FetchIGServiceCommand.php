<?php

namespace App\Console\Commands\BJS;

use App\Actions\BJS\FetchFollowOrder;
use App\Actions\BJS\FetchLikeOrder;
use App\Client\InstagramClient;
use App\Enums\BJSOrderStatus;
use App\Services\BJSService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FetchIGServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bjs:fetch-ig';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull new Instagram orders from BJS';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! $this->ready()) {
            return Command::SUCCESS;
        }

        /** @var BJSService */
        $bjsService = app(BJSService::class);
        /** @var InstagramClient */
        $igClient = app(InstagramClient::class);

        $this->info('Starting fetching like orders');
        /** @var FetchLikeOrder */
        $likeAct = app(FetchLikeOrder::class);
        // TODO: get service based on .env
        foreach ([167, 182] as $serviceID) {
            $likeAct->handle($bjsService, $serviceID, BJSOrderStatus::PENDING->value);
        }

        $this->info('Starting fetching follow orders');
        /** @var FetchFollowOrder */
        $follAct = app(FetchFollowOrder::class);
        // TODO: get service based on .env
        foreach ([164, 183] as $serviceID) {
            $follAct->handle($bjsService, $igClient, $serviceID, BJSOrderStatus::PENDING->value);
        }

        $this->call('order:cache');

        return Command::SUCCESS;
    }

    private function ready(): bool
    {
        if (! (bool) Redis::get('system:bjs:login-state')) {
            $this->warn('Skipping – login state is false');

            return false;
        }

        if (! app(BJSService::class)->auth()) {
            $this->warn('Authentication failed');

            return false;
        }

        return true;
    }
}
