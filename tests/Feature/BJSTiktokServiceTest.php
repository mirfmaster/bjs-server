<?php

namespace Tests\Feature;

use App\Services\BJSTiktokService;
use Tests\TestCase;

class BJSTiktokServiceTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_fetch_tiktok()
    {
        /** @var \App\Services\BJSTiktokService $service */
        $service = app(BJSTiktokService::class);
        $service->fetch();
        $pendings = $service->getPendingOrders();
        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: BJSTiktokServiceTest#test_fetch_tiktok $pendings: "\n"', $pendings); // __AUTO_GENERATED_PRINT_VAR_END__
    }
}
