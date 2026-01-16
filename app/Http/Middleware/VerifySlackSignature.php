<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySlackSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signingSecret = config('slack.signing_secret');

        if (empty($signingSecret)) {
            Log::warning('Slack signing secret missing.');
            return response('Slack signing secret missing.', 500);
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (!$timestamp || !$signature) {
            return response('Missing Slack signature headers.', 401);
        }

        if (abs(time() - (int) $timestamp) > 60 * 5) {
            return response('Stale Slack request.', 401);
        }

        $baseString = sprintf('v0:%s:%s', $timestamp, $request->getContent());
        $computed = 'v0=' . hash_hmac('sha256', $baseString, $signingSecret);

        if (!hash_equals($computed, $signature)) {
            return response('Invalid Slack signature.', 401);
        }

        return $next($request);
    }
}
