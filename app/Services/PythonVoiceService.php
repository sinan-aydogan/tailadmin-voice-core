<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PythonVoiceService
{
    protected string $baseUrl;
    protected string $enginePath;
    protected string $pythonBin;

    public function __construct()
    {
        $this->baseUrl = config('services.python_voice.url', env('PYTHON_VOICE_URL', 'http://127.0.0.1:5001'));
        $this->enginePath = base_path('engine');
        $this->pythonBin = config('services.python_voice.binary', env('PYTHON_BINARY', 'python'));
    }


    /**
     * Check if Python FastAPI service is running.
     */
    public function isServiceOnline(): bool
    {
        static $localCache = null;
        static $localCacheTime = 0;

        if ($localCache !== null && (microtime(true) - $localCacheTime) < 3.0) {
            return $localCache;
        }

        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?? '127.0.0.1';
        $port = parse_url($this->baseUrl, PHP_URL_PORT) ?? 5001;

        // Fast non-blocking socket probe (50ms timeout)
        $fp = @fsockopen($host, (int) $port, $errno, $errstr, 0.05);
        if (!$fp) {
            $localCache = false;
            $localCacheTime = microtime(true);
            return false;
        }
        fclose($fp);

        try {
            $response = Http::timeout(1)->get("{$this->baseUrl}/health");
            $localCache = $response->successful();
        } catch (\Throwable $e) {
            $localCache = false;
        }

        $localCacheTime = microtime(true);
        return $localCache;
    }

    /**
     * Ensure Python FastAPI microservice is running. If not, attempt to start it.
     */
    public function ensureServiceRunning(): void
    {
        if ($this->isServiceOnline()) {
            return;
        }

        Log::info("Starting Python Voice Core microservice at {$this->enginePath}...");

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B {$this->pythonBin} {$this->enginePath}/main.py", "r"));
        } else {
            exec("{$this->pythonBin} {$this->enginePath}/main.py > /dev/null 2>&1 &");
        }

        // Wait up to 5 seconds for service to come up
        for ($i = 0; $i < 10; $i++) {
            usleep(500000);
            if ($this->isServiceOnline()) {
                Log::info("Python Voice Core microservice started successfully.");
                return;
            }
        }

        // Not our bug to fix: this usually means the OS's networking stack
        // is broken on this machine (e.g. a corrupted Windows Winsock catalog
        // — WinError 10106 — from VPN/antivirus software), which stops
        // Python's asyncio event loop from binding a socket. STT/TTS still
        // work via the synchronous CLI fallback in generateTts()/transcribeStt(),
        // so this is a heads-up, not a failure.
        Log::warning("Python Voice Core microservice did not come online within 5s — falling back to the CLI path for STT/TTS. If this persists, check for a broken network stack on this machine (e.g. run 'netsh winsock reset' as Administrator on Windows).");
    }

    /**
     * Generate speech from text (TTS).
     */
    public function generateTts(string $text, string $engine = 'piper-tr', string $language = 'tr', ?string $profilePath = null, ?string $outputPath = null): array
    {
        $text = self::sanitizeTextForTts($text);

        // Preferred: call HTTP microservice if online
        if ($this->isServiceOnline()) {
            $response = Http::timeout(600)->post("{$this->baseUrl}/tts/generate", [
                'text' => $text,
                'engine' => $engine,
                'language' => $language,
                'profile_path' => $profilePath,
                'output_path' => $outputPath,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("FastAPI TTS call failed, falling back to CLI: " . $response->body());
        }

        // Fallback: execute isolated CLI process via Process facade
        $cmd = [
            $this->pythonBin,
            '-m', 'app.cli',
            'tts',
            '--text', $text,
            '--engine', $engine,
            '--language', $language,
        ];

        if ($outputPath) {
            $cmd[] = '--output';
            $cmd[] = $outputPath;
        }

        if ($profilePath) {
            $cmd[] = '--profile';
            $cmd[] = $profilePath;
        }

        $result = Process::path($this->enginePath)
            ->env($this->getExecutionEnvironment())
            ->timeout(600)
            ->run($cmd);

        if (!$result->successful()) {
            $humanMsg = self::parseHumanErrorMessage($result->errorOutput(), $engine);
            throw new \RuntimeException($humanMsg);
        }

        $output = self::cleanUtf8($result->output());
        $data = json_decode($output, true);
        if (!$data || empty($data['success'])) {
            $raw = $data['error'] ?? $output;
            $humanMsg = self::parseHumanErrorMessage($raw, $engine);
            throw new \RuntimeException($humanMsg);
        }

        return $data;
    }

    /**
    /**
     * Get user-configured or environment default TTS engine.
     */
    public function getDefaultTtsEngine(): string
    {
        $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            if (!empty($settings['default_tts_engine'])) {
                return $settings['default_tts_engine'];
            }
        }
        return env('DEFAULT_TTS_ENGINE', 'piper-tr');
    }

    /**
     * Get user-configured or environment default STT model.
     */
    public function getDefaultSttModel(): string
    {
        $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            if (!empty($settings['default_stt_model'])) {
                return $settings['default_stt_model'];
            }
        }
        return env('DEFAULT_STT_MODEL', 'whisper-medium');
    }

    /**
     * Transcribe speech to text (STT).
     */
    public function transcribeStt(string $audioFilePath, string $language = 'tr', ?string $modelSize = null, ?string $engine = null): array
    {
        if (!file_exists($audioFilePath)) {
            throw new \RuntimeException("Audio file not found: {$audioFilePath}");
        }

        if (filesize($audioFilePath) < 100) {
            return [
                'text' => '',
                'language' => $language,
                'segments' => [],
            ];
        }

        if (empty($modelSize)) {
            $modelSize = $this->getDefaultSttModel();
        }

        if ($this->isServiceOnline()) {
            $postData = ['language' => $language];
            if (!empty($modelSize)) {
                $postData['model_size'] = $modelSize;
            }
            if (!empty($engine)) {
                $postData['engine'] = $engine;
            }

            $response = Http::timeout(600)
                ->attach('file', file_get_contents($audioFilePath), basename($audioFilePath))
                ->post("{$this->baseUrl}/stt/transcribe", $postData);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("FastAPI STT call failed, falling back to CLI: " . $response->body());
        }

        $cmd = [
            $this->pythonBin,
            '-m', 'app.cli',
            'stt',
            '--audio', $audioFilePath,
            '--language', $language,
        ];

        if (!empty($modelSize)) {
            $cmd[] = '--model-size';
            $cmd[] = $modelSize;
        }

        if (!empty($engine)) {
            $cmd[] = '--engine';
            $cmd[] = $engine;
        }

        $result = Process::path($this->enginePath)
            ->env($this->getExecutionEnvironment())
            ->timeout(600)
            ->run($cmd);

        if (!$result->successful()) {
            throw new \RuntimeException("STT transcription CLI failed: " . self::cleanUtf8($result->errorOutput()));
        }

        $output = self::cleanUtf8($result->output());
        return json_decode($output, true) ?? ['text' => $output];
    }

    /**
     * Total size in bytes of all files inside a directory.
     */
    public static function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            // Ignore concurrent write access errors
        }

        return $size;
    }

    /**
     * Download a model from HuggingFace / Piper with live progress monitoring.
     */
    public function downloadModel(string $modelId, ?callable $onProgress = null): array
    {
        Cache::forget('voice_available_models');

        $models = $this->getAvailableModels();
        $targetModel = collect($models)->firstWhere('id', $modelId);

        // Cloud models (Patientdesk, Freya, OpenAI, ElevenLabs, etc.) don't have local weights to download
        if (!empty($targetModel['is_cloud'])) {
            $provider = strtolower($targetModel['cloud_provider'] ?? 'freya');
            $keyMap = [
                'freya' => 'freya_api_key',
                'patientdesk' => 'patientdesk_api_key',
                'openai' => 'openai_api_key',
                'elevenlabs' => 'elevenlabs_api_key',
                'google' => 'google_cloud_api_key',
                'groq' => 'groq_api_key',
                'gemini' => 'gemini_api_key',
                'anthropic' => 'anthropic_api_key',
                'deepseek' => 'deepseek_api_key',
                'openrouter' => 'openrouter_api_key',
            ];
            $fieldName = $keyMap[$provider] ?? "{$provider}_api_key";
            $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
            $providerKey = env(strtoupper($fieldName));
            if (file_exists($settingsFile)) {
                $st = json_decode(file_get_contents($settingsFile), true);
                if (!empty($st[$fieldName])) {
                    $providerKey = trim($st[$fieldName]);
                }
            }
            if (empty($providerKey)) {
                $providerName = ucfirst($provider);
                throw new \RuntimeException("{$targetModel['name']} bir Bulut API servisidir (yerel dosya indirmesi gerekmez). Lütfen Model Yöneticisi veya Ayarlar sayfasından {$providerName} API Anahtarınızı giriniz.");
            }
            if ($onProgress) {
                $onProgress(100.0, 0, 0);
            }
            return ['success' => true, 'is_cloud' => true];
        }

        $estimateMb = (int) ($targetModel['size_estimate_mb'] ?? 1000);
        $totalBytes = max(1024 * 1024, $estimateMb * 1024 * 1024);
        $targetDir = base_path('data/models/' . $modelId);

        // Load custom models dir and HF token from data/settings.json if present
        $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        $hfToken = env('HF_TOKEN');
        if (file_exists($settingsFile)) {
            try {
                $st = json_decode(file_get_contents($settingsFile), true);
                if (!empty($st['hf_token'])) {
                    $hfToken = trim($st['hf_token']);
                }
                if (!empty($st['models_dir'])) {
                    $targetDir = rtrim($st['models_dir'], '/\\') . DIRECTORY_SEPARATOR . $modelId;
                }
            } catch (\Throwable $e) {}
        }

        $cmd = [
            $this->pythonBin,
            '-m', 'app.cli',
            'download',
            '--model', $modelId,
        ];
        if ($hfToken) {
            $cmd[] = '--token';
            $cmd[] = $hfToken;
        }

        $env = [];
        if ($hfToken) {
            $env['HF_TOKEN'] = $hfToken;
            $env['HUGGING_FACE_HUB_TOKEN'] = $hfToken;
        }

        $env = $this->getExecutionEnvironment($env);

        $process = Process::path($this->enginePath)
            ->timeout(3600)
            ->env($env)
            ->start($cmd);

        while ($process->running()) {
            if ($onProgress) {
                $currentBytes = self::getDirectorySize($targetDir);
                $pct = $totalBytes > 0
                    ? min(99.0, max(5.0, round(($currentBytes / $totalBytes) * 100, 1)))
                    : 5.0;
                $onProgress($pct, $currentBytes, $totalBytes);
            }
            sleep(1);
        }

        $result = $process->wait();
        Cache::forget('voice_available_models');

        if (!$result->successful()) {
            throw new \RuntimeException("Model download failed: " . self::cleanUtf8($result->errorOutput()));
        }

        $output = self::cleanUtf8($result->output());
        return json_decode($output, true) ?? ['success' => true];
    }

    /**
     * Get available models list.
     */
    public function getAvailableModels(): array
    {
        return Cache::remember('voice_available_models', 180, function () {
            $fromPython = [];

            if ($this->isServiceOnline()) {
                try {
                    $response = Http::timeout(2)->get("{$this->baseUrl}/models/available");
                    if ($response->successful()) {
                        $fromPython = $response->json() ?? [];
                    }
                } catch (\Throwable $e) {
                    // fall through
                }
            }

            if (empty($fromPython)) {
                $result = Process::path($this->enginePath)
                    ->env($this->getExecutionEnvironment())
                    ->timeout(10)
                    ->run([$this->pythonBin, '-m', 'app.cli', 'models']);

                $output = self::cleanUtf8($result->output());
                $fromPython = json_decode($output, true) ?? [];
            }

            // Augment: inject or reconcile AudioGen model
            $audiogenPaths = [
                base_path('data/models/audiogen-medium'),
                base_path('data/models/audiogen-base'),
                base_path('data/models/audiocraft'),
                base_path('engine/models/audiogen'),
            ];
            $isAudiogenInstalled = collect($audiogenPaths)->contains(fn($p) => is_dir($p));

            $hasAudiogen = false;
            foreach ($fromPython as &$m) {
                if (str_contains(strtolower($m['id'] ?? ''), 'audiogen')) {
                    $hasAudiogen = true;
                    $m['type'] = 'sfx';
                    $m['category'] = 'sfx';
                    if ($isAudiogenInstalled) {
                        $m['is_downloaded'] = true;
                    }
                }
            }
            unset($m);

            if (!$hasAudiogen) {
                $fromPython[] = [
                    'id'               => 'audiogen-medium',
                    'name'             => 'AudioGen Medium',
                    'type'             => 'sfx',
                    'category'         => 'sfx',
                    'description'      => 'Meta AudioCraft — AI tabanlı ses efekti üretimi (foley, ambiyans, sinematik SFX). SFX Stüdyosu\'nda Yerel AudioGen motoru ile kullanılır.',
                    'is_downloaded'    => $isAudiogenInstalled,
                    'is_cloud'         => false,
                    'size_estimate_mb' => 1500,
                    'tags'             => ['sfx', 'audiogen', 'audiocraft', 'meta'],
                    'hf_repo'          => 'facebook/audiogen-medium',
                    'engine'           => 'audiogen',
                    'requires_gpu'     => false,
                    'notes'            => $isAudiogenInstalled ? 'Kurulu — SFX Stüdyosu\'nda kullanılabilir.' : 'Kurulu değil — indirmek için tıklayın.',
                ];
            }

            // Augment: ElevenLabs Sound Effects cloud model check
            $hasElevenlabsSfx = collect($fromPython)->contains(fn($m) => ($m['id'] ?? '') === 'elevenlabs-sfx');
            $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
            $elevenKey = env('ELEVENLABS_API_KEY');
            if (file_exists($settingsFile)) {
                $st = json_decode(file_get_contents($settingsFile), true);
                if (!empty($st['elevenlabs_api_key'])) {
                    $elevenKey = trim($st['elevenlabs_api_key']);
                }
            }
            $isElevenConfigured = !empty($elevenKey);

            if ($hasElevenlabsSfx) {
                foreach ($fromPython as &$m) {
                    if (($m['id'] ?? '') === 'elevenlabs-sfx') {
                        $m['is_downloaded'] = $isElevenConfigured;
                    }
                }
                unset($m);
            } else {
                $fromPython[] = [
                    'id'               => 'elevenlabs-sfx',
                    'name'             => 'ElevenLabs Sound Effects',
                    'type'             => 'sfx',
                    'category'         => 'sfx',
                    'description'      => 'ElevenLabs yapay zeka ses efekti ve foley motoru. Zengin ve sinematik ses efektleri üretir (Bulut API).',
                    'is_downloaded'    => $isElevenConfigured,
                    'is_cloud'         => true,
                    'cloud_provider'   => 'elevenlabs',
                    'size_estimate_mb' => 0,
                    'tags'             => ['sfx', 'elevenlabs', 'cloud'],
                    'engine'           => 'elevenlabs-sfx',
                    'requires_gpu'     => false,
                ];
            }

            // Augment: Patientdesk models check
            $patientdeskKey = env('PATIENTDESK_API_KEY');
            if (file_exists($settingsFile)) {
                $st = json_decode(file_get_contents($settingsFile), true);
                if (!empty($st['patientdesk_api_key'])) {
                    $patientdeskKey = trim($st['patientdesk_api_key']);
                }
            }
            $isPatientdeskConfigured = !empty($patientdeskKey);

            $hasAlania = collect($fromPython)->contains(fn($m) => ($m['id'] ?? '') === 'alania');
            $hasDuyu = collect($fromPython)->contains(fn($m) => ($m['id'] ?? '') === 'duyu');

            foreach ($fromPython as &$m) {
                if (($m['cloud_provider'] ?? '') === 'patientdesk') {
                    $m['is_downloaded'] = $isPatientdeskConfigured;
                }
            }
            unset($m);

            if (!$hasAlania) {
                $fromPython[] = [
                    'id'               => 'alania',
                    'name'             => 'Patientdesk Alania (Türkçe TTS • Bulut)',
                    'type'             => 'tts',
                    'category'         => 'tts',
                    'engine'           => 'alania',
                    'repo_id'          => 'patientdesk/alania',
                    'description'      => 'Patientdesk.ai Türkçe metinden sese modeli. Doğal ve tutarlı tek bir ses üzerinden, düşük gecikmeli akış (streaming) desteğiyle telefon görüşmeleri ve sesli asistanlar için optimize edilmiştir. OpenAI API formatıyla tam uyumlu.',
                    'is_downloaded'    => $isPatientdeskConfigured,
                    'is_cloud'         => true,
                    'cloud_provider'   => 'patientdesk',
                    'size_estimate_mb' => 0,
                    'languages'        => ['tr'],
                    'license'          => 'Lansman Ücretsiz (1 Ay) / Ticari',
                    'homepage_url'     => 'https://speech.patientdesk.ai',
                ];
            }

            if (!$hasDuyu) {
                $fromPython[] = [
                    'id'               => 'duyu',
                    'name'             => 'Patientdesk Duyu (Türkçe STT • Bulut)',
                    'type'             => 'stt',
                    'category'         => 'stt',
                    'engine'           => 'duyu',
                    'repo_id'          => 'patientdesk/duyu',
                    'description'      => 'Patientdesk.ai konuşmayı metne dönüştürme (STT) modeli. Kayıtlı sesleri ve canlı konuşmaları metne aktarır. FLEURS açık Türkçe veri setinde %4.71 kelime hata oranıyla Whisper Large-v3\'ü (%5.04) geride bıraktı; telefon konuşmalarında %9.87 WER elde etti. OpenAI Whisper API formatıyla tam uyumlu.',
                    'is_downloaded'    => $isPatientdeskConfigured,
                    'is_cloud'         => true,
                    'cloud_provider'   => 'patientdesk',
                    'size_estimate_mb' => 0,
                    'languages'        => ['tr'],
                    'license'          => 'Lansman Ücretsiz (1 Ay) / Ticari',
                    'homepage_url'     => 'https://speech.patientdesk.ai',
                ];
            }

            return $fromPython;
        });
    }


    /**
     * Get system resource statistics (CPU, RAM, Disk, GPU).
     */
    public function getSystemStats(): array
    {
        return Cache::remember('system_stats_metric', 5, function () {
            if ($this->isServiceOnline()) {
                try {
                    $response = Http::timeout(1)->get("{$this->baseUrl}/system/stats");
                    if ($response->successful()) {
                        return $response->json();
                    }
                } catch (\Throwable $e) {
                    // fall through to non-blocking native fallback
                }
            }

            // High-speed native PHP metrics (< 0.1ms, non-blocking)
            $diskTotal = @disk_total_space(base_path()) ?: 1;
            $diskFree = @disk_free_space(base_path()) ?: 0;
            $diskUsed = max(0, $diskTotal - $diskFree);
            $diskPct = round(($diskUsed / $diskTotal) * 100, 1);

            return [
                'cpu_pct' => 0.0,
                'ram' => [
                    'used_gb' => 0.0,
                    'total_gb' => 0.0,
                    'pct' => 0.0,
                ],
                'disk' => [
                    'used_gb' => round($diskUsed / (1024 ** 3), 1),
                    'total_gb' => round($diskTotal / (1024 ** 3), 1),
                    'pct' => $diskPct,
                ],
                'gpu' => [
                    'backend' => 'cpu',
                    'allocated_gb' => 0.0,
                ],
            ];
        });
    }

    /**
     * Ensure string is safe, valid UTF-8, converting Windows-1254/CP857 if necessary.
     */
    public static function cleanUtf8(?string $string): string
    {
        if ($string === null || $string === '') {
            return '';
        }
        if (mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }
        $converted = @iconv('WINDOWS-1254', 'UTF-8//IGNORE', $string);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }

    /**
     * Parse raw CLI stderr and return a user-friendly, descriptive message.
     */
    public static function parseHumanErrorMessage(string $rawError, string $engine = ''): string
    {
        $clean = self::cleanUtf8($rawError);

        if (str_contains($clean, 'Freya Voice API HTTP error 403') || (str_contains(strtolower($clean), 'freya') && str_contains($clean, 'Invalid API key'))) {
            return "Freya Voice API Hatası (HTTP 403): API anahtarınız geçersiz (Invalid API key). Lütfen geçerli bir Freya API anahtarı tanımlayın veya yerel bir vokal motoru (Piper TR) seçin.";
        }

        if (str_contains($clean, 'FREYA_API_KEY is not configured')) {
            return "Freya Voice API anahtarı tanımlanmamış. Lütfen Ayarlar veya Model Yöneticisi'nden Freya API anahtarınızı kaydedin ya da yerel motorlardan (Piper TR) birini seçin.";
        }

        if (str_contains($clean, 'Freya Voice Cloud API connection error')) {
            return "Freya Voice bulut sunucusuna bağlanılamadı. Lütfen internet bağlantınızı kontrol edin veya yerel Piper TR motorunu seçin.";
        }

        if (str_contains($clean, 'OpenAI') && (str_contains($clean, '401') || str_contains($clean, 'Incorrect API key'))) {
            return "OpenAI API Hatası: API anahtarınız geçersiz veya yetkisiz. Lütfen OpenAI API anahtarınızı kontrol edin.";
        }

        if (str_contains($clean, 'ElevenLabs') && (str_contains($clean, '401') || str_contains($clean, 'quota'))) {
            return "ElevenLabs API Hatası: API anahtarınız geçersiz veya kullanım kotanız dolmuş.";
        }

        if (str_contains($clean, 'Google Cloud') && str_contains($clean, 'error')) {
            return "Google Cloud TTS Hatası: Google Cloud API anahtarı veya servis hesabı doğrulanamadı.";
        }

        if (str_contains(strtolower($clean), 'patientdesk') && (str_contains($clean, '401') || str_contains($clean, '403') || str_contains($clean, 'API key'))) {
            return "Patientdesk.ai API Hatası: API anahtarınız geçersiz veya yetkisiz. Lütfen speech.patientdesk.ai üzerinden aldığınız anahtarı kontrol edin.";
        }

        if (str_contains($clean, 'Model not found') && str_contains($clean, 'Model Manager')) {
            return "Seçilen model dosyaları yerel diskte bulunamadı. Lütfen Model Yöneticisi'nden modeli indirin veya kurulu bir model seçin.";
        }

        // Strip loguru prefixes if present e.g. "... | ERROR | ... - "
        if (preg_match('/\|\s*ERROR\s*\|\s*[^:]+:\d+\s*-\s*(.+)$/m', $clean, $m)) {
            $msg = trim($m[1]);
            if (preg_match('/\{.*"detail"\s*:\s*"([^"]+)".*\}/', $msg, $jm)) {
                return "TTS Hatası: " . $jm[1];
            }
            if (preg_match('/\{.*"error"\s*:\s*"([^"]+)".*\}/', $msg, $jm)) {
                return "TTS Hatası: " . $jm[1];
            }
            return "TTS Hatası: " . $msg;
        }

        if (strlen($clean) > 280) {
            $clean = substr($clean, 0, 280) . '...';
        }

        return "TTS Üretim Hatası: " . $clean;
    }

    /**
     * Sanitize text for TTS to ensure speech models (Piper, XTTS, etc.)
     * do not pronounce markdown formatting, asterisks, emojis, or stage directions aloud.
     */
    public static function sanitizeTextForTts(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // 1. Remove voiceover / director notes blocks: (Seslendirme Notu: ...) or [Yönetmen Notu: ...]
        $text = preg_replace('/(?:\*{0,2})[\[\(](?:seslendirme\s*notu|y\x{00F6}netmen\s*notu|ton|tarz|talimat|ses\s*tonu|not)[:\-–\s]+[^\]\)]+[\]\)](?:\*{0,2})/iu', ' ', $text);

        // 2. Remove Markdown headings (### 🎤 Title)
        $text = preg_replace('/(?:^|\n)\s*#{1,6}\s+[^\n]+/u', ' ', $text);
        $text = preg_replace('/#{1,6}\s+[^*\n]+(?=\*\*|\*|\n|$)/iu', ' ', $text);
        $text = preg_replace('/#{1,6}\s+/u', ' ', $text);

        // 3. Remove stage / section directions in parentheses or brackets:
        // e.g. **(Giriş – Enerjik)**, (Bülten – Dinamik), (Kapanış – Güven Veren)
        $text = preg_replace('/(?:\*{0,2})[\[\(](?:giri\x{015F}|geli\x{015F}me|b\x{00FC}lten|kapan\x{0131}\x{015F}|anons|m\x{00FC}zik|es|ton|arka\s*plan|efekt|enerjik|dinamik|tarafs\x{0131}z|g\x{00FC}ven|selamlay\x{0131}c\x{0131}|seslendirme|spiker|not|talimat)[^\]\)]*[\]\)](?:\*{0,2})/iu', ' ', $text);
        $text = preg_replace('/\*\*\([^)]+\)\*\*/u', ' ', $text);
        $text = preg_replace('/\[\([^)]+\)\]/u', ' ', $text);

        // 4. Remove horizontal dividers & decorative asterisks
        $text = preg_replace('/[\*\-_]{3,}/u', ' ', $text);

        // 5. Remove inline markdown bold, italic, code
        $text = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $text);
        $text = preg_replace('/\*([^*]+)\*/u', '$1', $text);
        $text = preg_replace('/__([^_]+)__/u', '$1', $text);
        $text = preg_replace('/_([^_]+)_/u', '$1', $text);
        $text = preg_replace('/`([^`]+)`/u', '$1', $text);
        $text = preg_replace('/~~([^~]+)~~/u', '$1', $text);

        // 6. Remove emojis
        $text = preg_replace('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F1E0}-\x{1F1FF}]/u', ' ', $text);

        // 7. Remove orphaned symbols
        $text = preg_replace('/[*#~]/u', '', $text);

        // 8. Normalize whitespace
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * Get safe execution environment variables for Python subprocesses.
     * On Windows, ensures SystemRoot and PATH are present so Winsock (WSAStartup / _overlapped / asyncio)
     * does not fail with WinError 10106 (WSAEPROVIDERFAILEDINIT).
     */
    public function getExecutionEnvironment(?array $additionalEnv = null): array
    {
        $env = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';
            $winDir = getenv('WINDIR') ?: getenv('windir') ?: $systemRoot;
            $systemDrive = getenv('SYSTEMDRIVE') ?: 'C:';
            $path = getenv('PATH') ?: getenv('Path') ?: "{$systemRoot}\\system32;{$systemRoot};{$systemRoot}\\System32\\Wbem";
            $temp = getenv('TEMP') ?: getenv('TMP') ?: sys_get_temp_dir();

            $env['SystemRoot'] = $systemRoot;
            $env['SYSTEMROOT'] = $systemRoot;
            $env['WINDIR'] = $winDir;
            $env['SYSTEMDRIVE'] = $systemDrive;
            $env['PATH'] = $path;
            $env['TEMP'] = $temp;
            $env['TMP'] = $temp;

            if ($appData = getenv('APPDATA')) {
                $env['APPDATA'] = $appData;
            }
            if ($localAppData = getenv('LOCALAPPDATA')) {
                $env['LOCALAPPDATA'] = $localAppData;
            }
            if ($userProfile = getenv('USERPROFILE')) {
                $env['USERPROFILE'] = $userProfile;
            }
        } else {
            if ($path = getenv('PATH')) {
                $env['PATH'] = $path;
            }
            if ($home = getenv('HOME')) {
                $env['HOME'] = $home;
            }
        }

        if ($additionalEnv) {
            $env = array_merge($env, $additionalEnv);
        }

        // Pass Cloud Provider API Keys to Python subprocess
        $settingsFile = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        $savedSettings = [];
        if (file_exists($settingsFile)) {
            $savedSettings = json_decode(file_get_contents($settingsFile), true) ?: [];
        }

        $cloudKeys = [
            'FREYA_API_KEY' => $savedSettings['freya_api_key'] ?? env('FREYA_API_KEY'),
            'OPENAI_API_KEY' => $savedSettings['openai_api_key'] ?? env('OPENAI_API_KEY'),
            'ELEVENLABS_API_KEY' => $savedSettings['elevenlabs_api_key'] ?? env('ELEVENLABS_API_KEY'),
            'GOOGLE_CLOUD_API_KEY' => $savedSettings['google_cloud_api_key'] ?? $savedSettings['gemini_api_key'] ?? env('GOOGLE_CLOUD_API_KEY') ?: env('GEMINI_API_KEY'),
            'GROQ_API_KEY' => $savedSettings['groq_api_key'] ?? env('GROQ_API_KEY'),
            'PATIENTDESK_API_KEY' => $savedSettings['patientdesk_api_key'] ?? env('PATIENTDESK_API_KEY'),
        ];

        foreach ($cloudKeys as $envName => $keyValue) {
            if (!isset($env[$envName]) && !empty($keyValue)) {
                $env[$envName] = trim($keyValue);
            }
        }

        return $env;
    }
}
