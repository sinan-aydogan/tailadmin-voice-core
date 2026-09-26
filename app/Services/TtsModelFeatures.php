<?php

namespace App\Services;

class TtsModelFeatures
{
    /**
     * Get all supported TTS models with their discovered features, shortcodes, and prompt rules.
     */
    public function getAll(): array
    {
        return [
            // --- Freya Voice Models ---
            [
                'id' => 'freya-adam',
                'engine' => 'freya-adam',
                'name' => 'Freya Voice - Adam (Bulut API Erkek)',
                'provider' => 'freya',
                'category' => 'Freya Voice',
                'badge' => 'AudioRealismBench #1',
                'badge_color' => 'purple',
                'description' => '1418 puan ile dünyanın en gerçekçi insansı erkek ses modeli. Akıcı fonetik diksiyon, doğal vurgu ve konuşma akışını destekler.',
                'features' => ['İnsansı Konuşma Akışı', 'Doğal Diksiyon', 'Nefes & Duraklama [pause]', 'Dinamik Noktalama'],
                'shortcodes' => [
                    ['code' => '[pause]', 'label' => 'Es / Duraklama', 'desc' => 'Cümle içi veya cümleler arası sessizlik payı', 'icon' => '⏸️'],
                    ['code' => '—', 'label' => 'Uzun Tire (Nefes Es\'i)', 'desc' => 'Cümle içi akıcı ve doğal geçiş es\'i', 'icon' => '➖'],
                    ['code' => '...', 'label' => 'Düşünme Es\'i', 'desc' => 'Cümleler arası doğal soluklanma', 'icon' => '⏳'],
                    ['code' => ',', 'label' => 'Virgül Soluklanması', 'desc' => 'Doğal ritim ve nefes payı', 'icon' => '쉼'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin Freya Voice (Adam) ses motoru tarafından seslendirilecektir. Model konuşma metnini doğrudan fonetik olarak seslendirir.\n- Metin içine [sigh], [laughter] gibi yapay etiketler EKLEME; model bunları okuyamaz veya yabancı kelime sanabilir.\n- Vurguları, tebessümü veya iç çekişleri metnin doğal Türkçe diyalog akışına yedir (\"Hah, işte o an...\", \"Ahhh, kediler...\").\n- Duraklamalar için [pause] veya üç nokta (...) ve uzun tire (—) kullan.",
            ],
            [
                'id' => 'freya-eve',
                'engine' => 'freya-eve',
                'name' => 'Freya Voice - Eve (Bulut API Kadın)',
                'provider' => 'freya',
                'category' => 'Freya Voice',
                'badge' => 'AudioRealismBench #1',
                'badge_color' => 'purple',
                'description' => 'Ultra-gerçekçi kadın insansı ses modeli. Empatik tonlama, akıcı konuşma ve doğal Türkçe diksiyona sahiptir.',
                'features' => ['İnsansı Konuşma Akışı', 'Empatik Tonlama', 'Nefes & Duraklama [pause]', 'Dinamik Noktalama'],
                'shortcodes' => [
                    ['code' => '[pause]', 'label' => 'Es / Duraklama', 'desc' => 'Cümle içi veya cümleler arası sessizlik payı', 'icon' => '⏸️'],
                    ['code' => '—', 'label' => 'Uzun Tire (Nefes Es\'i)', 'desc' => 'Cümle içi akıcı ve doğal geçiş es\'i', 'icon' => '➖'],
                    ['code' => '...', 'label' => 'Düşünme Es\'i', 'desc' => 'Cümleler arası doğal soluklanma', 'icon' => '⏳'],
                    ['code' => ',', 'label' => 'Virgül Soluklanması', 'desc' => 'Doğal ritim ve nefes payı', 'icon' => '쉼'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin Freya Voice (Eve) kadın ses motoru tarafından seslendirilecektir.\n- Metin içine [sigh], [laughter] gibi etiketler ekleme; duyguyu kelimelerin ve diyalogun kendi sıcaklığıyla hissettir.\n- Doğal duraklamalar için [pause], üç nokta (...) ve uzun tire (—) kullan.",
            ],
            [
                'id' => 'freya-tts',
                'engine' => 'freya-tts',
                'name' => 'FreyaTTS (Açık Kaynak Türkçe - 183M DiT)',
                'provider' => 'freya',
                'category' => 'Freya Voice',
                'badge' => 'Yerel DiT',
                'badge_color' => 'indigo',
                'description' => 'Açık kaynaklı Türkçe Diffusion Transformer modeli. Bilgisayarınızda yerel çalışır ve pürüzsüz Türkçe fonetik diksiyon sunar.',
                'features' => ['Yerel GPU/CPU', 'Türkçe DiT', 'Duraklama [pause]', 'Noktalama Akışı'],
                'shortcodes' => [
                    ['code' => '[pause]', 'label' => 'Es / Duraklama', 'desc' => 'Cümle içi duraklama', 'icon' => '⏸️'],
                    ['code' => '...', 'label' => 'Üç Nokta Duraklama', 'desc' => 'Masalsı veya anlatı es\'i', 'icon' => '⏳'],
                    ['code' => ',', 'label' => 'Virgül Soluklanması', 'desc' => 'Doğal ritim', 'icon' => '쉼'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin yerel FreyaTTS Türkçe modeli ile seslendirilecektir. Metin akışına uygun noktalarda noktalama işaretlerini ve duraklamalar için [pause] etiketini kullan.",
            ],

            // --- Suno Bark ---
            [
                'id' => 'bark',
                'engine' => 'bark',
                'name' => 'Suno Bark (Çok Dilli Akustik TTS)',
                'provider' => 'suno',
                'category' => 'Suno Bark',
                'badge' => 'Yerleşik Akustik Efektler',
                'badge_color' => 'amber',
                'description' => 'Konuşma dışı insan seslerini (iç çekme, gülüş, esneme, fısıltı, şarkı) ve müzikal tonlamayı doğrudan modelin kendisi üretebilen transformer motoru.',
                'features' => ['İç Çekiş [sigh]', 'Gülüş [laughter]', 'Şaşkınlık [gasp]', 'Fısıltı [whisper]', 'Esneme [yawn]', 'Müzikal Melodi ♪', 'BÜYÜK HARF Vurgusu'],
                'shortcodes' => [
                    ['code' => '[laughter]', 'label' => 'Gülüş', 'desc' => 'Kahkaha, tebessüm veya kıkırdama sesi', 'icon' => '😄'],
                    ['code' => '[sigh]', 'label' => 'İç Çekiş', 'desc' => 'Yorgunluk, hüzün veya rahatlama iç çekişi', 'icon' => '💨'],
                    ['code' => '[gasp]', 'label' => 'Şaşkınlık / Nefes', 'desc' => 'Ani şaşkınlık, heyecan veya nefes kesilmesi', 'icon' => '😲'],
                    ['code' => '[whisper]', 'label' => 'Fısıltı', 'desc' => 'Fısıltıyla gizli konuşma', 'icon' => '🤫'],
                    ['code' => '[throat-clearing]', 'label' => 'Boğaz Temizleme', 'desc' => 'Öksürük / boğaz temizleme', 'icon' => '🗣️'],
                    ['code' => '[yawn]', 'label' => 'Esneme', 'desc' => 'Uykulu esneme efekti', 'icon' => '🥱'],
                    ['code' => '[groan]', 'label' => 'İnleme / Sızlanma', 'desc' => 'Zorlanma veya sızlanma sesi', 'icon' => '😣'],
                    ['code' => '...', 'label' => 'Dramatik Duraklama', 'desc' => 'Cümle içi sessizlik ve merak uyandırma', 'icon' => '⏳'],
                    ['code' => '♪ ... ♪', 'label' => 'Şarkı / Melodi', 'desc' => 'Şarkı sözü ve melodi tonlaması', 'icon' => '🎵'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin Suno Bark ses motoru ile seslendirilecektir. Suno Bark modeli metin içindeki akustik etiketleri modelin kendisi doğrudan insan ses efektlerine dönüştürür.\nMetin içinde sahneye göre aşağıdaki kısa kodları doğal bir şekilde kullan:\n- Gülüş veya neşeli anlar için: [laughter]\n- Rahatlama, yorgunluk veya hüzünlü anlar için: [sigh]\n- Şaşkınlık ve heyecan için: [gasp]\n- Fısıltılı gizli cümleler için: [whisper]\n- Boğaz temizleme için: [throat-clearing]\n- Esneme veya uyku teması için: [yawn]\n- İnleme / sitem için: [groan]\n- Melodi veya şarkı mırıldanması için: ♪ şarkı sözü ♪\n- Güçlü vurgu gereken kelimeleri BÜYÜK HARFLE yaz.\nBu etiketleri sahnenin akışına göre metne doğal olarak serpiştir.",
            ],

            // --- Patientdesk.ai Models ---
            [
                'id' => 'alania',
                'engine' => 'alania',
                'name' => 'Patientdesk Alania (Türkçe TTS • Bulut)',
                'provider' => 'patientdesk',
                'category' => 'Patientdesk.ai',
                'badge' => 'Türkçe Asistan & Akış',
                'badge_color' => 'blue',
                'description' => 'Düşük gecikmeli, akıcı ve pürüzsüz Türkçe diksiyon modeli. Telefon görüşmeleri ve sesli asistanlar için optimize edilmiştir.',
                'features' => ['Türkçe Fonetik Diksiyon', 'Doğal Duraklama [pause]', 'Akıcı Diyalog', 'Dengeli Ritim'],
                'shortcodes' => [
                    ['code' => '[pause]', 'label' => 'Duraklama / Es', 'desc' => 'Cümleler arası nefes payı ve dinlendirici es', 'icon' => '⏸️'],
                    ['code' => '—', 'label' => 'Uzun Tire (Nefes Es\'i)', 'desc' => 'Dinamik düşünme ve geçiş aralığı', 'icon' => '➖'],
                    ['code' => '...', 'label' => 'Düşünme Es\'i', 'desc' => 'Akıcı düşünme aralığı', 'icon' => '⏳'],
                    ['code' => ',', 'label' => 'Mikro Nefes', 'desc' => 'Cümle içi kısa soluklanma', 'icon' => '쉼'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin Patientdesk Alania Türkçe ses modeli ile seslendirilecektir. Model akıcı, net ve profesyonel Türkçe diksiyona sahiptir.\n- Alania bir fonetik konuşma modelidir; metne [sigh], [laughter] gibi etiketler EKLEME; bunları metin sanıp okuyabilir.\n- Doğallık, tebessüm ve iç çekiş duygusunu repliklerin içine doğal Türkçe ünlem ve kelimelerle yedir (\"Hah, kediler...\", \"Ahhh, işte o an...\", \"Tabii ki!\").\n- Duraklamalar için [pause] veya üç nokta (...) ve uzun tire (—) kullan.",
            ],
            [
                'id' => 'antalia-1',
                'engine' => 'antalia-1',
                'name' => 'Patientdesk Antalia-1 (Açık Kaynak Türkçe TTS)',
                'provider' => 'patientdesk',
                'category' => 'Patientdesk.ai',
                'badge' => '0.3B Flow-Matching',
                'badge_color' => 'cyan',
                'description' => 'Patientdesk.ai tarafından geliştirilen 304M Flow-Matching açık kaynaklı Türkçe ses modeli. 5 saatlik stüdyo kayıtlarıyla eğitilmiştir.',
                'features' => ['Stüdyo Kaydı Diksiyon', 'Flow-Matching', 'Nefes Payı [pause]'],
                'shortcodes' => [
                    ['code' => '[pause]', 'label' => 'Es / Duraklama', 'desc' => 'Cümleler arası doğal es', 'icon' => '⏸️'],
                    ['code' => '—', 'label' => 'Uzun Tire Es', 'desc' => 'Cümle içi akıcı ve dinamik geçiş', 'icon' => '➖'],
                    ['code' => '...', 'label' => 'Üç Nokta Duraklama', 'desc' => 'Masalsı veya anlatı es\'i', 'icon' => '⏳'],
                    ['code' => ',', 'label' => 'Virgül Soluklanması', 'desc' => 'Doğal ritim', 'icon' => '쉼'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin Patientdesk Antalia-1 Türkçe ses modeli ile seslendirilecektir. Edebi, masalsı veya bilgilendirici tonda, pürüzsüz Türkçe fonetik ritmi koru. Cümle aralarında (...) ve [pause] ile nefes boşlukları bırak.",
            ],

            // --- ElevenLabs Models ---
            [
                'id' => 'elevenlabs-multilingual',
                'engine' => 'elevenlabs-multilingual',
                'name' => 'ElevenLabs Multilingual v2',
                'provider' => 'elevenlabs',
                'category' => 'ElevenLabs',
                'badge' => 'Endüstri Standardı Duygu',
                'badge_color' => 'emerald',
                'description' => '29+ dilde zengin duygu, aktör performansı ve tonlama üreten dünya lideri bulut ses motoru. Duyguyu noktalama ve diyalog yapısından türetir.',
                'features' => ['Zengin Aktör Performansı', 'Tire Esleri (—)', 'Üç Nokta (...)', 'Fonetik Gülüş & İç Çekiş', 'Soru/Ünlem Vurgusu'],
                'shortcodes' => [
                    ['code' => '—', 'label' => 'Uzun Tire (Nefes Es\'i)', 'desc' => 'ElevenLabs\'ın en doğal aktör nefesi ve geçiş duraklaması', 'icon' => '➖'],
                    ['code' => '...', 'label' => 'Doğal Duraklama', 'desc' => 'Düşünme veya nefes alma sessizliği', 'icon' => '⏳'],
                    ['code' => 'Haha / Hehe', 'label' => 'Doğal Gülüş', 'desc' => 'Metin içine diyalog olarak yazılan gülüş sesletimi', 'icon' => '😄'],
                    ['code' => 'Ahhh / Haaah', 'label' => 'Fonetik İç Çekiş', 'desc' => 'Modelin ciğerden nefes vererek iç çekmesini sağlayan fonetik sesletim', 'icon' => '💨'],
                    ['code' => '!', 'label' => 'Vurgulu Yükseliş', 'desc' => 'Coşku ve enerji yükselmesi', 'icon' => '❗'],
                    ['code' => '[pause]', 'label' => 'Sistem Es\'i', 'desc' => 'Milisaniye seviyesinde ses aralığı', 'icon' => '⏸️'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin ElevenLabs ses motoru ile seslendirilecektir. ElevenLabs köşeli parantezli shortcode'lar ([sigh], [laughter]) KULLANMAZ; duyguyu bir seslendirme aktörü gibi metnin anlatımından ve noktalama işaretlerinden üretir.\n- Doğal nefes esleri ve duraklamalar için uzun tire (—) ve üç nokta (...) kullan.\n- Gülümseme veya kıkırdama için repliğin içine doğal diyalog ekle (\"Haha, aynen öyle...\", \"Hehe...\").\n- İç çekme ve rahatlama için fonetik sesletim kullan (\"Ahhh, işte o an...\", \"Haaah...\").\n- Coşku ve soru tonlamalarını noktalama işaretleriyle (!, ?) belirginleştir.",
            ],
            [
                'id' => 'elevenlabs-flash',
                'engine' => 'elevenlabs-flash',
                'name' => 'ElevenLabs Flash v2.5',
                'provider' => 'elevenlabs',
                'category' => 'ElevenLabs',
                'badge' => 'Ultra Düşük Gecikme',
                'badge_color' => 'emerald',
                'description' => 'Hızlı ve canlı interaktif diyaloglar için optimize edilmiş ElevenLabs modeli.',
                'features' => ['Hızlı Cevap', 'Canlı Diyalog', 'Noktalama Duyarlılığı', 'Tire Esleri (—)'],
                'shortcodes' => [
                    ['code' => '...', 'label' => 'Duraklama', 'desc' => 'Kısa düşünme payı', 'icon' => '⏳'],
                    ['code' => '—', 'label' => 'Tire Es', 'desc' => 'Cümle içi es', 'icon' => '➖'],
                    ['code' => '[pause]', 'label' => 'Es', 'desc' => 'Belirgin duraklama', 'icon' => '⏸️'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin ElevenLabs Flash v2.5 modeli ile seslendirilecektir. Kısa, canlı, etkili ve konuşma diline uygun cümleler oluştur. Esler için (...) ve — kullan.",
            ],

            // --- Coqui XTTS v2 ---
            [
                'id' => 'xtts-v2',
                'engine' => 'xtts',
                'name' => 'Coqui XTTS v2 (Ses Klonlama)',
                'provider' => 'coqui',
                'category' => 'Coqui XTTS',
                'badge' => 'Ses Klonlama & Çok Dilli',
                'badge_color' => 'rose',
                'description' => 'Referans ses dosyası ile klonlama yapabilen çok dilli derin öğrenme modeli.',
                'features' => ['Klonlama Uyumu', 'Noktalama Ritim Kontrolü', 'Duraklama [pause]'],
                'shortcodes' => [
                    ['code' => '...', 'label' => 'Cümleler Arası Es', 'desc' => 'Belirgin duraklama payı', 'icon' => '⏳'],
                    ['code' => '[pause]', 'label' => 'Duraklama', 'desc' => 'Soluklanma aralığı', 'icon' => '⏸️'],
                    ['code' => ',', 'label' => 'Nefes Payı', 'desc' => 'Cümle içi kısa soluk', 'icon' => '쉼'],
                    ['code' => '!', 'label' => 'Enerjik Vurgu', 'desc' => 'Vurgulu sonlandırma', 'icon' => '❗'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin Coqui XTTS v2 ses klonlama motoru ile seslendirilecektir. Model noktalama ve ritme çok duyarlıdır.\nCümlelerin nefes paylarını virgül (,), üç nokta (...) ve es için [pause] ile dengele.",
            ],

            // --- Piper TTS ---
            [
                'id' => 'piper-tr',
                'engine' => 'piper-tr',
                'name' => 'Piper TTS (Türkçe Hızlı Nöral)',
                'provider' => 'rhasspy',
                'category' => 'Piper TTS',
                'badge' => 'Yerel & Çok Hızlı',
                'badge_color' => 'green',
                'description' => 'Düşük sistem gereksinimiyle çalışan hızlı ve hafif yerel Türkçe ses motoru.',
                'features' => ['Hızlı Çıktı', 'CPU Dostu', 'Net Türkçe Okunuş', 'Noktalama Odaklı Es'],
                'shortcodes' => [
                    ['code' => '...', 'label' => 'Uzun Duraklama', 'desc' => 'Cümleler arası belirgin sessizlik', 'icon' => '⏳'],
                    ['code' => '—', 'label' => 'Düşünme Es\'i', 'desc' => 'Cümle içi ara duraklama', 'icon' => '➖'],
                    ['code' => ',', 'label' => 'Kısa Nefes', 'desc' => 'Akıcı soluklanma', 'icon' => '쉼'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin Piper Türkçe TTS motoru ile seslendirilecektir. Hızlı ve fonetik bir motordur.\nDuraklama ve konuşma temposunu yönetmek için üç nokta (...), virgül (,) ve uzun tire (—) işaretlerini özenle kullan. Yabancı karmaşık kelimeler yerine Türkçe okunuşa uygun ve akıcı cümleler tercih et.",
            ],
            [
                'id' => 'piper-en',
                'engine' => 'piper-en',
                'name' => 'Piper TTS (English Fast Neural)',
                'provider' => 'rhasspy',
                'category' => 'Piper TTS',
                'badge' => 'Lightweight CPU',
                'badge_color' => 'green',
                'description' => 'Lightweight and fast English neural speech synthesis running purely on CPU.',
                'features' => ['Fast Synthesis', 'Punctuation-based Pacing'],
                'shortcodes' => [
                    ['code' => '...', 'label' => 'Pause', 'desc' => 'Long pause between phrases', 'icon' => '⏳'],
                    ['code' => '—', 'label' => 'Dash Break', 'desc' => 'Thought hesitation', 'icon' => '➖'],
                    ['code' => ',', 'label' => 'Breath', 'desc' => 'Short comma breath', 'icon' => '쉼'],
                ],
                'system_prompt_addon' => "IMPORTANT VOICEOVER INSTRUCTION:\nThis text will be synthesized using Piper English TTS. Use punctuation (... , —) to establish natural pauses and cadence.",
            ],

            // --- OpenAI TTS ---
            [
                'id' => 'openai-tts-1',
                'engine' => 'openai-tts-1',
                'name' => 'OpenAI TTS (tts-1)',
                'provider' => 'openai',
                'category' => 'OpenAI TTS',
                'badge' => 'Stüdyo Kalitesi',
                'badge_color' => 'sky',
                'description' => 'OpenAI standart bulut TTS servisi. Doğal konuşma tonlaması ve tempo kontrolü.',
                'features' => ['Stüdyo Kalitesi', 'Doğal Tonlama', 'Konuşma Dili Devriklik'],
                'shortcodes' => [
                    ['code' => '...', 'label' => 'Dramatik Duraklama', 'desc' => 'Cümleler arası doğal es', 'icon' => '⏳'],
                    ['code' => '—', 'label' => 'Vurgu Es\'i', 'desc' => 'Düşünce ayracı', 'icon' => '➖'],
                    ['code' => '!', 'label' => 'Heyecan & Vurgu', 'desc' => 'Dinamik ton yükselişi', 'icon' => '❗'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin OpenAI TTS motoru ile seslendirilecektir. Doğal stüdyo tonlaması için konuşma diline uygun devrik cümleler, üç noktalar (...) ve tireler (—) kullanarak akıcı bir anlatım oluştur.",
            ],
            [
                'id' => 'openai-tts-hd',
                'engine' => 'openai-tts-hd',
                'name' => 'OpenAI TTS HD (tts-1-hd)',
                'provider' => 'openai',
                'category' => 'OpenAI TTS',
                'badge' => 'Yüksek Çözünürlük',
                'badge_color' => 'sky',
                'description' => 'OpenAI yüksek çözünürlüklü anlatım modeli. Podcast ve sesli kitap için detaylı tonlama.',
                'features' => ['HD Çözünürlük', 'Podcast & Kitap Anlatımı', 'Duygusal Derinlik'],
                'shortcodes' => [
                    ['code' => '...', 'label' => 'Derin Duraklama', 'desc' => 'Anlatı duraklaması', 'icon' => '⏳'],
                    ['code' => '—', 'label' => 'Parantez Es', 'desc' => 'Akıcı tonlama', 'icon' => '➖'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin OpenAI TTS HD motoru ile seslendirilecektir. Profesyonel podcast ve sesli kitap anlatımı kalitesinde, zengin betimlemeli ve üç noktalarla (...) dengelenmiş duraklamalar kullan.",
            ],

            // --- Google Cloud TTS ---
            [
                'id' => 'google-cloud-tts',
                'engine' => 'google-cloud-tts',
                'name' => 'Google Cloud TTS (Journey & Neural2)',
                'provider' => 'google',
                'category' => 'Google Cloud',
                'badge' => 'SSML Destekli',
                'badge_color' => 'teal',
                'description' => 'Google Journey ve Neural2 sesleri. SSML duraklama etiketlerini destekler.',
                'features' => ['SSML Desteği', 'Milisaniye Es', 'Vurgu Seviyesi'],
                'shortcodes' => [
                    ['code' => '<break time="500ms"/>', 'label' => 'SSML Duraklama', 'desc' => 'Milisaniyelik kesin duraklama', 'icon' => '⏱️'],
                    ['code' => '<emphasis level="strong">', 'label' => 'Güçlü Vurgu', 'desc' => 'Vurgulanan kelime', 'icon' => '🌟'],
                    ['code' => '...', 'label' => 'Doğal Es', 'desc' => 'Standart duraklama', 'icon' => '⏳'],
                ],
                'system_prompt_addon' => "ÖNEMLİ SESLENDİRME TALİMATI:\nBu metin Google Cloud TTS ile seslendirilecektir. Belirgin duraklamalar için üç nokta (...) veya SSML <break time=\"500ms\"/> etiketini kullanabilirsin.",
            ],

            // --- Tortoise TTS ---
            [
                'id' => 'tortoise',
                'engine' => 'tortoise',
                'name' => 'Tortoise TTS (High-Quality Expressive)',
                'provider' => 'tortoise',
                'category' => 'Tortoise TTS',
                'badge' => 'Deep Multivoice',
                'badge_color' => 'neutral',
                'description' => 'Expressive multi-voice text-to-speech system with high acoustic depth.',
                'features' => ['Deep Acoustic Resonance', 'Narrative Cadence'],
                'shortcodes' => [
                    ['code' => '...', 'label' => 'Pacing Pause', 'desc' => 'Deep narrative pause', 'icon' => '⏳'],
                    ['code' => '[pause]', 'label' => 'Pause', 'desc' => 'Break in speech', 'icon' => '⏸️'],
                ],
                'system_prompt_addon' => "IMPORTANT VOICEOVER INSTRUCTION:\nThis text will be synthesized using Tortoise TTS. Use thoughtful pacing and ellipses (...) for deep narrative delivery.",
            ],
        ];
    }

    /**
     * Find model features by ID, engine, or alias.
     */
    public function find(?string $engineOrId): ?array
    {
        if (empty($engineOrId)) {
            return null;
        }

        $needle = strtolower(trim($engineOrId));
        $all = $this->getAll();

        // 1. Direct match on id or engine
        foreach ($all as $model) {
            if (strtolower($model['id']) === $needle || strtolower($model['engine']) === $needle) {
                return $model;
            }
        }

        // 2. Fuzzy match
        foreach ($all as $model) {
            if (str_contains($needle, strtolower($model['id'])) || str_contains(strtolower($model['id']), $needle)) {
                return $model;
            }
        }

        // 3. Fallback partial checks
        if (str_contains($needle, 'freya')) {
            return $all[0]; // freya-adam
        }
        if (str_contains($needle, 'bark')) {
            return $all[3]; // bark
        }
        if (str_contains($needle, 'alania')) {
            return $all[4]; // alania
        }
        if (str_contains($needle, 'antalia')) {
            return $all[5]; // antalia-1
        }
        if (str_contains($needle, 'eleven')) {
            return $all[6]; // elevenlabs-multilingual
        }
        if (str_contains($needle, 'xtts')) {
            return $all[8]; // xtts-v2
        }
        if (str_contains($needle, 'piper')) {
            return $all[9]; // piper-tr
        }

        return null;
    }

    /**
     * Build the LLM system prompt addon for the selected TTS engine.
     */
    public function buildSystemInstruction(?string $engineOrId): string
    {
        $model = $this->find($engineOrId);
        if (!$model) {
            return '';
        }

        return $model['system_prompt_addon'] ?? '';
    }

    /**
     * Enrich mock/simulated generation with acoustic shortcodes for local testing.
     */
    public function enrichMockResponse(string $text, ?string $engineOrId): string
    {
        $model = $this->find($engineOrId);
        if (!$model || empty($model['shortcodes'])) {
            return $text;
        }

        $id = $model['id'];

        // Inject expressive shortcodes realistically according to actual model capability
        if (str_starts_with($id, 'freya')) {
            return "Güneşli bir ilkbahar sabahıydı... Küçük tavşan Zıpzıp, ormanın en lezzetli dağ çileklerini toplarken neşeyle şarkı mırıldanıyordu. Tam o sırada bilge karga Gakgak'ı fark etti. Zıpzıp biraz durdu [pause] ve sepetini kargaya doğru uzatarak — 'Gel beraber paylaşalım, lezzet paylaştıkça tatlanır!' dedi.";
        }

        if ($id === 'bark') {
            return "Güneşli bir ilkbahar sabahıydı... Küçük tavşan Zıpzıp, ormanın derinliklerinde çilek toplarken [laughter] bilge karga Gakgak'la karşılaştı. Gakgak günlerdir açtı. Zıpzıp şaşkınlıkla [gasp] kargaya baktı. Sonra rahat bir nefes alıp [sigh] 'Gel beraber paylaşalım, çünkü DOSTLUK paylaştıkça büyür!' dedi. [whisper] Orman sakinleri bu güzel dostluğu hiç unutmadı.";
        }

        if (str_starts_with($id, 'elevenlabs')) {
            return "Güneşli bir ilkbahar sabahıydı... Zıpzıp adındaki küçük tavşan — ormanın en lezzetli yabani çileklerini topluyordu. Tam sepetini doldurmuştu ki, dalın ucundan meraklı bakışlarla kendisini izleyen bilge karga Gakgak'ı fark etti. Zıpzıp gülümsedi — 'Haha, günaydın karga dostum!' Biraz düşündü... [pause] 'Ahhh, gel beraber paylaşalım, lezzet paylaştıkça tatlanır!' dedi.";
        }

        if ($id === 'alania' || $id === 'antalia-1') {
            return "Güneşli bir ilkbahar sabahı, Zıpzıp adındaki küçük tavşan ormanın en lezzetli yabani çileklerini topluyordu. [pause] Tam sepetini doldurmuştu ki, bilge karga Gakgak'ı fark etti... Zıpzıp biraz düşündü — sepetini karganın önüne doğru uzatarak, 'Gel beraber paylaşalım, lezzet paylaştıkça tatlanır!' dedi.";
        }

        if ($id === 'xtts-v2') {
            return "Güneşli bir ilkbahar sabahı... Zıpzıp adındaki küçük tavşan, ormanın en lezzetli yabani çileklerini topluyordu. [pause] Tam sepetini doldurmuştu ki, bilge karga Gakgak'ı fark etti! 'Gel beraber paylaşalım, lezzet paylaştıkça tatlanır!' dedi.";
        }

        return $text;
    }
}
