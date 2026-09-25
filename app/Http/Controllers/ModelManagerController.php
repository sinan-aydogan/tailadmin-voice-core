<?php

namespace App\Http\Controllers;

use App\Models\ModelDownload;
use App\Jobs\DownloadModelJob;
use App\Services\PythonVoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use Illuminate\Http\JsonResponse;

class ModelManagerController extends Controller
{
    public function index(PythonVoiceService $service): Response
    {
        $availableModels = $service->getAvailableModels();
        $downloads = ModelDownload::latest()->get();

        return Inertia::render('Models/Index', [
            'availableModels' => $availableModels,
            'downloads' => $downloads,
        ]);
    }

    public function download(Request $request, $modelId, PythonVoiceService $service)
    {
        $availableModels = $service->getAvailableModels();
        $targetModel = collect($availableModels)->firstWhere('id', $modelId);

        // Cloud models require an API key, not a file download
        if ($targetModel && !empty($targetModel['is_cloud'])) {
            $provider = $targetModel['cloud_provider'] ?? 'freya';
            $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
            $st = [];
            if (file_exists($settingsFile)) {
                $st = json_decode(file_get_contents($settingsFile), true) ?: [];
            }

            $providerKeyMap = [
                'freya' => $st['freya_api_key'] ?? env('FREYA_API_KEY', ''),
                'openai' => $st['openai_api_key'] ?? env('OPENAI_API_KEY', ''),
                'elevenlabs' => $st['elevenlabs_api_key'] ?? env('ELEVENLABS_API_KEY', ''),
                'google' => $st['google_cloud_api_key'] ?? env('GOOGLE_CLOUD_API_KEY', ''),
                'gemini' => $st['gemini_api_key'] ?? $st['google_cloud_api_key'] ?? env('GEMINI_API_KEY') ?: env('GOOGLE_CLOUD_API_KEY', ''),
                'groq' => $st['groq_api_key'] ?? env('GROQ_API_KEY', ''),
                'anthropic' => $st['anthropic_api_key'] ?? env('ANTHROPIC_API_KEY', ''),
                'claude' => $st['anthropic_api_key'] ?? env('ANTHROPIC_API_KEY', ''),
                'deepseek' => $st['deepseek_api_key'] ?? env('DEEPSEEK_API_KEY', ''),
                'openrouter' => $st['openrouter_api_key'] ?? env('OPENROUTER_API_KEY', ''),
                'patientdesk' => $st['patientdesk_api_key'] ?? env('PATIENTDESK_API_KEY', ''),
            ];

            $providerNames = [
                'freya' => 'Freya Voice',
                'openai' => 'OpenAI',
                'elevenlabs' => 'ElevenLabs',
                'google' => 'Google Cloud',
                'gemini' => 'Google Gemini',
                'groq' => 'Groq (LPU)',
                'anthropic' => 'Anthropic Claude',
                'claude' => 'Anthropic Claude',
                'deepseek' => 'DeepSeek',
                'openrouter' => 'OpenRouter',
                'patientdesk' => 'Patientdesk.ai',
            ];

            $apiKey = trim($providerKeyMap[$provider] ?? '');
            $pName = $providerNames[$provider] ?? strtoupper($provider);

            if (empty($apiKey)) {
                $errorMsg = "{$targetModel['name']} bir Bulut API modelidir (yerel dosya indirmesi gerekmez). Kullanabilmek için lütfen {$pName} API anahtarınızı tanımlayınız.";

                $download = ModelDownload::updateOrCreate(
                    ['model_id' => $modelId],
                    [
                        'status' => 'failed',
                        'progress' => 0.0,
                        'error_message' => $errorMsg,
                    ]
                );

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMsg,
                        'download' => $download,
                    ], 422);
                }

                return redirect()->back()->with('error', $errorMsg);
            }

            $download = ModelDownload::updateOrCreate(
                ['model_id' => $modelId],
                [
                    'status' => 'completed',
                    'progress' => 100.0,
                    'downloaded_bytes' => 0,
                    'total_bytes' => 0,
                    'error_message' => null,
                ]
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$targetModel['name']} ({$pName}) API bağlantısı hazır ve kullanıma uygun.",
                    'download' => $download,
                ]);
            }

            return redirect()->back()->with('success', "{$targetModel['name']} ({$pName}) API bağlantısı hazır ve kullanıma uygun.");
        }

        $download = ModelDownload::updateOrCreate(
            ['model_id' => $modelId],
            [
                'status' => 'pending',
                'progress' => 0.0,
                'error_message' => null,
            ]
        );

        DownloadModelJob::dispatch($download->id)->onQueue('default');
        \App\Services\QueueWorkerService::ensureRunning();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$modelId} modeli indirme kuyruğuna alındı.",
                'download' => $download,
            ]);
        }

        return redirect()->back()->with('success', "{$modelId} modeli indirme kuyruğuna alındı.");
    }

    public function downloadsApi(PythonVoiceService $service): JsonResponse
    {
        $downloads = ModelDownload::latest()->get();
        $availableModels = $service->getAvailableModels();

        // Reconcile active downloads with real-time disk bytes
        foreach ($downloads as $d) {
            $targetModel = collect($availableModels)->firstWhere('id', $d->model_id);
            if ($targetModel && !empty($targetModel['is_downloaded'])) {
                if ($d->status !== 'completed') {
                    $d->update([
                        'status' => 'completed',
                        'progress' => 100,
                        'error_message' => null,
                    ]);
                }
                continue;
            }

            if ($d->status === 'downloading' || $d->status === 'pending') {
                $isJobActive = \Illuminate\Support\Facades\DB::table('jobs')
                    ->where('payload', 'like', '%DownloadModelJob%')
                    ->where('payload', 'like', "%{$d->id}%")
                    ->exists();

                $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
                $modelsBaseDir = base_path('data/models');
                if (file_exists($settingsFile)) {
                    $st = json_decode(file_get_contents($settingsFile), true);
                    if (!empty($st['models_dir'])) {
                        $modelsBaseDir = rtrim($st['models_dir'], '/\\');
                    }
                }
                $dir = $modelsBaseDir . DIRECTORY_SEPARATOR . $d->model_id;
                $bytes = is_dir($dir) ? PythonVoiceService::getDirectorySize($dir) : 0;

                // Ensure queue worker is alive if there are pending or downloading models
                \App\Services\QueueWorkerService::ensureRunning();

                // If no background worker job exists, record is not newly created (>60s), AND disk has no growth, mark stalled download as failed
                $hasDiskActivity = ($bytes > ($d->downloaded_bytes ?? 0));
                if (!$isJobActive && !$hasDiskActivity && $d->updated_at && $d->updated_at->diffInSeconds(now()) > 60) {
                    $d->update([
                        'status' => 'failed',
                        'error_message' => 'İndirme işlemi kesintiye uğradı. "Tekrar İndirmeyi Dene" butonuna basarak indirmeyi yeniden başlatabilirsiniz.',
                    ]);
                    continue;
                }

                if (is_dir($dir)) {
                    $estimateMb = (int) ($targetModel['size_estimate_mb'] ?? 1000);
                    $estimatedTotalBytes = max(1024 * 1024, $estimateMb * 1024 * 1024);
                    $totalBytes = max($estimatedTotalBytes, (int)($bytes * 1.05), 1024 * 1024);

                    $pct = $totalBytes > 0
                        ? min(99.0, max(5.0, round(($bytes / $totalBytes) * 100, 1)))
                        : 5.0;

                    if ($bytes > $d->downloaded_bytes || $pct > $d->progress || $totalBytes !== $d->total_bytes) {
                        $d->update([
                            'progress' => $pct,
                            'downloaded_bytes' => $bytes,
                            'total_bytes' => $totalBytes,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'availableModels' => $availableModels,
            'downloads' => $downloads->fresh(),
        ]);
    }

    public function getCloudKeys()
    {
        $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        $st = [];
        if (file_exists($settingsFile)) {
            $st = json_decode(file_get_contents($settingsFile), true) ?: [];
        }

        $definitions = [
            'gemini' => [
                'name' => 'Google Gemini',
                'description' => 'Google Flash 2.0 & Pro yüksek hızlı ve çok modlu dil modelleri.',
                'key_field' => 'gemini_api_key',
                'env_key' => 'GEMINI_API_KEY',
                'fallback_env' => 'GOOGLE_CLOUD_API_KEY',
                'models' => ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'],
                'docs_url' => 'https://aistudio.google.com/app/apikey',
            ],
            'anthropic' => [
                'name' => 'Anthropic Claude',
                'description' => 'Claude 3.5 Sonnet ve Haiku üstün edebi diyalog ve senaryo motorları.',
                'key_field' => 'anthropic_api_key',
                'env_key' => 'ANTHROPIC_API_KEY',
                'models' => ['claude-3-5-sonnet', 'claude-3-5-haiku'],
                'docs_url' => 'https://console.anthropic.com/settings/keys',
            ],
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'Gelişmiş TTS (tts-1, tts-1-hd), Whisper STT ve GPT-4o dil modelleri.',
                'key_field' => 'openai_api_key',
                'env_key' => 'OPENAI_API_KEY',
                'models' => ['openai-tts-1', 'openai-tts-hd', 'openai-whisper', 'openai-gpt-4o', 'openai-gpt-4o-mini'],
                'docs_url' => 'https://platform.openai.com/api-keys',
            ],
            'groq' => [
                'name' => 'Groq (LPU)',
                'description' => 'Ultra hızlı LPU donanımında Whisper Large v3 ve Llama 3.3 70B.',
                'key_field' => 'groq_api_key',
                'env_key' => 'GROQ_API_KEY',
                'models' => ['groq-whisper', 'groq-llama-3.3-70b', 'groq-llama-3.1-8b'],
                'docs_url' => 'https://console.groq.com/keys',
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'description' => 'Açık kaynak lideri DeepSeek Chat V3 ve Reasoner R1 derin muhakeme modelleri.',
                'key_field' => 'deepseek_api_key',
                'env_key' => 'DEEPSEEK_API_KEY',
                'models' => ['deepseek-chat', 'deepseek-reasoner'],
                'docs_url' => 'https://platform.deepseek.com/api_keys',
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'description' => 'Tek API anahtarıyla yüzlerce yapay zeka modeline küresel yönlendirme.',
                'key_field' => 'openrouter_api_key',
                'env_key' => 'OPENROUTER_API_KEY',
                'models' => ['openrouter-ai'],
                'docs_url' => 'https://openrouter.ai/keys',
            ],
            'elevenlabs' => [
                'name' => 'ElevenLabs',
                'description' => '29+ dilde zengin duygusal tonlama ve gerçekçi insan sesleri.',
                'key_field' => 'elevenlabs_api_key',
                'env_key' => 'ELEVENLABS_API_KEY',
                'models' => ['elevenlabs-multilingual', 'elevenlabs-flash'],
                'docs_url' => 'https://elevenlabs.io/app/settings/api-keys',
            ],
            'freya' => [
                'name' => 'Freya Voice',
                'description' => 'AudioRealismBench #1 insansı ses modelleri (Adam & Eve).',
                'key_field' => 'freya_api_key',
                'env_key' => 'FREYA_API_KEY',
                'models' => ['freya-adam', 'freya-eve'],
                'docs_url' => 'https://freyavoice.ai',
            ],
            'google' => [
                'name' => 'Google Cloud',
                'description' => 'Journey, Neural2 ve Chirp v2 kurumsal ses ve transkripsiyon servisleri.',
                'key_field' => 'google_cloud_api_key',
                'env_key' => 'GOOGLE_CLOUD_API_KEY',
                'fallback_env' => 'GEMINI_API_KEY',
                'models' => ['google-cloud-tts', 'google-cloud-stt'],
                'docs_url' => 'https://console.cloud.google.com/apis/credentials',
            ],
            'patientdesk' => [
                'name' => 'Patientdesk.ai',
                'description' => 'Yerli Türkçe metinden sese Alania ve yüksek doğruluklu sesten metne Duyu modelleri (Lansmana özel 1 ay ücretsiz).',
                'key_field' => 'patientdesk_api_key',
                'env_key' => 'PATIENTDESK_API_KEY',
                'models' => ['alania', 'duyu'],
                'docs_url' => 'https://speech.patientdesk.ai',
            ],
        ];

        $providers = [];
        foreach ($definitions as $id => $def) {
            $key = $st[$def['key_field']] ?? env($def['env_key'], '');
            if (empty($key) && !empty($def['fallback_env'])) {
                $key = $st['gemini_api_key'] ?? env($def['fallback_env'], '');
            }
            $isConfigured = !empty(trim($key));
            $masked = '';
            if ($isConfigured) {
                $k = trim($key);
                $masked = strlen($k) > 8 ? substr($k, 0, 4) . '...' . substr($k, -4) : '••••••••';
            }

            $providers[$id] = [
                'id' => $id,
                'name' => $def['name'],
                'description' => $def['description'],
                'key' => trim($key),
                'masked_key' => $masked,
                'is_configured' => $isConfigured,
                'models' => $def['models'],
                'docs_url' => $def['docs_url'],
            ];
        }

        return response()->json($providers);
    }

    public function saveCloudKey(Request $request)
    {
        $provider = strtolower(trim($request->input('provider', '')));
        $key = trim($request->input('key', ''));

        $fieldMap = [
            'gemini' => ['gemini_api_key', ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'], 'Google Gemini'],
            'anthropic' => ['anthropic_api_key', ['claude-3-5-sonnet', 'claude-3-5-haiku'], 'Anthropic Claude'],
            'claude' => ['anthropic_api_key', ['claude-3-5-sonnet', 'claude-3-5-haiku'], 'Anthropic Claude'],
            'deepseek' => ['deepseek_api_key', ['deepseek-chat', 'deepseek-reasoner'], 'DeepSeek'],
            'openrouter' => ['openrouter_api_key', ['openrouter-ai'], 'OpenRouter'],
            'openai' => ['openai_api_key', ['openai-tts-1', 'openai-tts-hd', 'openai-whisper', 'openai-gpt-4o', 'openai-gpt-4o-mini'], 'OpenAI'],
            'groq' => ['groq_api_key', ['groq-whisper', 'groq-llama-3.3-70b', 'groq-llama-3.1-8b'], 'Groq'],
            'elevenlabs' => ['elevenlabs_api_key', ['elevenlabs-multilingual', 'elevenlabs-flash'], 'ElevenLabs'],
            'google' => ['google_cloud_api_key', ['google-cloud-tts', 'google-cloud-stt'], 'Google Cloud'],
            'freya' => ['freya_api_key', ['freya-adam', 'freya-eve'], 'Freya Voice'],
            'patientdesk' => ['patientdesk_api_key', ['alania', 'duyu'], 'Patientdesk.ai'],
        ];

        if (!isset($fieldMap[$provider])) {
            return response()->json([
                'success' => false,
                'message' => "Bilinmeyen bulut sağlayıcı: {$provider}",
            ], 422);
        }

        [$fieldName, $modelIds, $providerName] = $fieldMap[$provider];

        $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        $settings = [];
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
        }

        $settings[$fieldName] = $key;
        if ($provider === 'gemini' && empty($settings['google_cloud_api_key'])) {
            $settings['google_cloud_api_key'] = $key;
        }

        // Keep llm_providers_config synchronized
        if (!isset($settings['llm_providers_config']) || !is_array($settings['llm_providers_config'])) {
            $settings['llm_providers_config'] = [];
        }
        $pKey = ($provider === 'claude') ? 'anthropic' : $provider;
        if (!isset($settings['llm_providers_config'][$pKey])) {
            $settings['llm_providers_config'][$pKey] = [];
        }
        $settings['llm_providers_config'][$pKey]['api_key'] = $key;
        if (($settings['llm_provider'] ?? '') === $pKey) {
            $settings['llm_api_key'] = $key;
        }
        if (!is_dir(dirname($settingsFile))) {
            @mkdir(dirname($settingsFile), 0755, true);
        }
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        \Illuminate\Support\Facades\Cache::forget('voice_available_models');

        if (!empty($key)) {
            foreach ($modelIds as $cloudModelId) {
                ModelDownload::updateOrCreate(
                    ['model_id' => $cloudModelId],
                    [
                        'status' => 'completed',
                        'progress' => 100.0,
                        'downloaded_bytes' => 0,
                        'total_bytes' => 0,
                        'error_message' => null,
                    ]
                );
            }
        } else {
            foreach ($modelIds as $cloudModelId) {
                ModelDownload::where('model_id', $cloudModelId)->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => !empty($key)
                ? "{$providerName} API anahtarı kaydedildi. İlgili bulut modelleri kullanıma hazır!"
                : "{$providerName} API anahtarı kaldırıldı.",
        ]);
    }

    public function testCloudConnection(Request $request)
    {
        $provider = strtolower(trim($request->input('provider', '')));
        $key = trim($request->input('key', ''));

        // If no explicit key passed, read configured key
        if (empty($key)) {
            $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
            $st = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
            $keyMap = [
                'gemini' => $st['gemini_api_key'] ?? $st['google_cloud_api_key'] ?? env('GEMINI_API_KEY') ?: env('GOOGLE_CLOUD_API_KEY', ''),
                'anthropic' => $st['anthropic_api_key'] ?? env('ANTHROPIC_API_KEY', ''),
                'claude' => $st['anthropic_api_key'] ?? env('ANTHROPIC_API_KEY', ''),
                'deepseek' => $st['deepseek_api_key'] ?? env('DEEPSEEK_API_KEY', ''),
                'openrouter' => $st['openrouter_api_key'] ?? env('OPENROUTER_API_KEY', ''),
                'openai' => $st['openai_api_key'] ?? env('OPENAI_API_KEY', ''),
                'elevenlabs' => $st['elevenlabs_api_key'] ?? env('ELEVENLABS_API_KEY', ''),
                'google' => $st['google_cloud_api_key'] ?? $st['gemini_api_key'] ?? env('GOOGLE_CLOUD_API_KEY') ?: env('GEMINI_API_KEY', ''),
                'groq' => $st['groq_api_key'] ?? env('GROQ_API_KEY', ''),
                'freya' => $st['freya_api_key'] ?? env('FREYA_API_KEY', ''),
                'patientdesk' => $st['patientdesk_api_key'] ?? env('PATIENTDESK_API_KEY', ''),
            ];
            $key = trim($keyMap[$provider] ?? '');
        }

        if (empty($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Lütfen test etmek için önce bir API anahtarı giriniz.',
            ], 422);
        }

        try {
            // Test LLM providers via LlmService
            $llmProviders = ['gemini', 'anthropic', 'claude', 'deepseek', 'openrouter'];
            if (in_array($provider, $llmProviders)) {
                $llmService = app(\App\Services\LlmService::class);
                $p = $provider === 'claude' ? 'anthropic' : $provider;
                $res = $llmService->testConnection($p, null, $key);
                return response()->json($res, $res['success'] ? 200 : 400);
            }
            if ($provider === 'openai') {
                $res = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withToken($key)
                    ->get('https://api.openai.com/v1/models');
                if ($res->successful()) {
                    return response()->json(['success' => true, 'message' => 'OpenAI API bağlantısı başarılı!']);
                }
                return response()->json(['success' => false, 'message' => 'OpenAI API hatası: ' . ($res->json()['error']['message'] ?? $res->body())], 400);
            }

            if ($provider === 'elevenlabs') {
                $res = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withHeaders(['xi-api-key' => $key])
                    ->get('https://api.elevenlabs.io/v1/user');
                if ($res->successful()) {
                    $userData = $res->json();
                    $tier = $userData['subscription']['tier'] ?? 'Standard';
                    return response()->json(['success' => true, 'message' => "ElevenLabs bağlantısı başarılı! Plan: {$tier}"]);
                }
                return response()->json(['success' => false, 'message' => 'ElevenLabs API hatası: ' . ($res->json()['detail']['message'] ?? $res->body())], 400);
            }

            if ($provider === 'google') {
                $res = \Illuminate\Support\Facades\Http::timeout(10)
                    ->get("https://texttospeech.googleapis.com/v1/voices?key={$key}");
                if ($res->successful()) {
                    return response()->json(['success' => true, 'message' => 'Google Cloud Text-to-Speech API bağlantısı başarılı!']);
                }
                return response()->json(['success' => false, 'message' => 'Google Cloud API hatası: ' . ($res->json()['error']['message'] ?? $res->body())], 400);
            }

            if ($provider === 'groq') {
                $res = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withToken($key)
                    ->get('https://api.groq.com/openai/v1/models');
                if ($res->successful()) {
                    return response()->json(['success' => true, 'message' => 'Groq LPU Cloud API bağlantısı başarılı!']);
                }
                return response()->json(['success' => false, 'message' => 'Groq API hatası: ' . ($res->json()['error']['message'] ?? $res->body())], 400);
            }

            if ($provider === 'freya') {
                $res = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withToken($key)
                    ->get('https://tts.freyavoice.ai/v1/models');
                if ($res->successful() || $res->status() === 405 || $res->status() === 200) {
                    return response()->json(['success' => true, 'message' => 'Freya Voice API bağlantısı kuruldu!']);
                }
                return response()->json(['success' => false, 'message' => 'Freya Voice API hatası: ' . $res->body()], 400);
            }

            if ($provider === 'patientdesk') {
                $res = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withToken($key)
                    ->get('https://voice.patientdesk.ai/v1/models');
                if ($res->successful() || $res->status() === 405 || $res->status() === 200) {
                    return response()->json(['success' => true, 'message' => 'Patientdesk.ai API bağlantısı başarılı!']);
                }
                if ($res->status() === 401) {
                    return response()->json(['success' => false, 'message' => 'Patientdesk.ai API anahtarı geçersiz veya yetkisiz (HTTP 401).'], 400);
                }
                return response()->json(['success' => true, 'message' => 'Patientdesk.ai API bağlantısı kuruldu.']);
            }

            return response()->json(['success' => false, 'message' => 'Bilinmeyen sağlayıcı.'], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bağlantı testi sırasında hata oluştu: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getFreyaKey()
    {
        $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        $key = env('FREYA_API_KEY', '');
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
            if (!empty($settings['freya_api_key'])) {
                $key = $settings['freya_api_key'];
            }
        }
        return response()->json([
            'key' => $key,
            'is_configured' => !empty($key),
        ]);
    }

    public function saveFreyaKey(Request $request)
    {
        $request->merge(['provider' => 'freya']);
        return $this->saveCloudKey($request);
    }
}
