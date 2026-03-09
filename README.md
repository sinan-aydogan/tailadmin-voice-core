# TailAdmin Voice Core

<p align="center">
  <strong>Çok Dilli Ses Klonlama, TTS ve STT Platformu</strong><br>
  <strong>Multilingual Voice Cloning, TTS & STT Platform</strong>
</p>

<p align="center">
  <a href="#türkçe">Türkçe</a> •
  <a href="#english">English</a>
</p>

---

<a name="türkçe"></a>
## 🇹🇷 Türkçe

TailAdmin Voice Core, çok dilli ses klonlama, metin-ses (TTS) ve ses-metin (STT) işlevlerini bir arada sunan yerel çalışan (local) bir web uygulamasıdır.

### 🎯 Özellikler

| Özellik | Açıklama |
|---------|----------|
| **🎙️ Çoklu TTS Motorları** | XTTS v2 (varsayılan), Bark, Tortoise TTS |
| **🎤 STT** | Whisper (faster-whisper ile optimize) |
| **🎭 Ses Profilleri** | Ses örneği ile kişiselleştirilmiş ses üretimi |
| **⚡ GPU Hızlandırma** | CUDA (NVIDIA), MPS (Apple Silicon), CPU desteği |
| **📦 Model Yönetimi** | Uygulama içinden model indirme ve yönetimi |
| **⏱️ Görev Kuyruğu** | Toplu işlem desteği |
| **🔐 JWT Kimlik Doğrulama** | Güvenli API erişimi |
| **🎛️ Web Arayüzü** | TailAdmin tabanlı modern yönetim paneli |

### 🌐 Desteklenen Diller

| Dil | TTS (XTTS v2) | STT (Whisper) |
|-----|---------------|---------------|
| 🇹🇷 Türkçe | ✅ | ✅ |
| 🇬🇧 İngilizce | ✅ | ✅ |
| 🇩🇪 Almanca | ✅ | ✅ |
| 🇫🇷 Fransızca | ✅ | ✅ |
| 🇪🇸 İspanyolca | ✅ | ✅ |
| 🇮🇹 İtalyanca | ✅ | ✅ |
| 🇵🇹 Portekizce | ✅ | ✅ |
| 🇯🇵 Japonca | ✅ | ✅ |

### 🚀 Hızlı Başlangıç

#### macOS (Apple Silicon GPU - MPS)

```bash
# 1. Repoyu klonla
git clone <repo-url>
cd tailadmin-voice-core

# 2. macOS başlatma script'ini çalıştır
./start-macos.sh

# Masaüstü kısayolu oluştur (isteğe bağlı)
./start-macos.sh --create-shortcut
```

#### Windows (NVIDIA GPU - CUDA)

```powershell
# 1. Repoyu klonla
git clone <repo-url>
cd tailadmin-voice-core

# 2. Docker ile başlat
docker compose up -d
```

#### Linux (NVIDIA GPU - CUDA)

```bash
# 1. Repoyu klonla
git clone <repo-url>
cd tailadmin-voice-core

# 2. Docker ile başlat
docker compose up -d
```

### 🐳 Docker ile Çalıştırma

#### Avantajlar
- ✅ Tutarlı ortam (dependency çakışmaları yok)
- ✅ Kolay kurulum (tek komut)
- ✅ İzole çalışma ortamı
- ✅ NVIDIA GPU desteği (Linux/Windows)

#### Dezavantajlar
- ❌ macOS'te GPU desteği yok (Docker Desktop sınırlaması)
- ❌ Daha fazla disk kullanımı (image boyutu)
- ❌ Container içinde debug zorluğu

**macOS Docker kullanıcıları:** GPU yerine CPU modunda çalışır:
```bash
docker compose -f docker-compose.yml -f docker-compose.override.yml up -d
```

### 💻 Docker'siz Çalıştırma (Native)

#### Avantajlar
- ✅ macOS'te Apple Silicon GPU (MPS) desteği
- ✅ Daha hızlı başlangıç (container overhead yok)
- ✅ Kolay debug ve geliştirme
- ✅ Daha az disk kullanımı

#### Dezavantajlar
- ❌ Manuel dependency yönetimi
- ❌ Ortam farklılıkları ("bende çalışıyor" sorunu)
- ❌ Manuel Python kurulumu gerekli

### 📋 Sistem Gereksinimleri

| Bileşen | Minimum | Önerilen |
|---------|---------|----------|
| **CPU** | 4 çekirdek | 8+ çekirdek |
| **RAM** | 8 GB | 16+ GB |
| **GPU** | - | NVIDIA (CUDA) veya Apple Silicon (MPS) |
| **Disk** | 10 GB | 50+ GB (modeller için) |
| **Python** | 3.10 | 3.10 - 3.11 |

### 🔌 Varsayılan Portlar

| Servis | Port | Açıklama |
|--------|------|----------|
| API | 5001 | FastAPI backend |
| UI | 5002 | Web arayüzü |

### 🔐 Varsayılan Giriş Bilgileri

Uygulama ilk başlatıldığında otomatik olarak oluşturulan varsayılan kullanıcı:

| Alan | Değer |
|------|-------|
| **Kullanıcı Adı** | `tailadmin.dev` |
| **Şifre** | `admin` |

> ⚠️ **Güvenlik:** İlk girişten sonra şifrenizi değiştirmeniz önemle tavsiye edilir!

### 📚 Dokümantasyon

- [DOCKER.md](DOCKER.md) - Docker kurulum ve kullanım kılavuzu
- [project.md](project.md) - Detaylı proje dokümantasyonu

---

<a name="english"></a>
## 🇬🇧 English

TailAdmin Voice Core is a locally-running web application that combines multilingual voice cloning, text-to-speech (TTS), and speech-to-text (STT) capabilities.

### 🎯 Features

| Feature | Description |
|---------|-------------|
| **🎙️ Multiple TTS Engines** | XTTS v2 (default), Bark, Tortoise TTS |
| **🎤 STT** | Whisper (optimized with faster-whisper) |
| **🎭 Voice Profiles** | Personalized voice generation with audio samples |
| **⚡ GPU Acceleration** | CUDA (NVIDIA), MPS (Apple Silicon), CPU support |
| **📦 Model Management** | Download and manage models from within the app |
| **⏱️ Task Queue** | Batch processing support |
| **🔐 JWT Authentication** | Secure API access |
| **🎛️ Web Interface** | Modern admin panel based on TailAdmin |

### 🌐 Supported Languages

| Language | TTS (XTTS v2) | STT (Whisper) |
|----------|---------------|---------------|
| 🇹🇷 Turkish | ✅ | ✅ |
| 🇬🇧 English | ✅ | ✅ |
| 🇩🇪 German | ✅ | ✅ |
| 🇫🇷 French | ✅ | ✅ |
| 🇪🇸 Spanish | ✅ | ✅ |
| 🇮🇹 Italian | ✅ | ✅ |
| 🇵🇹 Portuguese | ✅ | ✅ |
| 🇯🇵 Japanese | ✅ | ✅ |

### 🚀 Quick Start

#### macOS (Apple Silicon GPU - MPS)

```bash
# 1. Clone the repo
git clone <repo-url>
cd tailadmin-voice-core

# 2. Run macOS startup script
./start-macos.sh

# Create Desktop shortcut (optional)
./start-macos.sh --create-shortcut
```

#### Windows (NVIDIA GPU - CUDA)

```powershell
# 1. Clone the repo
git clone <repo-url>
cd tailadmin-voice-core

# 2. Start with Docker
docker compose up -d
```

#### Linux (NVIDIA GPU - CUDA)

```bash
# 1. Clone the repo
git clone <repo-url>
cd tailadmin-voice-core

# 2. Start with Docker
docker compose up -d
```

### 🐳 Running with Docker

#### Advantages
- ✅ Consistent environment (no dependency conflicts)
- ✅ Easy setup (single command)
- ✅ Isolated runtime environment
- ✅ NVIDIA GPU support (Linux/Windows)

#### Disadvantages
- ❌ No GPU support on macOS (Docker Desktop limitation)
- ❌ Higher disk usage (image size)
- ❌ Harder to debug inside container

**macOS Docker users:** Runs in CPU mode instead of GPU:
```bash
docker compose -f docker-compose.yml -f docker-compose.override.yml up -d
```

### 💻 Running without Docker (Native)

#### Advantages
- ✅ Apple Silicon GPU (MPS) support on macOS
- ✅ Faster startup (no container overhead)
- ✅ Easy debugging and development
- ✅ Lower disk usage

#### Disadvantages
- ❌ Manual dependency management
- ❌ Environment differences ("works on my machine" issues)
- ❌ Requires manual Python installation

### 📋 System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **CPU** | 4 cores | 8+ cores |
| **RAM** | 8 GB | 16+ GB |
| **GPU** | - | NVIDIA (CUDA) or Apple Silicon (MPS) |
| **Disk** | 10 GB | 50+ GB (for models) |
| **Python** | 3.10 | 3.10 - 3.11 |

### 🔌 Default Ports

| Service | Port | Description |
|---------|------|-------------|
| API | 5001 | FastAPI backend |
| UI | 5002 | Web interface |

### 🔐 Default Login Credentials

Default user automatically created on first startup:

| Field | Value |
|-------|-------|
| **Username** | `tailadmin.dev` |
| **Password** | `admin` |

> ⚠️ **Security:** Please change your password after the first login!

### 📚 Documentation

- [DOCKER.md](DOCKER.md) - Docker setup and usage guide
- [project.md](project.md) - Detailed project documentation

---

## 🔗 Links & Contact

- **Website:** [https://tailadmin.dev](https://tailadmin.dev)
- **Contact:** [contact@tailadmin.dev](mailto:contact@tailadmin.dev)

---
License
------
The TailAdmin is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

<a href="https://ko-fi.com/sinanaydogan" target="_blank">
    <img src="https://ko-fi.com/img/githubbutton_sm.svg">
</a>

<a href="https://www.buymeacoffee.com/sinanaydogan" target="_blank">
    <img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 40px !important; !important;" >
</a>

---

<p align="center">
  Made with ❤️ by TailAdmin Team
</p>
