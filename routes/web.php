<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\EmailLinkController;
use App\Http\Controllers\ManageEquipmentController;
use App\Http\Controllers\ManageRoomsController;
use App\Http\Controllers\ManageRulesController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\RepController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TodayController;
use Illuminate\Support\Facades\Route;

// ---- Log in / log out ----
// 'guest' = only for people who aren't logged in (others go to their home page)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Phase 9: "Forgot password?" emails a link to pick a new one
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:forgot-password')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:reset-password')->name('password.update');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Phase 9: the button in the "connect your email" email. 'signed' = only the exact link we sent,
// before it expires; it works whether or not you're logged in (e.g. opened on a phone).
Route::get('/connect-email/{user}/{hash}', [EmailLinkController::class, 'connect'])
    ->middleware(['signed', 'throttle:email-links'])->name('email.connect');

// ---- Pages for logged-in users ('auth' sends everyone else to /login) ----
// 'auth.session' logs a session out when the account's password changes (on another device, or by an admin).
// 'account' logs out turned-off accounts and asks for a new password after an admin set one.
Route::middleware(['auth', 'auth.session', 'account'])->group(function () {
    // Everyone lands on their own home page
    Route::get('/', fn () => redirect(auth()->user()->homeUrl()))->name('home');

    // Phase 9: first login (or after an admin set a new password): pick your own password
    Route::get('/welcome', [PasswordController::class, 'welcome'])->name('welcome');
    Route::put('/welcome', [PasswordController::class, 'saveWelcome'])->name('welcome.save');

    // Phase 9: My account (everyone): Information and Password tabs
    Route::get('/account', [AccountController::class, 'edit'])->name('account');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::post('/account/photo', [AccountController::class, 'photo'])->name('account.photo');
    Route::delete('/account/photo', [AccountController::class, 'removePhoto'])->name('account.photo.remove');
    Route::post('/account/email-link', [EmailLinkController::class, 'resend'])->name('email.resend');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('password.change');
    Route::get('/photos/{user}', [PhotoController::class, 'show'])->name('photos.show');

    // Phase 4: which rooms are free (every role)
    Route::get('/rooms', [AvailabilityController::class, 'index'])->name('rooms');

    // Phase 5: booking a room, equipment, or both (block reps)
    Route::middleware('role:rep')->group(function () {
        // Phase 9: only after the rep's Gmail or PUP webmail is connected
        Route::middleware('email.connected')->group(function () {
            Route::get('/book', [BookingController::class, 'create'])->name('book');
            Route::post('/book', [BookingController::class, 'store'])->name('book.store');
        });

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

        // Phase 7: the lab staff's day
        Route::get('/today', [TodayController::class, 'index'])->name('today');
        Route::post('/bookings/{reservation}/hand-out', [TodayController::class, 'handOut'])->name('bookings.hand-out');
        Route::post('/bookings/{reservation}/returned', [TodayController::class, 'returned'])->name('bookings.returned');
        Route::post('/bookings/{reservation}/no-show', [TodayController::class, 'noShow'])->name('bookings.no-show');
        Route::post('/bookings/{reservation}/staff-cancel', [TodayController::class, 'cancel'])->name('bookings.staff-cancel');
        Route::post('/bookings/{reservation}/move', [TodayController::class, 'move'])->name('bookings.move');

        // Phase 8: managing rooms, equipment and the booking rules
        Route::prefix('manage')->name('manage.')->group(function () {
            Route::get('/', fn () => redirect()->route('manage.rooms'))->name('index');
            Route::get('/rooms', [ManageRoomsController::class, 'index'])->name('rooms');
            Route::post('/rooms', [ManageRoomsController::class, 'store'])->name('rooms.store');
            Route::get('/rooms/{room}', [ManageRoomsController::class, 'edit'])->name('rooms.edit');
            Route::put('/rooms/{room}', [ManageRoomsController::class, 'update'])->name('rooms.update');
            Route::get('/equipment', [ManageEquipmentController::class, 'index'])->name('equipment');
            Route::post('/equipment', [ManageEquipmentController::class, 'store'])->name('equipment.store');
            Route::get('/equipment/{equipment}', [ManageEquipmentController::class, 'edit'])->name('equipment.edit');
            Route::put('/equipment/{equipment}', [ManageEquipmentController::class, 'update'])->name('equipment.update');
            Route::post('/categories', [ManageEquipmentController::class, 'storeCategory'])->name('categories.store');
            Route::put('/categories/{category}', [ManageEquipmentController::class, 'updateCategory'])->name('categories.update');
            Route::get('/rules', [ManageRulesController::class, 'edit'])->name('rules');
            Route::put('/rules', [ManageRulesController::class, 'update'])->name('rules.update');
            Route::put('/semesters/{term}', [ManageRulesController::class, 'updateTerm'])->name('terms.update');
        });
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

    // Phase 9 (admins only): the department's rep and lab staff accounts
    Route::prefix('accounts')->name('accounts.')->middleware('role:admin')->group(function () {
        Route::get('/', [AccountsController::class, 'index'])->name('index');
        Route::post('/', [AccountsController::class, 'store'])->name('store');
        Route::get('/{account}', [AccountsController::class, 'edit'])->name('edit');
        Route::put('/{account}', [AccountsController::class, 'update'])->name('update');
        Route::put('/{account}/password', [AccountsController::class, 'password'])->name('password');
        Route::delete('/{account}', [AccountsController::class, 'destroy'])->name('destroy');
    });
});

// ---- Any other address: our "Page not found" page ----
Route::fallback(fn () => abort(404));
