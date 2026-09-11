<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // 1. Extract provided API Key from headers, Bearer token, or query parameter
        $providedKey = $request->header('X-API-Key');
        if (!$providedKey && $bearer = $request->bearerToken()) {
            $providedKey = $bearer;
        }
        if (!$providedKey) {
            $providedKey = $request->query('api_key');
        }

        // 2. Resolve Master Key from .env or settings.json
        $masterKey = config('services.voice_core.api_key', env('VOICE_CORE_API_KEY'));
        if (empty($masterKey) && !app()->environment('testing')) {
            $settingsPath = base_path('data/settings.json');
            if (file_exists($settingsPath)) {
                $savedSettings = json_decode((string) @file_get_contents($settingsPath), true) ?? [];
                $masterKey = $savedSettings['voice_core_api_key'] ?? null;
            }
        }

        // 3. Check active database API keys
        $matchedApiKey = null;
        $hasDbKeys = false;
        try {
            $hasDbKeys = ApiKey::exists();
            if ($providedKey) {
                $matchedApiKey = ApiKey::where('key', (string) $providedKey)
                    ->where('is_active', true)
                    ->first();
            }
        } catch (\Throwable $e) {
            // Table might not be ready in some testing environments
            $hasDbKeys = false;
        }

        $isAuthorized = false;

        if ($matchedApiKey) {
            $isAuthorized = true;
            $request->attributes->set('authenticated_api_key', $matchedApiKey);
            // Increment request counter & record last_used_at
            try {
                $matchedApiKey->increment('requests_count');
                $matchedApiKey->updateQuietly(['last_used_at' => now()]);
            } catch (\Throwable $e) {
                // Ignore silent counter errors
            }
        } elseif (!empty($masterKey) && $providedKey && hash_equals((string) $masterKey, (string) $providedKey)) {
            $isAuthorized = true;
        } elseif (!$hasDbKeys && (empty($masterKey) || trim($masterKey) === '')) {
            // Open / Desktop Mode (No keys configured at all)
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing API key.',
            ], 401);
        }

        // 4. Process Request
        $response = $next($request);

        // 5. Record Operation Log
        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
        $this->recordLog($request, $response, $matchedApiKey, $responseTimeMs);

        return $response;
    }

    /**
     * Record API request details to api_logs table.
     */
    protected function recordLog(Request $request, Response $response, ?ApiKey $apiKey, int $responseTimeMs): void
    {
        try {
            $endpoint = '/' . ltrim($request->path(), '/');
            $method = $request->method();
            $statusCode = $response->getStatusCode();
            $actionType = $this->resolveActionType($request);
            $taskId = $this->resolveTaskId($request, $response);
            $summary = $this->buildRequestSummary($request, $response);

            ApiLog::create([
                'api_key_id' => $apiKey?->id,
                'action_type' => $actionType,
                'endpoint' => $endpoint,
                'method' => $method,
                'ip_address' => $request->ip(),
                'status_code' => $statusCode,
                'task_id' => $taskId,
                'request_summary' => $summary,
                'response_time_ms' => $responseTimeMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let logging failure break the API response
            \Illuminate\Support\Facades\Log::warning('Failed to record API log: ' . $e->getMessage());
        }
    }

    /**
     * Determine user-friendly action type based on endpoint and method.
     */
    protected function resolveActionType(Request $request): string
    {
        $path = $request->path();
        $method = strtoupper($request->method());

        if (str_contains($path, 'tts/generate')) {
            return 'tts_generate';
        }
        if (str_contains($path, 'stt/transcribe')) {
            return 'stt_transcribe';
        }
        if (str_contains($path, 'models/download')) {
            return 'model_download';
        }
        if (str_contains($path, 'models/downloads')) {
            return 'models_download_status';
        }
        if (str_contains($path, 'models')) {
            return 'models_list';
        }
        if (str_contains($path, 'tasks')) {
            if ($method === 'DELETE') {
                return 'task_delete';
            }
            if ($request->route('id')) {
                return 'task_show';
            }
            return 'tasks_index';
        }
        if (str_contains($path, 'profiles')) {
            if ($method === 'POST') {
                return 'profile_create';
            }
            if ($method === 'DELETE') {
                return 'profile_delete';
            }
            return 'profiles_index';
        }
        if (str_contains($path, 'system/stats')) {
            return 'system_stats';
        }

        return Str::slug(str_replace(['api/v1/', 'api/'], '', $path), '_') ?: 'api_request';
    }

    /**
     * Extract voice_task ID if available in route or response.
     */
    protected function resolveTaskId(Request $request, Response $response): ?int
    {
        if ($routeId = $request->route('id')) {
            if (is_numeric($routeId)) {
                return (int) $routeId;
            }
        }

        if (method_exists($response, 'getContent')) {
            $content = json_decode((string) $response->getContent(), true);
            if (is_array($content) && !empty($content['task_id']) && is_numeric($content['task_id'])) {
                return (int) $content['task_id'];
            }
        }

        return null;
    }

    /**
     * Build a clean summary array of the request.
     */
    protected function buildRequestSummary(Request $request, Response $response): array
    {
        $summary = [];

        if ($request->is('*/tts/generate')) {
            $summary['engine'] = $request->input('engine', 'piper-tr');
            $summary['language'] = $request->input('language', 'tr');
            $text = (string) $request->input('text', '');
            $summary['text_preview'] = Str::limit($text, 100);
            $summary['text_length'] = mb_strlen($text);
            if ($profileId = $request->input('profile_id')) {
                $summary['profile_id'] = $profileId;
            }
        } elseif ($request->is('*/stt/transcribe')) {
            $summary['model_size'] = $request->input('model_size', 'base');
            $summary['language'] = $request->input('language', 'tr');
            if ($request->hasFile('audio')) {
                $summary['file_name'] = $request->file('audio')->getClientOriginalName();
                $summary['file_size_kb'] = (int) round($request->file('audio')->getSize() / 1024);
            }
        } elseif ($request->is('*/models/download/*')) {
            $summary['model_id'] = $request->route('modelId') ?? $request->input('model_id');
        } elseif ($request->is('*/profiles*')) {
            if ($request->isMethod('POST')) {
                $summary['name'] = $request->input('name');
            }
        }

        if ($query = $request->query()) {
            unset($query['api_key']); // never log secret in summary
            if (!empty($query)) {
                $summary['query'] = $query;
            }
        }

        return $summary;
    }
}
