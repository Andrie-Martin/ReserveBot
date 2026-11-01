<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Messenger webhook from the first setup (the bot is paused; kept for a
 * possible Messenger version later). Messages are written to
 * storage/logs/laravel.log.
 */
class WebhookController extends Controller
{
    // Meta calls this once to confirm you control the URL
    public function verify(Request $request): Response
    {
        // PHP turns "hub.mode" into "hub_mode" in query strings
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');
        $expected = (string) config('services.messenger.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
            Log::info('Webhook verified!');

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    // Meta sends each new message here
    public function receive(Request $request): Response
    {
        Log::info('Messenger webhook', $request->all());

        if ($request->input('object') === 'page') {
            return response('OK', 200);
        }

        return response('Not Found', 404);
    }
}
