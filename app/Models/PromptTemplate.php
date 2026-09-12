<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    protected $fillable = [
        'title',
        'category',
        'content',
        'description',
        'system_prompt',
        'variables_schema',
        'is_favorite',
    ];

    protected $casts = [
        'variables_schema' => 'array',
        'is_favorite' => 'boolean',
    ];

    /**
     * Extract unique variable placeholders ($1, $2, $variable_name) from template content.
     *
     * @return array<string>
     */
    public function extractVariables(): array
    {
        if (empty($this->content)) {
            return [];
        }

        preg_match_all('/\$([a-zA-Z0-9_]+)/', $this->content, $matches);
        if (empty($matches[0])) {
            return [];
        }

        // Return distinct variables preserving natural occurrence order
        $seen = [];
        $result = [];
        foreach ($matches[0] as $var) {
            if (!isset($seen[$var])) {
                $seen[$var] = true;
                $result[] = $var;
            }
        }

        return $result;
    }

    /**
     * Render the template by replacing placeholders with supplied values.
     *
     * @param array $values Key-value map like ['$1' => 'değer'] or ['1' => 'değer']
     * @return string
     */
    public function renderPrompt(array $values = []): string
    {
        $rendered = $this->content;

        // Resolve schema defaults if defined
        $schema = is_array($this->variables_schema) ? $this->variables_schema : [];
        $schemaDefaults = [];
        foreach ($schema as $s) {
            if (isset($s['key']) && !empty($s['default'])) {
                $schemaDefaults[$s['key']] = (string) $s['default'];
            }
        }

        $allVars = $this->extractVariables();
        foreach ($allVars as $varKey) {
            $rawKeyWithoutDollar = ltrim($varKey, '$');
            $val = $values[$varKey] ?? ($values[$rawKeyWithoutDollar] ?? ($schemaDefaults[$varKey] ?? ''));
            $valStr = is_scalar($val) ? trim((string) $val) : '';
            if ($valStr === '' && isset($schemaDefaults[$varKey])) {
                $valStr = $schemaDefaults[$varKey];
            }
            $rendered = str_replace($varKey, $valStr, $rendered);
        }

        return $rendered;
    }

    /**
     * Seed default templates if the table is empty.
     */
    public static function seedDefaultsIfEmpty(): void
    {
        if (static::count() > 0) {
            return;
        }

        $defaults = [
            [
                'title' => 'Çocuk Hikayesi',
                'category' => 'Hikaye',
                'description' => '5-12 yaş arası çocuklar için eğitici ve sürükleyici mini hikaye.',
                'content' => '$1 yaş grubu çocuklar için mini bir hikaye yaz. Hikaye $2 hakkında olsun ve ana karakterler $3 olsun. Sıcak, merak uyandırıcı, paylaşımcı ve eğitici bir dille anlat. Yaklaşık 120-160 kelime olsun.',
                'system_prompt' => 'Sen çocuk edebiyatında uzman, sıcak ve akıcı Türkçe hikayeler yazan bir çocuk masal yazarısın. Metinleri sesli okunmaya uygun bir ritim ve akıcılıkla hazırla.',
                'variables_schema' => [
                    ['key' => '$1', 'label' => 'Yaş Grubu', 'default' => '5-12'],
                    ['key' => '$2', 'label' => 'Hikaye Konusu', 'default' => 'paylaşımcı olmak ve arkadaşlık'],
                    ['key' => '$3', 'label' => 'Karakterler', 'default' => 'tavşan ile karga'],
                ],
                'is_favorite' => true,
            ],
            [
                'title' => 'Radyo & Haber Bülteni',
                'category' => 'Haber',
                'description' => 'Spiker tonunda profesyonel haber ve bülten seslendirme metni.',
                'content' => 'Aşağıdaki konu hakkında profesyonel bir haber spikeri anonsu ve kısa bülten yaz: $1. Sunum tonu $2 olsun. Giriş ve kapanış selamlaması içersin, yaklaşık 80-100 kelime.',
                'system_prompt' => 'Sen deneyimli bir radyo ve televizyon haber spikerisin. Cümleleri net, vurgulu ve seslendirme için akıcı Türkçe ile oluştur.',
                'variables_schema' => [
                    ['key' => '$1', 'label' => 'Haber Başlığı / Olay', 'default' => 'Yapay zeka ses teknolojilerinde yeni yerel modeller yayınlandı'],
                    ['key' => '$2', 'label' => 'Sunum Tonu', 'default' => 'Dinamik ve tarafsız'],
                ],
                'is_favorite' => true,
            ],
            [
                'title' => 'Masal & Uyku Öncesi',
                'category' => 'Masal',
                'description' => 'Sakinleştirici, dinlendirici ve şiirsel bir masal anlatımı.',
                'content' => 'Bir varmış bir yokmuş diye başlayan, $1 diyarında geçen, kahramanı $2 olan büyülü ve dinlendirici bir masal anlat. Masalın ana teması $3 olsun. Cümleler sakin ve huzurlu bir tonla aksın.',
                'system_prompt' => 'Sen dinleyicileri huzurlu bir yolculuğa çıkaran sakin tonlu bir masal anlatıcısısın.',
                'variables_schema' => [
                    ['key' => '$1', 'label' => 'Masal Diyarı', 'default' => 'Işıltılı Bulutlar Ülkesi'],
                    ['key' => '$2', 'label' => 'Kahraman', 'default' => 'Uykucu Küçük Ayıcık'],
                    ['key' => '$3', 'label' => 'Ana Fikir', 'default' => 'Yıldızların dostluğu ve güven hissi'],
                ],
                'is_favorite' => false,
            ],
            [
                'title' => 'Ürün & Marka Reklamı',
                'category' => 'Pazarlama',
                'description' => '30 saniyelik etkileyici ve akılda kalıcı reklam jingle/anons metni.',
                'content' => '$1 için 30 saniyelik etkileyici bir seslendirme reklamı yaz. Hedef kitle $2 olsun. Öne çıkacak ana fayda: $3. Heyecan uyandıran net bir harekete geçirici mesaj (CTA) ile bitsin.',
                'system_prompt' => 'Sen yaratıcı bir reklam metni yazarı (copywriter) ve seslendirme yönetmenisin.',
                'variables_schema' => [
                    ['key' => '$1', 'label' => 'Ürün / Hizmet', 'default' => 'Voice Core AI Ses İstasyonu'],
                    ['key' => '$2', 'label' => 'Hedef Kitle', 'default' => 'Geliştiriciler ve dijital içerik üreticileri'],
                    ['key' => '$3', 'label' => 'Ana Fayda', 'default' => 'İnternet gerekmeden tamamen yerel ve sınırsız Türkçe ses üretimi'],
                ],
                'is_favorite' => false,
            ],
            [
                'title' => 'İki Kişilik Diyalog',
                'category' => 'Diyalog',
                'description' => 'İki kişi arasında geçen doğal, esprili veya bilgilendirici konuşma.',
                'content' => '$1 ile $2 arasında $3 üzerine geçen canlı ve doğal bir konuşma metni yaz. Karakter isimlerini başa yazarak diyalog formatında oluştur.',
                'system_prompt' => 'Sen doğal Türkçe konuşma kalıplarını ustalıkla kullanan bir diyalog ve senaryo yazarısın.',
                'variables_schema' => [
                    ['key' => '$1', 'label' => '1. Karakter', 'default' => 'Kerem (Meraklı öğrenci)'],
                    ['key' => '$2', 'label' => '2. Karakter', 'default' => 'Zeynep (Bilgisayar mühendisi)'],
                    ['key' => '$3', 'label' => 'Konu', 'default' => 'Ses modellerinin nasıl eğitildiği'],
                ],
                'is_favorite' => false,
            ],
        ];

        foreach ($defaults as $data) {
            static::create($data);
        }
    }
}
