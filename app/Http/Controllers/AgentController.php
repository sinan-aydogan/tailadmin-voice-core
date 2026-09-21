<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Flow;
use App\Models\KnowledgeDocument;
use App\Models\VoiceProfile;
use App\Services\Agent\AgentExecutorService;
use App\Services\PythonVoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(): Response
    {
        $agents = Agent::withCount('runs')
            ->latest()
            ->get()
            ->map(function (Agent $agent) {
                $lastRun = $agent->runs()->latest()->first();
                $agent->trigger_url = url("/api/v1/agents/{$agent->trigger_slug}/run");
                $agent->last_run_status = $lastRun?->status;
                $agent->last_run_at = $lastRun?->created_at;
                return $agent;
            });

        return Inertia::render('Agents/Index', [
            'agents' => $agents,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:190',
        ]);

        $agent = Agent::create([
            'name' => $validated['name'] ?? 'Yeni Ajan',
            'is_active' => true,
            'trigger_type' => 'webhook',
            'tools' => [],
        ]);

        return redirect()->route('agents.edit', $agent->id);
    }

    /**
     * Hands-free live voice session: continuous mic capture + client-side
     * VAD (voice activity detection) turns each utterance into a call to
     * testRun's exact endpoint, so it reuses the same executor/response
     * shape without any new backend logic.
     */
    public function voice($id): Response
    {
        $agent = Agent::findOrFail($id);

        return Inertia::render('Agents/Voice', [
            'agent' => $agent->only(['id', 'name', 'response_mode', 'is_active']),
        ]);
    }

    public function edit($id, PythonVoiceService $voiceService): Response
    {
        $agent = Agent::findOrFail($id);
        $agent->trigger_url = url("/api/v1/agents/{$agent->trigger_slug}/run");

        return Inertia::render('Agents/Builder', [
            'agent' => $agent,
            'flows' => Flow::orderBy('name')->get(['id', 'name']),
            'voiceProfiles' => VoiceProfile::orderBy('name')->get(['id', 'name']),
            'voiceModels' => $voiceService->getAvailableModels(),
            'knowledgeDocuments' => KnowledgeDocument::orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $agent = Agent::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'description' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|string',
            'tools' => 'nullable|array',
            'max_tool_iterations' => 'nullable|integer|min:1|max:20',
            'use_knowledge' => 'nullable|boolean',
            'knowledge_document_ids' => 'nullable|array',
            'stt_enabled' => 'nullable|boolean',
            'stt_language' => 'nullable|string',
            'stt_model_size' => 'nullable|string',
            'tts_engine' => 'nullable|string',
            'tts_language' => 'nullable|string',
            'tts_profile_id' => 'nullable|integer',
            'response_mode' => 'nullable|string',
            'twiml_type' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $agent->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'agent' => $agent]);
        }

        return redirect()->back()->with('success', 'Ajan kaydedildi.');
    }

    public function toggle(Request $request, $id)
    {
        $agent = Agent::findOrFail($id);
        $agent->is_active = !$agent->is_active;
        $agent->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'agent' => $agent]);
        }

        return redirect()->back()->with('success', 'Ajan durumu güncellendi.');
    }

    public function destroy($id)
    {
        $agent = Agent::findOrFail($id);
        $agent->delete();

        return redirect()->route('agents.index')->with('success', 'Ajan silindi.');
    }

    public function duplicate($id)
    {
        $agent = Agent::findOrFail($id);
        $clone = $agent->duplicate();

        return redirect()->route('agents.edit', $clone->id)->with('success', 'Ajan başarıyla kopyalandı.');
    }

    public function runs($id)
    {
        $agent = Agent::findOrFail($id);
        $runs = $agent->runs()
            ->with(['steps' => fn($q) => $q->orderBy('step_number', 'asc')])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'runs' => $runs,
        ]);
    }

    public function clearSession(Request $request, $id)
    {
        $agent = Agent::findOrFail($id);
        $conversationId = $request->input('conversation_id') ?? $request->input('session_id');

        if ($conversationId) {
            $session = $agent->sessions()->where('conversation_id', $conversationId)->first();
            $session?->clearMessages();
        } else {
            $agent->sessions()->each(fn($s) => $s->clearMessages());
        }

        return response()->json([
            'success' => true,
            'message' => 'Oturum hafızası temizlendi.',
        ]);
    }

    /**
     * Synchronously run the agent with sample input, returning the full
     * agent_run_steps trace (LLM turns + tool calls) for the builder's
     * "Test Et" panel.
     */
    public function testRun(Request $request, $id, AgentExecutorService $executor)
    {
        $agent = Agent::findOrFail($id);

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
            $filename = 'agent_test_' . Str::random(12) . '.' . $request->file('audio')->getClientOriginalExtension();
            $request->file('audio')->move($uploadDir, $filename);
            $triggerContext['audio_path'] = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        }

        $run = AgentRun::create([
            'agent_id' => $agent->id,
            'status' => 'running',
            'trigger_payload' => $triggerContext,
            'started_at' => now(),
        ]);

        try {
            $result = $executor->run($agent, $triggerContext, $run);

            $run->update([
                'status' => 'completed',
                'final_reply' => $result['reply_text'] ?? null,
                'final_output' => $result['final'] ?? null,
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'run' => $run->fresh('steps'),
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
                'run' => $run->fresh('steps'),
            ], 422);
        }
    }
}
