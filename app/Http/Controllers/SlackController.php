<?php

namespace App\Http\Controllers;

use App\Services\Slack\SlackInteractionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        if (isset($payload['command'])) {
            return $this->handleSlashCommand($payload, $handler);
        }

        $handler->handleEvent($payload);

        return response('', 200);
    }

    private function handleSlashCommand(array $payload, SlackInteractionHandler $handler): Response
    {
        $command = $payload['command'] ?? '';
        $userId = $payload['user_id'] ?? '';
        $channelId = $payload['channel_id'] ?? '';
        $triggerId = $payload['trigger_id'] ?? '';

        if ($command === '/lunch') {
            $handler->handleLunchDashboard($userId, $channelId, $triggerId);
        }

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
