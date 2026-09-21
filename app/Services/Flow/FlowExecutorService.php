<?php

namespace App\Services\Flow;

use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\FlowRunLog;
use App\Services\Flow\NodeHandlers\ConditionNodeHandler;
use App\Services\Flow\NodeHandlers\HttpNodeHandler;
use App\Services\Flow\NodeHandlers\LlmNodeHandler;
use App\Services\Flow\NodeHandlers\NodeHandlerInterface;
use App\Services\Flow\NodeHandlers\ResponseNodeHandler;
use App\Services\Flow\NodeHandlers\SttNodeHandler;
use App\Services\Flow\NodeHandlers\TtsNodeHandler;

class FlowExecutorService
{
    protected const MAX_STEPS = 200;

    protected array $handlerMap = [
        'action.stt' => SttNodeHandler::class,
        'action.llm' => LlmNodeHandler::class,
        'action.tts' => TtsNodeHandler::class,
        'action.http' => HttpNodeHandler::class,
        'logic.condition' => ConditionNodeHandler::class,
        'output.response' => ResponseNodeHandler::class,
    ];

    public function __construct(protected VariableResolver $resolver) {}

    /**
     * Walk the flow's node graph from its trigger node, executing every
     * node along the single active path (condition nodes pick one of two
     * outgoing edges) until an `output.response` node or a dead end.
     *
     * @param array $triggerContext Available at {{trigger....}} in node configs.
     */
    public function run(Flow $flow, array $triggerContext, ?FlowRun $run = null): array
    {
        $context = ['trigger' => $triggerContext, '_flow' => ['id' => $flow->id]];

        $triggerNode = $flow->findTriggerNode();
        if (!$triggerNode) {
            throw new \RuntimeException('Akışta tetikleyici düğüm bulunamadı.');
        }

        $finalOutput = null;
        $steps = 0;
        $edges = $flow->edgesFrom($triggerNode['id']);

        while (!empty($edges)) {
            if (++$steps > self::MAX_STEPS) {
                throw new \RuntimeException('Akış çok fazla adım içeriyor, olası bir döngü tespit edildi.');
            }

            // Single active path model: only the first outgoing edge is followed.
            $edge = $edges[0];
            $node = $flow->findNode($edge['target']);
            if (!$node) {
                break;
            }

            $nodeId = $node['id'];
            $type = $node['type'];
            $resolvedData = $this->resolver->resolveConfig($node['data'] ?? [], $context);

            $startedAt = microtime(true);
            $output = [];
            $status = 'completed';
            $errorMessage = null;

            try {
                $output = $this->executeNode($type, $resolvedData, $context);
            } catch (\Throwable $e) {
                $status = 'failed';
                $errorMessage = $e->getMessage();
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($run) {
                FlowRunLog::create([
                    'flow_run_id' => $run->id,
                    'node_id' => $nodeId,
                    'node_type' => $type,
                    'status' => $status,
                    'input' => $resolvedData,
                    'output' => $status === 'completed' ? $output : null,
                    'error_message' => $errorMessage,
                    'duration_ms' => $durationMs,
                ]);
            }

            if ($status === 'failed') {
                throw new \RuntimeException("Düğüm '{$nodeId}' ({$type}) hata verdi: {$errorMessage}");
            }

            $context[$nodeId] = $output;

            if ($type === 'output.response') {
                $finalOutput = $output;
                break;
            }

            $handle = $type === 'logic.condition' ? ($output['matched_handle'] ?? 'false') : null;
            $edges = $flow->edgesFrom($nodeId, $handle);
        }

        return [
            'final' => $finalOutput,
            'context' => $context,
        ];
    }

    protected function executeNode(string $type, array $data, array $context): array
    {
        if (!isset($this->handlerMap[$type])) {
            throw new \RuntimeException("Bilinmeyen düğüm tipi: {$type}");
        }

        /** @var NodeHandlerInterface $handler */
        $handler = app($this->handlerMap[$type]);

        return $handler->handle($data, $context);
    }
}
