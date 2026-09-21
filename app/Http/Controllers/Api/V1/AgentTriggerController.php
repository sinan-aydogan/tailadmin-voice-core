<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\AgentRunJob;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Services\Agent\AgentExecutorService;
use App\Services\QueueWorkerService;
use App\Support\ChannelResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AgentTriggerController extends Controller
{
    use ChannelResponseFormatter;

    /**
     * Trigger an agent by its webhook slug. Runs synchronously by default and
     * returns the agent's final reply directly in the HTTP response; pass
     * ?async=1 to queue it instead and poll for the result.
     */
    public function run(Request $request, string $slug, AgentExecutorService $executor): Response
    {
        $agent = Agent::where('trigger_slug', $slug)->first();

        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Ajan bulunamadı.'], 404);
        }

        if (!$agent->is_active) {
            return response()->json(['success' => false, 'message' => 'Bu ajan şu anda pasif durumda.'], 423);
        }

        $triggerContext = [
            'body' => $request->except(['audio']),
            'query' => $request->query(),
        ];

        if ($request->hasFile('audio')) {
            $uploadDir = base_path('data/uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = 'agent_trigger_' . Str::random(12) . '.' . $request->file('audio')->getClientOriginalExtension();
            $request->file('audio')->move($uploadDir, $filename);
            $triggerContext['audio_path'] = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        }

        $isAsync = filter_var($request->query('async', false), FILTER_VALIDATE_BOOLEAN);

        if ($isAsync) {
            $run = AgentRun::create([
                'agent_id' => $agent->id,
                'status' => 'pending',
                'trigger_payload' => $triggerContext,
            ]);

            AgentRunJob::dispatch($run->id)->onQueue('default');
            QueueWorkerService::ensureRunning();

            return response()->json([
                'success' => true,
                'message' => 'Ajan çalıştırma kuyruğa alındı.',
                'run_id' => $run->id,
                'status' => 'pending',
                'poll_url' => url("/api/v1/agents/runs/{$run->id}"),
            ], 202);
        }

        // Synchronous execution — allow long-running LLM/tool/TTS/STT chains.
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

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
                'completed_at' => now(),
            ]);

            if (($result['final']['mode'] ?? null) === 'twiml') {
                return $this->twimlResponse($result['final'], url("/api/v1/agents/{$slug}/run"));
            }

            return response()->json($this->formatFinalOutput($result['final'], $run->id));
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ajan çalıştırılamadı: ' . $e->getMessage(),
                'run_id' => $run->id,
            ], 500);
        }
    }

    /**
     * Poll the status/result of an asynchronously dispatched agent run.
     */
    public function runStatus($id): JsonResponse
    {
        $run = AgentRun::find($id);

        if (!$run) {
            return response()->json(['success' => false, 'message' => 'Çalışma kaydı bulunamadı.'], 404);
        }

        $response = [
            'success' => true,
            'run_id' => $run->id,
            'status' => $run->status,
            'error_message' => $run->error_message,
        ];

        if ($run->status === 'completed') {
            $response = array_merge($response, $this->formatFinalOutput(
                $run->final_reply !== null ? ['mode' => 'text', 'value' => $run->final_reply] : null,
                $run->id
            ));
        }

        return response()->json($response);
    }
}
