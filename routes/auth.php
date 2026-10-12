<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/giris', [AuthController::class, 'login'])->name('login');
    Route::post('/giris', [AuthController::class, 'authenticate'])->middleware('throttle:15,1');
    Route::get('/kayit', [AuthController::class, 'register'])->name('register');
    Route::post('/kayit', [AuthController::class, 'storeUser'])->middleware('throttle:5,10');
    Route::get('/sifremi-unuttum', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/sifremi-unuttum', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,5')->name('password.email');
    Route::get('/sifre-sifirla/{token}', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/sifre-sifirla', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.store');
});
Route::middleware('auth')->group(function () {
    Route::get('/eposta-dogrula', [AuthController::class, 'verifyEmail'])->name('verification.notice');
    Route::get('/eposta-dogrula/{id}/{hash}', [AuthController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/eposta-dogrulama-bildirimi', [AuthController::class, 'resendVerification'])->middleware('throttle:3,5')->name('verification.send');
    Route::get('/sifre-onayi', [AuthController::class, 'confirmPassword'])->name('password.confirm');
    Route::post('/sifre-onayi', [AuthController::class, 'confirm'])->middleware('throttle:5,1');
    Route::post('/cikis', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profilim', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profilim', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profilim/sifre', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
    Route::put('/profilim/bildirim-tercihleri', [ProfileController::class, 'preferences'])->name('profile.preferences');
    Route::post('/profilim/silme-talebi', [ProfileController::class, 'requestDeletion'])->middleware('throttle:2,60')->name('profile.deletion');
    Route::get('/bildirimler', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/bildirimler/okundu', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/bildirimler/{notification}', [NotificationController::class, 'read'])->name('notifications.read');
});
