<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MailImportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingImportController;
use App\Http\Controllers\CountdownController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentOverviewController;
use App\Http\Controllers\OpenTasksController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripShareController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->is_admin ? 'admin.dashboard' : 'dashboard')
    : redirect()->route('login'));
Route::get('/shared/countdown/{token}', [CountdownController::class, 'public'])->name('countdown.public');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::get('/mail-import', [MailImportController::class, 'index'])->name('mail-import.index');
    Route::post('/mail-import', [MailImportController::class, 'store'])->name('mail-import.store');
});

Route::middleware(['auth', 'not_admin'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/tasks', OpenTasksController::class)->name('tasks.index');
    Route::get('/documents', DocumentOverviewController::class)->name('documents.index');
    Route::get('/countdown', CountdownController::class)->name('countdown.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/email-aliases', [SettingsController::class, 'storeEmailAlias'])->name('settings.email-aliases.store');
    Route::delete('/settings/email-aliases/{alias}', [SettingsController::class, 'destroyEmailAlias'])->name('settings.email-aliases.destroy');

    Route::resource('trips', TripController::class);
    Route::post('/trips/{trip}/shares', [TripShareController::class, 'store'])->name('trips.shares.store');
    Route::delete('/trips/{trip}/shares/{user}', [TripShareController::class, 'destroy'])->name('trips.shares.destroy');
    Route::post('/trips/{trip}/bookings/import', [BookingImportController::class, 'store'])->name('trips.bookings.import');
    Route::resource('trips.bookings', BookingController::class)->shallow()->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::resource('trips.tasks', TaskController::class)->shallow()->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::resource('trips.documents', DocumentController::class)->shallow()->only(['create', 'store', 'edit', 'update', 'destroy']);
});
