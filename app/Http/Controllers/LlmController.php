<?php

namespace App\Http\Controllers;

use App\Models\PromptTemplate;
use App\Services\LlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmController extends Controller
{
    public function __construct(
        protected LlmService $llmService
    ) {}

    /**
     * Generate text using the LLM service.
     */
    public function generate(Request $request): JsonResponse
    {
        // Allow unlimited execution time for local models (Ollama, LM Studio, CPU inference, etc.)
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $validated = $request->validate([
            'prompt' => 'nullable|string',
            'template_id' => 'nullable|integer',
            'variables' => 'nullable|array',
            'system_prompt' => 'nullable|string',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|string',
        ]);

        $promptText = trim($validated['prompt'] ?? '');
        $systemPrompt = $validated['system_prompt'] ?? null;
        $variables = $validated['variables'] ?? [];

        // If a template was selected, render it with supplied variables
        if (!empty($validated['template_id'])) {
            $template = PromptTemplate::find($validated['template_id']);
            if ($template) {
                $promptText = $template->renderPrompt($variables);
                if (empty($systemPrompt) && !empty($template->system_prompt)) {
                    $systemPrompt = $template->system_prompt;
                }
            }
        } elseif (!empty($variables) && !empty($promptText)) {
            // If free prompt with variables like $1, $2
            foreach ($variables as $key => $val) {
                $placeholder = str_starts_with($key, '$') ? $key : '$' . $key;
                $promptText = str_replace($placeholder, (string) $val, $promptText);
            }
        }

        if (empty($promptText)) {
            return response()->json([
                'success' => false,
                'message' => 'Lütfen bir prompt metni girin veya bir şablon seçin.',
            ], 422);
        }

        $result = $this->llmService->generate(
            prompt: $promptText,
            systemPrompt: $systemPrompt,
            model: $validated['model'] ?? null,
            provider: $validated['provider'] ?? null,
            apiKey: $validated['api_key'] ?? null,
            baseUrl: $validated['base_url'] ?? null
        );

        $result['compiled_prompt'] = $promptText;

        return response()->json($result);
    }

    /**
     * Test connection to the LLM endpoint.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $provider = $request->input('provider');
        $baseUrl = $request->input('base_url');
        $apiKey = $request->input('api_key');

        $result = $this->llmService->testConnection($provider, $baseUrl, $apiKey);

        return response()->json($result);
    }
}
