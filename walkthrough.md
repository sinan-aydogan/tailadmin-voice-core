# Walkthrough - NativePHP Multi-Platform Release (Windows, Linux, macOS)

Voice Core projesi için NativePHP çoklu platform derleme altyapısı kuruldu ve Windows, Linux, macOS paketleri GitHub Actions üzerinden derlenerek GitHub Releases üzerinde resmi olarak yayınlandı.

---

## 🚀 Yayınlanan Sürüm Bilgileri

- **GitHub Release:** [Voice Core Release v1.0.0](https://github.com/sinan-aydogan/tailadmin-voice-core/releases/tag/v1.0.0)
- **Tag:** `v1.0.0`
- **Tarih:** 11 Eylül 2026

### 📦 Derlenen ve Yayınlanan Paketler

| Platform | Mimari | Dosya Adı | Format |
|---|---|---|---|
| **Windows** | x64 | `electron.exe` | Windows Installer / Executable |
| **Linux** | x64 (amd64) | `Voice.Core-v1.0.0.AppImage` | Universal Linux AppImage |
| **Linux** | x64 (amd64) | `voice-core_v1.0.0_amd64.deb` | Debian / Ubuntu Package |
| **macOS** | Apple Silicon (arm64) | `Voice.Core-v1.0.0-arm64.dmg` | macOS Disk Image |
| **macOS** | Apple Silicon (arm64) | `Voice.Core-v1.0.0-arm64.zip` | macOS Application Zip |

---

## 🛠️ Gerçekleştirilen İşlemler & Düzeltmeler

1. **NativePHP Konfigürasyonu:**
   - [`config/nativephp.php`](file:///c:/Users/sinan/Projeler/tailadmin-voice-core/config/nativephp.php) yapılandırılarak `app_id` (`com.tailadmin.voicecore`), GitHub updater ayarları ve veri dışlama kuralları tanımlandı.
   - `config/app.php` ve `.env.example` içinde varsayılan `APP_NAME` `"Voice Core"` olarak ayarlandı.

2. **Upstream NativePHP Rollup Hatasının Çözümü:**
   - NativePHP v2.3.0 sürümünde `dist/server/api/system.js` dosyasının `../pdfPageSize.js` dosyasını çağırdığı ancak bu dosyanın dağıtım paketinde derlenip eklenmediği tespit edildi.
   - [`patches/pdfPageSize.js`](file:///c:/Users/sinan/Projeler/tailadmin-voice-core/patches/pdfPageSize.js) yaması oluşturuldu.
   - [`composer.json`](file:///c:/Users/sinan/Projeler/tailadmin-voice-core/composer.json) içerisindeki `post-autoload-dump` hook'una ve GitHub Actions workflow'una yama kopyalama adımı entegre edildi.

3. **CI/CD GitHub Actions Pipeline:**
   - [`.github/workflows/release.yml`](file:///c:/Users/sinan/Projeler/tailadmin-voice-core/.github/workflows/release.yml) iş akışı oluşturuldu:
     - `windows-latest` (Windows x64)
     - `ubuntu-latest` (Linux x64)
     - `macos-latest` (macOS arm64)
   - PHP 8.4, Node.js 22, Python 3.10 ve bağımlılık kurulumları yapılandırıldı.
   - Her platform `php artisan native:build` ile paketlendi, artifact'ler toplandı ve `softprops/action-gh-release@v2` ile otomatik olarak GitHub Release'e yüklendi.

---

## ✅ Doğrulama

- GitHub Actions Run [34627665343](https://github.com/sinan-aydogan/tailadmin-voice-core/actions/runs/34627665343) tüm adımlarıyla (Windows, Linux, macOS derlemeleri ve Publish Release) başarıyla tamamlandı.
- `gh release view v1.0.0` komutu ile release'in ve 5 adet indirme dosyasının yayında olduğu doğrulandı.
