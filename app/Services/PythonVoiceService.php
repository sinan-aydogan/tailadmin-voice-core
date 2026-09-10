<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

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
        try {
            $response = Http::timeout(2)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
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
            throw new \RuntimeException("TTS generation CLI failed: " . $result->errorOutput());
        }

        $data = json_decode($result->output(), true);
        if (!$data || empty($data['success'])) {
            throw new \RuntimeException("TTS generation CLI error: " . ($data['error'] ?? $result->output()));
        }

        return $data;
    }

    /**
     * Transcribe speech to text (STT).
     */
    public function transcribeStt(string $audioFilePath, string $language = 'tr'): array
    {
        if ($this->isServiceOnline()) {
            $response = Http::timeout(600)
                ->attach('file', file_get_contents($audioFilePath), basename($audioFilePath))
                ->post("{$this->baseUrl}/stt/transcribe", [
                    'language' => $language,
                ]);

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

        $result = Process::path($this->enginePath)
            ->timeout(600)
            ->run($cmd);

        if (!$result->successful()) {
            throw new \RuntimeException("STT transcription CLI failed: " . $result->errorOutput());
        }

        return json_decode($result->output(), true) ?? ['text' => $result->output()];
    }

    /**
     * Download a model from HuggingFace / Piper.
     */
    public function downloadModel(string $modelId): array
    {
        if ($this->isServiceOnline()) {
            $response = Http::timeout(30)->post("{$this->baseUrl}/models/download/{$modelId}");
            if ($response->successful()) {
                return $response->json();
            }
        }

        $result = Process::path($this->enginePath)
            ->timeout(1800)
            ->run([
                $this->pythonBin,
                '-m', 'app.cli',
                'download',
                '--model', $modelId,
            ]);

        if (!$result->successful()) {
            throw new \RuntimeException("Model download failed: " . $result->errorOutput());
        }

        return json_decode($result->output(), true) ?? ['success' => true];
    }

    /**
     * Get available models list.
     */
    public function getAvailableModels(): array
    {
        if ($this->isServiceOnline()) {
            try {
                $response = Http::timeout(5)->get("{$this->baseUrl}/models/available");
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

        return json_decode($result->output(), true) ?? [];
    }

    /**
     * Get system resource statistics (CPU, RAM, Disk, GPU).
     */
    public function getSystemStats(): array
    {
        if ($this->isServiceOnline()) {
            try {
                $response = Http::timeout(3)->get("{$this->baseUrl}/system/stats");
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $result = Process::path($this->enginePath)
            ->timeout(5)
            ->run([$this->pythonBin, '-m', 'app.cli', 'system']);

        return json_decode($result->output(), true) ?? [
            'cpu_pct' => 0,
            'ram' => ['used_gb' => 0, 'total_gb' => 0, 'pct' => 0],
            'disk' => ['used_gb' => 0, 'total_gb' => 0, 'pct' => 0],
            'gpu' => ['backend' => 'cpu', 'allocated_gb' => 0],
        ];
    }
}
