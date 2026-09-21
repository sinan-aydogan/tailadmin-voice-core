<?php

namespace App\Services\Flow\NodeHandlers;

use App\Models\FlowSession;
use App\Models\PromptTemplate;
use App\Services\Knowledge\KnowledgeService;
use App\Services\LlmService;

class LlmNodeHandler implements NodeHandlerInterface
{
    public function __construct(
        protected LlmService $llmService,
        protected KnowledgeService $knowledgeService,
    ) {}

    public function handle(array $data, array $context): array
    {
        $prompt = $data['prompt'] ?? '';
        $systemPrompt = $data['system_prompt'] ?? null;

        if (empty(trim((string) $prompt)) && !empty($data['prompt_template_id'])) {
            $template = PromptTemplate::find($data['prompt_template_id']);
            if ($template) {
                $prompt = $template->renderPrompt($data['template_values'] ?? []);
                $systemPrompt = $systemPrompt ?: $template->system_prompt;
            }
        }

        if (empty(trim((string) $prompt))) {
            throw new \RuntimeException('LLM düğümü için boş prompt gönderildi.');
        }

        $flowId = $context['_flow']['id'] ?? null;
        $userTurn = $prompt;

        $augmentedPrompt = $prompt;

        // RAG: prepend relevant chunks from this node's own selected documents
        // — each LLM node in a flow picks its own set, independently of others.
        if (!empty($data['use_knowledge']) && !empty($data['knowledge_document_ids'])) {
            $knowledgeBlock = $this->buildKnowledgeBlock($data['knowledge_document_ids'], $prompt);
            if ($knowledgeBlock !== '') {
                $augmentedPrompt = $knowledgeBlock . "\n\n" . $augmentedPrompt;
            }
        }

        // Multi-turn memory: prepend prior turns for this conversation_id.
        $session = null;
        $memoryTurns = (int) ($data['memory_turns'] ?? 6);
        // Already resolved by VariableResolver — lets each channel template map
        // its own field (Telegram chat.id, Twilio CallSid, ...) to memory.
        $conversationId = $data['conversation_id'] ?? null;

        if (!empty($data['use_memory']) && $flowId && !empty($conversationId)) {
            $session = FlowSession::firstOrCreate(
                ['flow_id' => $flowId, 'conversation_id' => (string) $conversationId],
                ['messages' => []]
            );

            $history = $session->renderHistory($memoryTurns);
            if ($history !== '') {
                $augmentedPrompt = "Önceki Konuşma:\n{$history}\n\nKullanıcı: {$augmentedPrompt}";
            }
        }

        $result = $this->llmService->generate(
            prompt: $augmentedPrompt,
            systemPrompt: $systemPrompt,
            model: $data['model'] ?? null,
            provider: $data['provider'] ?? null,
            apiKey: $data['api_key'] ?? null,
            baseUrl: $data['base_url'] ?? null,
        );

        if (empty($result['success'])) {
            throw new \RuntimeException('LLM üretimi başarısız: ' . ($result['warning'] ?? 'Bilinmeyen hata'));
        }

        if ($session) {
            $session->appendTurn($userTurn, $result['text'] ?? '', $memoryTurns);
            $result['conversation_id'] = $conversationId;
        }

        return $result;
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
}
