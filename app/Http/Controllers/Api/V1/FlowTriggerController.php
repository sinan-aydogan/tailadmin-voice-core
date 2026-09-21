<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunFlowJob;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Services\Flow\FlowExecutorService;
use App\Services\QueueWorkerService;
use App\Support\ChannelResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FlowTriggerController extends Controller
{
    use ChannelResponseFormatter;

    /**
     * Trigger a flow by its webhook slug. Runs synchronously by default and
     * returns the flow's `output.response` node value directly in the HTTP
     * response; pass ?async=1 to queue it instead and poll for the result.
     */
    public function run(Request $request, string $slug, FlowExecutorService $executor): Response
    {
        $flow = Flow::where('trigger_slug', $slug)->first();

        if (!$flow) {
            return response()->json(['success' => false, 'message' => 'Akış bulunamadı.'], 404);
        }

        if (!$flow->is_active) {
            return response()->json(['success' => false, 'message' => 'Bu akış şu anda pasif durumda.'], 423);
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
            $filename = 'flow_trigger_' . Str::random(12) . '.' . $request->file('audio')->getClientOriginalExtension();
            $request->file('audio')->move($uploadDir, $filename);
            $triggerContext['audio_path'] = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        }

        $isAsync = filter_var($request->query('async', false), FILTER_VALIDATE_BOOLEAN);

        if ($isAsync) {
            $run = FlowRun::create([
                'flow_id' => $flow->id,
                'status' => 'pending',
                'trigger_payload' => $triggerContext,
            ]);

            RunFlowJob::dispatch($run->id)->onQueue('default');
            QueueWorkerService::ensureRunning();

            return response()->json([
                'success' => true,
                'message' => 'Akış çalıştırma kuyruğa alındı.',
                'run_id' => $run->id,
                'status' => 'pending',
                'poll_url' => url("/api/v1/flows/runs/{$run->id}"),
            ], 202);
        }

        // Synchronous execution — allow long-running LLM/TTS/STT chains.
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

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

            if (($result['final']['mode'] ?? null) === 'twiml') {
                return $this->twimlResponse($result['final'], url("/api/v1/flows/{$slug}/run"));
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
                'message' => 'Akış çalıştırılamadı: ' . $e->getMessage(),
                'run_id' => $run->id,
            ], 500);
        }
    }

    /**
     * Poll the status/result of an asynchronously dispatched flow run.
     */
    public function runStatus($id): JsonResponse
    {
        $run = FlowRun::with('logs')->find($id);

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
            $finalLog = $run->logs->last();
            $response = array_merge($response, $this->formatFinalOutput(
                $finalLog && $finalLog->node_type === 'output.response' ? $finalLog->output : null,
                $run->id
            ));
        }

        return response()->json($response);
    }
}
