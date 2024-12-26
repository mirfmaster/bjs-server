<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkersTable extends Migration
{
    public function up()
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('username')->index();
            $table->text('password');
            $table->string('status')->nullable();
            $table->integer('followers_count')->default(0);
            $table->integer('following_count')->default(0);
            $table->integer('media_count')->default(0)->nullable();
            $table->string('pk_id')->nullable()->index();
            $table->boolean('is_max_following_error')->default(false);
            $table->boolean('is_probably_bot')->default(false);
            $table->boolean('is_verified_email')->default(false);
            $table->boolean('has_profile_picture')->default(false);
            $table->timestamp('last_access')->nullable();
            $table->string('code')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('on_work')->default(false);
            $table->timestamp('last_work')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('workers');
    }
}
