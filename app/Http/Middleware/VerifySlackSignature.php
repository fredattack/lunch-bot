<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySlackSignature
{
    use ExtractsSlackTeamId;

    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('x-slack-request-timestamp');
        $signature = $request->header('x-slack-signature');

        if (! $timestamp || ! $signature) {
            return response('Missing Slack signature headers.', 401);
        }

        if (abs(time() - (int) $timestamp) > 60 * 5) {
            return response('Stale Slack request.', 401);
        }

        $signingSecret = $this->resolveSigningSecret($request);

        if (empty($signingSecret)) {
            Log::warning('Slack signing secret missing.');

            return response('Slack signing secret missing.', 500);
        }

        $baseString = sprintf('v0:%s:%s', $timestamp, $request->getContent());
        $computed = 'v0='.hash_hmac('sha256', $baseString, $signingSecret);

        if (! hash_equals($computed, $signature)) {
            return response('Invalid Slack signature.', 401);
        }

        return $next($request);
    }

    private function resolveSigningSecret(Request $request): ?string
    {
        $teamId = $this->extractTeamId($request);

        if ($teamId) {
            $organization = Organization::with('installation')
                ->where('provider', 'slack')
                ->where('provider_team_id', $teamId)
                ->first();

            if ($organization?->installation?->signing_secret) {
                return $organization->installation->signing_secret;
            }
        }

        return config('slack.signing_secret');
    }
}
