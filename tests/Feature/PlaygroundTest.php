<?php

namespace Tests\Feature;

use App\Client\UtilClient;
use App\Utils\InstagramID;
use Tests\TestCase;

class PlaygroundTest extends TestCase
{
    /**
     * To run this test:
     * php artisan test --filter=PlaygroundTest::test_IG_get_info
     */
    public function test_ig_get_info(): void
    {

        $cli = new UtilClient;
        $info = $cli->__IGGetInfo('justinbieber');
        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: PlaygroundTest#test_IG_get_info $info: "\n"', $info); // __AUTO_GENERATED_PRINT_VAR_END__
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=PlaygroundTest::test_IG_get_info_media
     */
    public function test_ig_get_info_media(): void
    {
        // https://www.instagram.com/p/DEFz5wWS6B6/?img_index=1
        $cli = new UtilClient;
        $info = $cli->__IGGetInfoMedia('DEFz5wWS6B6');
        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: PlaygroundTest#test_IG_get_info $info: "\n"', $info); // __AUTO_GENERATED_PRINT_VAR_END__
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=PlaygroundTest::test_IG_get_media_info
     */
    public function test_ig_get_media_info(): void
    {
        // https://www.instagram.com/p/DEFz5wWS6B6/?img_index=1
        $shortcode = 'DEFz5wWS6B6';
        $ig = new InstagramID;
        $mediaID = $ig->fromCode($shortcode);

        $mediaID = '3524457040519166164';
        $cli = new UtilClient;
        $info = $cli->__IGGetMediaInfo($mediaID);
        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: PlaygroundTest#test_IG_get_info $info: "\n"', $info); // __AUTO_GENERATED_PRINT_VAR_END__
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=PlaygroundTest::test_bjs_get_media_data
     */
    public function test_bjs_get_media_data(): void
    {
        // https://www.instagram.com/p/DEFz5wWS6B6/?img_index=1
        $shortcode = 'DEFz5wWS6B6';

        $cli = new UtilClient;
        $info = $cli->BJSGetMediaData($shortcode);
        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: PlaygroundTest#test_IG_get_info $info: "\n"', $info); // __AUTO_GENERATED_PRINT_VAR_END__
        $this->assertTrue(true);
    }
}
