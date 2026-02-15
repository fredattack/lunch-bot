<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

trait ExtractsSlackTeamId
{
    private function extractTeamId(Request $request): ?string
    {
        if ($request->has('payload')) {
            $payload = json_decode($request->input('payload', '{}'), true);
            if (is_array($payload)) {
                return $payload['team']['id'] ?? $payload['team_id'] ?? null;
            }
        }

        $data = $request->all();

        if (! empty($data)) {
            return $data['team_id']
                ?? $data['team']['id']
                ?? $data['event']['team']
                ?? null;
        }

        $raw = json_decode($request->getContent(), true);
        if (is_array($raw)) {
            return $raw['team_id']
                ?? $raw['team']['id']
                ?? $raw['event']['team']
                ?? null;
        }

        return null;
    }
}
