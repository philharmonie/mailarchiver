<?php

use App\Http\Controllers\Api\EmailWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::post('/api/webhook/email', [EmailWebhookController::class, 'receive'])
    ->name('api.webhook.email');

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('emails', [App\Http\Controllers\EmailController::class, 'index'])->name('emails.index');
    Route::get('emails/{email}', [App\Http\Controllers\EmailController::class, 'show'])->name('emails.show');
});

require __DIR__.'/settings.php';
