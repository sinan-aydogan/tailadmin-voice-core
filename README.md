# TailAdmin Voice Core (Native Desktop Edition)

Masaüstü yapay zeka ses yönetim istasyonu: **NativePHP Electron + Laravel 13 + Inertia (Vue 3) + @tailadmin/ui + İzole Python Motoru**.

---

## 🚀 Mimari ve Donma Çözümü

Önceki mimaride PyTorch inference ve dosya indirme işlemleri arayüzü ve API event loop'unu Python GIL (Global Interpreter Lock) nedeniyle kilitliyordu.

Yeni mimaride **tam süreç izolasyonu (Process Isolation)** uygulanmıştır:
1. **Masaüstü Arayüzü:** NativePHP (Electron) + Inertia.js (Vue 3) ve `@tailadmin/ui` bileşenleri ile 60 FPS akıcı ve responsive arayüz.
2. **Kuyruk Katmanı (Queue):** Laravel Database Queue Worker (`GenerateTtsJob`, `TranscribeSttJob`, `DownloadModelJob`).
3. **İzole Python AI Çekirdeği (`engine/`):** Python FastAPI mikroservisi veya izole CLI (`python -m app.cli`) olarak ayrı işletim sistemi sürecinde çalışır. PyTorch model yükleme ve çıkarımları UI thread'ine asla temas etmez.
4. **Gerçek Zamanlı Durum:** Inertia polling ve canlı sistem kaynakları altbilgi çubuğu (CPU, RAM, Disk, GPU/MPS/CUDA).

```
┌────────────────────────────────────────────────────────┐
│              NativePHP Electron Shell                  │
├────────────────────────────────────────────────────────┤
│           Inertia.js + Vue 3 (@tailadmin/ui)           │
│                 (60 FPS Akıcı UI)                      │
└───────────────────────────┬────────────────────────────┘
                            │ HTTP / Inertia
┌───────────────────────────▼────────────────────────────┐
│                  Laravel 13 Backend                    │
│   • Controllers & Routes                               │
│   • SQLite Database (WAL Mode)                         │
│   • Laravel Queue (GenerateTtsJob, TranscribeSttJob)   │
└───────────────────────────┬────────────────────────────┘
                            │ Process::run() / HTTP
┌───────────────────────────▼────────────────────────────┐
│          İzole Python AI Çekirdeği (engine/)            │
│   • Piper TTS (TR / EN / DE / FR - ONNX Çok Hızlı)     │
│   • XTTS v2 (Ses Klonlama)                             │
│   • Bark (Doğal / İfadeli Ses)                         │
│   • Faster-Whisper (STT - Sesten Metne)                │
│   • HuggingFace & Piper Model Yöneticisi               │
└────────────────────────────────────────────────────────┘
```

---

## 📦 Proje Yapısı

- `app/`: Laravel 13 PHP backend (Controllers, Jobs, Models, Providers, Services)
- `engine/`: İzole Python ses motoru (FastAPI, CLI, Piper, XTTS, Bark, Whisper)
- `resources/js/`: Inertia Vue 3 sayfaları (`Dashboard`, `Tts`, `Stt`, `Profiles`, `Models`, `Queue`, `Playlists`, `Settings`)
- `resources/js/Components/`: Canlı CPU/RAM/Disk/GPU footer göstergesi ve UI bileşenleri
- `data/`: Modeller, ses profilleri, çıktı sesleri ve veritabanı

---

## 🛠️ Kurulum ve Geliştirme

### Gereksinimler
- PHP >= 8.3 (SQLite, PDO, cURL eklentileri ile)
- Composer >= 2.0
- Node.js >= 20 & npm
- Python >= 3.10 (PyTorch, Piper, Whisper bağımlılıkları ile)

### 1. Bağımlılıkları Kurun
```bash
composer install
npm install
```

### 2. Veritabanı ve Anahtar
```bash
php artisan key:generate
php artisan migrate
```

### 3. Varlık Derlemesi (Vite)
```bash
npm run build
```

### 4. Masaüstü Uygulamasını Başlatın
```bash
php artisan native:run
```

### 5. Masaüstü Uygulamasını Paketleyin (Windows / macOS / Linux)
```bash
php artisan native:build
```

---

## 🧪 Testler
```bash
php artisan test
```

---

## 📄 Lisans
Bu proje MIT lisansı ile lisanslanmıştır.

