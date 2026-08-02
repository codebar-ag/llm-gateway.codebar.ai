<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backlog_entries', function (Blueprint $table) {
            /**
             * Which endpoint the entry was logged for — see `BacklogKind`.
             *
             * Defaulted to `chat` rather than left nullable: every row that
             * existed before this column did was queued through /v1/responses
             * or /v1/chat/completions, so the default states what they are
             * instead of recording that nobody asked.
             */
            $table->string('kind')->default('chat')->after('status');

            // The Responses API narrows every read by kind, and the dashboard
            // filters by kind and then sorts by created_at — the same shape as
            // the composites the table already carries.
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('backlog_entries', function (Blueprint $table) {
            $table->dropIndex(['kind', 'created_at']);
            $table->dropColumn('kind');
        });
    }
};
