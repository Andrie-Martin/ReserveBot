<?php

use App\Http\Controllers\WebhookController;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Addresses that programs call, not people: no login, no sessions, no form tokens.
// (bootstrap/app.php loads this file without the usual "/api" in front of the addresses.)

// ---- Paused Messenger bot (kept for a possible Messenger version later) ----
Route::get('/webhook', [WebhookController::class, 'verify']);
Route::post('/webhook', [WebhookController::class, 'receive']);

// ---- Quick check that the website can reach the database (from Phase 2) ----
// Works even when the database is down, so it can show the error.
Route::get('/test-db', function () {
    try {
        return response()->json([
            'databaseTime' => DB::selectOne('SELECT NOW() AS now')->now,
            'rooms' => Room::where('is_active', true)->orderBy('name')->get(['id', 'name', 'building', 'capacity']),
        ]);
    } catch (Throwable $e) {
        report($e);

        return response('Database error: '.$e->getMessage(), 500)->header('Content-Type', 'text/plain');
    }
});
