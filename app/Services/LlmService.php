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
        ?string $baseUrl = null
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
                    return $this->generateMockResponse($prompt, $model ?: 'simulated-model', 'mock');
            }
        } catch (\Throwable $e) {
            Log::warning('LLM Generation failed, falling back to intelligent simulation', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            $mockResult = $this->generateMockResponse($prompt, $model ?: 'model', $provider);
            $mockResult['warning'] = 'Bağlantı uyarısı (' . $e->getMessage() . '). Test amaçlı simülasyon metni sunuldu. Ayarlar > LLM sekmesinden servis durumunu kontrol edebilirsiniz.';
            return $mockResult;
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
    protected function generateMockResponse(string $prompt, string $model, string $provider): array
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

        return $this->fromOpenAiMessage($response->json('choices.0.message') ?? []);
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

    protected function fromOpenAiMessage(array $message): array
    {
        if (!empty($message['tool_calls'])) {
            return [
                'role' => 'assistant',
                'content' => $message['content'] ?? null,
                'tool_calls' => array_map(fn ($tc) => [
                    'id' => $tc['id'] ?? ('call_' . Str::random(10)),
                    'name' => $tc['function']['name'] ?? '',
                    'arguments' => json_decode($tc['function']['arguments'] ?? '{}', true) ?? [],
                ], $message['tool_calls']),
            ];
        }

        return ['role' => 'assistant', 'content' => trim((string) ($message['content'] ?? ''))];
    }

    protected function chatWithClaude(string $apiKey, string $model, array $messages, array $tools): array
    {
        if (empty($apiKey)) {
            throw new \Exception('Anthropic Claude için API anahtarı girilmedi.');
        }

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
                    $content[] = ['type' => 'tool_use', 'id' => $tc['id'], 'name' => $tc['name'], 'input' => $tc['arguments'] ?? []];
                }
                $claudeMessages[] = ['role' => 'assistant', 'content' => $content];
                continue;
            }

            if ($role === 'tool') {
                $claudeMessages[] = ['role' => 'user', 'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => $m['tool_call_id'] ?? null,
                    'content' => (string) ($m['content'] ?? ''),
                ]]];
                continue;
            }

            $claudeMessages[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }

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

    protected function chatWithGemini(string $apiKey, string $model, array $messages, array $tools): array
    {
        if (empty($apiKey)) {
            throw new \Exception('Google Gemini için API anahtarı girilmedi.');
        }

        $geminiModel = $model ?: 'gemini-2.0-flash';
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
                    $parts[] = ['functionCall' => ['name' => $tc['name'], 'args' => $tc['arguments'] ?? []]];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];
                continue;
            }

            if ($role === 'tool') {
                $contents[] = ['role' => 'function', 'parts' => [[
                    'functionResponse' => [
                        'name' => $m['name'] ?? '',
                        'response' => ['result' => (string) ($m['content'] ?? '')],
                    ],
                ]]];
                continue;
            }

            $contents[] = ['role' => $role === 'assistant' ? 'model' : 'user', 'parts' => [['text' => (string) ($m['content'] ?? '')]]];
        }

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
}
