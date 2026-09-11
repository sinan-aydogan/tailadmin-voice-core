# 🐳 Docker ile TailAdmin Voice Core Sunucu Dağıtımı

Bu kılavuz, **TailAdmin Voice Core** projesini bir sunucuya (VPS, bulut sanal makinesi veya yerel sunucu) tek bir komutla %100 Dockerize edilmiş olarak kurma ve çalıştırma talimatlarını içerir.

Sistem, **harici hiçbir bağımlılığa (PHP, Composer, Node.js vb.) gerek duymadan**, Web arayüzü, Laravel kuyruk işleyicisi ve PyTorch/CUDA destekli Python AI motorunu birlikte ayağa kaldırır.

---

## 🏗️ Mimari ve Servisler

Docker Compose iki izole ve optimize servis çalıştırır:

1. **`voice-core-app` (Port 8000):**
   * Web Yönetim Arayüzü (Inertia Vue 3 + Tailwind CSS).
   * RESTful API v1 (`/api/v1/...`).
   * Dahili Laravel Queue Worker (görev işleyici).
   * SQLite veritabanı (WAL modu).
2. **`voice-core-engine` (Port 5001):**
   * PyTorch 2.1.0 + CUDA 12.1 runtime.
   * Piper TTS, XTTS v2, Bark, Tortoise TTS, Faster-Whisper.
   * Model indirme ve bellek yöneticisi.

---

## ⚡ Hızlı Başlangıç

### 1. Depoyu İndirin ve `.env` Dosyasını Hazırlayın

```bash
git clone https://github.com/sinan-aydogan/tailadmin-voice-core.git
cd tailadmin-voice-core

# Örnek konfigürasyon dosyasını kopyalayın
cp .env.example .env
```

`.env` dosyasındaki önemli ayarlar:
```env
# Dışarıya açılacak Web & API portu (Varsayılan: 8000)
PORT=8000

# REST API güvenliği için gizli anahtar (Boş bırakılırsa yerel/açık modda çalışır)
VOICE_CORE_API_KEY=super-secret-token-12345

# GPU modu: auto, cuda, cpu
USE_GPU=auto
CUDA_VISIBLE_DEVICES=0
```

---

### 2. Başlatma Senaryoları

#### Senaryo A: NVIDIA GPU / CUDA Destekli Sunucularda Başlatma (Önerilen)

Sunucunuzda [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html) kurulu olmalıdır:

```bash
# Ubuntu / Debian için NVIDIA Toolkit kurulumu:
sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit
sudo systemctl restart docker

# Tüm sistemi GPU hızlandırmasıyla başlatın:
docker compose up -d --build
```

#### Senaryo B: CPU-Only Sunucularda veya macOS Docker Desktop Üzerinde Başlatma

Eğer sunucunuzda GPU yoksa veya macOS Docker Desktop kullanıyorsanız GPU rezervasyonunu kaldıran override dosyasını kullanın:

```bash
docker compose -f docker-compose.yml -f docker-compose.override.yml up -d --build
```

*(İpucu: Her seferinde `-f` yazmamak için terminalde `export COMPOSE_FILE=docker-compose.yml:docker-compose.override.yml` tanımlayabilirsiniz.)*

---

### 3. Sisteme Erişim ve Doğrulama

Container'lar ayağa kalktıktan sonra:

* 🌐 **Web Yönetim Arayüzü:** `http://<sunucu-ip>:8000`
* 🩺 **Sağlık Kontrolü:** `http://<sunucu-ip>:8000/api/v1/health`
* 📖 **REST API Referansı:** [API.md](file:///c:/Users/sinan/Projeler/tailadmin-voice-core/API.md)

Sağlık kontrolünü test edin:
```bash
curl http://localhost:8000/api/v1/health
```

**Beklenen Çıktı:**
```json
{
  "status": "ok",
  "app": "TailAdmin Voice Core",
  "version": "1.0.0",
  "services": {
    "api": "healthy",
    "python_engine": "online",
    "queue_worker": "running"
  }
}
```

---

## 💾 Kalıcı Veri (Volumes)

Container'lar silinse veya güncellense dahi verileriniz asla kaybolmaz. Aşağıdaki dizinler host makineye bağlıdır:

* `./data/models`: İndirilen yapay zeka modelleri (Hugging Face / Piper).
* `./data/outputs`: Üretilen ses kayıtları (WAV/MP3).
* `./data/profiles`: Ses klonlama referans ses dosyaları.
* `./database`: SQLite veritabanı dosyası (`database.sqlite`).
* `./storage`: Laravel uygulama logları ve oturum verileri.

---

## 🛠️ Yönetim ve Bakım Komutları

```bash
# Canlı logları izleme (Web + API)
docker compose logs -f voice-core-app

# Canlı logları izleme (Python AI Engine)
docker compose logs -f voice-core-engine

# Container'ları durdurma
docker compose down

# Kod değişikliklerinden sonra yeniden derleme ve başlatma
docker compose up -d --build

# Python container içinde GPU durumunu kontrol etme
docker exec -it voice-core-engine nvidia-smi
```

---

## 🔒 Üretim Ortamı (Nginx Reverse Proxy & SSL)

Sunucunuzda `domain.com` üzerinden güvenli erişim (HTTPS) sağlamak için örnek Nginx yapılandırması:

```nginx
server {
    listen 80;
    server_name ses.sirketiniz.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ses.sirketiniz.com;

    ssl_certificate /etc/letsencrypt/live/ses.sirketiniz.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ses.sirketiniz.com/privkey.pem;

    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # WebSocket ve Uzun TTS İstekleri için Zaman Aşımı
        proxy_read_timeout 600s;
        proxy_send_timeout 600s;
    }
}
```

---

## 🎛️ Sunucu Panelleriyle Kullanım (Coolify, CapRover, Easypanel, aaPanel vb.)

Modern sunucu yönetim panelleri üzerinden tek tıkla dağıtım yaparken:
* **Dockerfile Değişikliği Gerekmez:** Paneller uygulamanın önüne otomatik Nginx/Traefik Reverse Proxy koyar.
* **Port Ayarı:** Panel ayarlarındaki **Container Port** kutusuna yalnızca `8000` yazmanız yeterlidir. Dışarıya rastgele host portu açmanıza gerek kalmaz.
* **Domain & SSL:** Panelin arayüzünden domaininizi (`ses.alanadiniz.com`) eklediğinizde panel Let's Encrypt sertifikasını otomatik kurar. Laravel tarafında `trustProxies(at: '*')` aktif olduğu için SSL yönlendirmeleri sorunsuz çalışır.
* **Ortam Değişkenleri (.env):** Eğer sunucuda doğrudan port değiştirmek isterseniz panelin Environment Variables kısmına `PORT=8085` yazabilirsiniz.
