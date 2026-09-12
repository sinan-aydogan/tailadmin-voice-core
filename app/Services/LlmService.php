<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        // Allow up to 300 seconds (5 minutes) for slow local models (Ollama, CPU inference, etc.)
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

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
     * Generate via Ollama native endpoint or OpenAI-compatible endpoint.
     */
    protected function generateWithOllama(string $baseUrl, string $model, string $prompt, string $systemPrompt): array
    {
        $url = $baseUrl . '/api/generate';
        $response = Http::timeout(300)->post($url, [
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

        $chatUrl = $baseUrl . '/v1/chat/completions';
        $chatResponse = Http::timeout(300)->post($chatUrl, [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
        ]);

        if ($chatResponse->successful()) {
            $data = $chatResponse->json();
            $text = trim($data['choices'][0]['message']['content'] ?? '');
            if (!empty($text)) {
                return [
                    'success' => true,
                    'text' => $text,
                    'provider' => 'ollama',
                    'model' => $model,
                ];
            }
        }

        throw new \Exception('Ollama sunucusundan geçerli yanıt alınamadı: HTTP ' . $response->status());
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
                    $res = Http::timeout(5)->get($baseUrl . '/api/tags');
                    if ($res->successful()) {
                        $tags = $res->json('models') ?? [];
                        $modelNames = array_map(fn($m) => $m['name'] ?? '', $tags);
                        return [
                            'success' => true,
                            'message' => 'Ollama bağlantısı başarılı! Kurulu model sayısı: ' . count($modelNames),
                            'models' => $modelNames,
                        ];
                    }
                    return [
                        'success' => false,
                        'message' => 'Ollama sunucusuna ulaşılamadı (HTTP ' . $res->status() . '). ' . $baseUrl . ' adresinin çalıştığından emin olun.',
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
}
