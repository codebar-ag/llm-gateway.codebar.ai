<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backlog_entries', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('status')->default('queued');
            $table->string('model')->nullable();

            /**
             * Which gateway accepted the entry, frozen at queue time.
             *
             * Not resolved again in the worker: the default can move and a
             * gateway can be edited between accepting a request and running
             * it, and an answer has to come from the upstream the caller
             * actually addressed.
             *
             * Nullable because retiring a gateway is a soft delete but force
             * deleting one is not, and history outliving its upstream is
             * better than history disappearing with it.
             */
            $table->foreignUuid('gateway_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->json('request');
            // Encrypted: carries the caller's Authorization. Cleared once final.
            $table->text('headers')->nullable();
            $table->json('output')->nullable();
            $table->json('usage')->nullable();
            $table->json('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(2);
            $table->string('api_key_hash', 64)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['status', 'created_at']);
            // `ListResponsesController` and the dashboard both filter by
            // api_key_hash and then sort by created_at; a standalone
            // api_key_hash index cannot serve that sort, so the composite
            // covers both in one pass.
            $table->index(['api_key_hash', 'created_at']);
            // The dashboard filters by gateway and then sorts by created_at,
            // the same shape as the api_key_hash composite above.
            $table->index(['gateway_id', 'created_at']);
            // The dashboard filters and sorts by model on every refresh, and
            // the unfiltered listing orders by created_at alone, which the
            // ['status', 'created_at'] composite above cannot serve.
            $table->index('model');
            $table->index('created_at');
        });

        /**
         * The dashboard's `id LIKE 'resp_…%'` search only uses the primary key
         * index under the `C` locale or a pattern-matching operator class —
         * under a typical `en_US.UTF-8` cluster it falls back to a sequential
         * scan. Postgres only: SQLite (used in tests) has no locale-dependent
         * LIKE behaviour to fix.
         */
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'create index backlog_entries_id_pattern_index on backlog_entries (id text_pattern_ops)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backlog_entries');
    }
};
