<?php

use App\Enums\RoleEnum;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'laravel-auth-middleware', RoleEnum::webDashboardMiddleware()])->group(function () {
    Route::get('/', DashboardController::class)->name('start.index');
});

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

include 'auth.php';
