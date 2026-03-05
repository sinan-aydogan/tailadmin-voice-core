# tailadmin-voice-core — Project Documentation

> **Durum:** 🚧 Geliştirme Aşamasında  
> **Son Güncelleme:** 2026-03-05  
> **Editör Uyumu:** Cursor, Antigravity

---

## 📌 Proje Özeti

Çok dilli ses klonlama, TTS (Metin → Ses) ve STT (Ses → Metin) işlevlerini bir arada sunan yerel çalışan (local) bir web uygulaması. Hem macOS hem Windows üzerinde yalnızca Python komutuyla başlar. API ve arayüz birbirinden bağımsız portlarda yayınlanır.

---

## 🎯 Temel Kararlar

| Konu | Karar | Gerekçe |
|---|---|---|
| TTS Modelleri | XTTS v2 (varsayılan), Bark, Tortoise TTS | Türkçe desteği + çoklu model esnekliği |
| STT Modeli | Whisper (varsayılan) + çoklu model altyapısı | Hız ve doğruluk dengesi |
| Veritabanı | SQLite + SQLAlchemy | Kurulum gerektirmeyen, yerel çalışma ortamına uygun |
| API Port | `5001` | FastAPI (uvicorn) |
| UI Port | `5002` | Ayrı frontend sunucusu |
| Kimlik Doğrulama | JWT tabanlı, kullanıcı adı/şifre | Varsayılan: `tailadmin.dev` / `admin` |
| Async Strateji | `asyncio.run_in_executor` + `BackgroundTasks` | 504 timeout sorunlarının önlenmesi |
| Model Dağıtımı | Uygulama içinde gelmiyor, arayüzden indirilir | Büyük model dosyalarını repo dışında tutmak için |
| GPU Cihaz Tespiti | Otomatik (`auto`): CUDA → MPS → CPU | macOS M-serisi + NVIDIA + CPU cross-platform |
| Ayarlar Yönetimi | Tüm `.env` ayarları arayüzden tab'lı panelde düzenlenebilir | Kullanıcı `.env` dosyasıyla uğraşmaz |

---

## 🏗️ Teknoloji Yığını

### Backend
| Paket | Versiyon | Amaç |
|---|---|---|
| Python | 3.10+ | Çalışma ortamı |
| FastAPI | ≥0.110 | REST API framework |
| Uvicorn | ≥0.27 | ASGI sunucu (port 5001) |
| SQLAlchemy | ≥2.0 | ORM + SQLite bağlantısı |
| Pydantic | v2 | Schema doğrulama |
| python-jose | ≥3.3 | JWT token üretimi/doğrulaması |
| passlib[bcrypt] | ≥1.7 | Şifre hash'leme |
| python-multipart | latest | Dosya yükleme desteği |
| aiofiles | latest | Async dosya okuma/yazma |
| httpx | latest | Async HTTP (model indirme) |
| tqdm | latest | İndirme ilerleme yönetimi |

### TTS Motorları
| Paket | Versiyon | Amaç |
|---|---|---|
| TTS (Coqui) | ≥0.22 | XTTS v2 motoru |
| bark | latest (GitHub) | Bark TTS motoru |
| tortoise-tts | latest (GitHub) | Tortoise TTS motoru |
| torch | ≥2.1 (cu118/cpu) | Tüm modellerin çalışma altyapısı |
| torchaudio | ≥2.1 | Ses işleme |

### STT Motorları
| Paket | Versiyon | Amaç |
|---|---|---|
| faster-whisper | ≥1.0 | Whisper (CTranslate2 tabanlı, hızlı) |
| openai-whisper | latest | Whisper alternatif backend |

### Frontend
| Teknoloji | Versiyon | Amaç |
|---|---|---|
| TailAdmin | v2 (Free) | Admin panel template (Tailwind + JS) |
| Tailwind CSS | v3 | UI framework |
| Alpine.js | v3 | Reaktif UI etkileşimleri |
| Port | `5002` | Ayrı sunucu üzerinden yayın |

### Diğer
| Paket | Amaç |
|---|---|
| python-dotenv | `.env` yönetimi |
| loguru | Gelişmiş loglama |
| psutil | CPU/RAM izleme |
| humanize | Boyut/süre formatlama |

---

## 📁 Klasör Yapısı

```
tailadmin-voice-core/
│
├── project.md                  # Bu dosya — proje ana dökümanı
├── main.py                     # API sunucusu başlatıcı (port 5001)
├── frontend_server.py          # UI sunucusu başlatıcı (port 5002)
├── requirements.txt
├── .env.example
├── .env                        # (gitignore'da)
├── README.md
│
├── app/                        # Backend uygulama kodu
│   ├── __init__.py
│   ├── config.py               # Uygulama konfigürasyonu (.env okuyucu)
│   ├── database.py             # SQLAlchemy engine + session yönetimi
│   │
│   ├── core/                   # Paylaşılan yardımcı modüller
│   │   ├── __init__.py
│   │   ├── device.py           # GPU/CPU otomatik cihaz tespiti (CUDA/MPS/CPU)
│   │   └── exceptions.py       # Özel hata sınıfları
│   │
│   ├── auth/                   # Kimlik doğrulama
│   │   ├── __init__.py
│   │   ├── router.py           # /auth/ endpoint'leri
│   │   ├── service.py          # Login, token üretme
│   │   └── dependencies.py     # get_current_user dependency
│   │
│   ├── models/                 # SQLAlchemy ORM modelleri
│   │   ├── __init__.py
│   │   ├── user.py
│   │   ├── profile.py
│   │   ├── tts_output.py
│   │   ├── stt_result.py
│   │   ├── task.py             # Kuyruk görevleri
│   │   └── model_download.py   # İndirme durumları
│   │
│   ├── schemas/                # Pydantic şemaları
│   │   ├── __init__.py
│   │   ├── auth.py
│   │   ├── profile.py
│   │   ├── tts.py
│   │   ├── stt.py
│   │   ├── queue.py
│   │   └── model.py
│   │
│   ├── tts/                    # TTS motorları (Repository Pattern)
│   │   ├── __init__.py
│   │   ├── base.py             # Abstract TTSEngine sınıfı
│   │   ├── xtts.py             # XTTS v2 implementasyonu (varsayılan)
│   │   ├── bark.py             # Bark implementasyonu
│   │   ├── tortoise.py         # Tortoise TTS implementasyonu
│   │   ├── registry.py         # Engine factory + kayıt defteri
│   │   └── router.py           # /tts/ endpoint'leri
│   │
│   ├── stt/                    # STT motorları (Repository Pattern)
│   │   ├── __init__.py
│   │   ├── base.py             # Abstract STTEngine sınıfı
│   │   ├── whisper.py          # Whisper implementasyonu (varsayılan)
│   │   ├── registry.py         # Engine factory + kayıt defteri
│   │   └── router.py           # /stt/ endpoint'leri
│   │
│   ├── profiles/               # Ses profili yönetimi
│   │   ├── __init__.py
│   │   ├── service.py          # CRUD operasyonları
│   │   └── router.py           # /profiles/ endpoint'leri
│   │
│   ├── downloader/             # Model indirme yöneticisi
│   │   ├── __init__.py
│   │   ├── download_manager.py # İndirme, ilerleme, doğrulama, onarım
│   │   ├── model_catalog.py    # İndirilebilir modeller kataloğu
│   │   └── router.py           # /models/ endpoint'leri
│   │
│   ├── queue/                  # Görev kuyruğu
│   │   ├── __init__.py
│   │   ├── task_queue.py       # SQLite tabanlı kuyruk yönetimi
│   │   ├── worker.py           # Background worker (asyncio executor)
│   │   └── router.py           # /queue/ endpoint'leri
│   │
│   ├── settings/               # Sistem ayarları
│   │   ├── __init__.py
│   │   ├── service.py          # Ayar okuma/yazma, .env sync, DB persistance
│   │   └── router.py           # /settings/ endpoint'leri
│   │
│   └── logs/                   # Log yönetimi
│       ├── __init__.py
│       └── router.py           # /logs/ endpoint'leri
│
├── static/                     # Frontend dosyaları (TailAdmin)
│   ├── index.html              # Login sayfası
│   ├── dashboard.html
│   ├── tts.html
│   ├── stt.html
│   ├── profiles.html
│   ├── models.html
│   ├── queue.html
│   ├── settings.html
│   ├── css/
│   ├── js/
│   │   ├── api.js              # API istemcisi (port 5001)
│   │   ├── auth.js             # JWT token yönetimi
│   │   └── ...
│   └── assets/
│
├── data/                       # Uygulama verileri (gitignore'da)
│   ├── voice_core.db           # SQLite veritabanı
│   ├── profiles/               # Profil ses dosyaları
│   ├── outputs/tts/            # TTS çıktıları
│   ├── outputs/stt/            # STT sonuçları
│   ├── uploads/                # Kullanıcı yüklemeleri (geçici)
│   └── models/                 # İndirilen model dosyaları
│       ├── tts/xtts_v2/
│       ├── tts/bark/
│       ├── tts/tortoise/
│       └── stt/whisper/
│
├── tests/
│   ├── __init__.py
│   ├── test_tts.py
│   ├── test_stt.py
│   ├── test_profiles.py
│   ├── test_downloader.py
│   └── test_queue.py
│
└── logs/                       # Uygulama log dosyaları
    └── app.log
```

---

## 🔌 API Endpoint'leri (Port 5001)

### Auth `/auth`
| Method | Endpoint | Açıklama | Auth |
|---|---|---|---|
| POST | `/auth/login` | Giriş, JWT token döner | ❌ |
| POST | `/auth/refresh` | Token yenileme | ✅ |
| GET | `/auth/me` | Mevcut kullanıcı bilgisi | ✅ |

### TTS `/tts`
| Method | Endpoint | Açıklama | Auth |
|---|---|---|---|
| POST | `/tts/generate` | Metin → Ses (model + profil seçilebilir) | ✅ |
| POST | `/tts/batch` | Toplu TTS kuyruğa ekler | ✅ |
| GET | `/tts/outputs` | TTS çıktı listesi | ✅ |
| GET | `/tts/outputs/{id}` | Tek çıktı detayı | ✅ |
| GET | `/tts/outputs/{id}/download` | Ses dosyası indir | ✅ |
| PUT | `/tts/outputs/{id}` | Meta veri güncelle | ✅ |
| DELETE | `/tts/outputs/{id}` | Çıktıyı sil | ✅ |
| GET | `/tts/engines` | Kullanılabilir TTS motorları | ✅ |

### STT `/stt`
| Method | Endpoint | Açıklama | Auth |
|---|---|---|---|
| POST | `/stt/transcribe` | Ses dosyası → Metin | ✅ |
| POST | `/stt/batch` | Toplu STT kuyruğa ekler | ✅ |
| GET | `/stt/results` | STT sonuç listesi | ✅ |
| GET | `/stt/results/{id}` | Tek sonuç detayı | ✅ |
| PUT | `/stt/results/{id}` | Sonucu düzenle | ✅ |
| DELETE | `/stt/results/{id}` | Sonucu sil | ✅ |
| GET | `/stt/engines` | Kullanılabilir STT motorları | ✅ |

### Profiller `/profiles`
| Method | Endpoint | Açıklama | Auth |
|---|---|---|---|
| GET | `/profiles` | Profil listesi (filtreli) | ✅ |
| POST | `/profiles` | Yeni profil oluştur | ✅ |
| GET | `/profiles/{id}` | Profil detayı | ✅ |
| PUT | `/profiles/{id}` | Profil güncelle | ✅ |
| DELETE | `/profiles/{id}` | Profil sil | ✅ |
| POST | `/profiles/{id}/audio` | Ses örneği yükle | ✅ |
| GET | `/profiles/{id}/audio` | Ses örneğini indir | ✅ |

### Modeller `/models`
| Method | Endpoint | Açıklama | Auth |
|---|---|---|---|
| GET | `/models/catalog` | İndirilebilir model kataloğu | ✅ |
| GET | `/models/installed` | Yüklü modeller | ✅ |
| POST | `/models/{model_id}/download` | İndirme başlat | ✅ |
| GET | `/models/{model_id}/status` | İndirme durumu/ilerlemesi | ✅ |
| POST | `/models/{model_id}/cancel` | İndirmeyi iptal et | ✅ |
| POST | `/models/{model_id}/verify` | Model bütünlüğünü doğrula | ✅ |
| POST | `/models/{model_id}/repair` | Bozuk modeli onar/yeniden indir | ✅ |
| DELETE | `/models/{model_id}` | Modeli sil | ✅ |
| GET | `/models/download-progress` | SSE: Tüm aktif indirmeler | ✅ |

### Kuyruk `/queue`
| Method | Endpoint | Açıklama | Auth |
|---|---|---|---|
| GET | `/queue` | Görev listesi | ✅ |
| GET | `/queue/{id}` | Görev detayı | ✅ |
| POST | `/queue/{id}/cancel` | Görevi iptal et | ✅ |
| POST | `/queue/{id}/retry` | Başarısız görevi yeniden başlat | ✅ |
| DELETE | `/queue/completed` | Tamamlananları temizle | ✅ |
| GET | `/queue/stream` | SSE: Canlı kuyruk güncellemeleri | ✅ |

### Ayarlar `/settings`
| Method | Endpoint | Açıklama | Auth |
|---|---|---|---|
| GET | `/settings` | Tüm ayarları (tab grupları ile) getir | ✅ |
| PUT | `/settings` | Ayarları güncelle (DB'ye yazar) | ✅ |
| POST | `/settings/reset` | Fabrika değerlerine sıfırla | ✅ |
| GET | `/settings/system` | Anlık sistem kaynakları (CPU/RAM/GPU/MPS) | ✅ |
| GET | `/settings/device-info` | Tespit edilen GPU/CPU cihaz bilgisi | ✅ |

### Loglar `/logs`
| Method | Endpoint | Açıklama | Auth |
|---|---|---|---|
| GET | `/logs` | İşlem geçmişi | ✅ |
| GET | `/logs/stream` | SSE: Canlı log akışı | ✅ |
| DELETE | `/logs` | Geçmişi temizle | ✅ |
| GET | `/logs/download` | Logları indir | ✅ |

---

## 🧩 Mimari Kararlar

### Repository Pattern — TTS & STT

Her TTS/STT motoru bir abstract base class'tan türer. Factory pattern ile motor seçimi yapılır. Yeni motor eklemek mevcut kodu değiştirmez.

```python
# tts/base.py
class TTSEngine(ABC):
    @abstractmethod
    async def generate(self, text: str, voice_profile: str, language: str, **kwargs) -> Path: ...

    @abstractmethod
    def is_available(self) -> bool: ...

    @property
    @abstractmethod
    def supported_languages(self) -> list[str]: ...

# tts/registry.py
ENGINES = {
    "xtts": XTTSEngine,       # varsayılan
    "bark": BarkEngine,
    "tortoise": TortoiseTTSEngine,
}
```

### GPU / Cihaz Otomatik Tespiti (`app/core/device.py`)

Tüm TTS/STT motorları başlangıçta bu modülü çağırır. Kullanıcı `.env` veya Ayarlar panelinden tercihini belirtebilir; varsayılan `auto`'dur.

```python
import torch

def detect_device(preferred: str = "auto") -> torch.device:
    """CUDA → MPS (Apple Silicon) → CPU öncelik sırasıyla en uygun cihazı seçer."""
    if preferred != "auto":
        return torch.device(preferred)
    if torch.cuda.is_available():
        return torch.device("cuda")      # NVIDIA GPU (Windows/Linux)
    elif torch.backends.mps.is_available():
        return torch.device("mps")       # Apple Silicon (M1/M2/M3/M4)
    return torch.device("cpu")
```

| Platform | Tespit Edilen Cihaz |
|---|---|
| macOS Apple Silicon (M1–M4) | `mps` |
| Windows/Linux + NVIDIA GPU | `cuda` |
| Diğer (GPU yok) | `cpu` |

> **Tortoise TTS için:** Bazı operasyonlar MPS'te desteklenmeyebilir. `PYTORCH_ENABLE_MPS_FALLBACK=1` env değişkeni açık tutulmalıdır.

### Async Non-Blocking (504 Önleme)

Tüm CPU/GPU yoğun işlemler (TTS üretimi, STT transkripsiyonu, model indirme) thread pool'da çalışır:

```python
result = await asyncio.get_event_loop().run_in_executor(
    executor,  # ThreadPoolExecutor
    blocking_tts_function,
    args
)
```

### Ayarlar Yönetimi — Panelden Tam Kontrol

Kullanıcı `.env` dosyasına dokunmak zorunda değildir. Tüm ayarlar `app_settings` tablosunda saklanır; `.env` yalnızca ilk başlatma için fallback kaynaktır. Uygulama her başladığında DB değerleri önceliklidir.

**Ayarlar Paneli Tab Yapısı (`settings.html`):**

| Tab | Ayarlar |
|---|---|
| 🔐 **Hesap** | Kullanıcı adı, şifre değiştirme |
| 🤖 **TTS** | Varsayılan motor, varsayılan dil, ses kalitesi |
| 🎙️ **STT** | Varsayılan motor, Whisper model boyutu, dil algılama |
| ⚡ **Donanım** | GPU modu (`auto/cpu/cuda/mps`), CPU thread limiti, MPS fallback |
| 📁 **Depolama** | Model dizini, çıktı dizini, profil dizini, upload dizini |
| 🔑 **Güvenlik** | Secret key, token süresi (dk) |
| 🌐 **Sunucu** | API portu, UI portu, CORS izinleri |
| 📋 **Log** | Log seviyesi, log tutma süresi, otomatik temizleme |

### Model İndirme Yöneticisi

- İndirme durumu SQLite'ta saklanır (`PENDING`, `DOWNLOADING`, `COMPLETED`, `FAILED`, `VERIFYING`)
- Yarıda kalan indirmeler `Range` header ile kaldığı yerden devam eder
- SHA256 checksum doğrulama
- Onarım = eksik/bozuk dosyaları silinerek yeniden indirir
- Canlı ilerleme SSE (Server-Sent Events) ile iletilir

### Kimlik Doğrulama

JWT tabanlı. Token süresi ayarlar panelinden değiştirilebilir (varsayılan: 60 dk).

```
Varsayılan:
  Kullanıcı adı: tailadmin.dev
  Şifre: admin
```

> ⚠️ **İlk Kurulumda:** Sistemi başlatınca varsayılan kullanıcı otomatik oluşturulur. Şifreyi **Ayarlar → Hesap** sekmesinden değiştirin.

---

## 🌐 Desteklenen Diller

| Dil | TTS (XTTS v2) | TTS (Bark) | TTS (Tortoise) | STT (Whisper) |
|---|---|---|---|---|
| Türkçe 🇹🇷 | ✅ (öncelik) | ✅ | ❌ | ✅ |
| İngilizce | ✅ | ✅ | ✅ | ✅ |
| Almanca | ✅ | ✅ | ❌ | ✅ |
| Fransızca | ✅ | ✅ | ❌ | ✅ |
| İspanyolca | ✅ | ✅ | ❌ | ✅ |
| İtalyanca | ✅ | ✅ | ❌ | ✅ |
| Portekizce | ✅ | ✅ | ❌ | ✅ |
| Japonca | ✅ | ✅ | ❌ | ✅ |

---

## ⚙️ Ortam Değişkenleri (`.env`)

> Bu değerler yalnızca **ilk başlatma** için varsayılandır. Uygulama çalıştıktan sonra tüm ayarlar **Ayarlar panelinden** yönetilir ve `app_settings` tablosuna kaydedilir.

```dotenv
# Uygulama
APP_ENV=development
SECRET_KEY=change-this-in-production
ACCESS_TOKEN_EXPIRE_MINUTES=60

# Portlar
API_PORT=5001
UI_PORT=5002

# Veritabanı
DATABASE_URL=sqlite:///./data/voice_core.db

# Varsayılan Kullanıcı (ilk başlatmada oluşturulur)
DEFAULT_USERNAME=tailadmin.dev
DEFAULT_PASSWORD=admin

# Model Yolları
MODELS_DIR=./data/models
OUTPUTS_DIR=./data/outputs
PROFILES_DIR=./data/profiles
UPLOADS_DIR=./data/uploads

# TTS Ayarları
DEFAULT_TTS_ENGINE=xtts
DEFAULT_TTS_LANGUAGE=tr

# STT Ayarları
DEFAULT_STT_ENGINE=whisper
DEFAULT_WHISPER_MODEL=medium

# Donanım / GPU Ayarları
USE_GPU=auto                        # auto | cpu | cuda | mps
MAX_CPU_THREADS=4
PYTORCH_ENABLE_MPS_FALLBACK=1      # Tortoise MPS hata toleransı

# Log
LOG_LEVEL=INFO
LOG_RETENTION_DAYS=30
```

---

## 🚦 Başlatma

### Kurulum
```bash
pip install -r requirements.txt
cp .env.example .env
# .env dosyasını düzenle
```

### API Sunucusu (port 5001)
```bash
python main.py
# veya
uvicorn main:app --host 0.0.0.0 --port 5001 --reload
```

### UI Sunucusu (port 5002)
```bash
python frontend_server.py
# veya
python -m http.server 5002 --directory static
```

### İkisini Birden Başlat
```bash
python start.py
```

### Swagger UI
```
http://localhost:5001/docs
```

---

## 🗄️ Veritabanı Şeması (SQLite)

### `users`
| Sütun | Tip | Açıklama |
|---|---|---|
| id | INTEGER PK | |
| username | TEXT UNIQUE | |
| hashed_password | TEXT | bcrypt |
| is_active | BOOLEAN | |
| created_at | DATETIME | |

### `voice_profiles`
| Sütun | Tip | Açıklama |
|---|---|---|
| id | INTEGER PK | |
| name | TEXT | |
| description | TEXT | |
| tags | TEXT | JSON array |
| audio_file_path | TEXT | Ses örneği yolu |
| language | TEXT | Varsayılan: `tr` |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### `tts_outputs`
| Sütun | Tip | Açıklama |
|---|---|---|
| id | INTEGER PK | |
| text | TEXT | Kaynak metin |
| engine | TEXT | `xtts`, `bark`, `tortoise` |
| profile_id | INTEGER FK | voice_profiles |
| language | TEXT | |
| output_path | TEXT | |
| duration_sec | REAL | |
| created_at | DATETIME | |

### `stt_results`
| Sütun | Tip | Açıklama |
|---|---|---|
| id | INTEGER PK | |
| input_path | TEXT | Kaynak ses dosyası |
| engine | TEXT | `whisper` |
| model_size | TEXT | `tiny`, `base`, `medium`, ... |
| transcript | TEXT | |
| language | TEXT | Tespit edilen dil |
| created_at | DATETIME | |

### `tasks` (Görev Kuyruğu)
| Sütun | Tip | Açıklama |
|---|---|---|
| id | INTEGER PK | |
| type | TEXT | `tts`, `stt` |
| status | TEXT | `pending`, `running`, `done`, `failed`, `cancelled` |
| payload | TEXT | JSON |
| result | TEXT | JSON (sonuç veya hata) |
| created_at | DATETIME | |
| started_at | DATETIME | |
| completed_at | DATETIME | |

### `model_downloads`
| Sütun | Tip | Açıklama |
|---|---|---|
| id | INTEGER PK | |
| model_id | TEXT UNIQUE | `xtts_v2`, `bark`, `whisper_medium`, ... |
| model_type | TEXT | `tts`, `stt` |
| status | TEXT | `pending`, `downloading`, `completed`, `failed`, `verifying` |
| progress_pct | REAL | 0–100 |
| downloaded_bytes | INTEGER | |
| total_bytes | INTEGER | |
| error_message | TEXT | |
| started_at | DATETIME | |
| completed_at | DATETIME | |

### `app_settings` (Ayarlar Paneli Persistance)
| Sütun | Tip | Açıklama |
|---|---|---|
| id | INTEGER PK | |
| tab | TEXT | `tts`, `stt`, `hardware`, `storage`, `security`, `server`, `log` |
| key | TEXT UNIQUE | Ayar anahtarı (ör: `use_gpu`, `default_tts_engine`) |
| value | TEXT | Ayar değeri (JSON string olabilir) |
| updated_at | DATETIME | |

---

## 📋 Geliştirme Planı

### MVP (Minimum Viable Product)
- [x] Proje dökümanı hazırlandı
- [ ] Klasör yapısı oluşturuldu
- [ ] SQLite + auth çalışıyor
- [ ] XTTS v2 ile Türkçe TTS döngüsü tamamlandı
- [ ] Whisper ile STT döngüsü tamamlandı
- [ ] Profil CRUD tamamlandı
- [ ] Model indirme yöneticisi çalışıyor
- [ ] Temel frontend (login + TTS sayfası)

### v1.0
- [ ] Bark ve Tortoise TTS entegrasyonu
- [ ] Toplu işlem kuyruğu
- [ ] Tam frontend (tüm sayfalar)
- [ ] Sistem kaynak izleme
- [ ] Testler tamamlandı

---

## 🔒 Güvenlik Notları

- Tüm API endpoint'leri (auth hariç) JWT doğrulaması gerektirir
- Şifreler bcrypt ile hash'lenir, düz metin olarak saklanmaz
- `.env` dosyası mutlaka `.gitignore`'da olmalı
- `SECRET_KEY` production ortamında değiştirilmeli
- Model dosyaları ve kullanıcı yüklemeleri `data/` altında, web root dışında saklanır

---

## 📝 Geliştirici Notları

- Tüm dosya yollarında `pathlib.Path` kullan (`os.path` değil)
- Kod stili: `Black` formatter + `flake8`
- Tüm fonksiyonlarda docstring zorunlu
- Hata mesajları Türkçe değil İngilizce olacak (log analizi kolaylığı için)
- Her yeni TTS/STT motoru `base.py` abstract class'ından türetilmeli
- `requirements.txt` her yeni bağımlılıkta güncellenmeli
