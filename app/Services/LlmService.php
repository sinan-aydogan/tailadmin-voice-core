<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LlmService
{
    public function getSettings(): array
    {
        $file = base_path('data' . DIRECTORY_SEPARATOR . 'settings.json');
        $saved = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $saved = $decoded;
            }
        }

        return [
            'llm_provider' => $saved['llm_provider'] ?? env('LLM_PROVIDER', 'ollama'),
            'llm_base_url' => $saved['llm_base_url'] ?? env('LLM_BASE_URL', 'http://127.0.0.1:11434'),
            'llm_api_key' => $saved['llm_api_key'] ?? env('LLM_API_KEY', ''),
            'llm_model' => $saved['llm_model'] ?? env('LLM_MODEL', 'llama3:latest'),
            'llm_system_prompt' => $saved['llm_system_prompt'] ?? env('LLM_SYSTEM_PROMPT', 'Sen seslendirme metinleri hazırlayan yaratıcı, akıcı ve profesyonel bir yapay zeka asistanısın. Yanıtlarında gereksiz selamlama veya açıklama yapmadan yalnızca doğrudan seslendirilecek metni ver.'),
            'llm_providers_config' => $saved['llm_providers_config'] ?? [],
        ];
    }

    /**
     * Generate text using the configured or specified LLM provider.
     */
    public function generate(
        string $prompt,
        ?string $systemPrompt = null,
        ?string $model = null,
        ?string $provider = null,
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $ttsEngine = null
    ): array {
        $settings = $this->getSettings();
        $provider = $provider ?: $settings['llm_provider'];
        $model = $model ?: $settings['llm_model'];
        $systemPrompt = $systemPrompt ?: $settings['llm_system_prompt'];
        $baseUrl = rtrim($baseUrl ?: $settings['llm_base_url'], '/');
        $apiKey = $apiKey !== null ? $apiKey : $settings['llm_api_key'];

        // Allow unlimited execution time for local models (Ollama, LM Studio, CPU inference, etc.)
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        try {
            switch ($provider) {
                case 'ollama':
                    return $this->generateWithOllama($baseUrl, $model, $prompt, $systemPrompt);

                case 'claude':
                case 'anthropic':
                    return $this->generateWithClaude($apiKey, $model, $prompt, $systemPrompt);

                case 'gemini':
                    return $this->generateWithGemini($apiKey, $model, $prompt, $systemPrompt);

                case 'groq':
                    $groqUrl = (!empty($baseUrl) && str_contains($baseUrl, 'groq')) ? $baseUrl : 'https://api.groq.com/openai/v1';
                    $groqModel = $model ?: 'llama-3.3-70b-versatile';
                    return $this->generateWithOpenAi($groqUrl, $apiKey, $groqModel, $prompt, $systemPrompt, 'groq');

                case 'deepseek':
                    $dsUrl = (!empty($baseUrl) && str_contains($baseUrl, 'deepseek')) ? $baseUrl : 'https://api.deepseek.com';
                    $dsModel = $model ?: 'deepseek-chat';
                    return $this->generateWithOpenAi($dsUrl, $apiKey, $dsModel, $prompt, $systemPrompt, 'deepseek');

                case 'openrouter':
                    $orUrl = (!empty($baseUrl) && str_contains($baseUrl, 'openrouter')) ? $baseUrl : 'https://openrouter.ai/api/v1';
                    $orModel = $model ?: 'meta-llama/llama-3.3-70b-instruct';
                    return $this->generateWithOpenAi($orUrl, $apiKey, $orModel, $prompt, $systemPrompt, 'openrouter');

                case 'openai':
                case 'openai_compatible':
                    $oaUrl = !empty($baseUrl) ? $baseUrl : 'https://api.openai.com/v1';
                    $oaModel = $model ?: 'gpt-4o-mini';
                    return $this->generateWithOpenAi($oaUrl, $apiKey, $oaModel, $prompt, $systemPrompt, 'openai');

                case 'mock':
                default:
                    return $this->generateMockResponse($prompt, $model ?: 'simulated-model', 'mock', $ttsEngine);
            }
        } catch (\Throwable $e) {
            Log::warning('LLM Generation failed', [
                'provider' => $provider,
                'model'    => $model,
                'error'    => $e->getMessage(),
            ]);
            // Re-throw so callers (e.g. enhanceMusicPrompt, enhanceSfxPrompt) can handle properly.
            throw $e;
        }
    }

    /**
     * Generate via Anthropic Claude Messages API.
     */
    protected function generateWithClaude(string $apiKey, string $model, string $prompt, string $systemPrompt): array
    {
        if (empty($apiKey)) {
            throw new \Exception('Anthropic Claude için API anahtarı girilmedi.');
        }

        $claudeModel = $model ?: 'claude-3-5-sonnet-20241022';
        $url = 'https://api.anthropic.com/v1/messages';

        $payload = [
            'model' => $claudeModel,
            'max_tokens' => 2048,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if (!empty($systemPrompt)) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::timeout(180)->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post($url, $payload);

        if ($response->successful()) {
            $data = $response->json();
            $text = trim($data['content'][0]['text'] ?? '');
            if (!empty($text)) {
                return [
                    'success' => true,
                    'text' => $text,
                    'provider' => 'claude',
                    'model' => $claudeModel,
                ];
            }
        }

        $errMsg = $response->json('error.message') ?? ('HTTP ' . $response->status() . ' - ' . $response->body());
        throw new \Exception('Claude API hatası: ' . $errMsg);
    }

    /**
     * Generate via Ollama native endpoint or OpenAI-compatible endpoint (LM Studio, vLLM, etc.).
     */
    protected function generateWithOllama(string $baseUrl, string $model, string $prompt, string $systemPrompt): array
    {
        $isLmStudio = str_contains($baseUrl, ':1234');

        // 1. If standard Ollama (not LM Studio), try Ollama's native /api/generate endpoint
        if (!$isLmStudio) {
            try {
                $url = $baseUrl . '/api/generate';
                $response = Http::timeout(600)->post($url, [
                    'model' => $model,
                    'prompt' => $prompt,
                    'system' => $systemPrompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.7,
                    ],
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = trim($data['response'] ?? '');
                    if (!empty($text)) {
                        return [
                            'success' => true,
                            'text' => $text,
                            'provider' => 'ollama',
                            'model' => $model,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Fallback to OpenAI-compatible endpoint below
            }
        }

        // 2. Try OpenAI-compatible endpoint (/v1/chat/completions) - used by LM Studio, Ollama, vLLM, etc.
        $chatUrl = $baseUrl . '/v1/chat/completions';
        $chatResponse = Http::timeout(600)->post($chatUrl, [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
        ]);

        if ($chatResponse->successful()) {
            $data = $chatResponse->json();
            $choice = $data['choices'][0] ?? [];
            $text = trim($choice['message']['content'] ?? '');

            // Support reasoning/thinking models (e.g. Qwen 3.5 thinking, DeepSeek-R1)
            if (empty($text) && !empty($choice['message']['reasoning_content'])) {
                $text = trim($choice['message']['reasoning_content']);
            }

            // Clean any residual <think>...</think> blocks if present
            if (!empty($text)) {
                $cleaned = preg_replace('/<think>.*?<\/think>/s', '', $text);
                $cleaned = trim($cleaned);
                $text = !empty($cleaned) ? $cleaned : $text;

                return [
                    'success' => true,
                    'text' => $text,
                    'provider' => 'ollama',
                    'model' => $model,
                ];
            }
        }

        $errStatus = $chatResponse->status() ?? 'Bilinmiyor';
        throw new \Exception('Yerel LLM sunucusundan yanıt alınamadı (HTTP ' . $errStatus . '). Modelin yüklü ve çalışır durumda olduğundan emin olun.');
    }

    /**
     * Generate via OpenAI or any OpenAI-compatible API (Groq, DeepSeek, OpenRouter, vLLM).
     */
    protected function generateWithOpenAi(
        string $baseUrl,
        string $apiKey,
        string $model,
        string $prompt,
        string $systemPrompt,
        string $providerName = 'openai'
    ): array {
        if (empty($apiKey)) {
            throw new \Exception(strtoupper($providerName) . ' sağlayıcısı için API anahtarı girilmedi.');
        }

        $url = rtrim($baseUrl, '/') . '/chat/completions';
        $response = Http::timeout(180)
            ->withToken($apiKey)
            ->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $text = trim($data['choices'][0]['message']['content'] ?? '');
            if (!empty($text)) {
                return [
                    'success' => true,
                    'text' => $text,
                    'provider' => $providerName,
                    'model' => $model,
                ];
            }
        }

        $errMsg = $response->json('error.message') ?? ('HTTP ' . $response->status() . ' - ' . $response->body());
        throw new \Exception(strtoupper($providerName) . ' API hatası: ' . $errMsg);
    }

    /**
     * Generate via Google Gemini REST API.
     */
    protected function generateWithGemini(string $apiKey, string $model, string $prompt, string $systemPrompt): array
    {
        if (empty($apiKey)) {
            throw new \Exception('Google Gemini için API anahtarı girilmedi.');
        }

        $geminiModel = $model ?: 'gemini-2.0-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$apiKey}";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
        ];

        if (!empty($systemPrompt)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ];
        }

        $response = Http::timeout(180)->post($url, $payload);

        if ($response->successful()) {
            $data = $response->json();
            $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if (!empty($text)) {
                return [
                    'success' => true,
                    'text' => $text,
                    'provider' => 'gemini',
                    'model' => $geminiModel,
                ];
            }
        }

        $errMsg = $response->json('error.message') ?? ('HTTP ' . $response->status() . ' - ' . $response->body());
        throw new \Exception('Gemini API hatası: ' . $errMsg);
    }

    /**
     * Test connection to selected provider.
     */
    public function testConnection(?string $provider = null, ?string $baseUrl = null, ?string $apiKey = null): array
    {
        $settings = $this->getSettings();
        $provider = $provider ?: $settings['llm_provider'];
        $baseUrl = rtrim($baseUrl ?: $settings['llm_base_url'], '/');
        $apiKey = $apiKey !== null ? $apiKey : $settings['llm_api_key'];

        try {
            switch ($provider) {
                case 'ollama':
                    $modelNames = [];
                    $isLmStudio = str_contains($baseUrl, ':1234');

                    // A) Check Ollama native /api/tags
                    if (!$isLmStudio) {
                        try {
                            $res = Http::timeout(5)->get($baseUrl . '/api/tags');
                            if ($res->successful()) {
                                $tags = $res->json('models') ?? [];
                                $modelNames = array_values(array_filter(array_map(fn($m) => $m['name'] ?? '', $tags)));
                            }
                        } catch (\Throwable) {}
                    }

                    // B) Check OpenAI-compatible /v1/models (LM Studio, vLLM, LocalAI)
                    if (empty($modelNames)) {
                        try {
                            $v1Res = Http::timeout(5)->get($baseUrl . '/v1/models');
                            if ($v1Res->successful()) {
                                $data = $v1Res->json('data') ?? [];
                                foreach ($data as $item) {
                                    $id = $item['id'] ?? '';
                                    if (!empty($id) && !str_contains($id, 'embed') && !str_contains($id, 'embedding')) {
                                        $modelNames[] = $id;
                                    }
                                }
                                $modelNames = array_values(array_unique($modelNames));
                            }
                        } catch (\Throwable) {}
                    }

                    if (!empty($modelNames)) {
                        $providerLabel = $isLmStudio ? 'LM Studio' : 'Ollama / Yerel LLM';
                        return [
                            'success' => true,
                            'message' => "{$providerLabel} bağlantısı başarılı! Yüklü model sayısı: " . count($modelNames),
                            'models' => $modelNames,
                        ];
                    }

                    if ((isset($res) && $res->successful()) || (isset($v1Res) && $v1Res->successful())) {
                        return [
                            'success' => true,
                            'message' => 'Yerel sunucuya bağlanıldı ancak yüklü model bulunamadı. Lütfen model arayüzünden bir model yükleyin.',
                            'models' => [],
                        ];
                    }

                    return [
                        'success' => false,
                        'message' => "Yerel LLM sunucusuna ulaşılamadı. {$baseUrl} adresinin çalıştığından emin olun.",
                    ];

                case 'claude':
                case 'anthropic':
                    if (empty($apiKey)) {
                        return ['success' => false, 'message' => 'Lütfen Anthropic Claude API anahtarı girin.'];
                    }
                    $res = Http::timeout(10)->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                        'content-type' => 'application/json',
                    ])->post('https://api.anthropic.com/v1/messages', [
                        'model' => 'claude-3-5-haiku-20241022',
                        'max_tokens' => 1,
                        'messages' => [['role' => 'user', 'content' => 'hi']],
                    ]);

                    if ($res->successful()) {
                        return [
                            'success' => true,
                            'message' => 'Anthropic Claude API bağlantısı başarılı!',
                            'models' => [
                                'claude-3-7-sonnet-20250219',
                                'claude-3-5-sonnet-20241022',
                                'claude-3-5-haiku-20241022',
                                'claude-3-opus-20240229',
                            ],
                        ];
                    }
                    $err = $res->json('error.message') ?? ('HTTP ' . $res->status());
                    return ['success' => false, 'message' => 'Claude API hatası: ' . $err];

                case 'gemini':
                    if (empty($apiKey)) {
                        return ['success' => false, 'message' => 'Lütfen Google Gemini API anahtarı girin.'];
                    }
                    $url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";
                    $res = Http::timeout(5)->get($url);
                    if ($res->successful()) {
                        $models = array_map(fn($m) => str_replace('models/', '', $m['name'] ?? ''), $res->json('models') ?? []);
                        return [
                            'success' => true,
                            'message' => 'Google Gemini API bağlantısı başarılı!',
                            'models' => array_values(array_filter($models, fn($m) => str_contains($m, 'gemini'))),
                        ];
                    }
                    return [
                        'success' => false,
                        'message' => 'Gemini API bağlantısı başarısız. Lütfen API anahtarınızı kontrol edin.',
                    ];

                case 'groq':
                    if (empty($apiKey)) {
                        return ['success' => false, 'message' => 'Lütfen Groq API anahtarı girin.'];
                    }
                    $groqUrl = (!empty($baseUrl) && str_contains($baseUrl, 'groq')) ? $baseUrl : 'https://api.groq.com/openai/v1';
                    $res = Http::timeout(5)->withToken($apiKey)->get($groqUrl . '/models');
                    if ($res->successful()) {
                        $data = $res->json('data') ?? [];
                        $models = array_slice(array_map(fn($m) => $m['id'] ?? '', $data), 0, 10);
                        return [
                            'success' => true,
                            'message' => 'Groq Cloud bağlantısı başarılı!',
                            'models' => $models,
                        ];
                    }
                    return ['success' => false, 'message' => 'Groq API bağlantısı başarısız. API anahtarınızı kontrol edin.'];

                case 'deepseek':
                    if (empty($apiKey)) {
                        return ['success' => false, 'message' => 'Lütfen DeepSeek API anahtarı girin.'];
                    }
                    $dsUrl = (!empty($baseUrl) && str_contains($baseUrl, 'deepseek')) ? $baseUrl : 'https://api.deepseek.com';
                    $res = Http::timeout(5)->withToken($apiKey)->get($dsUrl . '/models');
                    if ($res->successful()) {
                        return [
                            'success' => true,
                            'message' => 'DeepSeek API bağlantısı başarılı!',
                            'models' => ['deepseek-chat', 'deepseek-reasoner'],
                        ];
                    }
                    return ['success' => false, 'message' => 'DeepSeek API bağlantısı başarısız. API anahtarınızı kontrol edin.'];

                case 'openrouter':
                    if (empty($apiKey)) {
                        return ['success' => false, 'message' => 'Lütfen OpenRouter API anahtarı girin.'];
                    }
                    $orUrl = (!empty($baseUrl) && str_contains($baseUrl, 'openrouter')) ? $baseUrl : 'https://openrouter.ai/api/v1';
                    $res = Http::timeout(5)->withToken($apiKey)->get($orUrl . '/models');
                    if ($res->successful()) {
                        return [
                            'success' => true,
                            'message' => 'OpenRouter API bağlantısı başarılı!',
                            'models' => [
                                'meta-llama/llama-3.3-70b-instruct',
                                'anthropic/claude-3.5-sonnet',
                                'deepseek/deepseek-chat',
                                'google/gemini-2.0-flash-001',
                            ],
                        ];
                    }
                    return ['success' => false, 'message' => 'OpenRouter API bağlantısı başarısız. API anahtarınızı kontrol edin.'];

                case 'openai':
                case 'openai_compatible':
                    if (empty($apiKey)) {
                        return ['success' => false, 'message' => 'Lütfen bir API anahtarı girin.'];
                    }
                    $oaUrl = !empty($baseUrl) ? $baseUrl : 'https://api.openai.com/v1';
                    $res = Http::timeout(5)->withToken($apiKey)->get($oaUrl . '/models');
                    if ($res->successful()) {
                        $data = $res->json('data') ?? [];
                        $modelNames = array_slice(array_map(fn($m) => $m['id'] ?? '', $data), 0, 15);
                        return [
                            'success' => true,
                            'message' => 'OpenAI uyumlu servis bağlantısı başarılı!',
                            'models' => $modelNames,
                        ];
                    }
                    return [
                        'success' => false,
                        'message' => 'API bağlantısı başarısız (HTTP ' . $res->status() . '). Lütfen anahtarınızı ve URL adresini kontrol edin.',
                    ];

                case 'mock':
                default:
                    return [
                        'success' => true,
                        'message' => 'Simülasyon modu aktif. Gerçek bir servis yapılandırmadan da test yapabilirsiniz.',
                        'models' => ['simulated-voice-model'],
                    ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Bağlantı hatası: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Intelligent local fallback when external LLM is offline or in demo mode.
     */
    protected function generateMockResponse(string $prompt, string $model, string $provider, ?string $ttsEngine = null): array
    {
        $lower = mb_strtolower($prompt, 'UTF-8');

        if (str_contains($lower, 'hikaye') || str_contains($lower, 'tavşan') || str_contains($lower, 'masal') || str_contains($lower, 'çocuk')) {
            $text = "Güneşli bir ilkbahar sabahı, Zıpzıp adındaki küçük tavşan ormanın en lezzetli yabani çileklerini topluyordu. Tam sepetini doldurmuştu ki, dalın ucundan meraklı bakışlarla kendisini izleyen bilge karga Gakgak'ı fark etti. Gakgak günlerdir ormanın diğer ucunda yiyecek arıyor ama bir şey bulamıyordu. Zıpzıp biraz düşündü; çileklerin hepsi kendisi için fazlaydı. Sepetini karganın önüne doğru uzatarak, 'Gel beraber paylaşalım, lezzet paylaştıkça tatlanır!' dedi. O günden sonra tavşan ile karga ormanın en vefalı iki dostu oldular; çünkü gerçek zenginliğin paylaşmak olduğunu öğrenmişlerdi.";
        } elseif (str_contains($lower, 'haber') || str_contains($lower, 'bülten')) {
            $text = "İyi günler değerli dinleyiciler. Teknoloji dünyasındaki sıcak gelişmelerle bültenimize başlıyoruz. Açık kaynaklı yapay zeka ses istasyonları alanında çığır açan yeni Türkçe ses modelleri yayınlandı. Tamamen yerel donanımlarda internet bağlantısına ihtiyaç duymadan çalışan bu yeni sistemler, stüdyo kalitesinde gerçek zamanlı seslendirme imkanı tanıyor. Ayrıntılar ve sonraki bültenimizde tekrar görüşmek üzere, esen kalın.";
        } elseif (str_contains($lower, 'reklam') || str_contains($lower, 'tanıtım')) {
            $text = "Kendi projelerinizde stüdyo kalitesinde Türkçe sesler üretmek artık hayal değil! Voice Core ile yüksek donanım maliyetleri olmadan, tamamen kendi bilgisayarınızda sınırsız ve doğal seslendirmeler yapın. Üstelik sıfır gecikme ve tek tıkla entegrasyonla! Voice Core: Sesin en doğal hali.";
        } else {
            $text = "Yapay zeka ses dünyasına hoş geldiniz. Girmiş olduğunuz metin isteği doğrultusunda hazırlanan bu seslendirme metni, akıcı ve doğal Türkçe tonlamalarıyla dinleyicilerinize ulaşmaya hazır. Şimdi metni seslendir butonuna tıklayarak üretimi başlatabilirsiniz.";
        }

        if (!empty($ttsEngine)) {
            $featuresService = new \App\Services\TtsModelFeatures();
            $text = $featuresService->enrichMockResponse($text, $ttsEngine);
        }

        return [
            'success' => true,
            'text' => $text,
            'provider' => $provider,
            'model' => $model . ' (Simülasyon)',
        ];
    }

    /**
     * Multi-turn chat with optional tool-calling, used by the Agent
     * execution loop. Unlike generate(), this consumes/produces the full
     * conversation as a provider-agnostic message array and never touches
     * the simple single-prompt path above — a deliberately parallel code
     * path so the existing Flow LLM-node behavior can't regress.
     *
     * @param array $messages Provider-agnostic history: each item is
     *   {role: system|user|assistant|tool, content?, tool_calls?, tool_call_id?, name?}.
     *   An assistant message that wants to call tools carries `tool_calls`:
     *   [{id, name, arguments}]. A tool's result is sent back as
     *   {role:'tool', tool_call_id, name, content}.
     * @param array $tools Each: {name, description, parameters} where
     *   `parameters` is a JSON Schema object ({type, properties, required}).
     * @return array {role:'assistant', content:?string, tool_calls?:[{id,name,arguments}]}
     */
    public function chat(
        array $messages,
        array $tools = [],
        ?string $model = null,
        ?string $provider = null,
        ?string $apiKey = null,
        ?string $baseUrl = null
    ): array {
        $settings = $this->getSettings();
        $provider = $provider ?: $settings['llm_provider'];
        $model = $model ?: $settings['llm_model'];
        $baseUrl = rtrim($baseUrl ?: $settings['llm_base_url'], '/');
        $apiKey = $apiKey !== null ? $apiKey : $settings['llm_api_key'];

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        try {
            return match ($provider) {
                'claude', 'anthropic' => $this->chatWithClaude($apiKey, $model, $messages, $tools),
                'gemini' => $this->chatWithGemini($apiKey, $model, $messages, $tools),
                'groq' => $this->chatWithOpenAi(
                    (!empty($baseUrl) && str_contains($baseUrl, 'groq')) ? $baseUrl : 'https://api.groq.com/openai/v1',
                    $apiKey, $model ?: 'llama-3.3-70b-versatile', $messages, $tools
                ),
                'deepseek' => $this->chatWithOpenAi(
                    (!empty($baseUrl) && str_contains($baseUrl, 'deepseek')) ? $baseUrl : 'https://api.deepseek.com',
                    $apiKey, $model ?: 'deepseek-chat', $messages, $tools
                ),
                'openrouter' => $this->chatWithOpenAi(
                    (!empty($baseUrl) && str_contains($baseUrl, 'openrouter')) ? $baseUrl : 'https://openrouter.ai/api/v1',
                    $apiKey, $model ?: 'meta-llama/llama-3.3-70b-instruct', $messages, $tools
                ),
                'openai', 'openai_compatible' => $this->chatWithOpenAi(
                    !empty($baseUrl) ? $baseUrl : 'https://api.openai.com/v1',
                    $apiKey, $model ?: 'gpt-4o-mini', $messages, $tools
                ),
                'ollama' => $this->chatWithOpenAi($baseUrl . '/v1', $apiKey ?: '', $model, $messages, $tools),
                default => $this->chatMock($messages),
            };
        } catch (\Throwable $e) {
            Log::warning('Agent chat() failed, falling back to simulated reply', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            $mock = $this->chatMock($messages);
            $mock['warning'] = 'Bağlantı uyarısı (' . $e->getMessage() . '). Test amaçlı simülasyon metni sunuldu.';
            return $mock;
        }
    }

    /**
     * OpenAI-compatible /v1/chat/completions — covers OpenAI, Groq,
     * DeepSeek, OpenRouter, and Ollama/LM Studio's OpenAI-compatible endpoint.
     */
    protected function chatWithOpenAi(string $baseUrl, string $apiKey, string $model, array $messages, array $tools): array
    {
        $payload = [
            'model' => $model,
            'messages' => $this->toOpenAiMessages($messages),
        ];
        if (!empty($tools)) {
            $payload['tools'] = array_map(fn ($t) => [
                'type' => 'function',
                'function' => [
                    'name' => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ], $tools);
        }

        $request = Http::timeout(180);
        if (!empty($apiKey)) {
            $request = $request->withToken($apiKey);
        }

        $response = $request->post(rtrim($baseUrl, '/') . '/chat/completions', $payload);

        if (!$response->successful()) {
            throw new \Exception('LLM API hatası: HTTP ' . $response->status() . ' - ' . $response->body());
        }

        return $this->fromOpenAiMessage($response->json('choices.0.message') ?? [], $tools);
    }

    protected function toOpenAiMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? null) === 'assistant' && !empty($m['tool_calls'])) {
                $out[] = [
                    'role' => 'assistant',
                    'content' => $m['content'] ?? null,
                    'tool_calls' => array_map(fn ($tc) => [
                        'id' => $tc['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['name'],
                            'arguments' => json_encode($tc['arguments'] ?? [], JSON_UNESCAPED_UNICODE),
                        ],
                    ], $m['tool_calls']),
                ];
            } elseif (($m['role'] ?? null) === 'tool') {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => $m['tool_call_id'] ?? null,
                    'content' => (string) ($m['content'] ?? ''),
                ];
            } else {
                $out[] = ['role' => $m['role'] ?? 'user', 'content' => (string) ($m['content'] ?? '')];
            }
        }
        return $out;
    }

    protected function fromOpenAiMessage(array $message, array $tools = []): array
    {
        if (!empty($message['tool_calls'])) {
            return [
                'role' => 'assistant',
                'content' => $message['content'] ?? null,
                'tool_calls' => array_map(function ($tc) {
                    $rawArgs = $tc['function']['arguments'] ?? [];
                    $arguments = is_array($rawArgs)
                        ? $rawArgs
                        : (is_string($rawArgs) ? (json_decode($rawArgs, true) ?? []) : []);

                    return [
                        'id' => $tc['id'] ?? ('call_' . Str::random(10)),
                        'name' => $tc['function']['name'] ?? '',
                        'arguments' => $arguments,
                    ];
                }, $message['tool_calls']),
            ];
        }

        $content = trim((string) ($message['content'] ?? ''));

        // Smart fallback for local/Ollama models that output markdown JSON tool calls in text
        if (!empty($tools) && !empty($content)) {
            $parsedCall = $this->extractJsonToolCall($content, $tools);
            if ($parsedCall) {
                return [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [$parsedCall],
                ];
            }
        }

        return ['role' => 'assistant', 'content' => $content];
    }

    protected function extractJsonToolCall(string $content, array $tools): ?array
    {
        $toolNames = array_map(fn ($t) => $t['name'] ?? '', $tools);

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $content, $m) || preg_match('/(\{[\s\S]*?"name"\s*:\s*"[^"]+"[\s\S]*?\})/s', $content, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded) && !empty($decoded['name']) && in_array($decoded['name'], $toolNames)) {
                $rawArgs = $decoded['arguments'] ?? $decoded['parameters'] ?? [];
                return [
                    'id' => 'call_' . Str::random(10),
                    'name' => $decoded['name'],
                    'arguments' => is_array($rawArgs) ? $rawArgs : (is_string($rawArgs) ? (json_decode($rawArgs, true) ?? []) : []),
                ];
            }
        }

        return null;
    }

    public function toAnthropicMessages(array $messages, ?string &$system = null): array
    {
        $system = null;
        $claudeMessages = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'system') {
                $system = ($system ? $system . "\n\n" : '') . ($m['content'] ?? '');
                continue;
            }

            if ($role === 'assistant' && !empty($m['tool_calls'])) {
                $content = [];
                if (!empty($m['content'])) {
                    $content[] = ['type' => 'text', 'text' => $m['content']];
                }
                foreach ($m['tool_calls'] as $tc) {
                    $tcName = $tc['name'] ?? ($tc['function']['name'] ?? '');
                    $tcInput = $tc['arguments'] ?? ($tc['function']['arguments'] ?? []);
                    if (is_string($tcInput)) {
                        $tcInput = json_decode($tcInput, true) ?? [];
                    }
                    $content[] = ['type' => 'tool_use', 'id' => $tc['id'] ?? uniqid('call_'), 'name' => $tcName, 'input' => $tcInput];
                }
                $claudeMessages[] = ['role' => 'assistant', 'content' => $content];
                continue;
            }

            if ($role === 'tool') {
                $toolResultBlock = [
                    'type' => 'tool_result',
                    'tool_use_id' => $m['tool_call_id'] ?? null,
                    'content' => (string) ($m['content'] ?? ''),
                ];

                $lastIdx = count($claudeMessages) - 1;
                if ($lastIdx >= 0 && ($claudeMessages[$lastIdx]['role'] ?? '') === 'user' && is_array($claudeMessages[$lastIdx]['content'])) {
                    $claudeMessages[$lastIdx]['content'][] = $toolResultBlock;
                } else {
                    $claudeMessages[] = [
                        'role' => 'user',
                        'content' => [$toolResultBlock],
                    ];
                }
                continue;
            }

            $claudeMessages[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }

        return $claudeMessages;
    }

    protected function chatWithClaude(string $apiKey, string $model, array $messages, array $tools): array
    {
        if (empty($apiKey)) {
            throw new \Exception('Anthropic Claude için API anahtarı girilmedi.');
        }

        $system = null;
        $claudeMessages = $this->toAnthropicMessages($messages, $system);

        $payload = [
            'model' => $model ?: 'claude-3-5-sonnet-20241022',
            'max_tokens' => 2048,
            'messages' => $claudeMessages,
        ];
        if ($system) {
            $payload['system'] = $system;
        }
        if (!empty($tools)) {
            $payload['tools'] = array_map(fn ($t) => [
                'name' => $t['name'],
                'description' => $t['description'] ?? '',
                'input_schema' => $t['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ], $tools);
        }

        $response = Http::timeout(180)->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            throw new \Exception('Claude API hatası: ' . ($response->json('error.message') ?? $response->body()));
        }

        $text = null;
        $toolCalls = [];
        foreach ($response->json('content') ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text = ($text ?? '') . $block['text'];
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = ['id' => $block['id'], 'name' => $block['name'], 'arguments' => $block['input'] ?? []];
            }
        }

        if (!empty($toolCalls)) {
            return ['role' => 'assistant', 'content' => $text, 'tool_calls' => $toolCalls];
        }

        return ['role' => 'assistant', 'content' => trim((string) $text)];
    }

    public function toGeminiContents(array $messages, ?string &$systemInstruction = null): array
    {
        $systemInstruction = null;
        $contents = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'system') {
                $systemInstruction = ($systemInstruction ? $systemInstruction . "\n\n" : '') . ($m['content'] ?? '');
                continue;
            }

            if ($role === 'assistant' && !empty($m['tool_calls'])) {
                $parts = [];
                if (!empty($m['content'])) {
                    $parts[] = ['text' => $m['content']];
                }
                foreach ($m['tool_calls'] as $tc) {
                    $tcName = $tc['name'] ?? ($tc['function']['name'] ?? '');
                    $tcArgs = $tc['arguments'] ?? ($tc['function']['arguments'] ?? []);
                    if (is_string($tcArgs)) {
                        $tcArgs = json_decode($tcArgs, true) ?? [];
                    }
                    $parts[] = ['functionCall' => ['name' => $tcName, 'args' => $tcArgs]];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];
                continue;
            }

            if ($role === 'tool') {
                $functionResponsePart = [
                    'functionResponse' => [
                        'name' => $m['name'] ?? '',
                        'response' => ['result' => (string) ($m['content'] ?? '')],
                    ],
                ];

                $lastIdx = count($contents) - 1;
                if ($lastIdx >= 0 && ($contents[$lastIdx]['role'] ?? '') === 'function' && is_array($contents[$lastIdx]['parts'])) {
                    $contents[$lastIdx]['parts'][] = $functionResponsePart;
                } else {
                    $contents[] = [
                        'role' => 'function',
                        'parts' => [$functionResponsePart],
                    ];
                }
                continue;
            }

            $contents[] = ['role' => $role === 'assistant' ? 'model' : 'user', 'parts' => [['text' => (string) ($m['content'] ?? '')]]];
        }

        return $contents;
    }

    protected function chatWithGemini(string $apiKey, string $model, array $messages, array $tools): array
    {
        if (empty($apiKey)) {
            throw new \Exception('Google Gemini için API anahtarı girilmedi.');
        }

        $geminiModel = $model ?: 'gemini-2.0-flash';
        $systemInstruction = null;
        $contents = $this->toGeminiContents($messages, $systemInstruction);

        $payload = ['contents' => $contents];
        if ($systemInstruction) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }
        if (!empty($tools)) {
            $payload['tools'] = [[
                'function_declarations' => array_map(fn ($t) => [
                    'name' => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ], $tools),
            ]];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$apiKey}";
        $response = Http::timeout(180)->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception('Gemini API hatası: ' . ($response->json('error.message') ?? $response->body()));
        }

        $text = null;
        $toolCalls = [];
        foreach ($response->json('candidates.0.content.parts') ?? [] as $part) {
            if (isset($part['text'])) {
                $text = ($text ?? '') . $part['text'];
            } elseif (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => 'call_' . Str::random(10),
                    'name' => $part['functionCall']['name'] ?? '',
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        if (!empty($toolCalls)) {
            return ['role' => 'assistant', 'content' => $text, 'tool_calls' => $toolCalls];
        }

        return ['role' => 'assistant', 'content' => trim((string) $text)];
    }

    /**
     * Local fallback for chat() when no real provider is reachable — reuses
     * the same canned responses as generate()'s mock path.
     */
    protected function chatMock(array $messages): array
    {
        $lastTurn = collect($messages)->last(fn ($m) => in_array($m['role'] ?? null, ['user', 'tool']));
        $prompt = (string) ($lastTurn['content'] ?? '');
        $mock = $this->generateMockResponse($prompt, 'simulated-model', 'mock');

        return ['role' => 'assistant', 'content' => $mock['text']];
    }

    /**
     * Get available LLM providers and models configured in the system.
     */
    public function getAvailableLlmOptions(): array
    {
        $settings = $this->getSettings();
        $savedProvidersConfig = $settings['llm_providers_config'] ?? [];

        $providers = [
            [
                'provider' => 'ollama',
                'name' => 'Ollama (Yerel)',
                'icon' => '🦙',
                'models' => array_values(array_unique(array_filter([
                    $settings['llm_model'],
                    'google/gemma-4-e4b',
                    'llama3:latest',
                    'mistral:latest',
                    'qwen2.5:latest',
                ]))),
                'is_active' => $settings['llm_provider'] === 'ollama',
                'has_key' => true,
            ],
            [
                'provider' => 'groq',
                'name' => 'Groq (Ultra Hızlı LPU)',
                'icon' => '⚡',
                'models' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768'],
                'is_active' => $settings['llm_provider'] === 'groq',
                'has_key' => !empty($savedProvidersConfig['groq']['api_key'] ?? env('GROQ_API_KEY')),
            ],
            [
                'provider' => 'gemini',
                'name' => 'Google Gemini',
                'icon' => '✨',
                'models' => ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-pro'],
                'is_active' => $settings['llm_provider'] === 'gemini',
                'has_key' => !empty($savedProvidersConfig['gemini']['api_key'] ?? env('GEMINI_API_KEY')),
            ],
            [
                'provider' => 'openai',
                'name' => 'OpenAI',
                'icon' => '🧠',
                'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
                'is_active' => $settings['llm_provider'] === 'openai',
                'has_key' => !empty($savedProvidersConfig['openai']['api_key'] ?? env('OPENAI_API_KEY')),
            ],
            [
                'provider' => 'anthropic',
                'name' => 'Claude (Anthropic)',
                'icon' => '🎭',
                'models' => ['claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022'],
                'is_active' => $settings['llm_provider'] === 'anthropic',
                'has_key' => !empty($savedProvidersConfig['anthropic']['api_key'] ?? env('ANTHROPIC_API_KEY')),
            ],
            [
                'provider' => 'deepseek',
                'name' => 'DeepSeek',
                'icon' => '🐋',
                'models' => ['deepseek-chat', 'deepseek-reasoner'],
                'is_active' => $settings['llm_provider'] === 'deepseek',
                'has_key' => !empty($savedProvidersConfig['deepseek']['api_key'] ?? env('DEEPSEEK_API_KEY')),
            ],
            [
                'provider' => 'openrouter',
                'name' => 'OpenRouter',
                'icon' => '🌐',
                'models' => ['meta-llama/llama-3.3-70b-instruct', 'deepseek/deepseek-r1'],
                'is_active' => $settings['llm_provider'] === 'openrouter',
                'has_key' => !empty($savedProvidersConfig['openrouter']['api_key'] ?? env('OPENROUTER_API_KEY')),
            ],
        ];

        return [
            'current' => [
                'provider' => $settings['llm_provider'],
                'model' => $settings['llm_model'],
                'base_url' => $settings['llm_base_url'],
            ],
            'providers' => $providers,
        ];
    }

    /**
     * Enhance a music generation prompt using the selected LLM.
     */
    public function enhanceMusicPrompt(string $prompt, array $options = []): array
    {
        $provider = $options['provider'] ?? null;
        $model    = $options['model'] ?? null;
        $genre    = $options['genre'] ?? 'fairytale_children';
        $currentBpm   = (int)($options['bpm'] ?? 90);
        $currentScale = $options['scale'] ?? 'C Major';

        $systemPrompt = "Sen dünyaca ünlü bir müzik prodüktörü ve ses tasarımcısısın (MusicGen, AudioCraft, Suno ve sinematik orkestrasyon uzmanı).
Görevin, kullanıcının verdiği kaba veya kısa müzik fikrini; enstrümantasyon, akor/armoni, tempo (BPM), gam (Scale), duygu ve akustik doku içeren, müzik yapay zekası (AI Music Generator) için optimize edilmiş profesyonel bir prodüksiyon açıklamasına dönüştürmektir.

Aşağıdaki JSON formatında yanıt ver (Markdown formatı veya ekstra yazı ekleme):
{
  \"enhanced_prompt\": \"İngilizce veya Türkçe detaylı, zengin enstrüman ve duygu tasviri içeren müzik promptu\",
  \"suggested_bpm\": 90,
  \"suggested_scale\": \"C Major\",
  \"suggested_genre\": \"{$genre}\",
  \"suggested_texture\": \"acoustic\"
}";

        $userPrompt = "Kullanıcı fikri: \"" . ($prompt ?: 'Bahar masalı fon müziği') . "\"\nTür: {$genre}, Mevcut BPM: {$currentBpm}, Gam: {$currentScale}";

        try {
            $res = $this->generate(
                prompt: $userPrompt,
                systemPrompt: $systemPrompt,
                model: $model,
                provider: $provider
            );
        } catch (\Throwable $e) {
            throw new \Exception(
                '🔌 LLM bağlantı hatası: ' . $e->getMessage() .
                ' — Ayarlar sayfasından ' . ($provider ?? 'sağlayıcı') . ' servis ayarlarını kontrol edin.',
                0, $e
            );
        }

        $rawText = trim($res['text'] ?? '');

        if (empty($rawText)) {
            throw new \Exception('LLM boş yanıt döndürdü. Model veya bağlantı ayarlarını kontrol edin.');
        }

        $json = null;

        if (preg_match('/\{[\s\S]*\}/u', $rawText, $matches)) {
            $json = json_decode($matches[0], true);
        }

        if (is_array($json) && !empty($json['enhanced_prompt'])) {
            return [
                'success'          => true,
                'enhanced_prompt'  => trim($json['enhanced_prompt']),
                'suggested_bpm'    => (int)($json['suggested_bpm'] ?? $currentBpm),
                'suggested_scale'  => $json['suggested_scale'] ?? $currentScale,
                'suggested_genre'  => $json['suggested_genre'] ?? $genre,
                'suggested_texture'=> $json['suggested_texture'] ?? 'acoustic',
                'model_used'       => $res['model'] ?? $model,
                'provider_used'    => $res['provider'] ?? $provider,
            ];
        }

        $cleaned = trim(preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $rawText));
        $cleaned = trim(trim($cleaned, '"\''));

        if (empty($cleaned)) {
            throw new \Exception('LLM geçerli bir prompt üretemedi. Lütfen farklı bir model deneyin.');
        }

        return [
            'success'          => true,
            'enhanced_prompt'  => $cleaned,
            'suggested_bpm'    => $currentBpm,
            'suggested_scale'  => $currentScale,
            'suggested_genre'  => $genre,
            'suggested_texture'=> 'acoustic',
            'model_used'       => $res['model'] ?? $model,
            'provider_used'    => $res['provider'] ?? $provider,
        ];
    }

    /**
     * Enhance an SFX generation prompt using the selected LLM.
     */
    public function enhanceSfxPrompt(string $prompt, array $options = []): array
    {
        $provider = $options['provider'] ?? null;
        $model    = $options['model'] ?? null;
        $preset   = $options['preset'] ?? 'birds_chirping';
        $currentDuration = (float)($options['duration'] ?? 2.0);

        $systemPrompt = "Sen Hollywood düzeyinde bir foley sanatçısı ve ses efekti tasarımcısısın (AudioGen, foley sound and procedural SFX synthesis).
Görevin, kullanıcının verdiği ses efekti fikrini; sesin kaynağı, vuruş/atak karakteri, frekans ve tını dokusu (sub-bass, parlak kristal tını), akustik mekan ve geçiş dinamiklerini içeren profesyonel bir ses efekti promptuna dönüştürmektir.

Aşağıdaki JSON formatında yanıt ver (Markdown veya fazladan yazı ekleme):
{
  \"enhanced_prompt\": \"Detaylı akustik tasvir içeren ses efekti promptu\",
  \"suggested_duration\": 2.0,
  \"suggested_reverb\": \"room\",
  \"suggested_tone\": \"balanced\",
  \"suggested_preset\": \"{$preset}\"
}";

        $userPrompt = "Kullanıcı efekti: \"" . ($prompt ?: 'Kuş cıvıltısı ve orman') . "\"\nSeçili Şablon: {$preset}, Mevcut Süre: {$currentDuration}s";

        try {
            $res = $this->generate(
                prompt: $userPrompt,
                systemPrompt: $systemPrompt,
                model: $model,
                provider: $provider
            );
        } catch (\Throwable $e) {
            throw new \Exception(
                '🔌 LLM bağlantı hatası: ' . $e->getMessage() .
                ' — Ayarlar sayfasından ' . ($provider ?? 'sağlayıcı') . ' servis ayarlarını kontrol edin.',
                0, $e
            );
        }

        $rawText = trim($res['text'] ?? '');

        if (empty($rawText)) {
            throw new \Exception('LLM boş yanıt döndürdü. Model veya bağlantı ayarlarını kontrol edin.');
        }

        $json = null;

        if (preg_match('/\{[\s\S]*\}/u', $rawText, $matches)) {
            $json = json_decode($matches[0], true);
        }

        if (is_array($json) && !empty($json['enhanced_prompt'])) {
            return [
                'success'          => true,
                'enhanced_prompt'  => trim($json['enhanced_prompt']),
                'suggested_duration'=> (float)($json['suggested_duration'] ?? $currentDuration),
                'suggested_reverb' => $json['suggested_reverb'] ?? 'room',
                'suggested_tone'   => $json['suggested_tone'] ?? 'balanced',
                'suggested_preset' => $json['suggested_preset'] ?? $preset,
                'model_used'       => $res['model'] ?? $model,
                'provider_used'    => $res['provider'] ?? $provider,
            ];
        }

        $cleaned = trim(preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $rawText));
        $cleaned = trim(trim($cleaned, '"\''));

        if (empty($cleaned)) {
            throw new \Exception('LLM geçerli bir prompt üretemedi. Lütfen farklı bir model deneyin.');
        }

        return [
            'success'          => true,
            'enhanced_prompt'  => $cleaned,
            'suggested_duration'=> $currentDuration,
            'suggested_reverb' => 'room',
            'suggested_tone'   => 'balanced',
            'suggested_preset' => $preset,
            'model_used'       => $res['model'] ?? $model,
            'provider_used'    => $res['provider'] ?? $provider,
        ];
    }
}
