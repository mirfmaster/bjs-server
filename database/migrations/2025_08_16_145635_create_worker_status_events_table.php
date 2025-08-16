<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // 1. events table
        Schema::create('worker_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('previous_status')->nullable();
            $table->string('current_status');
            $table->string('activity')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['worker_id', 'created_at']);
            $table->index('created_at');
        });

        // 2. materialized view
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW mv_worker_status_30min AS
            SELECT
                date_trunc('minute', created_at) - interval '30 min' AS snapshot_ts,
                current_status                                          AS status,
                COUNT(*)::bigint                                        AS cnt
            FROM worker_status_events
            GROUP BY snapshot_ts, status
        SQL);

        // 3. index on the view
        DB::statement(
            'CREATE INDEX CONCURRENTLY idx_mv_ts_status ON mv_worker_status_30min (snapshot_ts, status)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_worker_status_30min');
        Schema::dropIfExists('worker_status_events');
    }
};
