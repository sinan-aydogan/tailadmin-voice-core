<?php

namespace App\Http\Controllers;

use App\Models\VoiceTask;
use App\Models\VoiceProfile;
use App\Services\QueueWorkerService;
use App\Services\PythonVoiceService;
use App\Jobs\GenerateTtsJob;
use App\Jobs\TranscribeSttJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    public function __construct(
        protected PythonVoiceService $voiceService
    ) {}

    public function index(): Response
    {
        $tasks = VoiceTask::latest()->limit(50)->get();
        $isWorkerRunning = QueueWorkerService::isRunning();
        $availableModels = $this->voiceService->getAvailableModels();
        $profiles = VoiceProfile::all();

        return Inertia::render('Queue/Index', [
            'tasks' => $tasks,
            'is_worker_running' => $isWorkerRunning,
            'available_models' => $availableModels,
            'profiles' => $profiles,
        ]);
    }

    public function tasksApi(): JsonResponse
    {
        $tasks = VoiceTask::latest()->limit(50)->get();
        $isWorkerRunning = QueueWorkerService::isRunning();
        $availableModels = $this->voiceService->getAvailableModels();
        $profiles = VoiceProfile::all();

        return response()->json([
            'tasks' => $tasks,
            'is_worker_running' => $isWorkerRunning,
            'available_models' => $availableModels,
            'profiles' => $profiles,
        ]);
    }

    public function retry(Request $request, $id)
    {
        $task = VoiceTask::findOrFail($id);

        $validated = $request->validate([
            'engine' => 'nullable|string|max:50',
            'model_size' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'profile_path' => 'nullable|string',
            'text' => 'nullable|string',
        ]);

        $payload = $task->payload ?? [];

        if (!empty($validated['engine'])) {
            $payload['engine'] = $validated['engine'];
        }
        if (!empty($validated['model_size'])) {
            $payload['model_size'] = $validated['model_size'];
        }
        if (!empty($validated['language'])) {
            $payload['language'] = $validated['language'];
        }
        if (isset($validated['profile_path'])) {
            $payload['profile_path'] = $validated['profile_path'];
        }
        if (!empty($validated['text'])) {
            $payload['text'] = $validated['text'];
        }

        // Clean previous output file if exists
        if ($task->output_path && file_exists($task->output_path)) {
            @unlink($task->output_path);
        }

        $task->update([
            'status' => 'pending',
            'progress' => 0,
            'payload' => $payload,
            'result' => null,
            'output_path' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'created_at' => now(),
        ]);

        if ($task->type === 'tts') {
            GenerateTtsJob::dispatch($task->id)->onQueue('default');
        } elseif ($task->type === 'stt') {
            TranscribeSttJob::dispatch($task->id)->onQueue('default');
        }

        QueueWorkerService::ensureRunning();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'İşlem yeniden başlatıldı ve kuyruğa eklendi.',
                'task' => $task->fresh(),
            ]);
        }

        return redirect()->back()->with('success', 'İşlem yeniden başlatıldı ve kuyruğa eklendi.');
    }

    public function repair(Request $request)
    {
        $result = QueueWorkerService::repair();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json($result);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    public function destroy($id)
    {
        $task = VoiceTask::findOrFail($id);
        if ($task->output_path && file_exists($task->output_path)) {
            @unlink($task->output_path);
        }
        if (!empty($task->payload['audio_path']) && file_exists($task->payload['audio_path'])) {
            @unlink($task->payload['audio_path']);
        }
        $task->delete();

        return redirect()->back()->with('success', 'İşlem kuyruktan silindi.');
    }
}
