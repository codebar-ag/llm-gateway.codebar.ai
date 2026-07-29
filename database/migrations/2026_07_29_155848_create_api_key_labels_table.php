<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_key_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();

            /**
             * The key hash a caller's entries carry, or the keyless sentinel.
             *
             * No foreign key is possible: backlog_entries.api_key_hash is
             * deliberately not unique — one key queues many entries — so there
             * is no single row for a reference to point at.
             *
             * The keyless bucket is stored as the literal string 'anonymous'
             * rather than as NULL because neither driver would enforce
             * uniqueness over NULLs: Postgres indexes are NULLS DISTINCT by
             * default and SQLite behaves the same, so two labels for "no key"
             * would both be accepted. Collision with a real caller is
             * impossible — a sha256 digest is 64 characters of [0-9a-f], which
             * 'anonymous' is not.
             */
            $table->string('api_key_hash', 64)->unique();

            // Unique so a name names one caller: two hashes sharing a label
            // would make the dashboard's caller filter ambiguous.
            $table->string('name')->unique();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_labels');
    }
};
