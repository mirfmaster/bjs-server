<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index()->nullable();
            $table->string('modem_type')->nullable();
            $table->string('apn')->nullable();
            $table->string('version')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('status');
            $table->string('mode')->default('worker');
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });

        Schema::create('device_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')
                ->constrained()
                ->onDelete('cascade');
            $table->integer('success_task_counter')->default(0);
            $table->string('connection_status');
            $table->integer('restart_modem_counter');
            $table->jsonb('errors_counter');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('device_statistics');
        Schema::dropIfExists('devices');
    }
};
