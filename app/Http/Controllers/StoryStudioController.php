<?php

namespace App\Http\Controllers;

use App\Models\StoryProject;
use App\Services\StoryDirectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoryStudioController extends Controller
{
    /**
     * Generate or refine structured Director Script via LLM.
     */
    public function generateScript(Request $request, StoryDirectorService $director): JsonResponse
    {
        $validated = $request->validate([
            'theme' => 'required|string|max:1000',
            'options' => 'nullable|array',
            'project_id' => 'nullable|integer',
        ]);

        $theme = $validated['theme'];
        $options = array_merge([
            'has_voice' => true,
            'has_bgm' => true,
            'has_sfx' => true,
            'has_visuals' => true,
            'has_prosody' => true,
            'audience' => 'children_5_10',
            'language' => 'tr',
            'tts_engine' => 'freya-adam',
            'ducking' => true,
        ], $validated['options'] ?? []);

        try {
            $script = $director->generateScript($theme, $options);

            if (!empty($validated['project_id'])) {
                $project = StoryProject::find($validated['project_id']);
                if ($project) {
                    $project->update([
                        'title' => $script['title'] ?? $project->title,
                        'theme' => $theme,
                        'target_audience' => $options['audience'] ?? 'children_5_10',
                        'options' => $options,
                        'script_data' => $script,
                        'status' => 'draft',
                    ]);
                }
            } else {
                $project = StoryProject::create([
                    'title' => $script['title'] ?? 'Yeni Sesli Hikaye',
                    'theme' => $theme,
                    'target_audience' => $options['audience'] ?? 'children_5_10',
                    'options' => $options,
                    'script_data' => $script,
                    'status' => 'draft',
                ]);
            }

            return response()->json([
                'success' => true,
                'project' => $project,
                'script' => $script,
                'is_simulation' => !empty($script['is_simulation']),
                'simulation_notice' => $script['simulation_notice'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Story Script generation failed: " . $e->getMessage());

            $llmSettings = (new \App\Services\LlmService())->getSettings();
            $provider = $options['llm_provider'] ?? $llmSettings['llm_provider'];
            $model = $options['llm_model'] ?? $llmSettings['llm_model'];

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider,
                'model' => $model,
            ], 422);
        }
    }

    /**
     * Start audio production asynchronously via queue job (TTS + Whisper Alignment + MusicGen BGM + SFX + Ducking mix).
     */
    public function produce(Request $request, $id): JsonResponse
    {
        $project = StoryProject::findOrFail($id);

        if ($request->has('script_data')) {
            $project->script_data = $request->input('script_data');
        }
        if ($request->has('options')) {
            $project->options = array_merge($project->options ?? [], $request->input('options'));
        }

        $project->status = 'processing';
        $project->progress_step = 'Prodüksiyon başlatılıyor...';
        $project->error_message = null;
        $project->save();

        try {
            // Ensure background queue worker is active
            \App\Services\QueueWorkerService::ensureRunning();

            // Dispatch asynchronous job so the browser request never times out
            \App\Jobs\ProduceStoryJob::dispatch($project->id)->onQueue('default');

            return response()->json([
                'success' => true,
                'status' => 'processing',
                'project' => $project->fresh(),
                'message' => 'Prodüksiyon arka planda başlatıldı.',
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch ProduceStoryJob: " . $e->getMessage());
            $project->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'progress_step' => 'Kuyruk hatası: ' . $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'project' => $project->fresh(),
            ], 500);
        }
    }

    /**
     * Stream or download story audio files directly with Accept-Ranges support.
     */
    public function getAudio($id, $type = 'master')
    {
        $project = StoryProject::findOrFail($id);
        $projectDir = storage_path("app/public/stories/{$id}");

        $cleanType = str_replace(['..', '/', '\\'], '', $type);
        if (!str_ends_with($cleanType, '.wav')) {
            $cleanType .= '.wav';
        }

        $possibleFiles = [
            "{$projectDir}/{$cleanType}",
            "{$projectDir}/master.wav",
            "{$projectDir}/scene_1_stems/{$cleanType}",
            "{$projectDir}/scene_2_stems/{$cleanType}",
            "{$projectDir}/scene_3_stems/{$cleanType}",
        ];

        $path = null;
        foreach ($possibleFiles as $candidate) {
            if (file_exists($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if (!$path || !file_exists($path)) {
            abort(404, "Story audio file not found: {$type}");
        }

        return response()->file($path, [
            'Content-Type' => 'audio/wav',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Get a story project status and data.
     */
    public function getProject($id): JsonResponse
    {
        $project = StoryProject::findOrFail($id);
        return response()->json([
            'success' => true,
            'project' => $project,
            'master_url' => $project->master_audio_url,
            'status' => $project->status,
            'progress_step' => $project->progress_step,
        ]);
    }

    /**
     * Get recent story projects.
     */
    public function getProjects(): JsonResponse
    {
        $projects = StoryProject::latest()->take(15)->get();
        return response()->json([
            'success' => true,
            'projects' => $projects,
        ]);
    }

    /**
     * Get list of available pre-rendered SFX.
     */
    public function getSfxCatalog(StoryDirectorService $director): JsonResponse
    {
        return response()->json([
            'success' => true,
            'sfx' => $director->getAvailableSfx(),
        ]);
    }

    /**
     * Get list of available BGM tracks.
     */
    public function getBgmCatalog(StoryDirectorService $director): JsonResponse
    {
        return response()->json([
            'success' => true,
            'bgm' => $director->getAvailableBgm(),
        ]);
    }

    /**
     * Delete a project.
     */
    public function destroy($id): JsonResponse
    {
        $project = StoryProject::findOrFail($id);
        $project->delete();
        return response()->json(['success' => true]);
    }
}
