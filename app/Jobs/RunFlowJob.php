<?php

namespace App\Jobs;

use App\Models\FlowRun;
use App\Services\Flow\FlowExecutorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunFlowJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        public int $flowRunId
    ) {}

    public function handle(FlowExecutorService $executor): void
    {
        $run = FlowRun::with('flow')->find($this->flowRunId);
        if (!$run || !$run->flow) {
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $executor->run($run->flow, $run->trigger_payload ?? [], $run);

            $run->update([
                'status' => 'completed',
                'context' => $result['context'],
                'completed_at' => now(),
            ]);

            Log::info("RunFlowJob #{$run->id} (flow #{$run->flow_id}) completed successfully.");
        } catch (\Throwable $e) {
            Log::error("RunFlowJob #{$run->id} failed: " . $e->getMessage());

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
