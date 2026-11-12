<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HotelController::class, 'home'])->name('home');
Route::get('/search', [HotelController::class, 'search'])->name('hotels.search');
Route::get('/destination-suggestions', [HotelController::class, 'suggest'])->name('destinations.suggest');
Route::get('/hotels/{hotel}', [HotelController::class, 'show'])->name('hotels.show');
Route::get('/destinations/{city}', [DestinationController::class, 'show'])->name('destinations.show');

Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'dashboard'])->name('account');
    Route::post('/favourites/{hotel}', [AccountController::class, 'toggleFavourite'])->name('favourites.toggle');
    Route::post('/hotels/{hotel}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/my-bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/hotels/{hotel}/booking-request', [BookingController::class, 'store'])->name('bookings.store');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [StaffController::class, 'admin'])->name('dashboard');
    Route::get('/users', [StaffController::class, 'users'])->name('users');
    Route::delete('/users/{user}', [StaffController::class, 'deleteUser'])->name('users.delete');
    Route::get('/reviews', [StaffController::class, 'reviews'])->name('reviews');
    Route::patch('/reviews/{review}/moderate', [StaffController::class, 'moderateReview'])->name('reviews.moderate');
});

Route::prefix('manager')->name('manager.')->middleware(['auth', 'role:manager'])->group(function () {
    Route::get('/', [StaffController::class, 'manager'])->name('dashboard');
});
