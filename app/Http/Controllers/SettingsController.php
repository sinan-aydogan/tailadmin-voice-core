<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    protected function getSettingsFilePath(): string
    {
        return base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
    }

    protected function loadSavedSettings(): array
    {
        $file = $this->getSettingsFilePath();
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    protected function getAllSettings(): array
    {
        $defaultModelsDir = base_path('data' . DIRECTORY_SEPARATOR . 'models');
        $saved = $this->loadSavedSettings();

        $defaults = [
            'models_dir' => $defaultModelsDir,
            'use_gpu' => env('USE_GPU', 'auto'),
            'max_cpu_threads' => (int) env('MAX_CPU_THREADS', 4),
            'default_tts_engine' => env('DEFAULT_TTS_ENGINE', 'piper-tr'),
            'default_stt_engine' => env('DEFAULT_STT_ENGINE', 'whisper'),
            'default_stt_model' => env('DEFAULT_STT_MODEL', 'whisper-medium'),
            'default_language' => env('DEFAULT_TTS_LANGUAGE', 'tr'),
            'api_port' => (int) env('API_PORT', 5001),
            'hf_token' => env('HF_TOKEN', ''),
            'voice_core_api_key' => env('VOICE_CORE_API_KEY', ''),
            'freya_api_key' => env('FREYA_API_KEY', ''),
            'freya_default_voice' => env('FREYA_DEFAULT_VOICE', 'adam'),
            'openai_api_key' => env('OPENAI_API_KEY', ''),
            'elevenlabs_api_key' => env('ELEVENLABS_API_KEY', ''),
            'google_cloud_api_key' => env('GOOGLE_CLOUD_API_KEY', ''),
            'groq_api_key' => env('GROQ_API_KEY', ''),
            'gemini_api_key' => env('GEMINI_API_KEY', ''),
            'anthropic_api_key' => env('ANTHROPIC_API_KEY', ''),
            'deepseek_api_key' => env('DEEPSEEK_API_KEY', ''),
            'openrouter_api_key' => env('OPENROUTER_API_KEY', ''),
            'patientdesk_api_key' => env('PATIENTDESK_API_KEY', ''),
            'llm_provider' => env('LLM_PROVIDER', 'ollama'),
            'llm_base_url' => env('LLM_BASE_URL', 'http://127.0.0.1:11434'),
            'llm_api_key' => env('LLM_API_KEY', ''),
            'llm_model' => env('LLM_MODEL', 'llama3:latest'),
            'llm_system_prompt' => env('LLM_SYSTEM_PROMPT', 'Sen seslendirme metinleri hazırlayan yaratıcı, akıcı ve profesyonel bir yapay zeka asistanısın. Yanıtlarında gereksiz selamlama veya açıklama yapmadan yalnızca doğrudan seslendirilecek metni ver.'),
            'llm_providers_config' => [],
        ];

        return array_merge($defaults, $saved);
    }

    public function index(): Response
    {
        $apiKeys = [];
        try {
            $apiKeys = \App\Models\ApiKey::withCount('logs')
                ->orderByDesc('id')
                ->get();
        } catch (\Throwable $e) {
            $apiKeys = [];
        }

        return Inertia::render('Settings/Index', [
            'settings' => $this->getAllSettings(),
            'default_models_dir' => base_path('data' . DIRECTORY_SEPARATOR . 'models'),
            'api_keys' => $apiKeys,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'models_dir' => 'nullable|string',
            'model_transfer_action' => 'nullable|string|in:move,delete,keep,none',
            'use_gpu' => 'nullable|string',
            'max_cpu_threads' => 'nullable|integer|min:1|max:64',
            'default_tts_engine' => 'nullable|string',
            'default_stt_engine' => 'nullable|string',
            'default_stt_model' => 'nullable|string',
            'hf_token' => 'nullable|string',
            'voice_core_api_key' => 'nullable|string',
            'freya_api_key' => 'nullable|string',
            'freya_default_voice' => 'nullable|string',
            'openai_api_key' => 'nullable|string',
            'elevenlabs_api_key' => 'nullable|string',
            'google_cloud_api_key' => 'nullable|string',
            'groq_api_key' => 'nullable|string',
            'gemini_api_key' => 'nullable|string',
            'anthropic_api_key' => 'nullable|string',
            'deepseek_api_key' => 'nullable|string',
            'openrouter_api_key' => 'nullable|string',
            'patientdesk_api_key' => 'nullable|string',
            'llm_provider' => 'nullable|string',
            'llm_base_url' => 'nullable|string',
            'llm_api_key' => 'nullable|string',
            'llm_model' => 'nullable|string',
            'llm_system_prompt' => 'nullable|string',
            'llm_providers_config' => 'nullable|array',
        ]);

        $current = $this->getAllSettings();
        $customMessage = null;
        $transferAction = $request->input('model_transfer_action', 'none');

        if (!empty($validated['models_dir'])) {
            $oldModelsDir = $current['models_dir'] ?? base_path('data' . DIRECTORY_SEPARATOR . 'models');
            $modelsDir = trim($validated['models_dir']);
            $modelsDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $modelsDir), DIRECTORY_SEPARATOR);

            $oldNorm = strtolower(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldModelsDir), DIRECTORY_SEPARATOR));
            $newNorm = strtolower($modelsDir);

            if (!is_dir($modelsDir)) {
                @mkdir($modelsDir, 0755, true);
            }

            if ($oldNorm !== $newNorm) {
                if ($transferAction === 'move') {
                    $moveRes = $this->moveDirectoryContents($oldModelsDir, $modelsDir);
                    $customMessage = "Model konumu güncellendi ve {$moveRes['moved']} adet model yeni konuma taşındı.";
                    Log::info("Models moved from {$oldModelsDir} to {$modelsDir}. Moved items: {$moveRes['moved']}");
                } elseif ($transferAction === 'delete') {
                    $delRes = $this->deleteDirectoryContents($oldModelsDir);
                    $customMessage = "Model konumu güncellendi. Eski konumdaki ({$delRes['deleted']}) model silindi.";
                    Log::info("Models deleted from {$oldModelsDir}. Deleted items: {$delRes['deleted']}");
                }
            }

            $current['models_dir'] = $modelsDir;
        }
        if (isset($validated['use_gpu'])) {
            $current['use_gpu'] = $validated['use_gpu'];
        }
        if (isset($validated['max_cpu_threads'])) {
            $current['max_cpu_threads'] = (int) $validated['max_cpu_threads'];
        }
        if (isset($validated['default_tts_engine'])) {
            $current['default_tts_engine'] = $validated['default_tts_engine'];
        }
        if (isset($validated['default_stt_engine'])) {
            $current['default_stt_engine'] = $validated['default_stt_engine'];
        }
        if (isset($validated['default_stt_model'])) {
            $current['default_stt_model'] = $validated['default_stt_model'];
        }
        if ($request->has('hf_token')) {
            $current['hf_token'] = trim($request->input('hf_token', ''));
        }
        if ($request->has('voice_core_api_key')) {
            $current['voice_core_api_key'] = trim($request->input('voice_core_api_key', ''));
        }
        if ($request->has('freya_api_key')) {
            $current['freya_api_key'] = trim($request->input('freya_api_key', ''));
        }
        if ($request->has('freya_default_voice')) {
            $current['freya_default_voice'] = trim($request->input('freya_default_voice', 'adam'));
        }
        if ($request->has('openai_api_key')) {
            $current['openai_api_key'] = trim($request->input('openai_api_key', ''));
        }
        if ($request->has('elevenlabs_api_key')) {
            $current['elevenlabs_api_key'] = trim($request->input('elevenlabs_api_key', ''));
        }
        if ($request->has('google_cloud_api_key')) {
            $current['google_cloud_api_key'] = trim($request->input('google_cloud_api_key', ''));
        }
        if ($request->has('groq_api_key')) {
            $current['groq_api_key'] = trim($request->input('groq_api_key', ''));
        }
        if ($request->has('gemini_api_key')) {
            $current['gemini_api_key'] = trim($request->input('gemini_api_key', ''));
        }
        if ($request->has('anthropic_api_key')) {
            $current['anthropic_api_key'] = trim($request->input('anthropic_api_key', ''));
        }
        if ($request->has('deepseek_api_key')) {
            $current['deepseek_api_key'] = trim($request->input('deepseek_api_key', ''));
        }
        if ($request->has('openrouter_api_key')) {
            $current['openrouter_api_key'] = trim($request->input('openrouter_api_key', ''));
        }
        if ($request->has('llm_provider')) {
            $current['llm_provider'] = trim($request->input('llm_provider', 'ollama'));
        }
        if ($request->has('llm_base_url')) {
            $current['llm_base_url'] = trim($request->input('llm_base_url', 'http://127.0.0.1:11434'));
        }
        if ($request->has('llm_api_key')) {
            $current['llm_api_key'] = trim($request->input('llm_api_key', ''));
        }
        if ($request->has('llm_model')) {
            $current['llm_model'] = trim($request->input('llm_model', 'llama3:latest'));
        }
        if ($request->has('llm_system_prompt')) {
            $current['llm_system_prompt'] = trim($request->input('llm_system_prompt', ''));
        }
        if ($request->has('llm_providers_config')) {
            $cfg = $request->input('llm_providers_config', []);
            if (is_array($cfg)) {
                $current['llm_providers_config'] = array_merge($current['llm_providers_config'] ?? [], $cfg);
            }
        }

        // Always keep active provider's configuration updated in llm_providers_config
        $activeProv = $current['llm_provider'] ?? 'ollama';
        if (!isset($current['llm_providers_config']) || !is_array($current['llm_providers_config'])) {
            $current['llm_providers_config'] = [];
        }
        $current['llm_providers_config'][$activeProv] = array_merge(
            $current['llm_providers_config'][$activeProv] ?? [],
            [
                'provider' => $activeProv,
                'model' => $current['llm_model'],
                'base_url' => $current['llm_base_url'],
            ]
        );
        if (!empty($current['llm_api_key'])) {
            $current['llm_providers_config'][$activeProv]['api_key'] = $current['llm_api_key'];
            if ($activeProv === 'gemini') $current['gemini_api_key'] = $current['llm_api_key'];
            if ($activeProv === 'anthropic' || $activeProv === 'claude') $current['anthropic_api_key'] = $current['llm_api_key'];
            if ($activeProv === 'deepseek') $current['deepseek_api_key'] = $current['llm_api_key'];
            if ($activeProv === 'openrouter') $current['openrouter_api_key'] = $current['llm_api_key'];
            if ($activeProv === 'openai') $current['openai_api_key'] = $current['llm_api_key'];
            if ($activeProv === 'groq') $current['groq_api_key'] = $current['llm_api_key'];
        }

        $dataDir = base_path('data');
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }

        file_put_contents($this->getSettingsFilePath(), json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Sync ModelDownload table for cloud providers
        $cloudProviders = [
            'freya' => $current['freya_api_key'] ?? '',
            'openai' => $current['openai_api_key'] ?? '',
            'elevenlabs' => $current['elevenlabs_api_key'] ?? '',
            'google' => $current['google_cloud_api_key'] ?? '',
            'gemini' => $current['gemini_api_key'] ?? ($current['google_cloud_api_key'] ?? ''),
            'groq' => $current['groq_api_key'] ?? '',
            'anthropic' => $current['anthropic_api_key'] ?? '',
            'deepseek' => $current['deepseek_api_key'] ?? '',
            'openrouter' => $current['openrouter_api_key'] ?? '',
            'patientdesk' => $current['patientdesk_api_key'] ?? '',
        ];

        try {
            $pythonService = app(\App\Services\PythonVoiceService::class);
            $allModels = $pythonService->getAvailableModels();
            foreach ($cloudProviders as $provider => $key) {
                $providerModels = collect($allModels)->filter(function ($m) use ($provider) {
                    return !empty($m['is_cloud']) && ($m['cloud_provider'] ?? '') === $provider;
                });

                if (!empty($key)) {
                    foreach ($providerModels as $m) {
                        \App\Models\ModelDownload::updateOrCreate(
                            ['model_id' => $m['id']],
                            [
                                'status' => 'completed',
                                'progress' => 100,
                                'downloaded_bytes' => 0,
                                'total_bytes' => 0,
                                'speed_mbps' => 0,
                                'error_message' => null,
                            ]
                        );
                    }
                } else {
                    foreach ($providerModels as $m) {
                        \App\Models\ModelDownload::where('model_id', $m['id'])->delete();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("SettingsController cloud model sync error: " . $e->getMessage());
        }

        Cache::forget('voice_available_models');

        $returnMsg = $customMessage ?: 'Ayarlar başarıyla kaydedildi.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $returnMsg,
                'settings' => $current,
            ]);
        }

        return redirect()->back()->with('success', $returnMsg);
    }

    /**
     * Check if models directory has any existing downloaded model files/folders.
     */
    public function checkModelsDirectory(Request $request): JsonResponse
    {
        $oldDir = trim($request->input('old_dir', ''));
        if (empty($oldDir)) {
            $saved = $this->loadSavedSettings();
            $oldDir = $saved['models_dir'] ?? base_path('data' . DIRECTORY_SEPARATOR . 'models');
        }
        $oldDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldDir);

        $hasModels = false;
        $modelsCount = 0;
        $totalSize = 0;
        $modelNames = [];

        if (is_dir($oldDir)) {
            $items = scandir($oldDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || str_starts_with($item, '.')) continue;
                $fullPath = $oldDir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($fullPath)) {
                    $hasModels = true;
                    $modelsCount++;
                    $modelNames[] = $item;
                    $totalSize += \App\Services\PythonVoiceService::getDirectorySize($fullPath);
                } elseif (is_file($fullPath) && (str_ends_with($item, '.onnx') || str_ends_with($item, '.bin') || str_ends_with($item, '.safetensors') || str_ends_with($item, '.pth') || str_ends_with($item, '.json'))) {
                    $hasModels = true;
                    $modelsCount++;
                    $modelNames[] = $item;
                    $totalSize += filesize($fullPath);
                }
            }
        }

        return response()->json([
            'exists' => is_dir($oldDir),
            'has_models' => $hasModels,
            'models_count' => $modelsCount,
            'model_names' => array_slice($modelNames, 0, 15),
            'total_size_bytes' => $totalSize,
        ]);
    }

    /**
     * Move contents of a directory to another directory (supports cross-volume / cross-drive).
     */
    protected function moveDirectoryContents(string $sourceDir, string $targetDir): array
    {
        if (!is_dir($sourceDir)) {
            return ['moved' => 0, 'errors' => ['Kaynak klasör bulunamadı']];
        }
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $movedCount = 0;
        $errors = [];
        $items = scandir($sourceDir) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) continue;

            $src = $sourceDir . DIRECTORY_SEPARATOR . $item;
            $dst = $targetDir . DIRECTORY_SEPARATOR . $item;

            try {
                // Try rename first (instant on same volume)
                if (@rename($src, $dst)) {
                    $movedCount++;
                    continue;
                }

                // Cross-drive fallback: copy recursively then delete source
                if (is_dir($src)) {
                    \Illuminate\Support\Facades\File::copyDirectory($src, $dst);
                    \Illuminate\Support\Facades\File::deleteDirectory($src);
                } else {
                    \Illuminate\Support\Facades\File::copy($src, $dst);
                    @unlink($src);
                }
                $movedCount++;
            } catch (\Throwable $e) {
                $errors[] = "{$item} taşınamadı: " . $e->getMessage();
                Log::error("Move error for {$item}: " . $e->getMessage());
            }
        }

        return ['moved' => $movedCount, 'errors' => $errors];
    }

    /**
     * Delete all contents of a directory while preserving the parent directory.
     */
    protected function deleteDirectoryContents(string $dir): array
    {
        if (!is_dir($dir)) {
            return ['deleted' => 0];
        }

        $count = 0;
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            try {
                if (is_dir($path)) {
                    \Illuminate\Support\Facades\File::deleteDirectory($path);
                } else {
                    @unlink($path);
                }
                $count++;
            } catch (\Throwable $e) {
                Log::warning("Error deleting model item {$path}: " . $e->getMessage());
            }
        }
        return ['deleted' => $count];
    }

    public function browseFolder(): JsonResponse
    {
        $path = null;

        // 1. Try NativePHP Dialog if running inside Electron
        if (class_exists(\Native\Desktop\Dialog::class)) {
            try {
                $path = \Native\Desktop\Dialog::new()
                    ->folders()
                    ->title('Model İndirme Klasörünü Seçin')
                    ->open();
            } catch (\Throwable $e) {
                Log::warning("NativePHP Dialog failed: " . $e->getMessage());
            }
        }

        // 2. Windows Fallback via PowerShell FolderBrowserDialog
        if (!$path && PHP_OS_FAMILY === 'Windows') {
            try {
                $cmd = 'powershell -NoProfile -Command "Add-Type -AssemblyName System.Windows.Forms; $f = New-Object System.Windows.Forms.FolderBrowserDialog; $f.Description = \'Yapay Zeka Modellerinin İndirileceği Klasörü Seçin\'; if ($f.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) { Write-Output $f.SelectedPath }"';
                $result = Process::timeout(30)->run($cmd);
                $out = trim($result->output());
                if (!empty($out) && is_dir($out)) {
                    $path = $out;
                }
            } catch (\Throwable $e) {
                Log::warning("PowerShell FolderBrowserDialog failed: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => !empty($path),
            'path' => $path ? str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path) : null,
        ]);
    }

    public function testFreyaConnection(Request $request): JsonResponse
    {
        $apiKey = trim($request->input('api_key', ''));
        if (empty($apiKey)) {
            $saved = $this->loadSavedSettings();
            $apiKey = $saved['freya_api_key'] ?? env('FREYA_API_KEY', '');
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Lütfen test etmek için bir Freya API anahtarı girin.',
            ]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://tts.freyavoice.ai/v1/audio/speech', [
                    'model' => 'tts-1',
                    'input' => 'Test',
                    'voice' => $request->input('voice', 'adam'),
                    'response_format' => 'wav'
                ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Freya Voice bulut API bağlantısı başarılı! (Adam & Eve hazır)',
                    'bytes' => strlen($response->body()),
                ]);
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return response()->json([
                    'success' => false,
                    'message' => "Yetkilendirme hatası ({$response->status()}): Girdiğiniz Freya API anahtarı geçersiz veya yetkisiz.",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Freya API yanıtı: Durum kodu {$response->status()}" . ($response->body() ? ' - ' . substr($response->body(), 0, 150) : ''),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bağlantı hatası: ' . $e->getMessage(),
            ]);
        }
    }
}
