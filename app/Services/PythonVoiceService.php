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
    }

    /**
     * Generate speech from text (TTS).
     */
    public function generateTts(string $text, string $engine = 'piper-tr', string $language = 'tr', ?string $profilePath = null, ?string $outputPath = null): array
    {
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
            ->timeout(600)
            ->run($cmd);

        if (!$result->successful()) {
            throw new \RuntimeException("TTS generation CLI failed: " . self::cleanUtf8($result->errorOutput()));
        }

        $output = self::cleanUtf8($result->output());
        $data = json_decode($output, true);
        if (!$data || empty($data['success'])) {
            throw new \RuntimeException("TTS generation CLI error: " . ($data['error'] ?? $output));
        }

        return $data;
    }

    /**
     * Transcribe speech to text (STT).
     */
    public function transcribeStt(string $audioFilePath, string $language = 'tr', ?string $modelSize = null): array
    {
        if ($this->isServiceOnline()) {
            $postData = ['language' => $language];
            if (!empty($modelSize)) {
                $postData['model_size'] = $modelSize;
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

        $result = Process::path($this->enginePath)
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
        $estimateMb = $targetModel['size_estimate_mb'] ?? 1000;
        $totalBytes = $estimateMb * 1024 * 1024;
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

        $process = Process::path($this->enginePath)
            ->timeout(3600)
            ->env($env)
            ->start($cmd);

        while ($process->running()) {
            if ($onProgress) {
                $currentBytes = self::getDirectorySize($targetDir);
                $pct = min(99.0, max(5.0, round(($currentBytes / $totalBytes) * 100, 1)));
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
            if ($this->isServiceOnline()) {
                try {
                    $response = Http::timeout(2)->get("{$this->baseUrl}/models/available");
                    if ($response->successful()) {
                        return $response->json();
                    }
                } catch (\Throwable $e) {
                    // fall through
                }
            }

            $result = Process::path($this->enginePath)
                ->timeout(10)
                ->run([$this->pythonBin, '-m', 'app.cli', 'models']);

            $output = self::cleanUtf8($result->output());
            return json_decode($output, true) ?? [];
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
}
