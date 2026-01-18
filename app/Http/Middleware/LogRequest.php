<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LogRequest
{
    public function handle(Request $request, Closure $next): Response|JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse
    {
        if ($this->shouldSkipLogging($request)) {
            /** @var Response|JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse */
            return $next($request);
        }

        if (! $this->canWriteLogs()) {
            /** @var Response|JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse */
            return $next($request);
        }

        $startTime = microtime(true);
        $requestId = $this->generateRequestId();

        Log::channel('requests')->withContext([
            'request_id' => $requestId,
        ]);

        $logTitle = sprintf('[%s] %s', $request->method(), $request->path());

        Log::channel('requests')->info($logTitle, [
            'event' => 'request.start',
            'request_id' => $requestId,
            'http' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'host' => $request->getHost(),
            ],
            'client' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            'params' => [
                'query' => $this->sanitizeData($request->query->all()),
                'body' => config('logging.request_logging.log_body', false)
                    ? $this->sanitizeData($request->except(['password', 'password_confirmation', 'token']))
                    : null,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            /** @var Response|JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse */
            $response = $next($request);

            $response->headers->set('X-Request-ID', $requestId);

            $statusCode = $response->getStatusCode();
            $logLevel = $this->getLogLevel($statusCode);
        } catch (Throwable $exception) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $routeName = $request->route()?->getName();
            $errorLogTitle = $routeName
                ? sprintf('[%s] %s (EXCEPTION)', $request->method(), $routeName)
                : sprintf('[%s] %s (EXCEPTION)', $request->method(), $request->path());

            Log::channel('requests')->error($errorLogTitle, [
                'event' => 'request.exception',
                'request_id' => $requestId,
                'http' => [
                    'route_name' => $routeName,
                ],
                'user' => [
                    'id' => auth()->id(),
                    'email' => auth()->user()?->email,
                ],
                'exception' => [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
                'performance' => [
                    'execution_time_ms' => $executionTime,
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                ],
                'timestamp' => now()->toIso8601String(),
            ]);

            throw $exception;
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        $routeName = $request->route()?->getName();
        $endLogTitle = $routeName
            ? sprintf('[%s] %s (%s)', $request->method(), $routeName, $statusCode)
            : sprintf('[%s] %s (%s)', $request->method(), $request->path(), $statusCode);

        Log::channel('requests')->$logLevel($endLogTitle, [
            'event' => 'request.end',
            'request_id' => $requestId,
            'http' => [
                'status_code' => $statusCode,
                'route_name' => $routeName,
            ],
            'user' => [
                'id' => auth()->id(),
                'email' => auth()->user()?->email,
            ],
            'performance' => [
                'execution_time_ms' => $executionTime,
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);

        return $response;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sensitive = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'signing_secret',
        ];

        $result = [];

        foreach ($data as $key => $value) {
            $keyString = (string) $key;
            $isSensitive = false;

            foreach ($sensitive as $sensitiveWord) {
                if (Str::contains(strtolower($keyString), $sensitiveWord)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $result[$keyString] = '***REDACTED***';
            } elseif (is_array($value)) {
                $result[$keyString] = $this->sanitizeData($value);
            } else {
                $result[$keyString] = $value;
            }
        }

        return $result;
    }

    private function shouldSkipLogging(Request $request): bool
    {
        if (! config('logging.request_logging.enabled', true)) {
            return true;
        }

        $currentPath = ltrim($request->path(), '/');

        /** @var array<int, string> $excludedPaths */
        $excludedPaths = config('logging.request_logging.excluded_paths', []);

        foreach ($excludedPaths as $path) {
            $normalizedPattern = ltrim($path, '/');

            if (Str::is($normalizedPattern, $currentPath)) {
                return true;
            }
        }

        /** @var array<int, string> $excludedRouteNames */
        $excludedRouteNames = config('logging.request_logging.excluded_route_names', []);
        $routeName = $request->route()?->getName();

        return $routeName && in_array($routeName, $excludedRouteNames, true);
    }

    private function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };
    }

    private function canWriteLogs(): bool
    {
        $logPath = storage_path('logs');

        if (! is_dir($logPath)) {
            return false;
        }

        return is_writable($logPath);
    }

    private function generateRequestId(): string
    {
        return str_replace('-', '', (string) Str::uuid());
    }
}
