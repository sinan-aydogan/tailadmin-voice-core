# 🌐 TailAdmin Voice Core - REST API v1 Referansı

TailAdmin Voice Core, harici uygulamalarınızın (mobil, web, otomasyon botları, arka plan servisleri) yapay zeka ses yeteneklerinden faydalanabilmesi için kapsamlı, yüksek performanslı bir **REST API** sunar.

---

## 🔑 Kimlik Doğrulama (API Key)

Sunucunuzda API anahtarı güvenliğini etkinleştirmek için `.env` dosyasında `VOICE_CORE_API_KEY` değerini tanımlayın:

```env
VOICE_CORE_API_KEY=super-secret-token-12345
```

> **Not:** `VOICE_CORE_API_KEY` boş bırakılırsa API yerel ağda ve masaüstü ortamında açık/kısıtlamasız modda çalışır.

Anahtar tanımlandığında isteklerinizi aşağıdaki yöntemlerden biriyle yetkilendirebilirsiniz:

1. **HTTP Başlığı (Önerilen):**
   ```http
   X-API-Key: super-secret-token-12345
   ```
2. **Bearer Token Başlığı:**
   ```http
   Authorization: Bearer super-secret-token-12345
   ```
3. **URL Parametresi (Özellikle `<audio src="...">` HTML oynatıcıları için):**
   ```
   GET /api/v1/audio/tts_xyz.wav?api_key=super-secret-token-12345
   ```

---

## 📋 Uç Noktalar Özeti

| Metot | Uç Nokta | Açıklama | Yetki |
|---|---|---|---|
| `GET` | `/api/v1/health` | Servis sağlık kontrolü (API, Engine, Queue) | Herkese Açık |
| `GET` | `/api/v1/audio/{filename}` | Ses dosyası oynatma / indirme | Açık / URL Param |
| `GET` | `/api/v1/system/stats` | CPU, RAM, Disk, GPU donanım istatistikleri | API Key |
| `POST` | `/api/v1/tts/generate` | Metinden ses üretimi (TTS) - Senkron / Asenkron | API Key |
| `POST` | `/api/v1/stt/transcribe` | Sesten metne deşifre (STT) - Senkron / Asenkron | API Key |
| `GET` | `/api/v1/tasks` | Ses görevlerini listeleme ve filtreleme | API Key |
| `GET` | `/api/v1/tasks/{id}` | Belirli bir görevin durumunu ve sonucunu alma | API Key |
| `DELETE` | `/api/v1/tasks/{id}` | Görevi ve üretilen ses dosyalarını silme | API Key |
| `GET` | `/api/v1/models` | Desteklenen ve indirilen modellerin listesi | API Key |
| `POST` | `/api/v1/models/download/{modelId}` | Arka planda model indirme tetikleme | API Key |
| `GET` | `/api/v1/models/downloads` | İndirme görevlerinin canlı durumu | API Key |
| `GET` | `/api/v1/profiles` | Ses klonlama profillerini listeleme | API Key |
| `POST` | `/api/v1/profiles` | Yeni ses klonlama profili ve örnek yükleme | API Key |
| `DELETE` | `/api/v1/profiles/{id}` | Ses profilini silme | API Key |

---

## 🔊 Metinden Sese (TTS - Text to Speech)

### `POST /api/v1/tts/generate`

Metni yapay zeka modelleriyle sese dönüştürür. 

#### İstek Parametreleri (JSON):
* `text` *(string, zorunlu)*: Seslendirilecek metin (maks. 10.000 karakter).
* `engine` *(string, varsayılan: `piper-tr`)*: Kullanılacak TTS motoru (`piper-tr`, `piper-en`, `xtts-v2`, `bark`, `tortoise`, `musicgen-small`).
* `language` *(string, varsayılan: `tr`)*: Dil kodu (`tr`, `en`, `de`, `fr` vb.).
* `profile_id` *(integer, isteğe bağlı)*: Ses klonlama (XTTS) için referans profil ID'si.
* `sync` *(boolean, varsayılan: `false`)*: 
  * `false`: İsteği kuyruğa atar, anında `task_id` döner (Asenkron).
  * `true`: Ses üretimi tamamlanana kadar bekler, doğrudan sonucu döner (Senkron).

#### 1. Asenkron Örnek (Kuyruk Modu - Varsayılan):
```bash
curl -X POST http://localhost:8000/api/v1/tts/generate \
  -H "Content-Type: application/json" \
  -H "X-API-Key: super-secret-token-12345" \
  -d '{
    "text": "Merhaba! Bu metin TailAdmin Voice Core API servisi ile üretilmektedir.",
    "engine": "piper-tr",
    "language": "tr"
  }'
```

**Yanıt (202 Accepted):**
```json
{
  "success": true,
  "message": "TTS generation task queued successfully.",
  "task_id": 42,
  "status": "pending",
  "poll_url": "http://localhost:8000/api/v1/tasks/42",
  "audio_url": "http://localhost:8000/api/v1/audio/tts_3k8f9a2b1c4d.wav"
}
```

#### 2. Senkron Örnek (Doğrudan Sonuç):
```bash
curl -X POST http://localhost:8000/api/v1/tts/generate \
  -H "Content-Type: application/json" \
  -H "X-API-Key: super-secret-token-12345" \
  -d '{
    "text": "Anında üretilen ses yanıtı.",
    "engine": "piper-tr",
    "language": "tr",
    "sync": true
  }'
```

**Yanıt (200 OK):**
```json
{
  "success": true,
  "message": "TTS generated successfully.",
  "task_id": 43,
  "status": "completed",
  "filename": "tts_8x1b9c2a3d4e.wav",
  "audio_url": "http://localhost:8000/api/v1/audio/tts_8x1b9c2a3d4e.wav",
  "engine": "piper-tr",
  "language": "tr",
  "result": {
    "success": true,
    "output_path": "/app/data/outputs/tts_8x1b9c2a3d4e.wav",
    "duration": 2.4
  }
}
```

---

## 🎙️ Sesten Metne (STT - Speech to Text)

### `POST /api/v1/stt/transcribe`

Ses dosyasını (WAV, MP3, M4A, OGG, FLAC) metne dönüştürür.

#### İstek Parametreleri (Multipart Form Data):
* `audio` *(file, zorunlu)*: Ses dosyası (maks. 100 MB).
* `language` *(string, varsayılan: `tr`)*: Sesin dili (boş bırakılırsa otomatik algılar).
* `model_size` *(string, isteğe bağlı)*: Whisper modeli (`tiny`, `base`, `small`, `medium`, `large-v3`).
* `sync` *(boolean, varsayılan: `false`)*: Senkron bekleme seçeneği.

#### Örnek:
```bash
curl -X POST http://localhost:8000/api/v1/stt/transcribe \
  -H "X-API-Key: super-secret-token-12345" \
  -F "audio=@/yol/ses_kaydi.mp3" \
  -F "language=tr" \
  -F "sync=true"
```

**Yanıt (200 OK):**
```json
{
  "success": true,
  "message": "Audio transcribed successfully.",
  "task_id": 44,
  "status": "completed",
  "text": "Bugün hava oldukça güzel ve açık.",
  "language": "tr",
  "duration": 3.12,
  "segments": [
    {
      "start": 0.0,
      "end": 3.12,
      "text": "Bugün hava oldukça güzel ve açık."
    }
  ]
}
```

---

## 📊 Görev Takibi (Tasks)

### `GET /api/v1/tasks/{id}`
Bir görevin güncel durumunu sorgular:

```bash
curl http://localhost:8000/api/v1/tasks/42 \
  -H "X-API-Key: super-secret-token-12345"
```

**Yanıt (200 OK):**
```json
{
  "success": true,
  "task": {
    "id": 42,
    "type": "tts",
    "status": "completed",
    "progress": 100,
    "audio_url": "http://localhost:8000/api/v1/audio/tts_3k8f9a2b1c4d.wav",
    "error_message": null,
    "started_at": "2026-09-12T00:15:10+00:00",
    "completed_at": "2026-09-12T00:15:12+00:00"
  }
}
```

---

## 💻 İstemci Kod Örnekleri

### Python ile Kullanım (requests)

```python
import time
import requests

API_URL = "http://localhost:8000/api/v1"
API_KEY = "super-secret-token-12345"

headers = {
    "X-API-Key": API_KEY,
    "Content-Type": "application/json"
}

# 1. Asenkron Ses Üretimi Başlat
response = requests.post(f"{API_URL}/tts/generate", headers=headers, json={
    "text": "Python üzerinden ses üretimi başarıyla tamamlandı.",
    "engine": "piper-tr",
    "language": "tr"
})
data = response.json()
task_id = data["task_id"]
print(f"Görev başlatıldı: #{task_id}")

# 2. Görev Bitişini Bekle (Polling)
while True:
    task = requests.get(f"{API_URL}/tasks/{task_id}", headers=headers).json()["task"]
    if task["status"] == "completed":
        print(f"Ses hazır! İndirme URL: {task['audio_url']}")
        break
    elif task["status"] == "failed":
        print(f"Hata: {task['error_message']}")
        break
    time.sleep(1)
```

### Node.js / JavaScript ile Kullanım (Fetch)

```javascript
const API_URL = "http://localhost:8000/api/v1";
const API_KEY = "super-secret-token-12345";

async function generateVoice(text) {
  const res = await fetch(`${API_URL}/tts/generate`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-API-Key": API_KEY,
    },
    body: JSON.stringify({
      text: text,
      engine: "piper-tr",
      language: "tr",
      sync: true, // Senkron bekleme
    }),
  });

  const result = await res.json();
  if (result.success) {
    console.log("Ses URL:", result.audio_url);
    return result.audio_url;
  }
  throw new Error(result.message);
}

generateVoice("Merhaba dünya! JavaScript ile API çağrısı.");
```
