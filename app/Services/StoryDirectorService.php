<?php

namespace App\Services;

use App\Models\StoryProject;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class StoryDirectorService
{
    protected LlmService $llmService;
    protected PythonVoiceService $voiceService;
    protected string $pythonBin;
    protected string $enginePath;

    public function __construct(LlmService $llmService, PythonVoiceService $voiceService)
    {
        $this->llmService = $llmService;
        $this->voiceService = $voiceService;
        $this->pythonBin = config('services.python_voice.binary', env('PYTHON_BINARY', 'python'));
        $this->enginePath = base_path('engine');
    }

    /**
     * Generate structured Director Script using LLM.
     */
    public function generateScript(string $theme, array $options = []): array
    {
        $hasBgm = $options['has_bgm'] ?? true;
        $hasSfx = $options['has_sfx'] ?? true;
        $hasVisuals = $options['has_visuals'] ?? true;
        $hasProsody = $options['has_prosody'] ?? true;
        $audience = $options['audience'] ?? 'children_5_10';
        $language = $options['language'] ?? 'tr';

        $systemPrompt = <<<PROMPT
Sen ödüllü bir AI Ses Tiyatrosu, Animasyon ve Çocuk Masalı Yönetmenisin (Audio Drama & Story Director).
Görevin, verilen temaya uygun çok kanallı (Multi-Track) bir sesli hikaye senaryosu ve zaman çizelgesi (Timeline) üretmektir.

Çıktıyı YALNIZCA geçerli bir JSON nesnesi olarak ver. Yanıtında JSON dışında hiçbir selamlama, markdown açıklaması veya önsöz YAZMA.

JSON Şeması:
{
  "title": "Hikaye Başlığı",
  "theme": "Ana Tema",
  "target_audience": "{$audience}",
  "estimated_duration_sec": 60,
  "scenes": [
    {
      "scene_id": 1,
      "name": "Sahne 1 Başlığı",
      "voiceover": {
        "speaker": "narrator",
        "emotion": "calm / cheerful / dramatic / sad",
        "text": "Seslendirme metni. Vurgu için [whisper]fısıltı[/whisper], [shout]bağırma[/shout], [slow]yavaş[/slow], [cough]öksürük[/cough] etiketleri eklenebilir.",
        "cues": [
          { "word_anchor": "öksürdü", "sfx_id": "cough", "offset_ms": 100 }
        ]
      },
      "bgm": {
        "prompt": "MusicGen için İngilizce müzik promptu. Örn: soft acoustic guitar, peaceful children story music, 70 bpm",
        "volume": 0.22,
        "ducking": true
      },
      "sfx": [
        { "id": "cough", "sound_name": "Kuru Öksürük", "relative_to": "word:öksürdü", "volume": 0.85 }
      ],
      "visual_direction": {
        "scene_prompt": "Animasyon/Görsel için DALL-E/Midjourney tarzı İngilizce sahne betimlemesi",
        "action": "karakter eylemi",
        "camera": "close_up / wide / pan_right"
      }
    }
  ]
}

Kullanılabilecek Temel SFX Kimlikleri (sfx_id):
- cat_meow (Kedi miyavlaması)
- dog_bark (Köpek havlaması)
- cough (Öksürük sesi)
- birds_chirping (Kuş cıvıltıları)
- wind_whoosh (Rüzgar uğultusu)
- magic_bell (Sihirli zil çanı)
- footsteps (Ayak sesleri)
- door_creak (Kapı gıcırtısı)

Kurallar:
1. Hikayeyi 2 ila 3 sahneye böl.
2. Sahne 1: Sakin veya neşeli giriş müziği (soft, cheerful).
3. Sahne 2: Sorun / dramatikleşen müzik veya temponun değiştiği an (dramatic, melancholic).
4. Eğer hikaye sigaranın zararları hakkındaysa, duman veya öksürük anında 'cough' ve 'wind_whoosh' ses efektlerini kelime hizalı olarak yerleştir.
PROMPT;

        $userPrompt = "Lütfen şu tema için 2-3 sahneli eksiksiz bir sesli hikaye yönetmen senaryosu üret:\n\nTema: {$theme}\nHedef Kitle: {$audience}\nDil: {$language}";

        $llmSettings = $this->llmService->getSettings();
        $provider = $options['llm_provider'] ?? $llmSettings['llm_provider'];
        $model = $options['llm_model'] ?? $llmSettings['llm_model'];
        $apiKey = $options['llm_api_key'] ?? null;
        $baseUrl = $options['llm_base_url'] ?? null;
        $allowSimulation = (bool) ($options['allow_simulation'] ?? false);

        try {
            $response = $this->llmService->generate(
                prompt: $userPrompt,
                systemPrompt: $systemPrompt,
                model: $model,
                provider: $provider,
                apiKey: $apiKey,
                baseUrl: $baseUrl,
            );
        } catch (\Throwable $e) {
            $errDetail = $e->getMessage();
            Log::warning("Story Director LLM generation failed", [
                'provider' => $provider,
                'model'    => $model,
                'error'    => $errDetail,
            ]);

            if (!$allowSimulation) {
                $friendlyProvider = match ($provider) {
                    'ollama'             => 'Ollama (Yerel LLM)',
                    'gemini'             => 'Google Gemini API',
                    'openai'             => 'OpenAI API',
                    'claude', 'anthropic'=> 'Anthropic Claude API',
                    'groq'               => 'Groq Cloud API',
                    'deepseek'           => 'DeepSeek API',
                    'openrouter'         => 'OpenRouter API',
                    default              => strtoupper($provider)
                };
                throw new \RuntimeException("Yapay Zeka ({$friendlyProvider} - {$model}) Hatası: {$errDetail}");
            }

            return $this->getSmartFallbackScript($theme, $audience);
        }

        // Legacy warning-based check (kept for safety, should not trigger anymore)
        if (isset($response['warning']) || ($response['success'] ?? true) === false || isset($response['error'])) {
            $errDetail = $response['warning'] ?? $response['error'] ?? 'Yapay Zeka (LLM) servisine ulaşılamadı.';
            Log::warning("Story Director LLM generation failed", [
                'provider' => $provider,
                'model'    => $model,
                'error'    => $errDetail,
            ]);

            if (!$allowSimulation) {
                $friendlyProvider = match ($provider) {
                    'ollama'             => 'Ollama (Yerel LLM)',
                    'gemini'             => 'Google Gemini API',
                    'openai'             => 'OpenAI API',
                    'claude', 'anthropic'=> 'Anthropic Claude API',
                    'groq'               => 'Groq Cloud API',
                    'deepseek'           => 'DeepSeek API',
                    'openrouter'         => 'OpenRouter API',
                    default              => strtoupper($provider)
                };
                throw new \RuntimeException("Yapay Zeka ({$friendlyProvider} - {$model}) Hatası: {$errDetail}");
            }

            return $this->getSmartFallbackScript($theme, $audience);
        }

        $text = $response['text'] ?? '';
        $parsed = $this->extractJson($text);

        if (!$parsed || !isset($parsed['scenes']) || !is_array($parsed['scenes'])) {
            Log::warning("LLM response did not contain valid story JSON", ['raw' => $text, 'provider' => $provider]);
            if (!$allowSimulation) {
                throw new \RuntimeException(
                    "Seçilen LLM modeli ({$provider}) geçerli bir senaryo JSON nesnesi üretemedi. " .
                    "Gelen yanıt metni: \"" . mb_substr(trim($text), 0, 140) . "...\" " .
                    "Lütfen istemi sadeleştirin veya daha yetkin bir model (örn. Gemini 2.0 Flash / GPT-4o-mini) seçin."
                );
            }

            return $this->getSmartFallbackScript($theme, $audience);
        }

        return $parsed;
    }

    /**
     * Robustly extract and parse JSON from LLM text output.
     */
    protected function extractJson(string $text): ?array
    {
        $text = trim($text);

        // Strip markdown ```json ... ``` blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Try finding outermost { ... }
        $firstBrace = strpos($text, '{');
        $lastBrace = strrpos($text, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonCandidate = substr($text, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($jsonCandidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Provide a smart dynamic fallback script adapting to the user's theme if simulation is enabled.
     */
    public function getSmartFallbackScript(string $theme, string $audience = 'children_5_10'): array
    {
        $cleanTheme = trim($theme);

        // Strip meta instruction phrases ("...anlatan doğa masalı", "konulu hikaye" vb.)
        $cleanSubject = preg_replace('/(\.?\s*(anlatan|konu alan|hakkında|için|eğitici bir|kurgulanan|öğreten|içeren)\s*(masal|hikaye|öykü|anlatı|radyo tiyatrosu|metin).*$)/iu', '', $cleanTheme);
        $cleanSubject = trim(preg_replace('/^(bize|lütfen)?\s*(bir\s+)?/iu', '', $cleanSubject));
        if (empty($cleanSubject)) {
            $cleanSubject = 'masalsı orman';
        }

        $title = mb_substr($cleanSubject, 0, 45);
        if (mb_strlen($cleanSubject) > 45) {
            $title .= '...';
        }

        $lower = mb_strtolower($cleanTheme, 'UTF-8');
        $hasBirds = str_contains($lower, 'kuş') || str_contains($lower, 'bahar') || str_contains($lower, 'orman') || str_contains($lower, 'ağaç') || str_contains($lower, 'doğa') || str_contains($lower, 'kuraklık');
        $hasCat = str_contains($lower, 'kedi') || str_contains($lower, 'pati') || str_contains($lower, 'yavru');
        $hasDog = str_contains($lower, 'köpek') || str_contains($lower, 'karabaş') || str_contains($lower, 'sadık');
        $hasCough = str_contains($lower, 'sigara') || str_contains($lower, 'duman') || str_contains($lower, 'öksürük') || str_contains($lower, 'akciğer');
        $hasSpace = str_contains($lower, 'mars') || str_contains($lower, 'uzay') || str_contains($lower, 'astronot') || str_contains($lower, 'gezegen');

        if ($hasBirds) {
            $scene1Title = '1. Bölüm: Masalsı Ormanda Bahar Uyanışı';
            $scene1Text = 'Güneş masalsı ormanın üzerine doğarken, yemyeşil dallar neşeli kuş cıvıltılarıyla canlandı. [slow]Güzel ve aydınlık bir sabah vaktiydi[/slow], fakat yaklaşan kuraklık tehlikesi ormanın kalbindeki bilge meşe ağacını derin düşüncelere sevk etmişti.';
            $sfx1 = ['id' => 'birds_chirping', 'sound_name' => 'Kuş Cıvıltıları', 'relative_to' => 'word:sabah', 'volume' => 0.6];
            $visual1 = 'A beautiful children storybook illustration of a vibrant magical forest at sunrise with cheerful singing birds on blooming branches and a wise ancient oak tree, soft warm lighting, watercolor pastel style, 8k --ar 16:9';

            $scene2Title = '2. Bölüm: Bilge Meşe Ağacı ve Büyük Dayanışma';
            $scene2Text = 'Bilge meşe ağacının çağrısıyla ormandaki tüm sevimli hayvanlar nehir kenarında bir araya toplandı. [whisper]Birlikten kuvvet doğar[/whisper] diyerek el ele verdiler, pınarları temizleyip kuraklığı yendiler ve doğayı yeniden neşeyle yeşerttiler.';
            $sfx2 = ['id' => 'wind_whoosh', 'sound_name' => 'Ilık Bahar Rüzgarı', 'relative_to' => 'word:kuvvet', 'volume' => 0.5];
            $visual2 = 'Heartwarming children storybook scene showing forest animals celebrating together around the wise oak tree with a crystal clear flowing river, lush green nature, golden hour light, storybook art, --ar 16:9';
        } elseif ($hasCat) {
            $scene1Title = '1. Bölüm: Yağmurlu Akşam ve Tavan Arası';
            $scene1Text = 'Yağmurlu ve serin bir sonbahar akşamı, minik yavru kedi Pati eski ahşap evin bahçesinde yapayalnız kalmıştı. [slow]Hafif bir rüzgar eserken[/slow], küçük sevimli pati sesleriyle tavan arasına doğru sığındı.';
            $sfx1 = ['id' => 'cat_meow', 'sound_name' => 'Kedi Miyavlaması', 'relative_to' => 'word:rüzgar', 'volume' => 0.85];
            $visual1 = 'A gentle storybook illustration of a cute lost kitten in a cozy old wooden house attic on a rainy autumn evening, warm lantern light, storybook art, --ar 16:9';

            $scene2Title = '2. Bölüm: Zeynep\'in Şefkati ve Sıcak Yuva';
            $scene2Text = 'Zeynep elindeki lambayla tavan arasını aydınlattı ve titreyen küçük dostunu sıcacık kucağına aldı. [whisper]Artık güvendesin minik pati[/whisper] diyerek onu sobanın yanındaki sıcacık yuvaya taşıdı.';
            $sfx2 = ['id' => 'footsteps', 'sound_name' => 'Küçük Adımlar', 'relative_to' => 'word:pati', 'volume' => 0.6];
            $visual2 = 'A heartwarming storybook illustration of a young girl lovingly hugging a happy kitten by a warm fireplace in a wooden house, cozy atmosphere, --ar 16:9';
        } elseif ($hasDog) {
            $scene1Title = '1. Bölüm: Neşeli Can ve Sadık Karabaş';
            $scene1Text = 'Güneşli bir günde küçük Can ve sadık köpeği Karabaş parkın yeşil çimlerinde koşup oynuyordu. [slow]Tatlı bir sabah rüzgarı eserken[/slow], patika boyunca yepyeni bir maceranın peşine düştüler.';
            $sfx1 = ['id' => 'dog_bark', 'sound_name' => 'Köpek Havlaması', 'relative_to' => 'word:sabah', 'volume' => 0.85];
            $visual1 = 'Cheerful children storybook illustration of a playful young boy and his happy dog running through a sunny green park, vibrant colors, storybook art, --ar 16:9';

            $scene2Title = '2. Bölüm: Birlikte Aşabileceğimiz Zorluklar';
            $scene2Text = 'Karabaş koklayarak kaybolan topu buldu ve sahibine getirdi. [whisper]Sen harika bir dostsun Karabaş[/whisper] diyerek sarıldılar ve sevinçle evin yolunu tuttular.';
            $sfx2 = ['id' => 'magic_bell', 'sound_name' => 'Sihirli Zil', 'relative_to' => 'word:dostsun', 'volume' => 0.6];
            $visual2 = 'Joyful children storybook illustration of a happy boy hugging his loyal dog under a rainbow in a park, soft warm lighting, --ar 16:9';
        } elseif ($hasSpace) {
            $scene1Title = '1. Bölüm: Mars Üssü ve Gizemli Yankı';
            $scene1Text = '2085 yılında, kızıl gezegen Mars\'taki araştırma istasyonunda derin bir sessizlik hakimdi. [slow]Dışarıda fırtına uğuldarken[/slow], kontrol panelinde birdenbire gizemli ve ritmik bir sinyal yankılanmaya başladı.';
            $sfx1 = ['id' => 'magic_bell', 'sound_name' => 'Gizemli Sinyal Sesi', 'relative_to' => 'word:sinyal', 'volume' => 0.8];
            $visual1 = 'Cinematic sci-fi storybook illustration of a high-tech research station on the red Martian surface during a dust storm, glowing control consoles, --ar 16:9';

            $scene2Title = '2. Bölüm: Genç Astronotun Keşfi';
            $scene2Text = 'Genç astronot Deniz şifreli sinyalin koordinatlarını çözerek istasyon kapısını temkinle açtı. [whisper]Kızıl gezegende yalnız değiliz[/whisper] diyerek heyecanla merkeze ilk tarihi raporunu iletti.';
            $sfx2 = ['id' => 'wind_whoosh', 'sound_name' => 'Gezegen Fırtınası', 'relative_to' => 'word:yalnız', 'volume' => 0.7];
            $visual2 = 'Exciting sci-fi storybook scene of an astronaut in space suit making a momentous discovery outside a Mars dome, star-filled cosmic sky, --ar 16:9';
        } elseif ($hasCough) {
            $scene1Title = '1. Bölüm: Parktaki Gri Duman Bulutu';
            $scene1Text = 'Küçük Can ve sevimli köpeği Karabaş parkın yeşil çimlerinde neşeyle koşup oynarken, bankta oturan birinin üflediği yoğun gri duman gökyüzünü kapladı. [slow]Temiz havayı solumak isterken[/slow], zehirli dumanın akciğerlere ne kadar zarar verdiğini hemen fark ettiler.';
            $sfx1 = ['id' => 'cough', 'sound_name' => 'Öksürük Efekti', 'relative_to' => 'word:dumanın', 'volume' => 0.9];
            $visual1 = 'Educational storybook illustration showing a clean sunny park suddenly clouded by grey cigarette smoke, children reacting thoughtfully, watercolor style, --ar 16:9';

            $scene2Title = '2. Bölüm: Temiz Nefes ve Dumansız Park';
            $scene2Text = 'Can cesaretle adım atarak parktaki herkesi temiz hava için uyardı ve dumansız bir dünya çağrısı yaptı. [whisper]Sağlıklı nefes en büyük zenginliktir[/whisper] diyerek parkı yeniden mis gibi çiçek kokularıyla doldurdular.';
            $sfx2 = ['id' => 'wind_whoosh', 'sound_name' => 'Ferahlatıcı Temiz Rüzgar', 'relative_to' => 'word:nefes', 'volume' => 0.6];
            $visual2 = 'Uplifting children storybook scene with smiling children enjoying fresh air in a blooming green park under a bright blue sky, cheerful atmosphere, --ar 16:9';
        } else {
            $scene1Title = '1. Bölüm: Başlangıç ve Merak Dolu Keşif';
            $scene1Text = 'Uzak diyarlarda, ' . mb_lcfirst($cleanSubject) . ' için yepyeni ve heyecan verici bir serüven filizleniyordu. [slow]Güzel ve aydınlık bir sabah vaktiydi[/slow], kahramanlarımız merak dolu adımlarla yola koyuldular.';
            $sfx1 = ['id' => 'birds_chirping', 'sound_name' => 'Doğa Ambiyansı', 'relative_to' => 'word:sabah', 'volume' => 0.5];
            $visual1 = 'Storybook art depicting the beginning of an adventure for: ' . $cleanSubject . ', soft warm lighting, watercolor style, --ar 16:9';

            $scene2Title = '2. Bölüm: Birlik ve Mutlu Son';
            $scene2Text = 'Karşılarına çıkan tüm engelleri büyük bir dayanışma ve cesaretle aştılar. [whisper]Birlikten ve sevgiden kuvvet doğar[/whisper] diyerek zaferlerini neşeyle kutladılar.';
            $sfx2 = ['id' => 'magic_bell', 'sound_name' => 'Sihirli Çan', 'relative_to' => 'word:kuvvet', 'volume' => 0.6];
            $visual2 = 'Happy conclusion storybook scene celebrating unity and triumph for ' . $cleanSubject . ', golden hour light, storybook art, --ar 16:9';
        }

        return [
            'title' => $title,
            'theme' => $theme,
            'target_audience' => $audience,
            'estimated_duration_sec' => 45,
            'is_simulation' => true,
            'simulation_notice' => 'Bu senaryo çevrimdışı simülasyon motoru ile oluşturuldu.',
            'scenes' => [
                [
                    'scene_id' => 1,
                    'name' => $scene1Title,
                    'voiceover' => [
                        'speaker' => 'narrator',
                        'emotion' => 'cheerful',
                        'text' => $scene1Text,
                        'cues' => [
                            ['word_anchor' => str_replace('word:', '', $sfx1['relative_to']), 'sfx_id' => $sfx1['id'], 'offset_ms' => 150]
                        ]
                    ],
                    'bgm' => [
                        'prompt' => 'gentle cheerful acoustic guitar, peaceful story music, uplifting, 75 bpm',
                        'volume' => 0.22,
                        'ducking' => true
                    ],
                    'sfx' => [$sfx1],
                    'visual_direction' => [
                        'scene_prompt' => $visual1,
                        'action' => 'gentle_morning_atmosphere',
                        'camera' => 'pan_right'
                    ]
                ],
                [
                    'scene_id' => 2,
                    'name' => $scene2Title,
                    'voiceover' => [
                        'speaker' => 'narrator',
                        'emotion' => 'dramatic',
                        'text' => $scene2Text,
                        'cues' => [
                            ['word_anchor' => str_replace('word:', '', $sfx2['relative_to']), 'sfx_id' => $sfx2['id'], 'offset_ms' => 100]
                        ]
                    ],
                    'bgm' => [
                        'prompt' => 'warm heartwarming orchestral strings, happy conclusion, inspirational, 80 bpm',
                        'volume' => 0.24,
                        'ducking' => true
                    ],
                    'sfx' => [$sfx2],
                    'visual_direction' => [
                        'scene_prompt' => $visual2,
                        'action' => 'joyful_celebration',
                        'camera' => 'close_up'
                    ]
                ]
            ]
        ];
    }

    /**
     * Clean prosody shortcode tags like [whisper]text[/whisper] or [cough] for clean TTS speech.
     */
    public static function cleanShortcodes(string $text): string
    {
        // Replace action tags like [cough], [sigh] with empty space
        $clean = preg_replace('/\[(cough|laugh|sigh|gasp|sneeze)\]/i', '', $text);
        // Replace wrapping tags like [whisper]text[/whisper] with text
        $clean = preg_replace('/\[(whisper|shout|slow|fast|speed:[\d.]+)\](.*?)\[\/\1\]/i', '$2', $clean);
        // Strip any residual brackets
        $clean = preg_replace('/\[.*?\]/', '', $clean);
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Align words in an audio file using Faster-Whisper.
     */
    public function alignWords(string $audioPath, string $language = 'tr', ?string $modelSize = null): array
    {
        if (!file_exists($audioPath)) {
            return [];
        }

        $cmd = [
            $this->pythonBin,
            '-m', 'app.cli',
            'story-align',
            '--audio', $audioPath,
            '--language', $language,
        ];

        if ($modelSize) {
            $cmd[] = '--model-size';
            $cmd[] = $modelSize;
        }

        try {
            $result = Process::path($this->enginePath)
                ->timeout(60)
                ->run($cmd);

            if ($result->successful()) {
                $output = json_decode($result->output(), true);
                return $output['words'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning("Whisper word alignment failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Mix voice, BGM, and SFX tracks into a unified master audio file.
     */
    public function mixTracks(
        string $voicePath,
        ?string $bgmPath,
        array $sfxList,
        ?string $outputPath,
        bool $ducking = true,
        float $bgmVolume = 0.25,
        ?string $stemsDir = null
    ): array {
        $cmd = [
            $this->pythonBin,
            '-m', 'app.cli',
            'story-mix',
            '--voice', $voicePath,
            '--bgm-volume', (string) $bgmVolume,
        ];

        if ($bgmPath && file_exists($bgmPath)) {
            $cmd[] = '--bgm';
            $cmd[] = $bgmPath;
        }

        if (!empty($sfxList)) {
            $tmpSfxJson = storage_path('app/temp_sfx_' . Str::random(8) . '.json');
            File::put($tmpSfxJson, json_encode($sfxList));
            $cmd[] = '--sfx-json';
            $cmd[] = $tmpSfxJson;
        } else {
            $tmpSfxJson = null;
        }

        if ($outputPath) {
            $cmd[] = '--output';
            $cmd[] = $outputPath;
        }

        if ($stemsDir) {
            $cmd[] = '--stems-dir';
            $cmd[] = $stemsDir;
        }

        if (!$ducking) {
            $cmd[] = '--no-ducking';
        }

        try {
            $result = Process::path($this->enginePath)
                ->timeout(120)
                ->run($cmd);

            if ($tmpSfxJson && file_exists($tmpSfxJson)) {
                @unlink($tmpSfxJson);
            }

            if ($result->successful()) {
                return json_decode($result->output(), true) ?? ['success' => true];
            } else {
                throw new \RuntimeException("Audio mixing failed: " . $result->errorOutput());
            }
        } catch (\Throwable $e) {
            if (isset($tmpSfxJson) && file_exists($tmpSfxJson)) {
                @unlink($tmpSfxJson);
            }
            throw $e;
        }
    }

    /**
     * Get list of available pre-curated SFX files.
     */
    public function getAvailableSfx(): array
    {
        $sfxDir = base_path('data/sfx');
        if (!is_dir($sfxDir)) {
            return [];
        }

        $metadataFile = base_path('data/sfx/metadata.json');
        $meta = file_exists($metadataFile) ? (json_decode(file_get_contents($metadataFile), true) ?: []) : [];

        $list = [];
        $files = File::files($sfxDir);
        foreach ($files as $f) {
            if ($f->getExtension() === 'wav') {
                $id = $f->getFilenameWithoutExtension();
                $name = $meta[$f->getFilename()]['title'] ?? match ($id) {
                    'cat_meow' => 'Kedi Miyavlaması',
                    'dog_bark' => 'Köpek Havlaması',
                    'cough' => 'Öksürük Sesi',
                    'birds_chirping' => 'Kuş Cıvıltıları',
                    'wind_whoosh' => 'Rüzgar Uğultusu',
                    'magic_bell' => 'Sihirli Zil',
                    'footsteps' => 'Ayak Sesleri',
                    'door_creak' => 'Kapı Gıcırtısı',
                    default => ucfirst(str_replace('_', ' ', $id)),
                };
                $list[] = [
                    'id' => $id,
                    'name' => $name,
                    'filename' => $f->getFilename(),
                    'file' => $f->getRealPath(),
                    'url' => '/api/audio/' . $f->getFilename(),
                    'audio_url' => '/api/audio/' . $f->getFilename(),
                ];
            }
        }

        return $list;
    }

    /**
     * Get list of available background music (BGM) tracks.
     */
    public function getAvailableBgm(): array
    {
        $bgmDir = base_path('data/bgm');
        if (!is_dir($bgmDir)) {
            return [];
        }

        $metadataFile = base_path('data/bgm/metadata.json');
        $meta = file_exists($metadataFile) ? (json_decode(file_get_contents($metadataFile), true) ?: []) : [];

        $list = [];
        $files = File::files($bgmDir);
        foreach ($files as $f) {
            if ($f->getExtension() === 'wav') {
                $id = $f->getFilenameWithoutExtension();
                $name = $meta[$f->getFilename()]['title'] ?? match ($id) {
                    'peaceful_ambient' => 'Huzurlu Orman & Masal Ambiyansı',
                    'dramatic_ambient' => 'Dramatik Hüzün & Kuraklık Teması',
                    'uplifting_ambient' => 'Bilgelik & Umut Dolu Uyanış',
                    default => ucfirst(str_replace('_', ' ', $id)),
                };
                $list[] = [
                    'id' => $id,
                    'name' => $name,
                    'filename' => $f->getFilename(),
                    'file' => $f->getRealPath(),
                    'url' => '/api/music/audio/' . $f->getFilename(),
                    'audio_url' => '/api/music/audio/' . $f->getFilename(),
                ];
            }
        }

        return $list;
    }

    /**
     * Orchestrate full multi-track production for a StoryProject.
     */
    public function produceProjectAudio(StoryProject $project): StoryProject
    {
        $project->update([
            'status' => 'processing',
            'progress_step' => 'Prodüksiyon başlatılıyor...',
            'error_message' => null,
        ]);

        $script = $project->script_data;
        if (empty($script['scenes']) || !is_array($script['scenes'])) {
            $project->update([
                'status' => 'failed',
                'error_message' => 'Senaryo sahneleri bulunamadı.',
                'progress_step' => 'Hata: Senaryo sahneleri bulunamadı.',
            ]);
            return $project;
        }

        $options = $project->options ?? [];
        $hasVoice = $options['has_voice'] ?? true;
        $ttsEngine = $options['tts_engine'] ?? 'freya-adam';
        $language = $options['language'] ?? 'tr';
        $hasBgm = $options['has_bgm'] ?? true;
        $bgmMode = $options['bgm_mode'] ?? 'musicgen-medium'; // 'ambient' or 'musicgen-small/medium/melody/large'
        $hasSfx = $options['has_sfx'] ?? true;
        $sfxModel = $options['sfx_model'] ?? 'whisper-medium';
        $ducking = $options['ducking'] ?? true;

        $projectStorageDir = storage_path("app/public/stories/{$project->id}");
        File::makeDirectory($projectStorageDir, 0755, true, true);

        $sceneMixes = [];
        $allStems = ['scenes' => []];
        $totalScenes = count($script['scenes']);

        try {
            foreach ($script['scenes'] as $idx => $scene) {
                $sceneId = $scene['scene_id'] ?? ($idx + 1);
                $rawText = $scene['voiceover']['text'] ?? '';
                $cleanText = self::cleanShortcodes($rawText);

                if (empty($cleanText)) {
                    continue;
                }

                // 1. Generate Voiceover via TTS (if enabled)
                $voiceFile = "{$projectStorageDir}/scene_{$sceneId}_voice.wav";
                if ($hasVoice) {
                    $project->update([
                        'progress_step' => "Sahne {$sceneId}/{$totalScenes}: Vokal ({$ttsEngine}) seslendiriliyor...",
                    ]);

                    $this->voiceService->generateTts(
                        text: $cleanText,
                        engine: $ttsEngine,
                        language: $language,
                        outputPath: $voiceFile
                    );

                    if (!file_exists($voiceFile)) {
                        throw new \RuntimeException("Sahne {$sceneId} seslendirme dosyası üretilemedi.");
                    }
                } else {
                    // Fallback short ambient/silent placeholder for mix
                    $fallbackSilence = base_path('data/sfx/wind_whoosh.wav');
                    if (file_exists($fallbackSilence)) {
                        @copy($fallbackSilence, $voiceFile);
                    }
                }

                // 2. Align words with Whisper for precise SFX anchoring (if SFX enabled)
                $wordTimestamps = [];
                if ($hasSfx && file_exists($voiceFile)) {
                    $project->update([
                        'progress_step' => "Sahne {$sceneId}/{$totalScenes}: SFX kelime zamanlamaları hizalanıyor ({$sfxModel})...",
                    ]);

                    $whisperSize = str_replace('whisper-', '', $sfxModel);
                    $wordTimestamps = $this->alignWords($voiceFile, $language, $whisperSize);
                }

                // 3. Resolve SFX placements
                $sfxList = [];
                if ($hasSfx && !empty($scene['sfx'])) {
                    foreach ($scene['sfx'] as $sfxItem) {
                        $sfxId = $sfxItem['id'] ?? '';
                        $sfxFile = base_path("data/sfx/{$sfxId}.wav");

                        if (!file_exists($sfxFile)) {
                            continue;
                        }

                        $timeMs = 0;
                        $relativeTo = $sfxItem['relative_to'] ?? '';
                        if (str_starts_with($relativeTo, 'word:')) {
                            $targetWord = strtolower(trim(substr($relativeTo, 5), ' .,!?":;'));
                            // Find matching word timestamp
                            foreach ($wordTimestamps as $w) {
                                if (str_contains($w['word'], $targetWord) || str_contains($targetWord, $w['word'])) {
                                    $timeMs = $w['end_ms'] ?? ($w['start_ms'] ?? 0);
                                    break;
                                }
                            }
                        } elseif (isset($sfxItem['start_ms'])) {
                            $timeMs = (float) $sfxItem['start_ms'];
                        }

                        $sfxList[] = [
                            'path' => $sfxFile,
                            'time_ms' => $timeMs,
                            'volume' => (float) ($sfxItem['volume'] ?? 0.8),
                        ];
                    }
                }

                // 4. Generate BGM with MusicGen or Instant Ambient
                $bgmFile = null;
                if ($hasBgm) {
                    if ($bgmMode === 'ambient') {
                        // Instant Ambient mode (0s delay)
                        $project->update([
                            'progress_step' => "Sahne {$sceneId}/{$totalScenes}: Hızlı ambiyans fonu hazırlanıyor...",
                        ]);

                        // Select context-appropriate ambient musical pad
                        $sceneMood = mb_strtolower(($scene['voiceover']['emotion'] ?? '') . ' ' . ($scene['bgm']['prompt'] ?? '') . ' ' . ($scene['name'] ?? ''), 'UTF-8');
                        if (str_contains($sceneMood, 'dramatic') || str_contains($sceneMood, 'melanchol') || str_contains($sceneMood, 'hüzün') || str_contains($sceneMood, 'kuraklık') || str_contains($sceneMood, 'endişe')) {
                            $ambientPath = base_path('data/bgm/dramatic_ambient.wav');
                        } elseif (str_contains($sceneMood, 'uplifting') || str_contains($sceneMood, 'magic') || str_contains($sceneMood, 'hope') || str_contains($sceneMood, 'umut') || str_contains($sceneMood, 'bilge') || str_contains($sceneMood, 'sihir')) {
                            $ambientPath = base_path('data/bgm/uplifting_ambient.wav');
                        } else {
                            $ambientPath = base_path('data/bgm/peaceful_ambient.wav');
                        }

                        if (file_exists($ambientPath)) {
                            $bgmFile = $ambientPath;
                        }
                    } elseif (!empty($scene['bgm']['prompt'])) {
                        // AI Generation via dynamic MusicGen engine
                        $musicEngine = str_starts_with($bgmMode, 'musicgen') ? $bgmMode : 'musicgen-medium';
                        $project->update([
                            'progress_step' => "Sahne {$sceneId}/{$totalScenes}: {$musicEngine} ile fon müziği besteleniyor...",
                        ]);
                        $bgmPrompt = $scene['bgm']['prompt'];
                        $bgmFileCandidate = "{$projectStorageDir}/scene_{$sceneId}_bgm.wav";

                        try {
                            $this->voiceService->generateTts(
                                text: $bgmPrompt,
                                engine: $musicEngine,
                                language: 'en',
                                outputPath: $bgmFileCandidate
                            );
                            if (file_exists($bgmFileCandidate) && filesize($bgmFileCandidate) > 1000) {
                                $bgmFile = $bgmFileCandidate;
                            }
                        } catch (\Throwable $bgmEx) {
                            Log::warning("MusicGen generation skipped for scene {$sceneId}: " . $bgmEx->getMessage());
                            $fallbackBgm = base_path('data/bgm/peaceful_ambient.wav');
                            if (file_exists($fallbackBgm)) {
                                $bgmFile = $fallbackBgm;
                            }
                        }
                    }
                }

                // 5. Mix Scene Audio
                $project->update([
                    'progress_step' => "Sahne {$sceneId}/{$totalScenes}: Çok kanallı ducking miksajı yapılıyor...",
                ]);

                $sceneOutputFile = "{$projectStorageDir}/scene_{$sceneId}_mix.wav";
                $sceneStemsDir = "{$projectStorageDir}/scene_{$sceneId}_stems";

                $mixResult = $this->mixTracks(
                    voicePath: $voiceFile,
                    bgmPath: $bgmFile,
                    sfxList: $sfxList,
                    outputPath: $sceneOutputFile,
                    ducking: $ducking,
                    bgmVolume: (float) ($scene['bgm']['volume'] ?? 0.22),
                    stemsDir: $sceneStemsDir
                );

                if (file_exists($sceneOutputFile)) {
                    $sceneMixes[] = $sceneOutputFile;
                    $allStems['scenes'][] = [
                        'scene_id' => $sceneId,
                        'mix' => url("/api/story/audio/{$project->id}/scene_{$sceneId}_mix"),
                        'voice' => url("/api/story/audio/{$project->id}/scene_{$sceneId}_voice"),
                        'bgm' => $bgmFile ? url("/api/story/audio/{$project->id}/scene_{$sceneId}_bgm") : null,
                        'sfx' => url("/api/story/audio/{$project->id}/stem_sfx"),
                    ];
                }
            }

            if (empty($sceneMixes)) {
                throw new \RuntimeException("Hiçbir sahne mikslenemedi.");
            }

            // 6. Concatenate Scene Mixes into Final Master Audio
            $project->update([
                'progress_step' => 'Master ses dosyası birleştiriliyor...',
            ]);

            $finalMasterFile = storage_path("app/public/stories/{$project->id}/master.wav");
            if (count($sceneMixes) === 1) {
                File::copy($sceneMixes[0], $finalMasterFile);
            } else {
                // Mix / concat scenes in Python
                $concatCmd = [
                    $this->pythonBin,
                    '-c',
                    "
import soundfile as sf
import numpy as np
files = " . json_encode($sceneMixes) . "
audios = [sf.read(f, dtype='float32')[0] for f in files]
full = np.concatenate(audios)
sf.write(r'{$finalMasterFile}', full, 24000, subtype='PCM_16')
print('Concat finished')
                    "
                ];
                Process::run($concatCmd);
            }

            $duration = 0.0;
            if (file_exists($finalMasterFile)) {
                $size = filesize($finalMasterFile);
                $duration = round(($size - 44) / (24000 * 2), 2); // 16-bit mono 24kHz = 48000 bytes/sec
            }

            $project->update([
                'status' => 'completed',
                'progress_step' => 'Tamamlandı',
                'master_audio_path' => $finalMasterFile,
                'stems' => $allStems,
                'duration_sec' => $duration,
            ]);

            return $project->fresh();

        } catch (\Throwable $e) {
            Log::error("Story production failed for project {$project->id}: " . $e->getMessage());
            $project->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'progress_step' => 'Hata: ' . $e->getMessage(),
            ]);
            return $project->fresh();
        }
    }
}
