<?php

namespace App\Http\Controllers;

use App\Slack\SlackInteractionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SlackController extends Controller
{
    public function events(Request $request, SlackInteractionHandler $handler): Response
    {
        if ($request->header('X-Slack-Retry-Num')) {
            return response('', 200);
        }

        $payload = $request->all();
        if (($payload['type'] ?? null) === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge'] ?? '']);
        }

        $handler->handleEvent($payload);

        return response('', 200);
    }

    public function interactivity(Request $request, SlackInteractionHandler $handler): Response
    {
        if ($request->header('X-Slack-Retry-Num')) {
            return response('', 200);
        }

        $payload = json_decode($request->input('payload', '{}'), true);
        if (! is_array($payload)) {
            return response('Invalid payload', 400);
        }

        return $handler->handleInteractivity($payload);
    }
}
