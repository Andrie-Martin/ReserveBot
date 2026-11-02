<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RepController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

// ---- Log in / log out ----
// 'guest' = only for people who aren't logged in (others go to their home page)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// ---- Pages for logged-in users ('auth' sends everyone else to /login) ----
Route::middleware('auth')->group(function () {
    // Everyone lands on their own home page
    Route::get('/', fn () => redirect(auth()->user()->homeUrl()))->name('home');

    // Phase 4: which rooms are free (every role)
    Route::get('/rooms', [AvailabilityController::class, 'index'])->name('rooms');

    // Phase 5: booking a room, equipment, or both (block reps)
    Route::middleware('role:rep')->group(function () {
        Route::get('/book', [BookingController::class, 'create'])->name('book');
        Route::post('/book', [BookingController::class, 'store'])->name('book.store');

        // Phase 6: a rep's own bookings, and cancelling one (with a reason)
        Route::get('/my-bookings', [BookingController::class, 'mine'])->name('bookings.mine');
        Route::post('/bookings/{reservation}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    });

    // One booking: the rep who made it, and their department's lab staff and admins
    Route::get('/bookings/{reservation}', [BookingController::class, 'show'])->name('bookings.show');

    // Phase 6: lab staff and admins approve or reject requests
    Route::middleware('role:lab_staff,admin')->group(function () {
        Route::get('/requests', [ReviewController::class, 'index'])->name('requests');
        Route::post('/bookings/{reservation}/approve', [ReviewController::class, 'approve'])->name('bookings.approve');
        Route::post('/bookings/{reservation}/reject', [ReviewController::class, 'reject'])->name('bookings.reject');
    });

    // Block reps
    Route::prefix('rep')->name('rep.')->middleware('role:rep')->group(function () {
        Route::get('/', [RepController::class, 'home'])->name('home');
    });

    // Lab staff (admins can open these too)
    Route::prefix('staff')->name('staff.')->middleware('role:lab_staff,admin')->group(function () {
        Route::get('/', [StaffController::class, 'home'])->name('home');
    });

    // Admins
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [AdminController::class, 'home'])->name('home');
    });
});

// ---- Any other address: our "Page not found" page ----
Route::fallback(fn () => abort(404));
