<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = $this->extractTeamId($request);

        if (! $teamId) {
            Log::warning('ResolveOrganization: team_id not found in payload.');

            return response('Missing team_id in payload.', 400);
        }

        $organization = Organization::with('installation')
            ->where('provider', 'slack')
            ->where('provider_team_id', $teamId)
            ->first();

        if (! $organization) {
            Log::warning('ResolveOrganization: Organization not found.', ['team_id' => $teamId]);

            return response('Organization not found.', 403);
        }

        if (! $organization->installation) {
            Log::warning('ResolveOrganization: Installation not found.', ['team_id' => $teamId]);

            return response('Organization installation not found.', 403);
        }

        Organization::setCurrent($organization);

        return $next($request);
    }

    private function extractTeamId(Request $request): ?string
    {
        if ($request->has('payload')) {
            $payload = json_decode($request->input('payload', '{}'), true);
            if (is_array($payload)) {
                return $payload['team']['id'] ?? $payload['team_id'] ?? null;
            }
        }

        $data = $request->all();

        if (isset($data['team_id'])) {
            return $data['team_id'];
        }

        if (isset($data['team']['id'])) {
            return $data['team']['id'];
        }

        if (isset($data['event']['team'])) {
            return $data['event']['team'];
        }

        return null;
    }
}
