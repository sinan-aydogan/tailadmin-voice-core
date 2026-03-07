# Docker ile Voice Core Kullanımı

Bu doküman, Voice Core projesini Docker ile GPU desteğiyle çalıştırma talimatlarını içerir.

> **macOS Kullanıcıları İçin Önemli Not:** Docker Desktop macOS'te GPU passthrough desteklemez. Apple Silicon (M1/M2/M3) GPU kullanmak için [macOS Native Çalıştırma](#macos-native-çalıştırma-apple-silicon-gpu) bölümüne bakın.

## Ön Koşullar

### 1. NVIDIA Docker Runtime (GPU Desteği İçin Zorunlu)

Docker container içinde GPU kullanabilmek için NVIDIA Container Toolkit kurulu olmalıdır:

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit
sudo systemctl restart docker

# Diğer sistemler için:
# https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html
```

Kurulumu test etmek için:
```bash
docker run --rm --gpus all nvidia/cuda:12.1.0-base-ubuntu22.04 nvidia-smi
```

### 2. Docker Compose v2+

GPU desteği için Docker Compose 2.0+ gereklidir:
```bash
docker compose version  # v2.x.x olmalı
```

## Hızlı Başlangıç

### 1. Ortam Değişkenlerini Ayarla

`.env` dosyasını kopyala ve düzenle:
```bash
cp .env.example .env
```

GPU kullanımı için önemli ayarlar:
```env
# GPU Yapılandırması
USE_GPU=auto          # auto, cuda, cuda:0, cuda:1, cpu
CUDA_VISIBLE_DEVICES=0  # Hangi GPU'yu kullanacağı (0, 1, 0,1, all)

# Bellek Optimizasyonu
PYTORCH_ENABLE_MPS_FALLBACK=1
```

### 2. Container'ı Başlat

```bash
# Tüm servisleri başlat (API + UI)
docker compose up -d

# Sadece API'yi başlat
docker compose up -d voice-core-api

# Logları izle
docker compose logs -f voice-core-api
```

### 3. Erişim

- API: http://localhost:5001
- Frontend: http://localhost:5002
- Health Check: http://localhost:5001/health

## GPU Doğrulama

Container içinde GPU'nun göründüğünü doğrula:

```bash
# Container içine gir
docker exec -it voice-core-api bash

# PyTorch ile GPU kontrolü
python -c "import torch; print(f'CUDA Available: {torch.cuda.is_available()}'); print(f'CUDA Device: {torch.cuda.get_device_name(0) if torch.cuda.is_available() else \"N/A\"}')"

# nvidia-smi ile GPU durumu
nvidia-smi
```

## Yönetim Komutları

```bash
# Container'ları durdur
docker compose down

# Container'ları ve volumeleri sil (veriler silinmez - ./data mount edildi)
docker compose down -v

# Image'ı yeniden build et
docker compose build --no-cache

# Container'ı yeniden başlat
docker compose restart

# Container içinde shell aç
docker exec -it voice-core-api bash
```

## Sorun Giderme

### GPU Görünmüyor

1. NVIDIA Container Toolkit kurulu mu kontrol et:
   ```bash
   docker run --rm --gpus all nvidia/cuda:12.1.0-base-ubuntu22.04 nvidia-smi
   ```

2. Docker daemon'ı yeniden başlat:
   ```bash
   sudo systemctl restart docker
   ```

3. docker-compose.yml içinde GPU bölümü aktif mi kontrol et:
   ```yaml
   deploy:
     resources:
       reservations:
         devices:
           - driver: nvidia
             count: all
             capabilities: [gpu]
   ```

### CUDA Out of Memory

`.env` dosyasına ekle:
```env
PYTORCH_CUDA_ALLOC_CONF=max_split_size_mb:512
```

Veya docker-compose.yml içinde environment bölümüne ekle.

### Model İndirme Sorunları

HuggingFace token gerekiyorsa `.env` dosyasına ekle:
```env
HF_TOKEN=your_token_here
```

## Performans Optimizasyonu

### Bellek Kullanımı

Büyük modeller için shared memory artırın:
```yaml
services:
  voice-core-api:
    shm_size: '8gb'  # docker-compose.yml içine ekle
```

### Çoklu GPU

Birden fazla GPU kullanmak için:
```env
CUDA_VISIBLE_DEVICES=0,1
```

veya docker-compose.yml içinde:
```yaml
deploy:
  resources:
    reservations:
      devices:
        - driver: nvidia
          device_ids: ['0', '1']
          capabilities: [gpu]
```

## Veri Kalıcılığı

Aşağıdaki dizinler host makineye mount edilir:
- `./data` - Modeller, veritabanı, çıktılar
- `./logs` - Uygulama logları

Container silinse bile veriler korunur.

## macOS Native Çalıştırma (Apple Silicon GPU)

Docker Desktop macOS'te GPU passthrough desteklemediği için, Apple Silicon (M1/M2/M3) GPU'yu kullanmak için uygulamayı **native** olarak çalıştırmanız gerekir.

### Hızlı Başlangıç (macOS)

```bash
# 1. start-macos.sh script'ini çalıştır
./start-macos.sh
```

Bu script otomatik olarak:
- Python 3 ve virtual environment kontrolü yapar
- Gerekli bağımlılıkları kurar
- MPS (Metal Performance Shaders) GPU desteğini kontrol eder
- Uygulamayı Apple Silicon GPU ile başlatır

### Manuel Kurulum (macOS)

```bash
# 1. Virtual environment oluştur
python3 -m venv .venv
source .venv/bin/activate

# 2. Bağımlılıkları kur
pip install -r requirements.txt

# 3. .env dosyasını hazırla
cp .env.example .env

# 4. Uygulamayı başlat
export USE_GPU=auto
export PYTORCH_ENABLE_MPS_FALLBACK=1
python3 main.py
```

### GPU Doğrulama (macOS)

```bash
# MPS kullanılabilirliğini kontrol et
python3 -c "import torch; print(f'MPS Available: {torch.backends.mps.is_available()}')"
```

### macOS Docker (CPU Only)

Eğer macOS'te Docker kullanmak isterseniz (CPU modunda):

```bash
# Override dosyası ile çalıştır (GPU'suz)
docker compose -f docker-compose.yml -f docker-compose.override.yml up -d
```

## Güvenlik Notları

- `.env` dosyası container'a read-only olarak mount edilir (`:ro`)
- Üretim ortamında `SECRET_KEY` ve `DEFAULT_PASSWORD` değiştirilmelidir
- API portunu (5001) sadece gerekirse dışarı açın
