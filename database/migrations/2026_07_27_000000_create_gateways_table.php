<?php

declare(strict_types=1);

use App\Enums\GatewayProtocol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateways', function (Blueprint $table) {
            // The primary key is the URL segment callers put before /v1, so it
            // has to be unguessable rather than merely unique.
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('protocol')->default(GatewayProtocol::OpenAi->value);
            $table->string('base_url');
            // Overrides GatewayProtocol::defaultPath() for upstreams that sit
            // behind a prefix, e.g. /openai/v1/chat/completions on Azure.
            $table->string('path')->nullable();
            // Used only when the caller's payload has no model of its own.
            $table->string('default_model')->nullable();
            $table->unsignedInteger('timeout');
            $table->unsignedInteger('connect_timeout');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('is_default');
            $table->index('is_active');
        });

        /**
         * One default, enforced by the database rather than only by
         * `Gateways::makeDefault()`. A plain unique index would allow a single
         * non-default row, so the predicate is what makes it usable. Postgres
         * only, the same guard backlog_entries uses for its pattern index;
         * SQLite (used in tests) is covered by the service and its test.
         */
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'create unique index gateways_single_default_index on gateways (is_default) where is_default'
            );
        }

        $this->seedDefaultGateway();
    }

    public function down(): void
    {
        Schema::dropIfExists('gateways');
    }

    /**
     * Carry an existing single-upstream deployment across: the URL that used
     * to live in LITELLM_URL becomes the default gateway, so nothing has to
     * change in .env for requests to keep landing where they always did.
     */
    private function seedDefaultGateway(): void
    {
        DB::table('gateways')->insert([
            'id' => Str::uuid7()->toString(),
            'name' => 'Default',
            'protocol' => GatewayProtocol::OpenAi->value,
            'base_url' => Config::string('gateway.upstream.default_url'),
            'path' => null,
            'default_model' => null,
            'timeout' => Config::integer('gateway.upstream.max_timeout'),
            'connect_timeout' => Config::integer('gateway.upstream.connect_timeout'),
            'is_default' => true,
            'is_active' => true,
            'description' => 'Seeded from GATEWAY_SEED_URL when the gateways table was created.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
