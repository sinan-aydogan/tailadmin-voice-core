<div align="center">

# 🎙️ TailAdmin Voice Core
### Masaüstü Yapay Zeka Ses İstasyonu & Docker Sunucu API Servisi

[![GitHub Release](https://img.shields.io/github/v/release/sinan-aydogan/tailadmin-voice-core?color=3b82f6&logo=github)](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/latest)
[![License: MIT](https://img.shields.io/badge/License-MIT-emerald.svg)](LICENSE.md)
[![Platform](https://img.shields.io/badge/Platform-Windows%20%7C%20Linux%20%7C%20macOS%20%7C%20Docker-blueviolet)](https://github.com/sinan-aydogan/tailadmin-voice-core/releases)
[![Laravel](https://img.shields.io/badge/Laravel-13.x-f43f5e?logo=laravel)](https://laravel.com)
[![NativePHP](https://img.shields.io/badge/NativePHP-2.3.0-6366f1)](https://nativephp.com)
[![Vue 3](https://img.shields.io/badge/Vue-3.x-42b883?logo=vue.js)](https://vuejs.org)
[![Python](https://img.shields.io/badge/Python-3.10%2B-3776ab?logo=python)](https://python.org)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ed?logo=docker)](DOCKER.md)

**Kişisel Masaüstü (NativePHP Electron) + Sunucu Dağıtımı (Docker Compose + REST API v1)**

[İndir (v1.0.0)](#-hızlı-indirme-v100) • [Özellikler](#-özellikler) • [REST API Dokümanı](API.md) • [Docker Kurulumu](DOCKER.md) • [Mimari](#-mimari-ve-süreç-izolasyonu) • [Geliştirme](#-kurulum-ve-geliştirme)

</div>

---

## 📥 Hızlı İndirme (v1.0.0)

Doğrudan masaüstünüzde çalıştırmak için platformunuza uygun sürümü indirin:

| Platform | Mimari | İndirme Bağlantısı | Tür |
|---|---|---|---|
| **Windows** | x64 | [⬇️ `electron.exe`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.0.0/electron.exe) | Windows Kurulum / Çalıştırılabilir Dosya |
| **Linux** | x64 (amd64) | [⬇️ `Voice.Core-v1.0.0.AppImage`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.0.0/Voice.Core-v1.0.0.AppImage) | Taşınabilir Universal Linux AppImage |
| **Linux** | x64 (amd64) | [⬇️ `voice-core_v1.0.0_amd64.deb`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.0.0/voice-core_v1.0.0_amd64.deb) | Debian / Ubuntu Kurulum Paketi |
| **macOS** | Apple Silicon (arm64) | [⬇️ `Voice.Core-v1.0.0-arm64.dmg`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.0.0/Voice.Core-v1.0.0-arm64.dmg) | macOS Disk İmajı (M1 / M2 / M3 / M4) |
| **macOS** | Apple Silicon (arm64) | [⬇️ `Voice.Core-v1.0.0-arm64.zip`](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/download/v1.0.0/Voice.Core-v1.0.0-arm64.zip) | macOS Taşınabilir Uygulama Arşivi |

> Tüm sürümleri ve değişiklik geçmişini [Releases](https://github.com/sinan-aydogan/tailadmin-voice-core/releases) sayfasında bulabilirsiniz.

---

## ✨ Özellikler

- 🔊 **Gelişmiş Metinden Sese (TTS) Motorları:**
  - **Piper TTS:** ONNX tabanlı, gerçek zamanlıdan 5-10 kat daha hızlı, ultra hafif metinden sese dönüştürme (Türkçe, İngilizce, Almanca, Fransızca).
  - **XTTS v2:** 3-6 saniyelik referans ses kaydı ile sıfır veri kaybıyla yüksek kaliteli ses klonlama (Voice Cloning).
  - **Bark:** Duygulu, nefes ve tonlama vurgularına sahip doğal insan konuşması üretimi.
  - **Tortoise TTS:** Derinlikli, yüksek doğruluklu metin seslendirme.
  - **MusicGen:** Metin tanımlarından müzik ve atmosferik ses üretimi.
- 🎙️ **Sesten Metne (STT - Speech to Text):**
  - **Faster-Whisper:** Türkçe dahil 90+ dilde düşük kaynak tüketimi ve yüksek doğruluk oranı ile ses kaydından metin çıkarma ve transkripsiyon.
- 👤 **Ses Profili ve Örnek Yönetimi:**
  - Kendi sesinizi veya özel konuşmacı seslerini yükleyip referans ses profilleri oluşturma.
- 📋 **Çalma Listeleri & Toplu Üretim (Playlists):**
  - Uzun metinleri parçalara bölerek tek veya karma motorlarla sıralı/toplu seslendirme kuyruğu oluşturma.
- 🧩 **Entegre Model Yöneticisi:**
  - Hugging Face ve Piper açık kaynak modellerini tek tıkla otomatik indirme, doğrulama (integrity check) ve disk kullanım yönetimi.
- 🖥️ **60 FPS Modern Masaüstü Deneyimi:**
  - `@tailadmin/ui` tasarım sistemi ve Tailwind CSS ile tamamen koyu mod uyumlu, responsive ve modern arayüz.
  - Dahili **Gelişmiş Ses Oynatıcı:** Canlı waveform görselleştirici, önceki/sonraki parça geçişi, hız ayarı ve tek tıkla WAV/MP3 indirme.
  - **Yeniden Dene (Retry Modal):** Hata alan görevleri farklı motor veya model seçerek anında yeniden kuyruğa alma.
- 📊 **Canlı Sistem Monitörü (Footer):**
  - CPU, RAM, Disk ve GPU/MPS/CUDA donanım kullanımını altbilgi çubuğunda canlı izleme.
- 🚀 **Sunucu & REST API Servisi:**
  - Tek komutla (`docker compose up -d`) %100 Dockerize sunucu kurulumu.
  - Harici uygulamalar, mobil istemciler ve otomasyon botları için kapsamlı [REST API v1](API.md) uç noktaları (`/api/v1/tts`, `/api/v1/stt`, `/api/v1/models`, `/api/v1/tasks`).
  - İsteğe bağlı API Key (`X-API-Key` / Bearer token) güvenliği.
- 🌐 **Çok Dilli Arayüz (i18n):**
  - Türkçe ve İngilizce tam arayüz yerelleştirmesi.

---

## 🚀 Mimari ve Süreç İzolasyonu

Yapay zeka çıkarım (inference) ve model indirme işlemleri geleneksel monolitik masaüstü uygulamalarında kullanıcı arayüzünü (UI) Python GIL (Global Interpreter Lock) nedeniyle kilitler. 

TailAdmin Voice Core, **Tam Süreç İzolasyonu (Process Isolation)** mimarisiyle tasarlanmıştır:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   NativePHP Electron Shell (Masaüstü)                  │
├────────────────────────────────────────────────────────────────────────┤
│                 Inertia.js + Vue 3 (@tailadmin/ui)                     │
│                (60 FPS Akıcı UI - Asla Donmaz / Kilitlenmez)           │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ HTTP / Inertia IPC
┌───────────────────────────────────▼────────────────────────────────────┐
│                          Laravel 13 Backend                            │
│   • Controllers & Routes (Masaüstü API / Sayfalar)                     │
│   • SQLite Database (WAL Mode - Eşzamanlı Okuma/Yazma)                 │
│   • Laravel Queue (GenerateTtsJob, TranscribeSttJob, DownloadModelJob) │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ Process::run() / CLI / HTTP
┌───────────────────────────────────▼────────────────────────────────────┐
│                 İzole Python AI Çekirdeği (engine/)                    │
│   • Piper TTS (ONNX Ultra Fast)                                        │
│   • XTTS v2 (Zero-shot Voice Cloning)                                  │
│   • Bark & Tortoise TTS (Doğal Konuşma)                                │
│   • Faster-Whisper (STT - Transkripsiyon)                              │
│   • HuggingFace & Piper Model Yöneticisi                               │
└────────────────────────────────────────────────────────────────────────┘
```

1. **Arayüz Katmanı:** Electron üzerinde koşan Vue 3 arayüzü yalnızca Laravel backend ile konuşur; yapay zeka çıkarımlarından tamamen yalıtılmıştır.
2. **Kuyruk Katmanı:** Ses üretimi ve model indirme görevleri Laravel veritabanı kuyruğuna alınır (`jobs`).
3. **AI Motoru:** Arka planda bağımsız işletim sistemi süreçleri (`python -m app.cli` / FastAPI) olarak çalışır; ağır PyTorch hesaplamaları UI thread'ine asla temas etmez.

---

## 🛠️ Kurulum ve Geliştirme

### Gereksinimler
- **PHP** >= 8.3 veya 8.4 (`mbstring`, `xml`, `intl`, `pdo_sqlite`, `sqlite3`, `curl`, `zip`, `fileinfo` eklentileri ile)
- **Composer** >= 2.0
- **Node.js** >= 20 veya 22 & **npm**
- **Python** >= 3.10 (PyTorch, Piper, Whisper bağımlılıkları ile)

### 1. Depoyu Klonlayın
```bash
git clone https://github.com/sinan-aydogan/tailadmin-voice-core.git
cd tailadmin-voice-core
```

### 2. PHP ve Node Bağımlılıklarını Kurun
```bash
composer install
npm install
```

### 3. Ortam Değişkenlerini ve Veritabanını Hazırlayın
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 4. Varlıkları Derleyin
```bash
npm run build
```

### 5. Masaüstü Uygulamasını Geliştirme Modunda Başlatın
```bash
php artisan native:run
```

### 6. Masaüstü Yükleyicilerini Paketleyin (Build)
```bash
# Windows x64 için:
php artisan native:build win x64

# Linux x64 için:
php artisan native:build linux x64

# macOS Apple Silicon için:
php artisan native:build mac arm64
```

---

## 🐳 Sunucu ve Docker ile Dağıtım (API & Web Servisi)

TailAdmin Voice Core'u bir sunucuya (VPS, bulut sanal makinesi veya GPU sunucusu) kurup **tam teşekküllü bir REST API ve Web Yönetim İstasyonu** olarak kullanmak için ek hiçbir yazılıma (PHP, Python, Node.js vb.) gerek yoktur.

### 1. Hızlı Başlatma (Ayağa Kaldırma)

```bash
# 1. Depoyu klonlayın ve klasöre girin
git clone https://github.com/sinan-aydogan/tailadmin-voice-core.git
cd tailadmin-voice-core

# 2. Örnek ortam dosyasını oluşturun
cp .env.example .env
```

Sunucu donanımınıza uygun komutla tüm yığını (Web Arayüzü + REST API + Laravel Worker + Python AI Motoru) başlatın:

* **GPU / CUDA Destekli Sunucularda (Önerilen):**
  ```bash
  docker compose up -d --build
  ```

* **CPU-Only Sunucularda veya macOS Docker Desktop Üzerinde:**
  ```bash
  docker compose -f docker-compose.yml -f docker-compose.override.yml up -d --build
  ```

---

### 🔌 Port Değişimi Nasıl Yapılır?

> [!TIP]
> **Önemli:** Port değişimi için **Dockerfile üzerinde hiçbir değişiklik yapmanıza gerek yoktur**.

Konteynerin iç çalışma portu standart `8000`'dir. Sunucunun dışarıya açtığı portu değiştirmek için `.env` dosyasındaki `PORT` değerini güncellemeniz yeterlidir:

```env
# Varsayılan 8000 portunu örneğin 8085 yapmak için .env dosyasına yazın:
PORT=8085
```

Ardından konteynerleri güncelleyin:
```bash
docker compose up -d
```
Artık arayüze ve API'ye `http://<sunucu-ip>:8085` adresinden erişebilirsiniz.

---

### 🌐 Sunucu Yönetim Panelleri ve Domaine Bağlama (Coolify, CapRover, Nginx vb.)

Birçok modern sunucu paneli (Coolify, CapRover, CloudPanel, aaPanel, Easypanel, Dokku vb.) uygulamaları domaine bağlamak için dahili **Reverse Proxy** kullanır:

1. **Dockerfile Değişikliği Gerekmez:** Paneller SSL sertifikasını (Let's Encrypt) ve 80/443 portunu kendileri yönetir.
2. **Port Ayarı:** Panel arayüzündeki **Container Port** alanına yalnızca `8000` yazmanız yeterlidir. Dışarıya rastgele host portu açmanıza gerek kalmaz.
3. **SSL / HTTPS Desteği:** Laravel katmanımızda `trustProxies` yapılandırması aktif olduğundan, `https://ses.siteniz.com` arkasında çalışırken yönlendirmeler ve asset yüklemeleri sorunsuz gerçekleşir.

---

### 📋 Hızlı Yönetim Komutları

```bash
# Canlı logları izleme (Web + API + Queue Worker)
docker compose logs -f voice-core-app

# Python AI motoru loglarını izleme
docker compose logs -f voice-core-engine

# Konteynerleri durdurma
docker compose down

# Sağlık durumunu kontrol etme
curl http://localhost:8000/api/v1/health
```

* 📖 **REST API Referansı:** [API.md](API.md) (Tüm uç noktalar ve cURL/Python kod örnekleri)
* 📋 **Detaylı Dağıtım Kılavuzu & Volumes:** [DOCKER.md](DOCKER.md)

---

## 📂 Dizin Yapısı

```text
tailadmin-voice-core/
├── app/
│   ├── Http/Controllers/     # Laravel Inertia Controller katmanı
│   ├── Jobs/                 # Arka plan kuyruk işleri (TTS, STT, Model İndirme)
│   ├── Models/               # Eloquent ORM modelleri (Playlist, Profile, Task vb.)
│   ├── Providers/            # NativeAppServiceProvider ve uygulama servisleri
│   └── Services/             # Python CLI köprüsü ve kuyruk izleme servisleri
├── config/
│   └── nativephp.php         # NativePHP masaüstü ve updater konfigürasyonu
├── engine/                   # İzole Python Yapay Zeka Çekirdeği
│   ├── app/
│   │   ├── cli.py            # Bağımsız CLI giriş noktası (donmasız çalıştırma)
│   │   ├── tts/              # TTS motorları (piper, xtts, bark, tortoise, musicgen)
│   │   ├── stt/              # Whisper STT motoru
│   │   └── downloader/       # Model indirme ve bütünlük kontrolü
│   └── requirements.txt      # Python kütüphane gereksinimleri
├── resources/
│   ├── js/
│   │   ├── Components/       # AudioPlayerModal, RetryTaskModal, SystemFooter vb.
│   │   ├── Layouts/          # AppLayout navigasyon ve düzen bileşenleri
│   │   └── Pages/            # Inertia sayfaları (Dashboard, Tts, Stt, Models, Playlists vb.)
│   └── css/                  # @tailadmin/ui ve Tailwind CSS stilleri
├── patches/                  # Upstream kütüphane yamaları
└── .github/workflows/        # Çoklu platform GitHub Actions Release iş akışı
```

---

## 💖 Destek ve Bağış

Voice Core tamamen açık kaynaklı ve ücretsiz bir projedir. Projenin gelişimine katkıda bulunmak veya bir kahve ısmarlamak isterseniz:

<p align="left">
  <a href="https://ko-fi.com/sinanaydogan" target="_blank">
    <img src="https://ko-fi.com/img/githubbutton_sm.svg" alt="Ko-fi ile Destek Ol">
  </a>
  &nbsp;&nbsp;
  <a href="https://www.buymeacoffee.com/sinanaydogan" target="_blank">
    <img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 36px !important;">
  </a>
</p>

### 🌟 Destekçilerimiz & Sponsorlar

<table border="0">
  <tr>
    <td align="center" width="160">
      <a href="https://panelica.com" target="_blank" rel="noopener noreferrer">
        <img src="https://panelica.com/assets/images/logo-light.png" alt="Panelica" width="140">
      </a>
    </td>
    <td>
      <strong><a href="https://panelica.com" target="_blank" rel="noopener noreferrer">Panelica</a></strong> — Modern sunucu ve altyapı yönetim paneli. Voice Core projesinin açık kaynak geliştirme süreçlerine verdikleri destek için teşekkür ederiz.
    </td>
  </tr>
</table>

---

## 🔗 Bağlantılar & Ekosistem

- **Yapımcı:** [TailAdmin](https://tailadmin.dev)
- **UI Kit:** [@tailadmin/ui (npm)](https://www.npmjs.com/package/@tailadmin/ui)
- **Kaynak Kod:** [sinan-aydogan/tailadmin-voice-core](https://github.com/sinan-aydogan/tailadmin-voice-core)
- **Sürüm Paketleri:** [GitHub Releases](https://github.com/sinan-aydogan/tailadmin-voice-core/releases)

---

## 📄 Lisans

Bu proje [MIT Lisansı](LICENSE.md) kapsamında lisanslanmıştır. Dilediğiniz gibi kullanabilir, katkıda bulunabilir ve genişletebilirsiniz.
