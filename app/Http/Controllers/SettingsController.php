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

        return [
            'models_dir' => $saved['models_dir'] ?? $defaultModelsDir,
            'use_gpu' => $saved['use_gpu'] ?? env('USE_GPU', 'auto'),
            'max_cpu_threads' => (int) ($saved['max_cpu_threads'] ?? env('MAX_CPU_THREADS', 4)),
            'default_tts_engine' => $saved['default_tts_engine'] ?? env('DEFAULT_TTS_ENGINE', 'piper-tr'),
            'default_language' => $saved['default_language'] ?? env('DEFAULT_TTS_LANGUAGE', 'tr'),
            'api_port' => (int) ($saved['api_port'] ?? env('API_PORT', 5001)),
            'hf_token' => $saved['hf_token'] ?? env('HF_TOKEN', ''),
            'voice_core_api_key' => $saved['voice_core_api_key'] ?? env('VOICE_CORE_API_KEY', ''),
            'llm_provider' => $saved['llm_provider'] ?? env('LLM_PROVIDER', 'ollama'),
            'llm_base_url' => $saved['llm_base_url'] ?? env('LLM_BASE_URL', 'http://127.0.0.1:11434'),
            'llm_api_key' => $saved['llm_api_key'] ?? env('LLM_API_KEY', ''),
            'llm_model' => $saved['llm_model'] ?? env('LLM_MODEL', 'llama3:latest'),
            'llm_system_prompt' => $saved['llm_system_prompt'] ?? env('LLM_SYSTEM_PROMPT', 'Sen seslendirme metinleri hazırlayan yaratıcı, akıcı ve profesyonel bir yapay zeka asistanısın. Yanıtlarında gereksiz selamlama veya açıklama yapmadan yalnızca doğrudan seslendirilecek metni ver.'),
        ];
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
            'models_dir' => 'required|string',
            'use_gpu' => 'nullable|string',
            'max_cpu_threads' => 'nullable|integer|min:1|max:64',
            'default_tts_engine' => 'nullable|string',
            'hf_token' => 'nullable|string',
            'voice_core_api_key' => 'nullable|string',
            'llm_provider' => 'nullable|string',
            'llm_base_url' => 'nullable|string',
            'llm_api_key' => 'nullable|string',
            'llm_model' => 'nullable|string',
            'llm_system_prompt' => 'nullable|string',
        ]);

        $current = $this->getAllSettings();
        $modelsDir = trim($validated['models_dir']);
        $modelsDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $modelsDir);

        if (!is_dir($modelsDir)) {
            @mkdir($modelsDir, 0755, true);
        }

        $current['models_dir'] = $modelsDir;
        if (isset($validated['use_gpu'])) {
            $current['use_gpu'] = $validated['use_gpu'];
        }
        if (isset($validated['max_cpu_threads'])) {
            $current['max_cpu_threads'] = (int) $validated['max_cpu_threads'];
        }
        if (isset($validated['default_tts_engine'])) {
            $current['default_tts_engine'] = $validated['default_tts_engine'];
        }
        $current['hf_token'] = trim($request->input('hf_token', ''));
        $current['voice_core_api_key'] = trim($request->input('voice_core_api_key', ''));
        $current['llm_provider'] = trim($request->input('llm_provider', 'ollama'));
        $current['llm_base_url'] = trim($request->input('llm_base_url', 'http://127.0.0.1:11434'));
        $current['llm_api_key'] = trim($request->input('llm_api_key', ''));
        $current['llm_model'] = trim($request->input('llm_model', 'llama3:latest'));
        $current['llm_system_prompt'] = trim($request->input('llm_system_prompt', ''));

        $dataDir = base_path('data');
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }

        file_put_contents($this->getSettingsFilePath(), json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Cache::forget('voice_available_models');

        return redirect()->back()->with('success', 'Ayarlar başarıyla kaydedildi.');
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
}
