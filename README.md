# tailadmin-voice-core

Çok dilli ses klonlama, TTS (Metin → Ses) ve STT (Ses → Metin) işlevlerini bir arada sunan yerel çalışan web uygulaması.

## Özellikler
- **Çoklu TTS:** XTTS v2, Bark, Tortoise TTS
- **STT:** Whisper (faster-whisper)
- **Donanım Algılama:** CUDA, Apple MPS (M serisi), CPU
- **Portlar Bağımsız:** API (5001) / Arayüz (5002)

## Kurulum

```bash
# 1. Repoyu klonla ve dizine gir
cd tailadmin-voice-core

# 2. Virtual env oluştur (opsiyonel ama önerilir)
python -m venv venv
source venv/bin/activate  # Mac/Linux
# venv\Scripts\activate   # Windows

# 3. Bağımlılıkları yükle
pip install -r requirements.txt

# 4. Çevre değişkenlerini yapılandır
cp .env.example .env

# 5. Başlat
python main.py             # API (5001)
python frontend_server.py  # UI (5002)
```

## Dokümantasyon
Daha fazla detay için [project.md](project.md) dosyasına bakabilirsiniz.
