<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class MusicGenerationService
{
    protected string $enginePath;
    protected string $pythonBin;
    protected string $bgmDir;
    protected string $metadataFile;
    protected PythonVoiceService $pythonService;

    public function __construct(PythonVoiceService $pythonService)
    {
        $this->pythonService = $pythonService;
        $this->enginePath = base_path('engine');
        $this->pythonBin = config('services.python_voice.binary', env('PYTHON_BINARY', 'python'));
        $this->bgmDir = base_path('data/bgm');
        $this->metadataFile = base_path('data/bgm/metadata.json');

        if (!File::exists($this->bgmDir)) {
            File::makeDirectory($this->bgmDir, 0755, true);
        }
    }

    /**
     * Get built-in genres, musical scales, and textures.
     */
    public function getPresets(): array
    {
        return [
            'genres' => [
                [
                    'id' => 'fairytale_children',
                    'name' => 'Masal & Çocuk (Neşeli & Huzurlu)',
                    'scale' => 'C Major',
                    'default_bpm' => 90,
                    'texture' => 'acoustic',
                    'icon' => 'sun',
                    'description' => 'Sıcak akustik tonlar, tatlı masalsı akorlar ve çocuk dünyasına uygun neşeli melodi yatağı.',
                ],
                [
                    'id' => 'dramatic_cinematic',
                    'name' => 'Dramatik Sinematik (Melankoli & Gerilim)',
                    'scale' => 'D Minor',
                    'default_bpm' => 70,
                    'texture' => 'strings',
                    'icon' => 'cloud-rain',
                    'description' => 'Hüzünlü yaylılar, kuraklık ve tehlike anlarında derin hissettiren sinematik minör akorlar.',
                ],
                [
                    'id' => 'uplifting_adventure',
                    'name' => 'Umut & Macera (Yükselen & Zafer)',
                    'scale' => 'G Major',
                    'default_bpm' => 105,
                    'texture' => 'orchestral',
                    'icon' => 'compass',
                    'description' => 'Bilgelik, dayanışma ve doğanın yeniden uyanışını kutlayan parlak, ilham verici müzik.',
                ],
                [
                    'id' => 'lofi_ambient',
                    'name' => 'Lo-Fi & Rahatlatıcı (Sakin Odaklanma)',
                    'scale' => 'A Minor',
                    'default_bpm' => 80,
                    'texture' => 'lofi',
                    'icon' => 'coffee',
                    'description' => 'Sıcak Rhodes elektro piyano akorları, hafif vinil dokusu ve dinlendirici fon ambiyansı.',
                ],
                [
                    'id' => 'cosmic_scifi',
                    'name' => 'Kozmik & Bilim Kurgu (Derin Uzay)',
                    'scale' => 'E Minor',
                    'default_bpm' => 65,
                    'texture' => 'synth',
                    'icon' => 'radio',
                    'description' => 'Derin analog synth pad\'leri, uzay boşluğu tınlaması ve fütüristik gizemli akorlar.',
                ],
            ],
            'scales' => [
                'C Major', 'D Minor', 'G Major', 'A Minor', 'E Minor', 'F Major'
            ],
            'textures' => [
                ['id' => 'acoustic', 'name' => 'Akustik & Masalsı (Sıcak Akorlar)'],
                ['id' => 'strings', 'name' => 'Sinematik Yaylılar & Pad'],
                ['id' => 'lofi', 'name' => 'Lo-Fi & Rhodes Piyano'],
                ['id' => 'synth', 'name' => 'Analog Synth & Doku'],
            ]
        ];
    }

    /**
     * Generate background music via Python engine.
     */
    public function generate(array $params): array
    {
        $prompt = trim($params['prompt'] ?? '');
        $genre = $params['genre'] ?? 'fairytale_children';
        $bpm = !empty($params['bpm']) ? (int)$params['bpm'] : 90;
        $scale = $params['scale'] ?? 'C Major';
        $texture = $params['texture'] ?? 'acoustic';
        $duration = (float)($params['duration'] ?? 15.0);
        $loop = !empty($params['loop']);
        $engineMode = $params['engine'] ?? 'smart_synth';

        // Unique filename
        $prefix = (!empty($genre) && $genre !== 'auto') ? $genre : 'music';
        $filename = "{$prefix}_" . substr(md5(uniqid('', true)), 0, 6) . '.wav';
        $outputPath = "{$this->bgmDir}/{$filename}";

        $cmd = [
            $this->pythonBin,
            '-m',
            'app.cli',
            'music-generate',
            '--prompt', $prompt,
            '--genre', $genre,
            '--bpm', (string)$bpm,
            '--scale', $scale,
            '--texture', $texture,
            '--duration', (string)$duration,
            '--output', $outputPath,
        ];

        if (!$loop) {
            $cmd[] = '--no-loop';
        }

        $result = Process::path($this->enginePath)
            ->env($this->pythonService->getExecutionEnvironment())
            ->timeout(45)
            ->run($cmd);

        if (!$result->successful() || !File::exists($outputPath)) {
            Log::error('Music generation failed', [
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
            throw new \RuntimeException('Müzik üretimi başarısız oldu: ' . ($result->errorOutput() ?: $result->output()));
        }

        $parsed = json_decode($result->output(), true) ?: [];

        // Save metadata
        $title = !empty($params['title']) ? $params['title'] : ($parsed['title'] ?? 'Yeni Fon Müziği');
        $this->saveMetadata($filename, [
            'filename' => $filename,
            'title' => $title,
            'genre' => $genre,
            'prompt' => $prompt,
            'scale' => $parsed['scale'] ?? $scale,
            'bpm' => $parsed['bpm'] ?? $bpm,
            'texture' => $parsed['texture'] ?? $texture,
            'duration_sec' => $parsed['duration_sec'] ?? $duration,
            'loopable' => $loop,
            'engine' => $engineMode,
            'created_at' => now()->toIso8601String(),
        ]);

        return array_merge($parsed, [
            'filename' => $filename,
            'title' => $title,
            'size_bytes' => File::size($outputPath),
            'audio_url' => url("/api/music/audio/{$filename}"),
        ]);
    }

    /**
     * Get all music tracks in the library.
     */
    public function getLibrary(): array
    {
        $meta = $this->loadMetadata();
        $files = File::glob("{$this->bgmDir}/*.wav");
        $library = [];

        // Known default labels
        $knownLabels = [
            'peaceful_ambient.wav' => [
                'title' => 'Huzurlu Orman & Masal Ambiyansı',
                'genre' => 'fairytale_children',
                'scale' => 'C Major',
                'bpm' => 90,
                'texture' => 'acoustic',
                'duration_sec' => 15.0,
                'loopable' => true
            ],
            'dramatic_ambient.wav' => [
                'title' => 'Dramatik Hüzün & Kuraklık Teması',
                'genre' => 'dramatic_cinematic',
                'scale' => 'D Minor',
                'bpm' => 70,
                'texture' => 'strings',
                'duration_sec' => 15.0,
                'loopable' => true
            ],
            'uplifting_ambient.wav' => [
                'title' => 'Bilgelik & Umut Dolu Uyanış',
                'genre' => 'uplifting_adventure',
                'scale' => 'G Major',
                'bpm' => 105,
                'texture' => 'orchestral',
                'duration_sec' => 15.0,
                'loopable' => true
            ],
        ];

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $size = File::size($filePath);
            $itemMeta = $meta[$filename] ?? ($knownLabels[$filename] ?? null);

            // Approximate duration from 24kHz 16-bit mono WAV: size / (24000 * 2)
            $calcDuration = round(($size - 44) / 48000, 2);
            $duration = $itemMeta['duration_sec'] ?? ($itemMeta['duration'] ?? max(1.0, $calcDuration));

            $library[] = [
                'filename' => $filename,
                'title' => $itemMeta['title'] ?? ucwords(str_replace(['_', '.wav'], [' ', ''], $filename)),
                'genre' => $itemMeta['genre'] ?? 'ambient',
                'scale' => $itemMeta['scale'] ?? 'C Major',
                'bpm' => $itemMeta['bpm'] ?? 90,
                'texture' => $itemMeta['texture'] ?? 'acoustic',
                'duration_sec' => $duration,
                'size_bytes' => $size,
                'loopable' => $itemMeta['loopable'] ?? true,
                'prompt' => $itemMeta['prompt'] ?? '',
                'audio_url' => url("/api/music/audio/{$filename}"),
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
     * Delete a music file.
     */
    public function delete(string $filename): bool
    {
        $clean = str_replace(['..', '/', '\\'], '', $filename);
        $path = "{$this->bgmDir}/{$clean}";
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
