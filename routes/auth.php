<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\SendEmailVerificationNotificationController;
use App\Http\Controllers\Auth\ShowEmailVerificationNoticeController;
use App\Http\Controllers\Auth\ShowLoginController;
use App\Http\Controllers\Auth\ShowRequestPasswordController;
use App\Http\Controllers\Auth\ShowResetPasswordController;
use App\Http\Controllers\Auth\StoreLoginController;
use App\Http\Controllers\Auth\StoreRequestPasswordController;
use App\Http\Controllers\Auth\StoreResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

Route::prefix('auth')
    ->name('auth.')
    ->group(function () {
        Route::middleware(['guest'])->group(function () {
            Route::prefix('login')->group(function () {
                Route::get('/', ShowLoginController::class)
                    ->name('login');

                Route::post('/store', StoreLoginController::class)
                    ->middleware(['throttle:5,1', ProtectAgainstSpam::class])
                    ->name('login.store');
            });

            Route::prefix('password')->group(function () {
                Route::get('/', ShowRequestPasswordController::class)
                    ->name('request-password');

                Route::post('store', StoreRequestPasswordController::class)
                    ->middleware(['throttle:3,1', ProtectAgainstSpam::class])
                    ->name('request-password.store');

                Route::get('token/{token}', ShowResetPasswordController::class)
                    ->name('reset-password')
                    ->middleware('signed');

                Route::post('reset', StoreResetPasswordController::class)
                    ->middleware(['throttle:3,1', ProtectAgainstSpam::class])
                    ->name('reset-password.store');
            });
        });

        Route::middleware(['auth'])->group(function () {
            Route::post('logout', LogoutController::class)
                ->name('logout');

            Route::prefix('email')->group(function () {
                Route::get('verify', ShowEmailVerificationNoticeController::class)
                    ->name('verification.notice');

                Route::get('verify/{id}/{hash}', VerifyEmailController::class)
                    ->middleware(['signed'])->name('verification.verify');

                Route::post('verification-notification', SendEmailVerificationNotificationController::class)
                    ->middleware(['throttle:6,1', ProtectAgainstSpam::class])
                    ->name('verification.send');
            });
        });
    });
