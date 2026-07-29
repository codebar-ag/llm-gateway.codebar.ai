<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operators who may open the dashboard.
     *
     * Deliberately absent: `password_reset_tokens` and `email_verified_at`.
     * There is no reset flow and no mail configuration, so a token table would
     * only be unused attack surface — accounts are made with
     * `php artisan user:register` on the server, and that is also how a
     * forgotten password is replaced.
     *
     * This is not the API's authentication. Callers send their own upstream key
     * and never have an account here; these rows only guard the pages that
     * render other people's prompts and answers.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
