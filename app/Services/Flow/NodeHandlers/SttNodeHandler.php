<?php

namespace App\Services\Flow\NodeHandlers;

use App\Services\PythonVoiceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SttNodeHandler implements NodeHandlerInterface
{
    public function __construct(protected PythonVoiceService $voiceService) {}

    public function handle(array $data, array $context): array
    {
        $audioPath = $data['audio_source'] ?? null;

        if (is_string($audioPath) && preg_match('/^https?:\/\//i', $audioPath)) {
            $audioPath = $this->downloadRemoteAudio($audioPath);
        }

        if (empty($audioPath) || !is_string($audioPath) || !file_exists($audioPath)) {
            throw new \RuntimeException('STT düğümü için geçerli bir ses dosyası bulunamadı (audio_source): ' . json_encode($data['audio_source'] ?? null));
        }

        $language = $data['language'] ?? 'tr';
        $modelSize = $data['model_size'] ?? null;

        $result = $this->voiceService->transcribeStt($audioPath, $language, $modelSize);

        return array_merge($result, [
            'text' => $result['text'] ?? '',
            'language' => $result['language'] ?? $language,
        ]);
    }

    /**
     * Download a remote audio URL (e.g. a Telegram/WhatsApp media URL) to a
     * local temp file so it can be fed into the same transcription path.
     */
    protected function downloadRemoteAudio(string $url): string
    {
        $response = Http::timeout(60)->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException("Uzak ses dosyası indirilemedi (HTTP {$response->status()}): {$url}");
        }

        $uploadDir = base_path('data/uploads');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'ogg';
        $path = $uploadDir . DIRECTORY_SEPARATOR . 'flow_remote_' . Str::random(12) . '.' . $extension;
        file_put_contents($path, $response->body());

        return $path;
    }
}
