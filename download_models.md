# Model İndirme Komutları

Bu dosya, Voice Core projesinde kullanılan tüm modellerin HuggingFace CLI ile indirme komutlarını içerir.

## HuggingFace CLI Kurulumu

```bash
pip install huggingface-hub
```

## HF_TOKEN Ayarlama (Önerilir - Rate Limit için)

```bash
huggingface-cli login
# veya
export HF_TOKEN=your_token_here
```

---

## TTS Modelleri

### Coqui XTTS v2
```bash
huggingface-cli download coqui/XTTS-v2 \
  --local-dir ./data/models/xtts-v2 \
  --local-dir-use-symlinks False
```

### Suno Bark (Small)
```bash
huggingface-cli download suno/bark-small \
  --local-dir ./data/models/bark \
  --local-dir-use-symlinks False
```

### Tortoise TTS (Devre Dışı)
```bash
huggingface-cli download Manmay/tortoise-tts \
  --local-dir ./data/models/tortoise \
  --local-dir-use-symlinks False
```

---

## MusicGen Modelleri

### MusicGen Small (1.5 GB)
```bash
huggingface-cli download facebook/musicgen-small \
  --local-dir ./data/models/musicgen-small \
  --local-dir-use-symlinks False
```

### MusicGen Medium (3.5 GB)
```bash
huggingface-cli download facebook/musicgen-medium \
  --local-dir ./data/models/musicgen-medium \
  --local-dir-use-symlinks False
```

### MusicGen Large (8 GB)
```bash
huggingface-cli download facebook/musicgen-large \
  --local-dir ./data/models/musicgen-large \
  --local-dir-use-symlinks False
```

### MusicGen Melody (3.5 GB)
```bash
huggingface-cli download facebook/musicgen-melody \
  --local-dir ./data/models/musicgen-melody \
  --local-dir-use-symlinks False
```

---

## STT Modelleri (Faster Whisper)

### Whisper Tiny
```bash
huggingface-cli download Systran/faster-whisper-tiny \
  --local-dir ./data/models/whisper-tiny \
  --local-dir-use-symlinks False
```

### Whisper Base
```bash
huggingface-cli download Systran/faster-whisper-base \
  --local-dir ./data/models/whisper-base \
  --local-dir-use-symlinks False
```

### Whisper Small
```bash
huggingface-cli download Systran/faster-whisper-small \
  --local-dir ./data/models/whisper-small \
  --local-dir-use-symlinks False
```

### Whisper Medium
```bash
huggingface-cli download Systran/faster-whisper-medium \
  --local-dir ./data/models/whisper-medium \
  --local-dir-use-symlinks False
```

### Whisper Large v3
```bash
huggingface-cli download Systran/faster-whisper-large-v3 \
  --local-dir ./data/models/whisper-large-v3 \
  --local-dir-use-symlinks False
```

---

## Tüm Modelleri Tek Seferde İndir

```bash
#!/bin/bash

# TTS Models
echo "İndiriliyor: XTTS v2"
huggingface-cli download coqui/XTTS-v2 --local-dir ./data/models/xtts-v2 --local-dir-use-symlinks False

echo "İndiriliyor: Bark"
huggingface-cli download suno/bark-small --local-dir ./data/models/bark --local-dir-use-symlinks False

# MusicGen Models
echo "İndiriliyor: MusicGen Small"
huggingface-cli download facebook/musicgen-small --local-dir ./data/models/musicgen-small --local-dir-use-symlinks False

echo "İndiriliyor: MusicGen Medium"
huggingface-cli download facebook/musicgen-medium --local-dir ./data/models/musicgen-medium --local-dir-use-symlinks False

echo "İndiriliyor: MusicGen Large"
huggingface-cli download facebook/musicgen-large --local-dir ./data/models/musicgen-large --local-dir-use-symlinks False

echo "İndiriliyor: MusicGen Melody"
huggingface-cli download facebook/musicgen-melody --local-dir ./data/models/musicgen-melody --local-dir-use-symlinks False

# STT Models
echo "İndiriliyor: Whisper Small"
huggingface-cli download Systran/faster-whisper-small --local-dir ./data/models/whisper-small --local-dir-use-symlinks False

echo "İndiriliyor: Whisper Medium"
huggingface-cli download Systran/faster-whisper-medium --local-dir ./data/models/whisper-medium --local-dir-use-symlinks False

echo "Tüm modeller indirildi!"
```

---

## Notlar

- `--local-dir-use-symlinks False`: Dosyaları sembolik link olarak değil, gerçek dosya olarak indirir
- İndirme boyutları toplamda ~20-30 GB olabilir
- İlk indirme sonrası modeller `data/models/` klasöründe saklanır
- Model Yöneticisi'nden indirme başarısız olursa bu komutları kullanın
