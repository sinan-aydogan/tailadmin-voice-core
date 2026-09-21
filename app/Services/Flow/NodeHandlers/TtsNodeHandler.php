<?php

namespace App\Services\Flow\NodeHandlers;

use App\Models\VoiceProfile;
use App\Services\PythonVoiceService;
use Illuminate\Support\Str;

class TtsNodeHandler implements NodeHandlerInterface
{
    public function __construct(protected PythonVoiceService $voiceService) {}

    public function handle(array $data, array $context): array
    {
        $text = $data['text'] ?? '';
        if (empty(trim((string) $text))) {
            throw new \RuntimeException('TTS düğümü için boş metin gönderildi.');
        }

        $engine = $data['engine'] ?? 'piper-tr';
        $language = $data['language'] ?? 'tr';

        $profilePath = null;
        if (!empty($data['profile_id'])) {
            $profile = VoiceProfile::find($data['profile_id']);
            $profilePath = $profile?->sample_path;
        }

        $outputDir = base_path('data/outputs');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $filename = 'flow_tts_' . Str::random(12) . '.wav';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

        $result = $this->voiceService->generateTts($text, $engine, $language, $profilePath, $outputPath);

        return array_merge($result, [
            'filename' => $filename,
            'output_path' => $result['output_path'] ?? $outputPath,
            'audio_url' => url("/api/v1/audio/{$filename}"),
        ]);
    }
}
