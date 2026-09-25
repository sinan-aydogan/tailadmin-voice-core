<?php

namespace App\Http\Controllers;

use App\Services\LlmService;
use App\Services\MusicGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MusicController extends Controller
{
    /**
     * Display Music & BGM Studio page.
     */
    public function index(MusicGenerationService $service, LlmService $llmService): Response
    {
        return Inertia::render('Music/Index', [
            'library' => $service->getLibrary(),
            'presets' => $service->getPresets(),
            'llm_options' => $llmService->getAvailableLlmOptions(),
        ]);
    }

    /**
     * Enhance a music generation prompt using AI LLM.
     */
    public function enhancePrompt(Request $request, LlmService $llmService): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'nullable|string|max:1000',
            'genre' => 'nullable|string|max:50',
            'bpm' => 'nullable|integer',
            'scale' => 'nullable|string',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        try {
            $result = $llmService->enhanceMusicPrompt(
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
     * Generate music track via API.
     */
    public function generate(Request $request, MusicGenerationService $service): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'nullable|string|max:2000',
            'genre' => 'nullable|string|max:50',
            'title' => 'nullable|string|max:100',
            'bpm' => 'nullable|integer|min:40|max:200',
            'scale' => 'nullable|string|max:50',
            'texture' => 'nullable|string|in:acoustic,strings,lofi,synth,orchestral',
            'duration' => 'nullable|numeric|min:5.0|max:120.0',
            'loop' => 'nullable|boolean',
            'engine' => 'nullable|string|in:smart_synth,musicgen,cloud',
        ]);

        try {
            $result = $service->generate($validated);
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Fon müziği başarıyla üretildi.',
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
    public function getLibrary(MusicGenerationService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'library' => $service->getLibrary(),
        ]);
    }

    /**
     * Stream or download music audio file.
     */
    public function streamAudio(string $filename)
    {
        $clean = str_replace(['..', '/', '\\'], '', $filename);
        if (!str_ends_with($clean, '.wav')) {
            $clean .= '.wav';
        }

        $path = base_path("data/bgm/{$clean}");
        if (!file_exists($path)) {
            abort(404, 'Müzik dosyası bulunamadı.');
        }

        return response()->file($path, [
            'Content-Type' => 'audio/wav',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Delete custom music track.
     */
    public function destroy(string $filename, MusicGenerationService $service): JsonResponse
    {
        $ok = $service->delete($filename);
        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Müzik parçası silindi.' : 'Dosya bulunamadı.',
        ]);
    }
}
