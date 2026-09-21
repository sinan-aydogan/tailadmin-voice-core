<?php

namespace App\Http\Controllers;

use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\KnowledgeDocument;
use App\Models\PromptTemplate;
use App\Models\VoiceProfile;
use App\Services\Flow\FlowExecutorService;
use App\Services\PythonVoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FlowController extends Controller
{
    public function index(): Response
    {
        $flows = Flow::withCount('runs')
            ->latest()
            ->get()
            ->map(function (Flow $flow) {
                $lastRun = $flow->runs()->latest()->first();
                $flow->trigger_url = url("/api/v1/flows/{$flow->trigger_slug}/run");
                $flow->last_run_status = $lastRun?->status;
                $flow->last_run_at = $lastRun?->created_at;
                return $flow;
            });

        return Inertia::render('Flows/Index', [
            'flows' => $flows,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:190',
            'definition' => 'nullable|array',
        ]);

        $flow = Flow::create([
            'name' => $validated['name'] ?? 'Yeni Akış',
            'is_active' => true,
            'trigger_type' => 'webhook',
            'definition' => $validated['definition'] ?? ['nodes' => [], 'edges' => []],
        ]);

        return redirect()->route('flows.edit', $flow->id);
    }

    public function edit($id, PythonVoiceService $voiceService): Response
    {
        $flow = Flow::findOrFail($id);
        $flow->trigger_url = url("/api/v1/flows/{$flow->trigger_slug}/run");

        return Inertia::render('Flows/Builder', [
            'flow' => $flow,
            'promptTemplates' => PromptTemplate::orderBy('title')->get(['id', 'title', 'category', 'content', 'system_prompt']),
            'voiceProfiles' => VoiceProfile::orderBy('name')->get(['id', 'name']),
            'voiceModels' => $voiceService->getAvailableModels(),
            'knowledgeDocuments' => KnowledgeDocument::orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $flow = Flow::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'definition' => 'required|array',
            'definition.nodes' => 'array',
            'definition.edges' => 'array',
        ]);

        $flow->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? $flow->is_active),
            'definition' => $validated['definition'],
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'flow' => $flow]);
        }

        return redirect()->back()->with('success', 'Akış kaydedildi.');
    }

    public function toggle(Request $request, $id)
    {
        $flow = Flow::findOrFail($id);
        $flow->is_active = !$flow->is_active;
        $flow->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'flow' => $flow]);
        }

        return redirect()->back()->with('success', 'Akış durumu güncellendi.');
    }

    public function destroy($id)
    {
        $flow = Flow::findOrFail($id);
        $flow->delete();

        return redirect()->route('flows.index')->with('success', 'Akış silindi.');
    }

    /**
     * Synchronously run the flow with sample input, returning the full
     * per-node execution trace for the builder's canvas visualization.
     */
    public function testRun(Request $request, $id, FlowExecutorService $executor)
    {
        $flow = Flow::findOrFail($id);

        $validated = $request->validate([
            'body' => 'nullable|array',
            'audio' => 'nullable|file|max:102400',
        ]);

        $triggerContext = [
            'body' => $validated['body'] ?? [],
        ];

        if ($request->hasFile('audio')) {
            $uploadDir = base_path('data/uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = 'flow_test_' . Str::random(12) . '.' . $request->file('audio')->getClientOriginalExtension();
            $request->file('audio')->move($uploadDir, $filename);
            $triggerContext['audio_path'] = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        }

        $run = FlowRun::create([
            'flow_id' => $flow->id,
            'status' => 'running',
            'trigger_payload' => $triggerContext,
            'started_at' => now(),
        ]);

        try {
            $result = $executor->run($flow, $triggerContext, $run);

            $run->update([
                'status' => 'completed',
                'context' => $result['context'],
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'run' => $run->fresh('logs'),
                'final' => $result['final'],
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'run' => $run->fresh('logs'),
            ], 422);
        }
    }
}
