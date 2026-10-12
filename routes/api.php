<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service:slug}', [ServiceController::class, 'show']);
Route::get('/services/{service}/availability', [ServiceController::class, 'availability']);

// Booking creation is open to guests too (name + phone)
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings/reference/{bookingNumber}', [BookingController::class, 'byReference']);

// Authenticated customers
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    // Admin only
    Route::middleware('admin')->group(function () {
        Route::get('/admin/dashboard', [BookingController::class, 'dashboard']);
        Route::get('/admin/bookings', [BookingController::class, 'index']);
        Route::patch('/admin/bookings/{booking}/status', [BookingController::class, 'updateStatus']);

        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::patch('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    });
});
