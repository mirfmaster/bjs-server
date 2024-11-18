<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable()->index();
            $table->string('kind')->index();
            $table->string('instagram_user_id')->nullable();
            $table->string('media_id')->nullable();
            $table->string('target');
            $table->bigInteger('requested')->default(0);
            $table->bigInteger('margin_request')->default(0);
            $table->bigInteger('start_count')->default(0);
            $table->bigInteger('processed')->default(0);
            $table->bigInteger('partial_count')->default(0);
            $table->bigInteger('bjs_id')->nullable()->index();
            $table->integer('priority')->default(0);
            $table->string('status')->default('pending')->index();
            $table->text('note')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('source')->nullable();
            $table->string('status_bjs')->default('pending');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
