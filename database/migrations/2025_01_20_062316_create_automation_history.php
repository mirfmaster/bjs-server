<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('last_checked')->nullable()->after('price')->index();
            $table->string('latest_media_id')->nullable()->after('price');
            $table->integer('autolike_target')->nullable()->after('price');
        });
        Schema::create('automation_histories', function (Blueprint $table) {
            $table->id();
            $table->string('url')->nullable();
            $table->string('status')->nullable();
            $table->string('media_id')->unique()->comment('Instagram ID for a posted media');
            $table->timestamp('media_created_at')->nullable();
            $table->string('instagram_user_id');
            $table->foreignId('automation_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('autolike_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->timestamps();

            $table->index('instagram_user_id');
            $table->index('media_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('automation_history');
    }
};
