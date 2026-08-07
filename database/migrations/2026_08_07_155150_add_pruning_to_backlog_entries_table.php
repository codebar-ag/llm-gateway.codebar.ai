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
             * When the payload columns were emptied — see `backlog:prune`.
             *
             * Its own column rather than inferring from a null `request`, so a
             * pruned entry stays distinguishable from one that never carried a
             * body: the page can say "removed on", not guess "never there".
             */
            $table->timestampTz('pruned_at')->nullable();

            // Nullable now, because pruning is the first thing that ever
            // takes a request away from a row.
            $table->json('request')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('backlog_entries', function (Blueprint $table) {
            $table->dropColumn('pruned_at');
            $table->json('request')->nullable(false)->change();
        });
    }
};
