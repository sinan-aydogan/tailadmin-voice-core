<?php

namespace App\Http\Controllers;

use App\Services\LlmService;
use App\Services\SfxGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SfxController extends Controller
{
    /**
     * Detect which SFX engines are actually available on this system.
     * - smart_synth: always available (pure Python DSP, no model files)
     * - audiogen: available if AudioGen/AudioCraft model weights exist on disk
     * - cloud_*: available if the provider's API key is configured in settings
     */
    protected function detectSfxEngines(): array
    {
        $engines = [
            [
                'value'     => 'smart_synth',
                'label'     => '⚡ Hızlı Akıllı DSP Sentez',
                'available' => true,
                'badge'     => null,
            ],
        ];

        // AudioGen: check for model weight directories
        $audiogenPaths = [
            base_path('data/models/audiogen-medium'),
            base_path('data/models/audiogen-base'),
            base_path('data/models/audiocraft'),
            base_path('engine/models/audiogen'),
        ];
        $audiogenInstalled = collect($audiogenPaths)->contains(fn($p) => is_dir($p));

        $engines[] = [
            'value'     => 'audiogen',
            'label'     => '🤖 Yerel AudioGen' . ($audiogenInstalled ? '' : ' (Kurulu Değil)'),
            'available' => $audiogenInstalled,
            'badge'     => $audiogenInstalled ? 'installed' : 'not_installed',
            'install_hint' => $audiogenInstalled ? null : 'Model Yöneticisi\'nden AudioGen modelini indirin.',
        ];

        // Cloud engines — check settings.json for API keys
        $settingsFile = base_path('data/settings.json');
        $settings = file_exists($settingsFile)
            ? (json_decode(file_get_contents($settingsFile), true) ?: [])
            : [];

        $cloudProviders = [
            'elevenlabs' => [
                'key_field' => 'elevenlabs_api_key',
                'env'       => 'ELEVENLABS_API_KEY',
                'label'     => '☁️ ElevenLabs Sound Effects',
                'value'     => 'cloud_elevenlabs',
            ],
            'openai' => [
                'key_field' => 'openai_api_key',
                'env'       => 'OPENAI_API_KEY',
                'label'     => '☁️ OpenAI Sound Generation',
                'value'     => 'cloud_openai',
            ],
        ];

        foreach ($cloudProviders as $provider => $def) {
            $key = trim($settings[$def['key_field']] ?? env($def['env'], ''));
            $configured = !empty($key);

            $engines[] = [
                'value'        => $def['value'],
                'label'        => $def['label'] . ($configured ? '' : ' (API Anahtarı Yok)'),
                'available'    => $configured,
                'badge'        => $configured ? 'cloud' : 'no_key',
                'install_hint' => $configured ? null : "Modeller sayfasından {$provider} API anahtarını ekleyin.",
            ];
        }

        return $engines;
    }

    /**
     * Display SFX Studio page.
     */
    public function index(SfxGenerationService $service, LlmService $llmService): Response
    {
        return Inertia::render('Sfx/Index', [
            'library'     => $service->getLibrary(),
            'categories'  => $service->getCategories(),
            'llm_options' => $llmService->getAvailableLlmOptions(),
            'sfx_engines' => $this->detectSfxEngines(),
        ]);
    }

    /**
     * Enhance an SFX generation prompt using AI LLM.
     */
    public function enhancePrompt(Request $request, LlmService $llmService): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'nullable|string|max:1000',
            'preset' => 'nullable|string|max:50',
            'duration' => 'nullable|numeric',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        try {
            $result = $llmService->enhanceSfxPrompt(
                prompt: $validated['prompt'] ?? '',
                options: $validated
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Prompt geliştirme başarısız: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate an SFX via API.
     */
    public function generate(Request $request, SfxGenerationService $service): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'nullable|string|max:2000',
            'preset' => 'nullable|string|max:50',
            'title' => 'nullable|string|max:100',
            'duration' => 'nullable|numeric|min:0.4|max:10.0',
            'reverb' => 'nullable|string|in:dry,room,cave,hall',
            'tone' => 'nullable|string|in:balanced,bass,bright',
            'engine' => 'nullable|string|in:smart_synth,audiogen,cloud,cloud_elevenlabs,cloud_openai',
        ]);

        try {
            $result = $service->generate($validated);
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Ses efekti başarıyla üretildi.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get library list.
     */
    public function getLibrary(SfxGenerationService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'library' => $service->getLibrary(),
        ]);
    }

    /**
     * Stream or download SFX audio file.
     */
    public function streamAudio(string $filename)
    {
        $clean = str_replace(['..', '/', '\\'], '', $filename);
        if (!str_ends_with($clean, '.wav')) {
            $clean .= '.wav';
        }

        $path = base_path("data/sfx/{$clean}");
        if (!file_exists($path)) {
            abort(404, 'Ses efekti dosyası bulunamadı.');
        }

        return response()->file($path, [
            'Content-Type' => 'audio/wav',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Delete custom SFX.
     */
    public function destroy(string $filename, SfxGenerationService $service): JsonResponse
    {
        $ok = $service->delete($filename);
        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Ses efekti silindi.' : 'Dosya bulunamadı.',
        ]);
    }
}
