<?php

namespace App\Http\Controllers;

use App\Models\VoiceTask;
use App\Models\VoiceProfile;
use App\Jobs\GenerateTtsJob;
use App\Services\PythonVoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class TtsController extends Controller
{
    public function index(PythonVoiceService $service): Response
    {
        $profiles = VoiceProfile::all();
        $tasks = VoiceTask::where('type', 'tts')->latest()->limit(15)->get();
        $models = $service->getAvailableModels();

        return Inertia::render('Tts/Index', [
            'profiles' => $profiles,
            'tasks' => $tasks,
            'models' => $models,
            'default_engine' => $service->getDefaultTtsEngine(),
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|min:1|max:5000',
            'engine' => 'required|string',
            'language' => 'required|string|max:10',
            'profile_id' => 'nullable|integer',
            'stability' => 'nullable|numeric|min:0|max:1',
            'speed' => 'nullable|numeric|min:0.25|max:3.0',
            'pitch' => 'nullable|numeric|min:-12|max:12',
            'similarity_boost' => 'nullable|numeric|min:0|max:1',
            'style' => 'nullable|numeric|min:0|max:1',
            'default_pause_sec' => 'nullable|numeric|min:0.1|max:5.0',
        ]);

        $profilePath = null;
        if (!empty($validated['profile_id'])) {
            $profile = VoiceProfile::find($validated['profile_id']);
            $profilePath = $profile?->sample_path;
        }

        $outputDir = base_path('data/outputs');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $filename = 'tts_' . Str::random(12) . '.wav';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

        $task = VoiceTask::create([
            'type' => 'tts',
            'status' => 'pending',
            'payload' => [
                'text' => $validated['text'],
                'engine' => $validated['engine'],
                'language' => $validated['language'],
                'profile_id' => $validated['profile_id'] ?? null,
                'profile_path' => $profilePath,
                'stability' => isset($validated['stability']) ? (float) $validated['stability'] : null,
                'speed' => isset($validated['speed']) ? (float) $validated['speed'] : null,
                'pitch' => isset($validated['pitch']) ? (float) $validated['pitch'] : null,
                'similarity_boost' => isset($validated['similarity_boost']) ? (float) $validated['similarity_boost'] : null,
                'style' => isset($validated['style']) ? (float) $validated['style'] : null,
                'default_pause_sec' => isset($validated['default_pause_sec']) ? (float) $validated['default_pause_sec'] : null,
                'output_path' => $outputPath,
                'filename' => $filename,
            ],
            'output_path' => $outputPath,
        ]);

        GenerateTtsJob::dispatch($task->id)->onQueue('default');
        \App\Services\QueueWorkerService::ensureRunning();

        return redirect()->back()->with('success', 'Ses üretimi kuyruğa eklendi.');
    }
}
