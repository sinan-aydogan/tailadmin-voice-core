<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SfxGenerationService
{
    protected string $enginePath;
    protected string $pythonBin;
    protected string $sfxDir;
    protected string $metadataFile;
    protected PythonVoiceService $pythonService;

    public function __construct(PythonVoiceService $pythonService)
    {
        $this->pythonService = $pythonService;
        $this->enginePath = base_path('engine');
        $this->pythonBin = config('services.python_voice.binary', env('PYTHON_BINARY', 'python'));
        $this->sfxDir = base_path('data/sfx');
        $this->metadataFile = base_path('data/sfx/metadata.json');

        if (!File::exists($this->sfxDir)) {
            File::makeDirectory($this->sfxDir, 0755, true);
        }
    }

    /**
     * Get built-in categories and quick-start presets.
     */
    public function getCategories(): array
    {
        return [
            [
                'id' => 'nature',
                'name' => 'Doğa & Çevre',
                'icon' => 'leaf',
                'presets' => [
                    ['id' => 'birds_chirping', 'name' => 'Kuş Cıvıltısı', 'prompt' => 'Sabah güneşiyle ormanda neşeli öten kuş cıvıltıları', 'duration' => 1.8],
                    ['id' => 'wind_whoosh', 'name' => 'Rüzgar Uğultusu', 'prompt' => 'Ağaçların arasından esen kuru rüzgar uğultusu', 'duration' => 2.5],
                    ['id' => 'thunder_storm', 'name' => 'Gök Gürültüsü & Şimşek', 'prompt' => 'Uzakta patlayan derin gök gürültüsü ve şimşek yankısı', 'duration' => 2.5],
                    ['id' => 'rain_drops', 'name' => 'Yağmur & Su Damlaları', 'prompt' => 'Yaprakların üzerine düşen sakin yağmur damlaları', 'duration' => 2.5],
                ]
            ],
            [
                'id' => 'fantasy',
                'name' => 'Fantezi & Büyü',
                'icon' => 'sparkles',
                'presets' => [
                    ['id' => 'magic_bell', 'name' => 'Sihirli Çan & Peri Tozu', 'prompt' => 'Işıltılı sihirli değnek peri tozu sesi ve kristal çan', 'duration' => 2.5],
                    ['id' => 'laser_blaster', 'name' => 'Lazer Silahı & Zap', 'prompt' => 'Fütüristik uzay gemisi lazer silahı atışı', 'duration' => 0.8],
                ]
            ],
            [
                'id' => 'cinematic',
                'name' => 'Sinematik & Mekanik',
                'icon' => 'film',
                'presets' => [
                    ['id' => 'sword_clash', 'name' => 'Kılıç & Metal Çarpışması', 'prompt' => 'İki şövalyenin kılıçlarının birbirine sertçe çarpması', 'duration' => 1.4],
                    ['id' => 'door_creak', 'name' => 'Eski Kapı Gıcırtısı', 'prompt' => 'Terk edilmiş eski şatodaki ahşap kapının gıcırtıyla açılması', 'duration' => 1.8],
                    ['id' => 'footsteps', 'name' => 'Adım Sesleri', 'prompt' => 'Toprak yolda temkinli yürüyen insan adım sesleri', 'duration' => 1.6],
                    ['id' => 'cartoon_boing', 'name' => 'Çizgi Film Yay / Boing', 'prompt' => 'Zıp zıp zıplayan eğlenceli çizgi film yay sesi', 'duration' => 1.0],
                ]
            ]
        ];
    }

    /**
     * Generate an SFX via Python engine.
     */
    public function generate(array $params): array
    {
        $prompt = trim($params['prompt'] ?? '');
        $preset = $params['preset'] ?? 'auto';
        $duration = (float)($params['duration'] ?? 2.0);
        $reverb = $params['reverb'] ?? 'room';
        $tone = $params['tone'] ?? 'balanced';
        $engineMode = $params['engine'] ?? 'smart_synth';

        // Unique filename
        $prefix = (!empty($preset) && $preset !== 'auto') ? $preset : 'sfx';
        $filename = "{$prefix}_" . substr(md5(uniqid('', true)), 0, 6) . '.wav';
        $outputPath = "{$this->sfxDir}/{$filename}";

        $parsed = [];

        if ($engineMode === 'cloud_elevenlabs') {
            $settingsFile = base_path('data/settings.json');
            $settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
            $elevenKey = trim($settings['elevenlabs_api_key'] ?? env('ELEVENLABS_API_KEY', ''));

            if (empty($elevenKey)) {
                throw new \RuntimeException('ElevenLabs API anahtarı tanımlı değil. Lütfen Modeller veya Ayarlar sayfasından ElevenLabs API anahtarınızı giriniz.');
            }

            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'xi-api-key' => $elevenKey,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post('https://api.elevenlabs.io/v1/sound-generation', [
                    'text' => $prompt ?: 'sound effect',
                    'duration_seconds' => min(10.0, max(0.5, $duration)),
                    'prompt_influence' => 0.3,
                ]);

                if (!$response->successful()) {
                    $err = $response->json('detail.message') ?? $response->body();
                    throw new \RuntimeException('ElevenLabs ses efekti üretimi başarısız: ' . $err);
                }

                File::put($outputPath, $response->body());
                $parsed = [
                    'success' => true,
                    'preset' => $preset,
                    'title' => !empty($params['title']) ? $params['title'] : 'ElevenLabs SFX',
                    'category' => 'Bulut Foley',
                    'duration_sec' => $duration,
                    'output_path' => $outputPath,
                    'filename' => $filename,
                ];
            } catch (\Throwable $e) {
                Log::error('ElevenLabs SFX API error: ' . $e->getMessage());
                throw $e;
            }
        } elseif ($engineMode === 'audiogen') {
            $audiogenPaths = [
                base_path('data/models/audiogen-medium'),
                base_path('data/models/audiogen-base'),
                base_path('data/models/audiocraft'),
                base_path('engine/models/audiogen'),
            ];
            $isAudiogenInstalled = collect($audiogenPaths)->contains(fn($p) => is_dir($p));

            if (!$isAudiogenInstalled) {
                throw new \RuntimeException('Yerel AudioGen modeli (AudioGen Medium) diskinizde henüz kurulu değil. Lütfen Modeller sayfasından AudioGen modelini indirin veya "Hızlı Akıllı DSP Sentez" motorunu seçin.');
            }

            // Execute local generator with audiogen preset
            $cmd = [
                $this->pythonBin,
                '-m',
                'app.cli',
                'sfx-generate',
                '--prompt', $prompt,
                '--preset', $preset,
                '--duration', (string)$duration,
                '--reverb', $reverb,
                '--tone', $tone,
                '--output', $outputPath,
            ];

            $result = Process::path($this->enginePath)
                ->env($this->pythonService->getExecutionEnvironment())
                ->timeout(45)
                ->run($cmd);

            if (!$result->successful() || !File::exists($outputPath)) {
                throw new \RuntimeException('AudioGen ses efekti üretimi başarısız: ' . ($result->errorOutput() ?: $result->output()));
            }

            $parsed = json_decode($result->output(), true) ?: [];
        } else {
            // Default smart_synth DSP
            $cmd = [
                $this->pythonBin,
                '-m',
                'app.cli',
                'sfx-generate',
                '--prompt', $prompt,
                '--preset', $preset,
                '--duration', (string)$duration,
                '--reverb', $reverb,
                '--tone', $tone,
                '--output', $outputPath,
            ];

            $result = Process::path($this->enginePath)
                ->env($this->pythonService->getExecutionEnvironment())
                ->timeout(25)
                ->run($cmd);

            if (!$result->successful() || !File::exists($outputPath)) {
                Log::error('SFX generation failed', [
                    'output' => $result->output(),
                    'error' => $result->errorOutput(),
                ]);
                throw new \RuntimeException('Ses efekti üretimi başarısız oldu: ' . ($result->errorOutput() ?: $result->output()));
            }

            $parsed = json_decode($result->output(), true) ?: [];
        }

        // Save metadata
        $title = !empty($params['title']) ? $params['title'] : ($parsed['title'] ?? 'Yeni Ses Efekti');
        $this->saveMetadata($filename, [
            'filename' => $filename,
            'title' => $title,
            'category' => $parsed['category'] ?? 'Özel Efekt',
            'preset' => $preset,
            'prompt' => $prompt,
            'duration_sec' => $parsed['duration_sec'] ?? $duration,
            'reverb' => $reverb,
            'tone' => $tone,
            'engine' => $engineMode,
            'created_at' => now()->toIso8601String(),
        ]);

        return array_merge($parsed, [
            'filename' => $filename,
            'title' => $title,
            'size_bytes' => File::size($outputPath),
            'audio_url' => url("/api/sfx/audio/{$filename}"),
        ]);
    }

    /**
     * Get all SFX in the library.
     */
    public function getLibrary(): array
    {
        $meta = $this->loadMetadata();
        $files = File::glob("{$this->sfxDir}/*.wav");
        $library = [];

        // Known default labels
        $knownLabels = [
            'birds_chirping.wav' => ['title' => 'Kuş Cıvıltıları', 'category' => 'Doğa & Çevre', 'duration' => 1.8],
            'wind_whoosh.wav' => ['title' => 'Rüzgar Uğultusu', 'category' => 'Doğa & Çevre', 'duration' => 2.5],
            'magic_bell.wav' => ['title' => 'Sihirli Çan', 'category' => 'Fantezi & Büyü', 'duration' => 2.4],
            'cat_meow.wav' => ['title' => 'Kedi Miyavlaması', 'category' => 'Hayvan & Karakter', 'duration' => 1.2],
            'dog_bark.wav' => ['title' => 'Köpek Havlaması', 'category' => 'Hayvan & Karakter', 'duration' => 0.8],
            'cough.wav' => ['title' => 'Öksürük Sesi', 'category' => 'Vücut & Sağlık', 'duration' => 0.9],
            'door_creak.wav' => ['title' => 'Kapı Gıcırtısı', 'category' => 'Mekanik & Gizem', 'duration' => 1.8],
            'footsteps.wav' => ['title' => 'Adım Sesleri', 'category' => 'Karakter & Hareket', 'duration' => 1.5],
        ];

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $size = File::size($filePath);
            $itemMeta = $meta[$filename] ?? ($knownLabels[$filename] ?? null);

            // Approximate duration from 24kHz 16-bit mono WAV: size / (24000 * 2)
            $calcDuration = round(($size - 44) / 48000, 2);
            $duration = $itemMeta['duration_sec'] ?? ($itemMeta['duration'] ?? max(0.2, $calcDuration));

            $library[] = [
                'filename' => $filename,
                'title' => $itemMeta['title'] ?? ucwords(str_replace(['_', '.wav'], [' ', ''], $filename)),
                'category' => $itemMeta['category'] ?? 'Genel Efekt',
                'duration_sec' => $duration,
                'size_bytes' => $size,
                'prompt' => $itemMeta['prompt'] ?? '',
                'audio_url' => url("/api/sfx/audio/{$filename}"),
                'created_at' => $itemMeta['created_at'] ?? date('c', File::lastModified($filePath)),
                'is_default' => isset($knownLabels[$filename]),
            ];
        }

        // Sort: newest first
        usort($library, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $library;
    }

    /**
     * Delete an SFX file.
     */
    public function delete(string $filename): bool
    {
        $clean = str_replace(['..', '/', '\\'], '', $filename);
        $path = "{$this->sfxDir}/{$clean}";
        if (File::exists($path)) {
            File::delete($path);
            $this->removeMetadata($clean);
            return true;
        }
        return false;
    }

    /**
     * Metadata helpers.
     */
    protected function loadMetadata(): array
    {
        if (File::exists($this->metadataFile)) {
            return json_decode(File::get($this->metadataFile), true) ?: [];
        }
        return [];
    }

    protected function saveMetadata(string $filename, array $data): void
    {
        $meta = $this->loadMetadata();
        $meta[$filename] = $data;
        File::put($this->metadataFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function removeMetadata(string $filename): void
    {
        $meta = $this->loadMetadata();
        unset($meta[$filename]);
        File::put($this->metadataFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
