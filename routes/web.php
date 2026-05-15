<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingImportController;
use App\Http\Controllers\CountdownController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\OpenTasksController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripShareController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/tasks', OpenTasksController::class)->name('tasks.index');
    Route::get('/countdown', CountdownController::class)->name('countdown.index');

    Route::resource('trips', TripController::class);
    Route::post('/trips/{trip}/shares', [TripShareController::class, 'store'])->name('trips.shares.store');
    Route::delete('/trips/{trip}/shares/{user}', [TripShareController::class, 'destroy'])->name('trips.shares.destroy');
    Route::post('/trips/{trip}/bookings/import', [BookingImportController::class, 'store'])->name('trips.bookings.import');
    Route::resource('trips.bookings', BookingController::class)->shallow()->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::resource('trips.tasks', TaskController::class)->shallow()->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::resource('trips.documents', DocumentController::class)->shallow()->only(['create', 'store', 'edit', 'update', 'destroy']);
});
