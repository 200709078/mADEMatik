<?php
use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'can:admin', 'password.confirm'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::resource('projects', Admin\ProjectController::class)->except('show');
    Route::resource('categories', Admin\CategoryController::class)->except('show');
    Route::post('projects/{project}/media', [Admin\MediaController::class, 'store'])->name('media.store');
    Route::patch('projects/{project}/media/{media}', [Admin\MediaController::class, 'update'])->name('media.update');
    Route::delete('projects/{project}/media/{media}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
    Route::resource('comments', Admin\CommentController::class)->only(['index', 'update', 'destroy']);
    Route::patch('comment-reports/{report}', [Admin\CommentController::class, 'resolveReport'])->name('comment-reports.resolve');
    Route::resource('users', Admin\UserController::class)->only(['index', 'edit', 'update', 'destroy']);
    Route::resource('contact-messages', Admin\ContactMessageController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::get('activity', [Admin\ActivityLogController::class, 'index'])->name('activity.index');
});
