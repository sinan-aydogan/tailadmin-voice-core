<div align="center">

# 🎙️ TailAdmin Voice Core

### Desktop AI Voice Workstation & Docker Server REST API
### Masaüstü Yapay Zeka Ses İstasyonu & Docker Sunucu API Servisi

[![GitHub Release](https://img.shields.io/github/v/release/sinan-aydogan/tailadmin-voice-core?color=3b82f6&logo=github)](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/latest)
[![License: MIT](https://img.shields.io/badge/License-MIT-emerald.svg)](LICENSE.md)
[![Platform](https://img.shields.io/badge/Platform-Windows%20%7C%20Linux%20%7C%20macOS%20%7C%20Docker-blueviolet)](https://github.com/sinan-aydogan/tailadmin-voice-core/releases)
[![Laravel](https://img.shields.io/badge/Laravel-13.x-f43f5e?logo=laravel)](https://laravel.com)
[![NativePHP](https://img.shields.io/badge/NativePHP-2.3.0-6366f1)](https://nativephp.com)
[![Vue 3](https://img.shields.io/badge/Vue-3.x-42b883?logo=vue.js)](https://vuejs.org)
[![Python](https://img.shields.io/badge/Python-3.10%2B-3776ab?logo=python)](https://python.org)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ed?logo=docker)](DOCKER.md)

**Personal Desktop App (NativePHP Electron) + Server Production Deployment (Docker Compose + REST API v1)**

🌐 **Languages / Diller:**  
[🇬🇧 English](#-english) • [🇹🇷 Türkçe](#-türkçe)

---

<!-- GitAds-Verify: QR5X8PA5WQFZ32X7AQM7K2WVA3L4CYO7 -->

</div>

---

<a name="-english"></a>
# 🇬🇧 English

TailAdmin Voice Core is an open-source, non-freezing desktop AI voice workstation and server-ready REST API service. Built with **NativePHP (Electron)**, **Laravel 13**, **Inertia.js**, **Vue 3**, and an isolated **Python AI Engine** (FastAPI / CLI), it brings state-of-the-art speech synthesis (TTS), speech recognition (STT), voice cloning, and LLM text generation to your desktop and cloud infrastructure.

[Downloads](#-downloads-v110) • [Key Features](#-key-features) • [Architecture](#-architecture--process-isolation) • [Quick Start](#-quick-start--development) • [Docker Deployment](DOCKER.md) • [REST API Docs](API.md) • [Sponsors](#-sponsors--donations)

---

## 📥 Downloads (v1.4.0)

Pre-built binaries for your desktop operating system:

| Platform | Architecture | Download Link | Type |
|---|---|---|---|
| **Windows** | x64 | [⬇️ `electron.exe`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/electron.exe) | Windows Installer / Executable |
| **Linux** | x64 (amd64) | [⬇️ `Voice.Core-v1.4.0.AppImage`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/Voice.Core-v1.4.0.AppImage) | Universal Linux AppImage |
| **Linux** | x64 (amd64) | [⬇️ `voice-core_v1.4.0_amd64.deb`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/voice-core_v1.4.0_amd64.deb) | Debian / Ubuntu Package |
| **macOS** | Apple Silicon (arm64) | [⬇️ `Voice.Core-v1.4.0-arm64.dmg`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/Voice.Core-v1.4.0-arm64.dmg) | macOS DMG (M1 / M2 / M3 / M4) |
| **macOS** | Apple Silicon (arm64) | [⬇️ `Voice.Core-v1.4.0-arm64.zip`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/Voice.Core-v1.4.0-arm64.zip) | Portable Application Archive |

> Release notes and version history are available on the [Releases](https://github.com/sinan-aydogan/tailadmin-voice-core/releases) page.

---

## ✨ Key Features

- 🔊 **Advanced Text-to-Speech (TTS) Engines:**
  - **Freya Voice (Adam & Eve):** Flagship hyper-realistic human voice models scoring 1418 on AudioRealismBench, featuring distinctive masculine baritone (`Adam`) and natural crystal-clear female (`Eve`) timbres.
  - **Piper TTS:** Ultra-fast, lightweight ONNX-based speech synthesis (5-10x faster than real-time) supporting Turkish, English, German, French, and more.
  - **Patientdesk AI (Alania & Antalia):** Dedicated Turkish voice models with high naturalness and expressive articulation.
  - **ElevenLabs Multilingual v2:** Industry standard for emotional nuance, laughter, giggles, and actor-grade delivery.
  - **XTTS v2:** Zero-shot realistic voice cloning using a 3-6 second reference audio sample.
  - **Bark:** Expressive speech synthesis with natural breaths, laughter (`[laughter]`), and emotional tags.
  - **Tortoise TTS & MusicGen:** Expressive deep voice acting and text-to-music generation with automatic CPU fallback optimization.
- 🎛️ **Ses Dinamiği & İfade Ayarları (Acoustic Voice Dynamics):**
  - **Stability & Emotion (%10 - %100):** Adjust dramatic expressive depth vs. official announcer neutrality.
  - **Speed / Tempo (0.50x - 2.00x):** Smooth tempo adjustments across all local and cloud engines.
  - **Acoustic Pitch Shift (-6 to +6 st):** High-fidelity phase-vocoder pitch transposition without tempo distortion.
  - **Clarity / Similarity Boost (%10 - %100):** Fine-tune vocal timbre adherence during synthesis.
  - **Style Exaggeration (%0 - %100):** Amplify emotional cadence and expressiveness.
  - **Custom Pause Duration (0.3s - 2.5s):** Dynamic zero-amplitude silence stitching for `[pause]`, `[es]`, and commas.
  - **Interactive Documentation Modal:** In-depth guide explaining model parameters, laughter, and punctuation handling.
- 🎭 **Suno-Style Voiceover Directives & Text Sanitizer:**
  - Automatically detects and separates director instructions (e.g. `(Voiceover Note: Medium tempo, confident tone)`) into a dedicated style prompt card.
  - Automatic markdown and symbol stripping: removes asterisks (`**`), headers (`###`), and emojis so speech engines never read formatting symbols aloud.
  - Windows UTF-8 text file isolation pipeline preventing shell quote mangling.
  - **"🧹 Clean Text"** button on the editor for instant one-click markdown & note sanitization.
- 🎙️ **Speech-to-Text (STT) Transcription:**
  - **Faster-Whisper:** Highly accurate audio transcription supporting 90+ languages with minimal CPU/GPU memory footprint.
- 👤 **Voice Profiles & Cloning Studio:**
  - Upload audio samples or record directly to build custom voice cloning profiles.
- 🔄 **Re-run & Retry with Different Model:**
  - Available for **both completed and failed tasks**: regenerate any voiceover or transcription with a different engine or voice profile in one click.
- 📋 **Batch Audio Playlists:**
  - Split long articles into chapters and generate queued audio using single or mixed engines.
- 🤖 **Creative AI Text Generation (Multi-Provider LLM):**
  - Connect Anthropic Claude (3.5/3.7 Sonnet), OpenAI (GPT-4o), Google Gemini 2.0 Flash, DeepSeek, Groq, OpenRouter, or local Ollama / LM Studio.
  - Parametric Prompt Templates with dynamic `$1`, `$2` variable placeholders.
- 🖥️ **60 FPS Modern Desktop Experience:**
  - Built with `@tailadmin/ui` and Tailwind CSS, featuring dark mode, waveform visualizer, and hardware monitors.
- 🐳 **Server Production & REST API:**
  - Single command (`docker compose up -d`) deployment for VPS and cloud GPU instances with comprehensive [REST API v1](API.md) endpoints.

---

## 🚀 Architecture & Process Isolation

Heavy AI inference (PyTorch) in traditional desktop apps freezes the UI due to the Python GIL. TailAdmin Voice Core completely isolates processes:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   NativePHP Electron Shell (Desktop)                   │
├────────────────────────────────────────────────────────────────────────┤
│                 Inertia.js + Vue 3 (@tailadmin/ui)                     │
│                (60 FPS Smooth UI - Never Freezes)                      │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ HTTP / Inertia IPC
┌───────────────────────────────────▼────────────────────────────────────┐
│                          Laravel 13 Backend                            │
│   • Controllers & Routes (Desktop API & Inertia Web Pages)             │
│   • SQLite Database (WAL Mode - High Concurrency Read/Write)           │
│   • Laravel Queue (GenerateTtsJob, TranscribeSttJob, DownloadModelJob) │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ Process::run() / CLI / FastAPI
┌───────────────────────────────────▼────────────────────────────────────┐
│                 Isolated Python AI Core (engine/)                      │
│   • Piper TTS (ONNX Real-Time Engine)                                  │
│   • XTTS v2 (Zero-shot Voice Cloning)                                  │
│   • Bark & Tortoise TTS (Expressive Speech)                            │
│   • Faster-Whisper (STT - Speech to Text)                              │
│   • HuggingFace & Piper Model Downloader                               │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 🛠️ Quick Start & Development

### Prerequisites
- **PHP** >= 8.3 or 8.4 (`mbstring`, `xml`, `intl`, `pdo_sqlite`, `sqlite3`, `curl`, `zip`, `fileinfo`)
- **Composer** >= 2.0
- **Node.js** >= 20 or 22 & **npm**
- **Python** >= 3.10 (with PyTorch, Piper, and Whisper dependencies)

```bash
# 1. Clone the repository
git clone https://github.com/sinan-aydogan/tailadmin-voice-core.git
cd tailadmin-voice-core

# 2. Install dependencies
composer install
npm install

# 3. Environment configuration & database migration
cp .env.example .env
php artisan key:generate
php artisan migrate

# 4. Build assets
npm run build

# 5. Launch the desktop application in development mode
php artisan native:run
```

### Packaging Desktop Installers
```bash
# Windows x64:
php artisan native:build win x64

# Linux x64:
php artisan native:build linux x64

# macOS Apple Silicon (M1/M2/M3/M4):
php artisan native:build mac arm64
```

---

## 🐳 Docker Server & REST API Deployment

To deploy TailAdmin Voice Core as a headless or web-accessible **cloud voice server**:

```bash
# 1. Clone repository
git clone https://github.com/sinan-aydogan/tailadmin-voice-core.git
cd tailadmin-voice-core
cp .env.example .env

# 2. Launch with Docker Compose (CUDA / GPU recommended)
docker compose up -d --build

# Or for CPU-only servers:
docker compose -f docker-compose.yml -f docker-compose.override.yml up -d --build
```

- 📖 **REST API v1 Documentation:** [API.md](API.md)
- 🧪 **Interactive Swagger UI:** `http://<host>:<port>/api/documentation` (OpenAPI spec at `/docs`)
- 📋 **Docker Guide & Volumes:** [DOCKER.md](DOCKER.md)

---

<br/>

---

<a name="-türkçe"></a>
# 🇹🇷 Türkçe

TailAdmin Voice Core; donmasız, tam süreç izolasyonuna sahip açık kaynaklı bir masaüstü yapay zeka ses istasyonu ve sunucu REST API servisidir. **NativePHP (Electron)**, **Laravel 13**, **Inertia.js**, **Vue 3** ve izole **Python AI Çekirdeği** (FastAPI / CLI) üzerine inşa edilmiştir.

[İndir (v1.4.0)](#-hızlı-indirme-v140) • [Özellikler](#-özellikler-tr) • [Mimari](#-mimari-ve-süreç-izolasyonu-tr) • [Geliştirme](#-kurulum-ve-geliştirme-tr) • [Docker Dağıtımı](DOCKER.md) • [REST API Dokümanı](API.md) • [Destek & Sponsorlar](#-destek-ve-bağış)

---

<a name="-hızlı-indirme-v140"></a>
## 📥 Hızlı İndirme (v1.4.0)

Masaüstünüzde doğrudan çalıştırmak için platformunuza uygun sürümü indirin:

| Platform | Mimari | İndirme Bağlantısı | Tür |
|---|---|---|---|
| **Windows** | x64 | [⬇️ `electron.exe`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/electron.exe) | Windows Kurulum / Çalıştırılabilir Dosya |
| **Linux** | x64 (amd64) | [⬇️ `Voice.Core-v1.4.0.AppImage`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/Voice.Core-v1.4.0.AppImage) | Taşınabilir Universal Linux AppImage |
| **Linux** | x64 (amd64) | [⬇️ `voice-core_v1.4.0_amd64.deb`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/voice-core_v1.4.0_amd64.deb) | Debian / Ubuntu Kurulum Paketi |
| **macOS** | Apple Silicon (arm64) | [⬇️ `Voice.Core-v1.4.0-arm64.dmg`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/Voice.Core-v1.4.0-arm64.dmg) | macOS Disk İmajı (M1 / M2 / M3 / M4) |
| **macOS** | Apple Silicon (arm64) | [⬇️ `Voice.Core-v1.4.0-arm64.zip`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.4.0/Voice.Core-v1.4.0-arm64.zip) | macOS Taşınabilir Uygulama Arşivi |

> Tüm sürümleri ve geçmiş sürümleri [Releases](https://github.com/sinan-aydogan/tailadmin-voice-core/releases) sayfasında bulabilirsiniz.

---

<a name="-özellikler-tr"></a>
## ✨ Özellikler

- 🔊 **Gelişmiş Metinden Sese (TTS) Motorları:**
  - **Freya Voice (Adam & Eve):** AudioRealismBench'te 1418 puan ile lider konumda bulunan amiral gemisi Türkçe modeller; tok ve karizmatik erkek bariton (`Adam`) ile berrak ve doğal kadın (`Eve`) ses tonları.
  - **Piper TTS:** ONNX tabanlı, gerçek zamanlıdan 5-10 kat daha hızlı, ultra hafif metinden sese dönüştürme (Türkçe, İngilizce, Almanca, Fransızca).
  - **Patientdesk AI (Alania & Antalia):** Yüksek akıcılık ve doğal diksiyona sahip yerli Türkçe seslendirme modelleri.
  - **ElevenLabs Multilingual v2:** Duygusal tonlama, gülüş (`[laughter]`), iç çekiş ve aktör düzeyinde seslendirme standardı.
  - **XTTS v2:** 3-6 saniyelik referans ses kaydı ile sıfır veri kaybıyla yüksek kaliteli ses klonlama (Voice Cloning).
  - **Bark:** Duygulu, nefes ve tonlama vurgularına sahip doğal insan konuşması üretimi.
  - **Tortoise TTS & MusicGen:** CPU güvenli akıllı optimizasyon ile derin metin seslendirme ve metinden müzik üretimi.
- 🎛️ **Ses Dinamiği & İfade Ayarları (Akustik Kontroller):**
  - **Duygu & Kararlılık (Stability - %10 - %100):** Aktör/dramatik tonlama ile spiker kararlılığı arasındaki dengeyi ayarlama.
  - **Konuşma Hızı (Tempo - 0.50x - 2.00x):** Tüm yerel ve bulut motorlarda akıcı hız kontrolü.
  - **Ses Perdesi (Pitch Shift - -6 ila +6 st):** Kalın/tok veya ince/genç ses tınısı için yüksek doğruluklu faz-vokoder perde transpozisyonu.
  - **Benzerlik / Berraklık (Clarity Boost - %10 - %100):** Orijinal konuşmacı tınısına sadakat oranı.
  - **Üslup Abartısı (Style Exaggeration - %0 - %100):** Duygusal coşku ve vurgu yoğunluğunu artırma.
  - **Özelleştirilebilir Es / Duraklama Süresi (0.3s - 2.5s):** `[pause]`, `[es]` ve virgül duraklamaları için kesin sıfır genlikli sessizlik dikişi.
  - **Seslendirme Dökümantasyonu Modalı:** Sağ üst köşedeki interaktif rehber ile her modelin duygu, gülüş ve noktalama davranışını ayrıntılı inceleme.
- 🎭 **Suno Tarzı Seslendirme Talimatları & Metin Arındırıcı:**
  - Metin içindeki `(Seslendirme Notu: ...)` veya `[Yönetmen Notu: ...]` talimatlarını otomatik olarak ayrı bir stil kartına ayırır.
  - Yıldız (`**`), başlık (`###`) ve emojileri temizleyerek ses motorunun sembolleri seslendirmesini engeller.
  - Windows UTF-8 geçici dosya izolasyon boru hattı ile tırnak işareti (`"`) ve kabuk kaçırma hatalarını tamamen önler.
  - Metin kutusu üzerindeki **"🧹 Yıldız & Notları Arındır"** butonu ile tek tıkla temiz seslendirme metni elde etme.
- 🎙️ **Sesten Metne (STT - Speech to Text):**
  - **Faster-Whisper:** Türkçe dahil 90+ dilde düşük kaynak tüketimi ve yüksek doğruluk oranı ile ses kaydından metin çıkarma ve transkripsiyon.
- 👤 **Ses Profili ve Örnek Yönetimi:**
  - Kendi sesinizi veya özel konuşmacı seslerini yükleyip referans ses profilleri oluşturma.
- 🔄 **Farklı Model ile Yeniden Üretme (Retry & Rerun):**
  - Hem **tamamlanan** hem de **başarısız olan** işlemler için tek tıkla farklı bir motor veya ses profili seçerek yeniden seslendirme yapabilme.
- 📋 **Çalma Listeleri & Toplu Üretim (Playlists):**
  - Uzun metinleri parçalara bölerek tek veya karma motorlarla sıralı/toplu seslendirme kuyruğu oluşturma.
- 🧩 **Entegre Model Yöneticisi:**
  - Hugging Face ve Piper açık kaynak modellerini tek tıkla otomatik indirme, doğrulama (integrity check) ve disk kullanım yönetimi.
- 🤖 **Yapay Zeka (LLM) Metin Üretim Motoru & Şablon Yöneticisi:**
  - **Çoklu LLM Desteği:** Anthropic Claude (3.5 / 3.7 Sonnet), OpenAI (GPT-4o), Google Gemini 2.0 Flash, DeepSeek, Groq Cloud (Llama 3.3 Ultra Hızlı), OpenRouter ve yerel LM Studio / Ollama desteği.
  - **Dinamik Değişkenli Prompt Şablonları:** `$1`, `$2` gibi parametrik yer tutucular içeren özel şablonlar oluşturma ve tek tıkla seslendirme metnine dönüştürme.
- 🖥️ **60 FPS Modern Masaüstü Deneyimi:**
  - `@tailadmin/ui` tasarım sistemi ve Tailwind CSS ile tamamen koyu mod uyumlu arayüz.
  - Dahili **Gelişmiş Ses Oynatıcı:** Canlı waveform görselleştirici, önceki/sonraki parça geçişi, hız ayarı ve WAV/MP3 indirme.
- 📊 **Canlı Sistem Monitörü (Footer):**
  - CPU, RAM, Disk ve GPU/MPS/CUDA donanım kullanımını altbilgi çubuğunda canlı izleme.
- 🚀 **Sunucu & REST API Servisi:**
  - Tek komutla (`docker compose up -d`) %100 Dockerize sunucu kurulumu.
  - Kapsamlı [REST API v1](API.md) uç noktaları (`/api/v1/tts`, `/api/v1/stt`, `/api/v1/models`, `/api/v1/tasks`).

---

<a name="-mimari-ve-süreç-izolasyonu-tr"></a>
## 🚀 Mimari ve Süreç İzolasyonu

Yapay zeka çıkarım (inference) ve model indirme işlemleri geleneksel monolitik masaüstü uygulamalarında kullanıcı arayüzünü (UI) Python GIL nedeniyle kilitler. TailAdmin Voice Core, **Tam Süreç İzolasyonu** mimarisiyle tasarlanmıştır:

1. **Arayüz Katmanı:** Electron üzerinde koşan Vue 3 arayüzü yalnızca Laravel backend ile konuşur; yapay zeka çıkarımlarından tamamen yalıtılmıştır.
2. **Kuyruk Katmanı:** Ses üretimi ve model indirme görevleri Laravel veritabanı kuyruğuna alınır (`jobs`).
3. **AI Motoru:** Arka planda bağımsız işletim sistemi süreçleri (`python -m app.cli` / FastAPI) olarak çalışır; ağır PyTorch hesaplamaları UI thread'ine asla temas etmez.

---

<a name="-kurulum-ve-geliştirme-tr"></a>
## 🛠️ Kurulum ve Geliştirme

### Gereksinimler
- **PHP** >= 8.3 veya 8.4 (`mbstring`, `xml`, `intl`, `pdo_sqlite`, `sqlite3`, `curl`, `zip`, `fileinfo`)
- **Composer** >= 2.0
- **Node.js** >= 20 veya 22 & **npm**
- **Python** >= 3.10 (PyTorch, Piper, Whisper bağımlılıkları ile)

```bash
# 1. Depoyu Klonlayın
git clone https://github.com/sinan-aydogan/tailadmin-voice-core.git
cd tailadmin-voice-core

# 2. Bağımlılıkları Kurun
composer install
npm install

# 3. Ortam Dosyası ve Veritabanı
cp .env.example .env
php artisan key:generate
php artisan migrate

# 4. Varlıkları Derleyin
npm run build

# 5. Geliştirme Modunda Başlatın
php artisan native:run
```

---

<a name="-destek-ve-bağış"></a>
## 💖 Destek ve Bağış / Sponsors & Donations

Voice Core is completely free and open-source. / Voice Core tamamen açık kaynaklı ve ücretsiz bir projedir.

<p align="left">
  <a href="https://ko-fi.com/sinanaydogan" target="_blank">
    <img src="https://ko-fi.com/img/githubbutton_sm.svg" alt="Support on Ko-fi">
  </a>
  &nbsp;&nbsp;
  <a href="https://www.buymeacoffee.com/sinanaydogan" target="_blank">
    <img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 36px !important;">
  </a>
</p>

### 🌟 Destekçilerimiz & Sponsorlar / Sponsors

<table border="0">
  <tr>
    <td align="center" width="160">
      <a href="https://panelica.com" target="_blank" rel="noopener noreferrer">
        <img src="https://panelica.com/assets/images/logo-light.png" alt="Panelica" width="140">
      </a>
    </td>
    <td>
      <strong><a href="https://panelica.com" target="_blank" rel="noopener noreferrer">Panelica</a></strong> — Modern server and infrastructure management panel. Thank you for supporting open-source development of Voice Core. / Modern sunucu ve altyapı yönetim paneli. Açık kaynak geliştirme süreçlerine verdikleri destek için teşekkür ederiz.
    </td>
  </tr>
</table>

---

## 🔗 Bağlantılar & Ekosistem / Links & Ecosystem

- **Author / Yapımcı:** [TailAdmin](https://tailadmin.dev)
- **UI Kit:** [@tailadmin/ui (npm)](https://www.npmjs.com/package/@tailadmin/ui)
- **Source Code / Kaynak Kod:** [sinan-aydogan/tailadmin-voice-core](https://github.com/sinan-aydogan/tailadmin-voice-core)
- **Releases / Sürümler:** [GitHub Releases](https://github.com/sinan-aydogan/tailadmin-voice-core/releases)
- **REST API Guide:** [API.md](API.md)
- **Docker Deployment:** [DOCKER.md](DOCKER.md)

---

## 📄 Lisans / License

Distributed under the [MIT License](LICENSE.md). Feel free to use, customize, and contribute. / [MIT Lisansı](LICENSE.md) kapsamında lisanslanmıştır. Dilediğiniz gibi kullanabilir, katkıda bulunabilir ve genişletebilirsiniz.
