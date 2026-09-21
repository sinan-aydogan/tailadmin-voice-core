<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Services\Agent\AgentExecutorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AgentRunJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        public int $agentRunId
    ) {}

    public function handle(AgentExecutorService $executor): void
    {
        $run = AgentRun::with('agent')->find($this->agentRunId);
        if (!$run || !$run->agent) {
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $executor->run($run->agent, $run->trigger_payload ?? [], $run);

            $run->update([
                'status' => 'completed',
                'final_reply' => $result['reply_text'] ?? null,
                'completed_at' => now(),
            ]);

            Log::info("AgentRunJob #{$run->id} (agent #{$run->agent_id}) completed successfully.");
        } catch (\Throwable $e) {
            Log::error("AgentRunJob #{$run->id} failed: " . $e->getMessage());

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
