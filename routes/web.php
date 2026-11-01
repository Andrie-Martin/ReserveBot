<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RepController;
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
