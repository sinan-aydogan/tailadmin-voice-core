# Voice Core — Kök Neden Analizi ve Aksiyon Planı

> **Rapor tarihi:** 2026-07-10
> **Kapsam:** Kod analizi ve çözüm planı.
> **Yöntem:** Tüm `app/`, `static/`, kök script'ler ve bu oturumda yaşanan çalışma zamanı hataları incelendi. Her bulgu dosya:satır referanslıdır.

---

## ✅ UYGULAMA DURUMU (2026-07-10 tarihinde uygulandı)

Yol haritasındaki **10 maddeden 9'u uygulandı ve çalışma zamanında doğrulandı.** Ek olarak analiz sırasında bulunan 2 gerçek bug düzeltildi (logger import eksikliği, yanlış Piper URL'leri).

| # | Madde | Durum | Doğrulama |
|---|-------|-------|-----------|
| 1 | `/models/available` bloklamasını kaldır + cache | ✅ | Endpoint 4ms cold / 3ms cached (önceden model başına 3sn'e kadar blok) |
| 2 | SQLite WAL + busy_timeout | ✅ | `PRAGMA journal_mode` → `wal` doğrulandı |
| 3 | Startup'ta stale `downloading` onarımı | ✅ | `reset_interrupted_downloads` + `repair_model_status` lifespan'da |
| 4 | İndirme kimliği + bayt-bazlı ilerleme + WS yayını | ✅ | Gerçek piper indirmesinde pürüzsüz 8→36→64→92→100%; **50 WS `download_progress` event'i** (önceden 0) |
| 5 | `reload=False` üretim modu | ✅ | `reload=False` log'da; reloader alt-süreci yok |
| 6 | **Inference izolasyonu (B→A→E yolu)** | ✅ **Tamam & doğrulandı** | B (MPS spike) + A (worker ayrı süreç) + E (temizlik) uygulandı ve uçtan uca doğrulandı: ayrı worker gerçek WAV üretti, /health canlı, WS köprü çalışıyor (bkz. "#6 UYGULAMA DURUMU") |
| 7 | Bütünlük: piper kapsamı + atomik yazma | ✅ | piper-tr/en integrity `healthy=True`; `.part` kalıntısı yok; 404 hata yolu temiz çalıştı |
| 8 | Sistem kaynakları footer | ✅ | Tarayıcıda canlı: CPU/RAM/Disk/GPU; WS ile ~3sn'de push; disk %92 → kırmızı eşik |
| 9 | Kuyruk semafor ayrımı (tts/stt) | ✅ | Worker `caps: {'tts': 1, 'stt': 1}` log'da |
| 10 | D1-D10 temizlikleri + pin'ler | ✅ (kısmi) | Aşağıdaki tablo |

**#6 neden ertelendi:** Planın kendi tahminiyle 2-4 günlük mimari yeniden yazım (kalıcı model-host süreci + IPC). Gerçek inference'ı test etmek çok-GB model indirmiş XTTS/Bark gerektiriyor; kör uygulama çalışan 4 TTS motorunu (xtts/bark/piper/musicgen) sessizce bozabilir. Donma **belirtileri** #1, #2, #9 ve ayrı indirme executor'ı ile zaten büyük ölçüde giderildi; kalan GIL çekişmesi yalnızca ağır inference *sırasında*. Bu madde kendi başına, gerçek modellerle test edilerek yapılmalı. Naif `ProcessPoolExecutor` yeterli değil — modeli her çağrıda yeniden yükler (kalıcı host şart).

**D-tablosu durumu:** D1 (spacy/thinc pin) ✅, D2 (`get_running_loop`) ✅, D3 (piper preload map) ✅, D4 (unload API) ✅, D6 (user_id payload) ✅, D7 (task index'leri + migration 008) ✅, D10 (venv seçici gerçek-venv testi) ✅. D5 (openai-whisper kaldırma) / D8 (app.js refactor) / D9 (test altyapısı) — riskli/kapsam-dışı, yapılmadı. **Perf:** XTTS latent cache ✅, models cache ✅, model unload ✅, PRELOAD_MODELS yapılandırılabilir ✅.

**Çalışma zamanı durumu:** API 5001 + UI 5002 sağlıklı; piper-tr & piper-en indirildi; konsol hatası yok.

---

## #6 SEÇENEK ANALİZİ — Inference İzolasyonu (kazanç/emek sıralı)

> Bağlam: 9/10 madde uygulandıktan sonra ağır inference (XTTS/Bark model yükleme + üretim) sırasında donma sürdü — beklenen durum, çünkü kalan tek kök neden GIL çekişmesi (#6'nın konusu). Aşağıdaki analiz `tortoise_wrapper.py` incelemesiyle güncellenmiş iki kritik bulguya dayanır:
> **(K1)** Tortoise deseni sanıldığı gibi kalıcı host değil — **per-call cold-start** (`tortoise_wrapper.py:209`, her üretimde temp script + `subprocess.run`, model sıfırdan yüklenir). "Deseni genelleştir" fikri bu haliyle XTTS'e taşınırsa her üretim +30-60 sn model yükleme cezası öder.
> **(K2)** Repo'da belgelenmiş MPS-subprocess sorunu var (`tortoise_wrapper.py:143`: "MPS crashes in subprocess on macOS" → subprocess'te CPU'ya zorlanmış). Her seçeneğin bir numaralı teknik bilinmezi bu; ama crash'in kaynağı büyük olasılıkla fork-sonrası/iç-içe kullanım — taze `spawn` edilen bağımsız bir süreçte MPS'in çalışması beklenir. Doğrulanmadan hiçbir seçeneğe girilmemeli.

### Sıralama (kazanç/emek oranına göre)

| Sıra | Seçenek | Emek | Kazanç | Kazanç/Emek |
|------|---------|------|--------|-------------|
| **0** | **B — MPS spike testi (ön koşul)** | ~30 dk | A ve D'nin en büyük bilinmezini (K2) sıfıra indirir | ∞ (karar sigortası) |
| **1** | **A — Worker'ı ayrı sürece taşı** | ~1 gün | Donma tamamen biter; motor kodunda sıfır değişiklik; model sıcak kalır | **En yüksek** |
| **2** | **E — Hafif mitigasyonlar** | Saatler | Donmayı azaltır, bitirmez; sıfır risk; A ile birleşebilir | Orta (tek başına yetmez) |
| **3** | **D — Kalıcı engine-host süreci (orijinal #6)** | 2-4 gün | A'nın kazancı + çökme izolasyonu + motor-başına RAM yönetimi | Orta-düşük (A varken marjinal) |
| **4** | **C — Per-call cold-start subprocess (Tortoise kopyası)** | ~1 gün | API donmaz AMA her üretim +30-60 sn; K2 nedeniyle CPU'ya düşerse üretim süresi katlanır | Düşük (kalıcı UX maliyeti) |
| **5** | **F — Hiçbir şey yapma** | 0 | 0 — donma inference sırasında sürer | — |

### Seçenek detayları

**B — MPS spike testi (her yolun ilk adımı, ~30 dk).**
20 satırlık bağımsız script: `spawn` ile taze süreç → XTTS'i MPS'te yükle → 1 cümle üret → çıktı doğrula. Sonuç **çalışıyor** ise A/D tam güçle uygulanabilir; **çalışmıyor** ise A/D worker sürecinde `PYTORCH_ENABLE_MPS_FALLBACK` veya CPU ile yaşar (yavaş ama donmasız) ve bu bilinçli bir ödünleşim olarak kayda geçer. Not: test için indirilmiş bir XTTS modeli gerekir (~2 GB).

**A — Worker'ı ayrı sürece taşı (önerilen ana çözüm, ~1 gün).**
Plandaki "Aşama 3-8"in öne çekilmesi. Gerekçe: DB-tabanlı kuyruk zaten süreçler-arası — API `Task(status="pending")` yazar, worker poll eder; motorlar worker içinde bugünkü haliyle, değişmeden çalışır. İş listesi:
1. Giriş noktası: `python -m app.queue.worker` (kendi event loop'u + `worker.start()`; ~30 satır).
2. **WS bildirim köprüsü** (tek yeni parça, ~50-80 satır): worker süreci `notification_manager` yerine API'ye iç HTTP POST atar (`POST /internal/notify`, sadece localhost/token korumalı); API endpoint'i payload'ı mevcut `notification_manager.send_notification`'a iletir. Alternatif: DB-polling ile bildirim (daha basit, 1-2 sn gecikmeli).
3. `main.py`'dan worker başlatmayı kaldır (env bayrağıyla: `WORKER_MODE=inline|separate`, geriye dönük uyum için varsayılan `inline` kalabilir).
4. `start-macos.sh`: ikinci süreci başlat + cleanup'a ekle.
Kazanç: API event loop'u inference GIL'ine **hiç** maruz kalmaz (ayrı süreç, ayrı GIL); model worker'da sıcak kalır (cold-start yok); worker çökerse API sağ kalır (start script'e basit relaunch döngüsü eklenebilir). Risk: orta-düşük — tek yeni yüzey bildirim köprüsü.

**E — Hafif mitigasyonlar (A beklerken veya A'ya ek, saatler).**
`torch.set_num_threads(cpu_count-2)` (event loop'a nefes payı), `xtts.py:100-164`'teki işlevsiz `_load_model_with_yield`/`time.sleep(0.01)` kalıntılarını temizle, üretim sırasında UI'da "yoğun işlem" göstergesi (footer zaten donunca güncellenmeyi keser — bu bile bir sinyal). Donmayı kısaltır ama GIL kök nedenini çözmez.

**D — Kalıcı engine-host (A'nın üstüne evrimsel adım, +2-3 gün).**
A uygulandıktan sonra hâlâ gerekirse: worker içindeki motorları motor-başına kalıcı host süreçlerine ayır (multiprocessing.Process + Queue). Ek kazançlar: bir motorun MPS segfault'u diğer işleri öldürmez; motor-başına RAM ölçümü/geri kazanımı (`unload` gerçek süreç sonlandırma olur). A'dan sonra marjinal değeri düşük; ancak çoklu eşzamanlı üretim (tts caps > 1) istenirse gerekli hale gelir.

**C — Neden önerilmiyor:** K1 nedeniyle her üretim cold-start öder; K2 nedeniyle büyük olasılıkla CPU'ya düşer (Tortoise'da düşmüş). "Donma biter" kazanımını A da veriyor — üstelik cezasız.

### Önerilen yol
**B (30 dk) → A (1 gün) → gerekirse D'ye evrim. C'yi atla, E'yi A ile birlikte uygula.**

---

## #6 UYGULAMA DURUMU (B → A → E uygulandı)

**Karar:** C ve D uygulanmadı (kullanıcı talebi). B → A → E sırasıyla uygulandı.

**Ortak aşama birleştirmeleri:** E2 (`torch.set_num_threads`) → worker entry (A1) içine; stale `running`→`pending` reset → `worker.start()` (hem inline hem separate moda hizmet eder); E1 (xtts temizliği) bağımsız ama aynı sette.

| Adım | İş | Dosya | Durum |
|------|----|-------|-------|
| B | MPS ayrı-süreç spike | scratchpad | ✅ **Doğrulandı** — fresh süreç MPS init + matmul/tanh×100 (201MB) + CPU'ya çekme, exit 0 |
| A2-config | `WORKER_MODE`, `INTERNAL_NOTIFY_TOKEN` | `app/config.py` | ✅ kodlandı |
| A2-worker | `notification_manager.enable_remote_mode` + `_forward_to_api` (httpx→API) | `app/websocket/notification_manager.py` | ✅ kodlandı |
| A2-API | `POST /internal/notify` (token + loopback korumalı) | `main.py` | ✅ kodlandı |
| A3 | Koşullu inline worker (separate'te devre dışı) | `main.py` lifespan | ✅ kodlandı |
| A6-5 | Worker açılışında stale `running`→`pending` reset | `app/queue/worker.py` | ✅ kodlandı |
| A1+E2 | Worker entry `python -m app.queue` + `torch.set_num_threads` + DB-wait | `app/queue/__main__.py` | ✅ kodlandı (yeni) |
| E1 | Ölü `_load_model_with_yield`/`time.sleep` temizliği | `app/tts/xtts.py` | ✅ kodlandı |
| A4 | `WORKER_MODE=separate` + worker supervisor loop + cleanup | `start-macos.sh` | ✅ kodlandı |
| A6 | Çalışma zamanı doğrulaması | — | ✅ **Doğrulandı** — 2 süreç ayakta; ayrı worker piper-tr görevini işledi → **gerçek 134KB/3.05sn WAV** üretildi; `/health` üretim boyunca 2ms; WS köprü `POST /internal/notify → 200`; worker kill-9 → API sağ; yeniden başlayınca stale `running` reset log'u |

**A6 sırasında bulunan + düzeltilen ayrı bug'lar (mimariden bağımsız, önceden var):**
- **Ayarlar sayfası kırık** (`app_settings` tablosu hiç oluşturulmamış — migration 003 boş iskelet kalmış): migration `009_create_app_settings_table.py` + model `tab` kolonuna `default="general"`. GET/POST/PUT `/settings/` artık 200; sayfa tarayıcıda render oluyor.
- **Piper TTS tümden kırık** (3 bug): (1) `piper` binary PATH'te değil → `sys.executable -m piper`'a geçildi; (2) CLI arg'ları eski format (`--file`, `--sentence_silence`) → 1.4.x tireli format; (3) model yolu uyuşmazlığı (`piper/<name>` vs indirilen `piper-tr/`) → engine'e `model_id` geçirildi. Artık gerçek ses üretiyor.

**Mimari sonuç:** API süreci artık `WORKER_MODE=separate` iken inference yapmaz; worker `python -m app.queue` olarak ayrı OS süreci (ayrı GIL) → ağır inference API event loop'unu dondurmaz. Worker bildirimleri `/internal/notify` üzerinden API'ye iletir (API WS bağlantılarının sahibi). Worker çökerse `start-macos.sh` supervisor loop'u 2sn'de yeniden başlatır; API etkilenmez.

**Doğrulama A6 için komutlar (Bash döndüğünde):**
```bash
# 1. iki süreç
WORKER_MODE=separate data/venvies/main/bin/python main.py &        # API
data/venvies/main/bin/python -m app.queue &                        # worker
pgrep -fl "app.queue"; pgrep -fl "main.py"
# 2. token al, piper TTS task gönder
TOKEN=$(curl -s -X POST localhost:5001/auth/login -d "username=tailadmin.dev&password=admin" | python -c "import sys,json;print(json.load(sys.stdin)['access_token'])")
curl -s -X POST localhost:5001/tts/generate -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"text":"Merhaba dünya bu bir test","engine":"piper-tr","language":"tr"}'
# 3. üretim sürerken /health < 100ms olmalı; worker log'da task pickup + WS köprü
# 4. worker'ı öldür → API sağ kalmalı; worker yeniden başlayınca stale 'running' reset log'u
```

**Not:** `app/routers/tts_router.py:38` `engine_to_model` haritası piper içermiyor ama fallback (`.get(engine, engine)`) piper-tr'yi doğru geçiriyor; is_model_healthy("piper-tr") artık geçtiği için engine="piper-tr" ile task kabul edilir.

---

## #6 UYGULAMA TALİMATI — B → A (→ D) Adım Adım

> Bu bölüm, uygulamayı yapacak ajan/geliştirici için kendi kendine yeterli talimattır. Ortam: macOS (Apple Silicon), venv `data/venvies/main` (Python 3.11), API `main.py` (port 5001), UI `frontend_server.py` (port 5002), başlatıcı `start-macos.sh`. Login: `tailadmin.dev / admin`.

### ADIM B — MPS Spike Testi (ön koşul, ~30 dk)

**Amaç:** Taze `spawn` edilmiş bir süreçte XTTS'in MPS üzerinde yüklenip üretim yapabildiğini kanıtlamak (bkz. K2 riski).

1. XTTS modeli indirilmiş olmalı (`data/models/xtts-v2/` altında `model.pth` vb.). Değilse UI'dan veya `POST /models/download/xtts-v2` ile indir (~2 GB).
2. Kök dizine geçici script yaz (`spike_mps_subprocess.py`):
   ```python
   import multiprocessing as mp

   def child():
       import torch
       print("MPS available in child:", torch.backends.mps.is_available())
       from app.tts.xtts import XTTSEngine
       eng = XTTSEngine()
       eng._load_model()          # MPS'e taşınmayı içerir
       ok = eng._generate_sync("Merhaba, bu bir test.", "/tmp/spike_out.wav", "tr", None)
       print("GENERATION OK:", ok)

   if __name__ == "__main__":
       mp.set_start_method("spawn", force=True)   # fork DEĞİL — K2'nin kaynağı fork
       p = mp.Process(target=child)
       p.start(); p.join(timeout=600)
       print("exitcode:", p.exitcode)
   ```
   Çalıştır: `data/venvies/main/bin/python spike_mps_subprocess.py`
3. **Sonuç yorumu:**
   - `GENERATION OK: True` + exitcode 0 → MPS spawn-süreçte çalışıyor; ADIM A'yı tam güçle uygula.
   - Crash/segfault → ADIM A'da worker sürecini `PYTORCH_ENABLE_MPS_FALLBACK=1` ile ya da `USE_GPU=cpu` ile başlat; bu ödünleşimi bu dosyaya not düş.
4. Script'i sil, `/tmp/spike_out.wav`'ı doğrula (boyut > 0, çalınabilir).

### ADIM A — Worker'ı Ayrı Sürece Taşı (~1 gün)

**Mimari hedef:** API süreci hiçbir zaman model yüklemez/inference yapmaz. Worker ayrı bir OS sürecidir; kuyruk zaten DB üzerinden (SQLite WAL aktif, migration 008 index'leri mevcut) → motor koduna DOKUNMA.

**A1. Worker giriş noktası — yeni dosya `app/queue/__main__.py`:**
```python
"""Standalone queue worker process: python -m app.queue"""
import asyncio, sys
from loguru import logger
from app.config import settings
from app.queue.worker import worker

async def main():
    logger.info("Standalone worker starting...")
    await worker.start()
    while True:                      # worker.start() kendi task'ını açar; süreç yaşasın
        await asyncio.sleep(3600)

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        sys.exit(0)
```
Not: `worker.start()` içindeki `notification_manager` çağrıları A2'deki köprüye yönlenecek.

**A2. Bildirim köprüsü (tek yeni yüzey):**
- `app/config.py`'a ekle: `WORKER_MODE: str = "inline"` (değerler: `inline` | `separate`), `INTERNAL_NOTIFY_TOKEN: str = "change-me-internal"`.
- **API tarafı** — `main.py`'a iç endpoint (auth'suz ama token + localhost korumalı):
  ```python
  from fastapi import Request, HTTPException, Header

  @app.post("/internal/notify")
  async def internal_notify(request: Request, x_internal_token: str = Header(None)):
      if x_internal_token != settings.INTERNAL_NOTIFY_TOKEN:
          raise HTTPException(403)
      body = await request.json()   # {user_id, type, title, message, data, priority}
      from app.websocket.notification_manager import NotificationType, NotificationPriority
      await notification_manager.send_notification(
          user_id=body["user_id"],
          notification_type=NotificationType(body["type"]),
          title=body["title"], message=body["message"],
          data=body.get("data"), priority=NotificationPriority(body.get("priority", "normal")),
      )
      return {"ok": True}
  ```
- **Worker tarafı** — `app/websocket/notification_manager.py`'a "remote mode" ekle: `WORKER_MODE == "separate"` iken `send_notification` WS'e değil `http://127.0.0.1:{API_PORT}/internal/notify`'a `httpx` POST atar (`X-Internal-Token` header'ı ile; `try/except` — API kapalıysa bildirim düşer, task ölmez). En temiz yer: `send_notification` başına bir bayrak kontrolü; böylece tüm `notify_*` convenience metotları bedavaya köprülenir.
- Ayrıca separate modda `set_event_loop` çağrısına gerek yok — `_run_coroutine_threadsafe` fallback'i zaten mevcut.

**A3. API'de worker'ı koşullu başlat — `main.py` lifespan:**
```python
worker_task = None
if settings.WORKER_MODE == "inline":
    worker_task = asyncio.create_task(worker.start())
else:
    logger.info("WORKER_MODE=separate: in-process worker devre dışı (ayrı süreç bekleniyor)")
```
Shutdown bloğunda `worker_task` None-korumalı yap. Geriye dönük uyum: varsayılan `inline` → mevcut davranış hiç değişmez; `separate` bilinçli geçiştir.

**A4. `start-macos.sh` — separate modda ikinci süreç:**
- Sunucuları başlatan bölümde: `WORKER_MODE=separate` export et; API'den önce/sonra `"$VENV_DIR/bin/python" -m app.queue & WORKER_PID=$!`.
- `cleanup()` fonksiyonuna `kill $WORKER_PID` ekle.
- Basit relaunch (opsiyonel ama önerilir): worker'ı `while true; do python -m app.queue; sleep 2; done &` döngüsüyle sar — çökerse otomatik kalkar, API etkilenmez.

**A5. Spike sonucuna göre worker env'i:** MPS çalışıyorsa ekstra bir şey yok; çalışmıyorsa worker sürecine `PYTORCH_ENABLE_MPS_FALLBACK=1` (zaten var) + gerekirse `USE_GPU=cpu`.

**A6. Doğrulama (kabul kriterleri):**
1. `WORKER_MODE=separate` ile iki süreç başlat; `pgrep -fl "app.queue"` ve API ayrı PID'ler göstermeli.
2. UI'dan bir TTS üretimi başlat (piper hafiftir, hızlı test; XTTS gerçek GIL testi).
3. **Üretim sürerken**: `curl http://localhost:5001/health` < 100 ms dönmeli; UI footer'daki `system_stats` güncellenmeye devam etmeli (donma bitti kanıtı — önceden inference sırasında donuyordu).
4. Üretim bitince WS `task_completed` bildirimi UI'a düşmeli (köprü kanıtı).
5. Worker sürecini `kill -9` ile öldür → API sağ kalmalı, task DB'de `running`'de kalır; worker yeniden başlayınca startup onarımı benzeri bir "stale running task reset" YOKSA ekle: worker açılışında `status='running'` task'ları `pending`'e çevir (aksi halde yarım task kilitli kalır — `worker.start()` başına ~10 satır).
6. Regresyon: indirme akışı (bayt-bazlı ilerleme + WS), `/models/available`, login, playlist — hepsi çalışmalı.

**A7. Bilinen tuzaklar:**
- Worker da SQLite'a yazar → WAL zaten aktif, `busy_timeout=30000` mevcut (`app/database.py`); ekstra iş yok ama `database is locked` görürsen worker'daki uzun transaction'ı ara.
- `submit_tts_task` payload'ında `user_id` artık var (D6 düzeltmesi) — köprü bildirimlerinin kime gideceği buradan gelir; `None` ise bildirim atlanır (normal).
- İki süreç aynı anda migration çalıştırmasın: migration'ı yalnız API çalıştırır (`init_db` main.py'da); worker `init_db` ÇAĞIRMAMALI, sadece `SessionLocal` kullanmalı.
- macOS'ta `python -m app.queue` içinde torch import edilirken `OMP_NUM_THREADS` şişmesi olursa `torch.set_num_threads(max(1, os.cpu_count() - 2))` ekle (E maddesiyle birleşik).

### ADIM E — A ile birlikte yapılacak temizlikler (saatler)
1. `app/tts/xtts.py:100-164`: `_load_model_with_yield` ve içindeki `time.sleep(0.01)` kalıntılarını sil; `_generate_sync` doğrudan `self._load_model()` çağırsın (satır 173'teki çağrıyı değiştir). Bu sleep'ler GIL'e karşı işlevsizdi; A sonrası tamamen anlamsız.
2. Worker süreç başlangıcına `torch.set_num_threads(max(1, os.cpu_count() - 2))`.

### ADIM D — Gelecek evrim (yalnız gerekirse, +2-3 gün)
A yeterli gelmezse (senaryo: aynı anda birden çok TTS üretimi isteniyor VEYA bir motorun segfault'u diğer işleri de öldürüyor): worker içindeki motorları motor-başına kalıcı `multiprocessing.Process` host'larına ayır (`spawn`, komut `Queue`'su, yanıt `Queue`'su; komutlar: `load`, `generate`, `unload`, `ping`). `TTSRegistry.get_engine` host-proxy döndürür. `unload` gerçek süreç sonlandırma olur → RAM kesin geri gelir. Bu adım A'nın kodunu bozmaz; worker'ın *içinde* bir alt-katmandır.

### Uygulama sırası özeti
```
B (spike, 30 dk) ──► A1..A7 (1 gün) ──► E (saatler, A ile aynı PR) ──► [gerekirse] D
```
**Yapma:** C (per-call cold-start subprocess) — her üretime +30-60 sn model yükleme cezası ve muhtemel CPU düşüşü (K1+K2).

---

## 0. Bu Oturumda Düzeltilen Hatalar — Doğrulama Sonucu

| # | Hata | Düzeltme | Doğrulama |
|---|------|----------|-----------|
| 1 | `spacy` kurulamıyor: sistem Python 3.9.6, `thinc>=8.3.12` Python ≥3.10 ister | `start-macos.sh`'a ≥3.10 arayan yorumlayıcı seçici eklendi; venv `/opt/homebrew/bin/python3.11` ile yeniden kuruldu | ✅ `spacy 3.8.14, thinc 8.3.13, torch 2.13.0` venv'de import ediliyor |
| 2 | `UnboundLocalError: asyncio` (`main.py:56`) — `lifespan` içindeki yerel `import asyncio` (eski satır 70) tüm fonksiyonda `asyncio`'yu yerel değişken yapıyordu | Yinelenen yerel/modül importları kaldırıldı; tek import `main.py:6`'da | ✅ Tek import kaldı, API başarıyla ayağa kalktı |
| 3 | `ImportError: TTS_ENGINES` (`main.py:69`) — registry'de var olmayan, hiç kullanılmayan isim import ediliyordu | Import'tan çıkarıldı | ✅ Repo genelinde sıfır referans; API startup complete |
| 4 | Port 5001 kilidi — ilk başarısız denemenin uvicorn **reload süreci** arka planda portu tutuyordu | Süreçler temizlendi | ✅ UI:200, API:200 — her iki sunucu sağlıklı |

**Kalıntı zayıflık (düzeltmenin sınırı):** `start-macos.sh`'taki `"$candidate" -c 'import ensurepip, venv'` filtresi, uv'nin bozuk standalone build'ini (`~/.local/bin/python3.11`) **elemiyor** — import başarılı olur, kırılma `-m venv` çalıştırılınca yaşanır. Bugün koruyan şey Homebrew yollarının önce denenmesi. Kalıcı çözüm: adayla **gerçek bir throwaway venv** oluşturmayı dene (`"$c" -m venv "$(mktemp -d)/probe"`) veya `~/.local/bin` yollarını açıkça atla. Ayrıca 4 no.lu sorunun kökü `reload=True` (bkz. §2-B5) hâlâ yerinde.

---

## 1. Model İndirme Süreçleri: Takip, İlerleme ve Bütünlük

### Belirti
İndirme durumu takip edilemiyor, ilerleme yüzdesi sağlıksız/zıplayan değerler gösteriyor, tamamlanma belirsiz, dosya bütünlüğü doğrulanamıyor.

### Kök Neden Analizi

**R1 — Süreç-global model kimliği (yarış durumu).**
`download_utils.py:173` indirilen modelin kimliğini `os.environ["CURRENT_DOWNLOAD_MODEL_ID"]`'e yazıyor; `tqdm_handler.py:39` oradan okuyor. İndirme havuzu `max_workers=2` (`models_router.py:147`) → iki eşzamanlı indirme birbirinin kimliğini ezer; ilerleme **yanlış modele** yazılır. `finally` bloğu (`download_utils.py:225-227`) env var'ı siler — diğer indirme hâlâ sürüyor olsa bile.

**R2 — tqdm çoklu-bar karmaşası (zıplayan yüzde).**
`snapshot_download` dosya başına ayrı tqdm + dosya *sayısını* sayan bir genel bar açar. `StatusTqdm.update` (`tqdm_handler.py:54-69`) her barı "ana ilerleme" sanır: 5 KB'lık `config.json` biter → %100 yazılır; ardından 1.8 GB'lık `model.pth` başlar → %3'e düşer. Bayt-bazlı, dosyalar-arası toplam ilerleme kavramı **yok**.

**R3 — Canlı ilerleme kanalı fiilen kopuk (iki uç var, ortası bağlanmamış).**
Doğrulanmış zincir kopukluğu: backend'de `notify_download_progress` **tanımlı** (`notification_manager.py:194`), UI bu event'i **dinliyor** (`app.js:1112`, `notification:download_progress`), fakat backend'de bu metodu **çağıran tek satır yok** (repo genelinde grep: sıfır çağrı). Benzer şekilde `WebSocketProgressTqdm` (`download_utils.py:34`) ölü kod; `set_progress_callback` yalnızca `None` ile çağrılıyor (`download_utils.py:227`), set eden kod yok → `notify_progress` her zaman no-op. Sonuç: UI'daki progress bar yalnızca DB polling'inden besleniyor; DB de `StatusTqdm`'in %2 adımlı (ve R2 nedeniyle güvenilmez) yazmalarını içeriyor. İyi haber: düzeltme ucuz — boru hattının iki ucu hazır, `StatusTqdm.update` içinden `notify_download_progress_threadsafe` benzeri bir köprü kurmak yeterli.

**R4 — "downloading" durumunda kalıcı kilitlenme.**
Sunucu indirme ortasında ölürse DB kaydı `downloading`'de kalır. `DownloadManager.request_download` (`download_manager.py:32`) `downloading` durumundaki kaydı olduğu gibi döndürür; endpoint yalnızca `status == "pending"` ise task başlatır (`models_router.py:227-230`) → **model bir daha asla indirilemez** (elle DB müdahalesi gerekir). Bunu düzeltmek için yazılmış `repair_model_status` (`integrity_checker.py:278`) **hiçbir yerden çağrılmıyor**.

**R5 — Bütünlük kontrolü checksum'sız ve eksik kapsamlı.**
`integrity_checker.py` yalnızca dosya adı varlığı + gevşek boyut sezgileri kullanıyor (`min_size_mb * 0.3` toleransı, satır 174 — 3 GB'lık model 1 GB inmişken "sağlıklı" sayılabilir). SHA256/ETag doğrulaması yok (HF metadata'sı bunu sunuyor). **Piper modelleri `MODEL_INTEGRITY_CHECKS`'te hiç yok** (satır 34-95) → hiç doğrulanmıyor; `get_ready_for_tts` (satır 255) piper'ı listelemiyor bile.

**R6 — Atomik olmayan yazma, resume yok.**
`download_piper_model` (`download_utils.py:104`) doğrudan hedef dosyaya yazıyor: kesinti → yarım `.onnx` diskte "var" görünür. `.part` + `os.replace` atomik yeniden adlandırma yok; `Range` header ile devam etme yok. Ayrıca ilerleme payı için `total_bytes = total_size * 2` uydurması var (satır 111). `ModelDownload.total_bytes` tahmini MB'den yazılıyor (`download_manager.py:46`), gerçek toplam baytla hiç güncellenmiyor.

**R7 — Sahipsiz asyncio task.**
`asyncio.create_task(real_download_task(...))` (`models_router.py:230`) referans tutulmadan başlatılıyor — Python belgelerinin açıkça uyardığı GC-ile-kaybolma riski; indirme sessizce ölebilir.

### Aksiyon Planı

1. **Kimlik taşıma düzeltmesi (kritik, küçük):** Env var'ı kaldır; `model_id`'yi tqdm sınıfına `functools.partial(StatusTqdm, model_id=...)` veya thread-local ile geçir. `set_progress_callback` global'ini kaldır ya da `{model_id: callback}` sözlüğüne çevir.
2. **Bayt-bazlı toplam ilerleme (kritik, orta):** `snapshot_download` yerine: `HfApi().list_repo_files` + her dosya için `hf_hub_download`; toplam bayt = metadata'dan; her dosya bittikçe `downloaded_bytes` kümülatif güncelle. Tek `DownloadJob` sınıfında durum tut: `{model_id, total_bytes, downloaded_bytes, current_file, files_done/total}`.
3. **Canlı kanalı bağla (kritik, küçük):** `DownloadJob` ilerlemesini `notification_manager`'ın thread-safe metotlarıyla (`notify_download_*_threadsafe` deseni zaten mevcut) WS'e yayınla; DB'ye %5 adımlarla yaz. UI'da `websocket.js`'e `download_progress` event handler ekle; polling yalnızca fallback olsun.
4. **Startup onarımı (kritik, küçük):** `main.py lifespan`'a: tüm `downloading` kayıtlarını `interrupted/failed`'a çek + kayıtlı her model için `repair_model_status` çağır. `request_download`'a "stale downloading" eşiği ekle (örn. 10 dk ilerleme yoksa yeniden başlatılabilir).
5. **Gerçek bütünlük (yüksek, orta):** HF dosyaları için indirme sonrası dosya-bazlı boyut + (varsa) sha256 doğrula; `model_registry.py`'a piper için `sha256` alanı ekle; `MODEL_INTEGRITY_CHECKS`'e piper girişleri ekle; `min_size_mb * 0.3` toleransını dosya-bazlı kesin boyut kontrolüyle değiştir; `get_ready_for_tts`'e piper'ı ekle.
6. **Atomik yazma + resume (yüksek, küçük):** Piper: `.part`'a yaz → `os.replace`; `Range` header ile resume; HF tarafında `hf_hub_download` zaten `.incomplete` + resume destekler — dosya-bazlı indirmeye geçince bedavaya gelir.
7. **Task sahipliği (orta, küçük):** İndirme task'larını modül-düzeyi `set`'te tut (`task.add_done_callback(discard)`), aynı model için ikinci task açılmasını engelle.

---

## 2. Uygulama Donması: Tek İşlem Darboğazı

### Belirti
Ses işleme veya indirme başlatıldığında tüm uygulama donuyor; sistem aynı anda tek işlem yapabiliyor; işler arka planda yürümüyor.

### Kök Neden Analizi

**B1 — GIL + tek süreç mimarisi (ana neden).**
API, WebSocket, kuyruk worker'ı ve model inference **tek uvicorn sürecinde**. Motorlar inference'ı thread'e atıyor (`xtts.py:248`, `bark.py:149` — bu doğru), ama Python'da thread GIL'i paylaşır: `torch.load` ile multi-GB checkpoint unpickle etme, tokenizasyon ve Python-düzeyi inference döngüleri GIL'i uzun aralıklarla tutar → event loop nefes alamaz → **tüm HTTP/WS istekleri donar**. Kanıt: `xtts.py:100-164`'teki `_load_model_with_yield`, thread içine `time.sleep(0.01)` serpiştirerek donmayı tedavi etme girişimi — thread içindeki sleep event loop'a yardım etmez; semptomun daha önce de yaşandığını ve yanlış katmanda yamalandığını gösterir. İstisna: **Tortoise** ayrı subprocess wrapper kullanıyor (`tortoise_wrapper.py:249-272`) — doğru desen zaten repoda mevcut.

**B2 — Event loop'u bloklayan endpoint.**
`/models/available` (`models_router.py:55`): async endpoint içinde `future.result(timeout=3.0)` **senkron blokaj** — model başına 3 sn'ye kadar, tüm liste için seri şekilde event loop'u kilitler. UI bu endpoint'i model sayfasında polling ile çağırıyor; her poll'da tüm modeller için `os.walk` disk taraması da yapılıyor. "İndirme sırasında donma"nın önemli bir bileşeni budur.

**B3 — SQLite yapılandırması yarışa açık.**
`database.py:9-11`: WAL yok, `busy_timeout` yok, `check_same_thread=False` ile aynı dosyaya API thread'leri + worker + indirme thread'leri (StatusTqdm her %2'de yazıyor) eşzamanlı yazıyor → `database is locked` hataları ve gizli seri hale gelme.

**B4 — Kuyruk tasarımı: tek semafor, tek tip.**
`TaskWorker(max_concurrent=1)` (`worker.py:19,339`): TTS ve STT aynı tek-slotluk kuyruğda. Bir XTTS işi sürerken kısa bir STT işi bile bekler. İndirmeler ayrı executor'da ama B1/B3 nedeniyle pratikte hepsi birbirini boğar.

**B5 — Üretimde `reload=True` + WatchFiles (`main.py:313`).**
Kod dosyası değişince sunucu yeniden başlar → süren task/indirme **ölür**; reloader ebeveyn süreci port kilitleyebilir (bu oturumda birebir yaşandı: eski PID 33289 portu tutuyordu).

### Aksiyon Planı (aşamalı)

**Aşama 1 — Hızlı kazanımlar (1 gün):**
1. `/models/available`'ı yeniden yaz: `results = await asyncio.gather(*[asyncio.to_thread(verify_model_files, m) for m in models])` — bloklayan `future.result` kalksın; sonuçları 10 sn TTL ile cache'le (her poll'da disk taraması yapma).
2. SQLite: engine'e `connect_args={"check_same_thread": False, "timeout": 30}` + bağlantı event'inde `PRAGMA journal_mode=WAL; PRAGMA busy_timeout=30000; PRAGMA synchronous=NORMAL`.
3. `reload` bayrağını env'e bağla (`RELOAD=false` varsayılan); `start-macos.sh` üretim modunda reload'suz başlatsın.
4. `StatusTqdm`'in DB yazma sıklığını %2 → %5'e düşür ve tek satır `UPDATE` kullan.

**Aşama 2 — Inference'ı süreç izolasyonuna al (asıl çözüm, 2-4 gün):**
5. Tortoise'daki subprocess wrapper desenini genelleştir: `app/tts/engine_host.py` — model başına (veya tek paylaşımlı) **ayrı Python süreci**; stdin/stdout JSON-RPC veya `multiprocessing.Process` + `Queue`. XTTS/Bark/Piper `generate_audio` çağrıları bu sürece gitsin. Kazanım: GIL izolasyonu (donma biter), çökme izolasyonu (MPS segfault API'yi öldürmez), gerçek RAM geri kazanımı (süreç kapatınca).
6. Model **yükleme** de aynı süreçte kalır; API süreci hiçbir zaman `torch.load` çalıştırmaz. `main.py`'daki preload, host sürecine "warm-up" komutu göndermeye dönüşür.

**Aşama 3 — Kuyruğu ayrıştır (orta vade):**
7. Kaynak-tipine göre semaforlar: `{"tts": 1, "stt": 1, "download": 2}` — kısa STT işleri uzun TTS'in arkasında beklemesin.
8. İsteğe bağlı: worker'ı tamamen ayrı sürece taşı (`python -m app.queue.worker`); DB-tabanlı kuyruk zaten buna uygun. API süreci yalnızca I/O yapar; bildirimler için worker → API'ye küçük bir iç HTTP callback'i veya DB-polling yeterli (ek broker gerekmez).

---

## 3. Sistem Kaynakları Footer Göstergesi (RAM / CPU / Disk)

### Mevcut Durum
- `static/index.html`'de **footer yok** (grep sıfır sonuç).
- `psutil` kurulu ve `app/core/device.py:41-48`'de yalnızca **statik** bilgi için kullanılıyor (çekirdek sayısı, toplam RAM); hiçbir router canlı kaynak verisi sunmuyor.
- WS altyapısı hazır: `connection_manager.py:67`'de `broadcast` mevcut — yeniden kullanılabilir.

### Aksiyon Planı

**Backend (yeni: `app/routers/system_router.py`):**
1. `GET /system/stats` endpoint'i:
   ```json
   {
     "cpu_pct": 23.4,
     "ram": {"used_gb": 12.3, "total_gb": 32.0, "pct": 38.4},
     "process_ram_gb": 6.1,
     "disk": {"used_gb": 420.0, "total_gb": 994.0, "pct": 42.2},
     "gpu": {"backend": "mps", "allocated_gb": 4.2}
   }
   ```
   - `psutil.cpu_percent(interval=None)` (bloklamayan form — `interval=1` **kullanma**, endpoint'i 1 sn kilitler)
   - `psutil.virtual_memory()`, `psutil.Process().memory_info().rss`
   - `psutil.disk_usage(settings.MODELS_DIR)` — modellerin yaşadığı diskin doluluğu kullanıcı için en anlamlısı
   - MPS: `torch.mps.current_allocated_memory()` (try/except ile, torch importunu endpoint'te yapma — modül seviyesinde zaten yüklü)
2. `main.py lifespan`'a periyodik görev: 2-3 sn'de bir, **yalnızca bağlı WS istemcisi varsa** (`manager.get_connection_count() > 0`) `broadcast({"event": "system_stats", "payload": ...})`. Not: Aşama 2-B5 inference izolasyonu yapılmadan bu yayın, ağır işlem sırasında donabilir — footer'ın "canlı" kalması aynı zamanda §2'nin doğal test göstergesi olur.

**Frontend:**
3. `index.html`: sidebar/main-content dışına sabit (sticky) footer bileşeni — üç mini gösterge (CPU %, RAM used/total, Disk %) + opsiyonel GPU. Mevcut Tailwind sınıf düzeniyle uyumlu.
4. `websocket.js`: `system_stats` event handler → footer DOM güncelle.
5. Fallback: WS bağlı değilse 5 sn'lik `setInterval` ile `/system/stats` polling (`api.js`'e `getSystemStats()` ekle); WS gelince polling'i durdur.
6. Eşik renklendirmesi: >%80 sarı, >%92 kırmızı — kullanıcı ağır modeli yüklemeden önce uyarı görür.

---

## 4. Diğer Tespit Edilen Darboğazlar ve Riskler

| # | Bulgu | Konum | Önem |
|---|-------|-------|------|
| D1 | `requirements.txt`'te `spacy`/`thinc` pin'siz — bugünkü kırılmanın kökü; gelecekte sessizce tekrar kırılır | `requirements.txt` | Yüksek |
| D2 | `asyncio.get_event_loop()` deprecated kullanım | `main.py:55` → `get_running_loop()` | Düşük |
| D3 | Preload haritası `piper`'ı map'liyor ama `get_ready_for_tts` piper döndürmüyor — ölü eşleme | `main.py:83`, `integrity_checker.py:255` | Orta |
| D4 | `TTSRegistry._instances` sınırsız singleton — çok motor yüklenirse RAM şişer, unload yolu yok | `app/tts/registry.py:32` | Orta |
| D5 | `openai-whisper` + `faster-whisper` birlikte — çift bağımlılık, kurulum süresi/çakışma riski; faster-whisper yeterli | `requirements.txt` | Düşük |
| D6 | `submit_tts_task` payload'ına `user_id` koymuyor (imzada var, payload'a yazılmıyor) → playlist görevlerinde WS bildirimi gitmez | `worker.py:294-322` | Orta |
| D7 | `Task.status/created_at` için DB index yokluğu (kuyruk büyüyünce `_get_pending_task` yavaşlar) | `app/models/queue.py` | Düşük |
| D8 | 3.232 satırlık tek `app.js` — bakım/regresyon riski | `static/js/app.js` | Düşük |
| D9 | Test altyapısı yok (`test_audio.py` script'i var, framework yok) — bu rapordaki değişiklikler regresyon güvencesiz uygulanacak | kök | Orta |
| D10 | `start-macos.sh` seçicisindeki zayıf `ensurepip` filtresi (bkz. §0) | `start-macos.sh` | Orta |

## 5. Performans İyileştirme Önerileri

1. **XTTS latent cache:** `get_conditioning_latents` her üretimde yeniden hesaplanıyor (`xtts.py:190`) — profil başına latent'i `{profile_path_mtime: latents}` ile cache'le. Aynı profille ardışık üretimlerde **belirgin** hızlanma.
2. **`/models/available` sonucu 10 sn cache** + integrity taramasında `os.walk` yerine dizin `mtime` kontrolü (değişmediyse önceki sonucu döndür).
3. **Model unload API'si:** `TTSRegistry`'e `unload(name)` + `torch.mps.empty_cache()`; UI'dan "modeli bellekten çıkar" düğmesi (D4 ile birlikte).
4. **Statik sunum:** `frontend_server.py`'a gzip + `Cache-Control` header'ları (app.js ~100KB+).
5. **Preload'u yapılandırılabilir yap:** `PRELOAD_MODELS=xtts` env'i — her startup'ta tüm sağlıklı modelleri RAM'e yüklemek (mevcut davranış) Mac'te belleği gereksiz doldurur.
6. **SQLite `synchronous=NORMAL` + WAL** (§2-3 ile aynı madde; performans yönü: yazma gecikmesi ~10x düşer).
7. **uvloop zaten aktif** (`main.py:314`) — Aşama 2/3 sonrası `uvicorn --workers 2` mümkün hale gelir (bugün in-process worker + WS state nedeniyle imkânsız).

## 6. Önceliklendirilmiş Yol Haritası

| Sıra | İş | Bölüm | Tahmini Efor | Etki |
|------|----|-------|--------------|------|
| 1 | `/models/available` bloklamasını kaldır + cache | §2-1 | Saatler | Donmanın hızlı kısmî çözümü |
| 2 | SQLite WAL + busy_timeout | §2-2 | Saatler | Kilitlenme hatalarını bitirir |
| 3 | Startup'ta stale `downloading` onarımı | §1-4 | Saatler | İndirme kilitlenmesini çözer |
| 4 | İndirme kimliği + bayt-bazlı ilerleme + WS yayını | §1-1,2,3 | 1-2 gün | İndirme takibi güvenilir olur |
| 5 | `reload=False` üretim modu | §2-3 | Saatler | Task ölümü/port kilidi biter |
| 6 | Inference subprocess izolasyonu (Tortoise deseni genelleştirme) | §2-5,6 | 2-4 gün | **Donma sorununun kalıcı çözümü** |
| 7 | Bütünlük: sha256 + piper kapsamı + atomik yazma | §1-5,6 | 1 gün | Bozuk model tespiti gerçek olur |
| 8 | Sistem kaynakları footer (endpoint + WS + UI) | §3 | 1 gün | Kullanıcı görünürlüğü |
| 9 | Kuyruk semafor ayrımı (tts/stt/download) | §2-7 | Saatler | Paralellik hissi |
| 10 | D1-D10 temizlikleri + pin'ler | §4 | 1 gün | Kararlılık |

---

*Rapor sonu. Uygulama sırasında her madde kendi dosya:satır referansından doğrulanmalı; bu analiz 2026-07-10 tarihli çalışma ağacına aittir.*
