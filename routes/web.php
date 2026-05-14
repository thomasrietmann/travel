<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('trips', TripController::class);
    Route::resource('trips.bookings', BookingController::class)->shallow()->except(['show']);
    Route::resource('trips.tasks', TaskController::class)->shallow()->except(['show']);
    Route::resource('trips.documents', DocumentController::class)->shallow()->except(['show']);
});
