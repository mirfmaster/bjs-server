<?php

namespace Tests\Feature;

use App\Client\UtilClient;
use Tests\TestCase;

class PlaygroundTest extends TestCase
{
    /**
     * To run this test:
     * php artisan test --filter=PlaygroundTest::test_IG_get_info
     */
    public function test_ig_get_info(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        $cli = new UtilClient();
        $info = $cli->__IGGetInfo('justinbieber');
        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: PlaygroundTest#test_IG_get_info $info: "\n"', $info); // __AUTO_GENERATED_PRINT_VAR_END__
        $this->assertTrue(true);
    }
}
