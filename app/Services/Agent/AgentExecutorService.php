<?php

namespace App\Services\Agent;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\AgentSession;
use App\Models\Flow;
use App\Models\VoiceProfile;
use App\Services\Flow\FlowExecutorService;
use App\Services\Knowledge\KnowledgeService;
use App\Services\LlmService;
use App\Services\PythonVoiceService;
use Illuminate\Support\Str;

/**
 * The agent loop: unlike a Flow (a fixed node graph run once per request),
 * an Agent lets the LLM itself decide, turn by turn via tool-calling, which
 * Flow(s) to invoke before producing a final reply. Each Flow is exposed to
 * the LLM unmodified as a callable tool — a tool call just becomes
 * FlowExecutorService::run($flow, ['body' => $arguments]), since Flows
 * already read {{trigger.body.x}}.
 */
class AgentExecutorService
{
    public function __construct(
        protected LlmService $llmService,
        protected FlowExecutorService $flowExecutor,
        protected KnowledgeService $knowledgeService,
        protected PythonVoiceService $voiceService,
    ) {}

    public function run(Agent $agent, array $triggerContext, AgentRun $run): array
    {
        $userText = (string) (
            data_get($triggerContext, 'body.text')
            ?? data_get($triggerContext, 'body.message')
            ?? ''
        );

        if (!empty($triggerContext['audio_path']) && $agent->stt_enabled) {
            $sttStart = microtime(true);
            $sttResult = $this->voiceService->transcribeStt(
                $triggerContext['audio_path'],
                $agent->stt_language ?: 'tr',
                $agent->stt_model_size ?: null
            );
            $this->logStep($run, 'stt', null, null, ['audio_path' => $triggerContext['audio_path']], $sttResult, null, $sttStart);
            $userText = trim((string) ($sttResult['text'] ?? ''));
        }

        if ($userText === '') {
            throw new \RuntimeException('Ajan için boş bir mesaj/ses gönderildi.');
        }

        $conversationId = (string) (data_get($triggerContext, 'body.conversation_id') ?: Str::random(16));

        $session = AgentSession::firstOrCreate(
            ['agent_id' => $agent->id, 'conversation_id' => $conversationId],
            ['messages' => []]
        );

        $run->conversation_id = $conversationId;
        $run->save();

        $systemPrompt = (string) ($agent->system_prompt ?? '');
        if ($agent->use_knowledge && !empty($agent->knowledge_document_ids)) {
            $knowledgeBlock = $this->buildKnowledgeBlock($agent->knowledge_document_ids, $userText);
            if ($knowledgeBlock !== '') {
                $systemPrompt = trim($systemPrompt . "\n\n" . $knowledgeBlock);
            }
        }

        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($session->messages ?? [] as $m) {
            $messages[] = $m;
        }
        $messages[] = ['role' => 'user', 'content' => $userText];

        $newTurnMessages = [['role' => 'user', 'content' => $userText]];

        $tools = array_map(fn ($t) => [
            'name' => $t['name'],
            'description' => $t['description'] ?? '',
            'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
        ], $agent->tools ?? []);

        $finalText = '';
        $iterations = 0;
        $maxIterations = max(1, (int) ($agent->max_tool_iterations ?: 6));

        while (true) {
            $iterations++;

            $llmStart = microtime(true);
            $reply = $this->llmService->chat(
                messages: $messages,
                tools: $tools,
                model: $agent->model,
                provider: $agent->provider,
                apiKey: $agent->api_key,
                baseUrl: $agent->base_url,
            );
            $this->logStep($run, 'llm_call', null, null, ['messages_count' => count($messages)], $reply, null, $llmStart);

            $messages[] = $reply;
            $newTurnMessages[] = $reply;

            if (empty($reply['tool_calls'])) {
                $finalText = trim((string) ($reply['content'] ?? ''));
                break;
            }

            if ($iterations >= $maxIterations) {
                $finalText = trim((string) ($reply['content'] ?? ''))
                    ?: 'Araç çağrısı döngüsü sınırına ulaşıldı, cevap tamamlanamadı.';
                break;
            }

            foreach ($reply['tool_calls'] as $toolCall) {
                $toolStart = microtime(true);
                $toolDef = $agent->findTool($toolCall['name']);
                $flow = $toolDef ? Flow::find($toolDef['flow_id'] ?? null) : null;
                $errorMessage = null;
                $toolContent = '';

                try {
                    if (!$flow) {
                        throw new \RuntimeException("Tool '{$toolCall['name']}' için tanımlı bir akış bulunamadı.");
                    }

                    $toolResult = $this->flowExecutor->run($flow, ['body' => $toolCall['arguments'] ?? []]);
                    $value = $toolResult['final']['value'] ?? null;
                    $toolContent = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                } catch (\Throwable $e) {
                    $errorMessage = $e->getMessage();
                    $toolContent = 'Hata: ' . $errorMessage;
                }

                $this->logStep(
                    $run, 'tool_call', $toolCall['name'], $flow?->id,
                    $toolCall['arguments'] ?? [], ['content' => $toolContent], $errorMessage, $toolStart
                );

                $toolMessage = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'name' => $toolCall['name'],
                    'content' => $toolContent,
                ];
                $messages[] = $toolMessage;
                $newTurnMessages[] = $toolMessage;
            }
        }

        $session->appendMessages($newTurnMessages);

        return [
            'final' => $this->packageResponse($agent, $finalText),
            'reply_text' => $finalText,
            'conversation_id' => $conversationId,
        ];
    }

    protected function buildKnowledgeBlock(array $documentIds, string $query): string
    {
        $chunks = $this->knowledgeService->search($documentIds, $query, 4);
        if (empty($chunks)) {
            return '';
        }

        $lines = ['Kaynak Bilgi:'];
        foreach ($chunks as $chunk) {
            $lines[] = "- ({$chunk['document_title']}) {$chunk['content']}";
        }

        return implode("\n", $lines);
    }

    protected function packageResponse(Agent $agent, string $text): array
    {
        $mode = $agent->response_mode ?: 'json';

        if ($mode === 'text' || $mode === 'json') {
            return ['mode' => $mode, 'value' => $text];
        }

        // audio or twiml: synthesize speech for the final reply text.
        $outputDir = base_path('data/outputs');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $filename = 'agent_tts_' . Str::random(12) . '.wav';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

        $profilePath = null;
        if (!empty($agent->tts_profile_id)) {
            $profile = VoiceProfile::find($agent->tts_profile_id);
            $profilePath = $profile?->sample_path;
        }

        $this->voiceService->generateTts(
            $text,
            $agent->tts_engine ?: 'piper-tr',
            $agent->tts_language ?: 'tr',
            $profilePath,
            $outputPath
        );

        return [
            'mode' => $mode,
            'value' => url("/api/v1/audio/{$filename}"),
            'twiml_type' => $agent->twiml_type ?: 'play',
        ];
    }

    protected function logStep(
        AgentRun $run,
        string $stepType,
        ?string $toolName,
        ?int $flowId,
        array $input,
        ?array $output,
        ?string $errorMessage,
        float $startedAt
    ): void {
        AgentRunStep::create([
            'agent_run_id' => $run->id,
            'step_type' => $stepType,
            'tool_name' => $toolName,
            'flow_id' => $flowId,
            'input' => $input,
            'output' => $output,
            'error_message' => $errorMessage,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
