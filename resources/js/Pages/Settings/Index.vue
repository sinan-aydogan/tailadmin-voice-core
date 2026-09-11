<template>
  <AppLayout title="Ayarlar">
    <div class="space-y-6 max-w-4xl pb-12">
      <!-- Success Banner -->
      <transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div
          v-if="saveSuccess"
          class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300 flex items-center justify-between shadow-lg shadow-emerald-500/5"
        >
          <div class="flex items-center gap-2.5">
            <svg class="w-4 h-4 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
            <span class="font-medium">Ayarlar başarıyla kaydedildi. Değişiklikler sisteme uygulandı.</span>
          </div>
          <button @click="saveSuccess = false" class="text-emerald-400 hover:text-emerald-200 cursor-pointer">✕</button>
        </div>
      </transition>

      <!-- Tab Navigation Bar -->
      <div class="p-1.5 rounded-2xl bg-surface border border-neutral-800 grid grid-cols-2 md:grid-cols-4 gap-1.5 shadow-sm">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          @click="setTab(tab.id)"
          :class="[
            'p-3 rounded-xl text-left transition-all flex items-center gap-3 cursor-pointer border',
            activeTab === tab.id
              ? 'bg-accent/15 border-accent text-neutral-100 shadow-md shadow-accent/5'
              : 'bg-transparent border-transparent text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/50'
          ]"
        >
          <span class="text-xl select-none flex-shrink-0">{{ tab.icon }}</span>
          <div class="min-w-0 flex-1">
            <div class="text-xs font-semibold truncate" :class="{ 'text-accent-300': activeTab === tab.id }">
              {{ tab.label }}
            </div>
            <div class="text-[10px] text-neutral-500 truncate flex items-center gap-1.5 mt-0.5">
              <span>{{ tab.desc }}</span>
              <span
                v-if="tab.id === 'api' && isApiProtected"
                class="w-1.5 h-1.5 rounded-full bg-emerald-400 flex-shrink-0"
                title="Korumalı Mod"
              ></span>
              <span
                v-else-if="tab.id === 'api'"
                class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"
                title="Açık Mod"
              ></span>
            </div>
          </div>
        </button>
      </div>

      <!-- Main Settings Form -->
      <form @submit.prevent="saveSettings" class="space-y-6">
        <!-- ==================== TAB 1: GENEL & ARAYÜZ ==================== -->
        <div v-show="activeTab === 'general'" class="space-y-6">
          <!-- Application Display Language Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                  <span class="text-base select-none">🌐</span>
                </div>
                <div>
                  <h2 class="text-base font-semibold text-neutral-100">{{ t('settings.app_language', 'Uygulama Arayüz Dili') }}</h2>
                  <p class="text-xs text-neutral-500 mt-0.5">{{ t('settings.app_language_desc', 'Yönetim panelinde kullanılacak varsayılan arayüz dili.') }}</p>
                </div>
              </div>
              <span class="px-2.5 py-1 rounded-md bg-accent/10 border border-accent/20 text-[10px] font-medium text-accent-400">
                Anında Uygulanır
              </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 pt-1">
              <button
                v-for="lang in languages"
                :key="lang.code"
                type="button"
                @click="setLocale(lang.code)"
                :class="[
                  'p-3 rounded-xl border text-left transition-all flex items-center gap-2.5 cursor-pointer',
                  locale === lang.code
                    ? 'bg-accent/15 border-accent text-neutral-100 font-semibold shadow-lg shadow-accent/10'
                    : 'bg-neutral-900/60 border-neutral-800 hover:border-neutral-700 text-neutral-300 hover:bg-neutral-900'
                ]"
              >
                <span class="text-lg">{{ lang.flag }}</span>
                <div class="min-w-0 flex-1">
                  <div class="text-xs truncate font-medium">{{ lang.nativeName }}</div>
                  <div class="text-[10px] text-neutral-500 truncate">{{ lang.name }}</div>
                </div>
                <span v-if="locale === lang.code" class="text-accent text-xs">✓</span>
              </button>
            </div>
          </div>

          <!-- Architecture & System Overview Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400">
                <span class="text-base select-none">💻</span>
              </div>
              <div>
                <h3 class="text-base font-semibold text-neutral-100">Sistem Mimarisi ve Donma Çözümü</h3>
                <p class="text-xs text-neutral-500 mt-0.5">Masaüstü ve sunucu çalışma mekanizması hakkında bilgiler.</p>
              </div>
            </div>

            <p class="text-xs text-neutral-400 leading-relaxed">
              TailAdmin Voice Core altyapısında tüm ses modelleri ve PyTorch çıkarım (inference) işlemleri ayrı işletim sistemi süreçlerinde (Process) çalıştırılır.
              Laravel Queue Worker arkaplanda görevleri işletirken Electron ve Inertia arayüzü ana thread'de 60 FPS akıcı çalışmaya devam eder.
            </p>

            <div class="p-3.5 rounded-xl bg-neutral-900/80 border border-neutral-800 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div>
                <div class="text-[10px] text-neutral-500">Backend Framework</div>
                <div class="font-mono text-neutral-200 font-medium">Laravel 13</div>
              </div>
              <div>
                <div class="text-[10px] text-neutral-500">Desktop Runtime</div>
                <div class="font-mono text-neutral-200 font-medium">NativePHP Electron</div>
              </div>
              <div>
                <div class="text-[10px] text-neutral-500">Arayüz Altyapısı</div>
                <div class="font-mono text-accent-400 font-medium">Vue 3 + Inertia</div>
              </div>
              <div>
                <div class="text-[10px] text-neutral-500">AI Motoru</div>
                <div class="font-mono text-neutral-200 font-medium">Python 3.11</div>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== TAB 2: AI MODELLERİ & DEPOLAMA ==================== -->
        <div v-show="activeTab === 'models'" class="space-y-6">
          <!-- Models Directory Configuration Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
              </div>
              <div>
                <h2 class="text-base font-semibold text-neutral-100">Model Depolama ve İndirme Klasörü</h2>
                <p class="text-xs text-neutral-500 mt-0.5">Yapay zeka ses modellerinin (XTTS, Whisper, Piper vb.) indirileceği ve saklanacağı yerel disk dizini.</p>
              </div>
            </div>

            <div class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1.5">Klasör Yolu</label>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                  <input
                    v-model="form.models_dir"
                    type="text"
                    required
                    placeholder="C:\...\data\models"
                    class="flex-1 rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs font-mono text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                  />

                  <button
                    type="button"
                    @click="browseFolder"
                    :disabled="isBrowsing"
                    class="px-4 py-3 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-semibold transition-colors disabled:opacity-50 flex items-center justify-center gap-2 whitespace-nowrap border border-neutral-700/60 cursor-pointer"
                  >
                    <svg v-if="isBrowsing" class="w-4 h-4 animate-spin text-accent" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                    </svg>
                    <span>{{ isBrowsing ? 'Açılıyor...' : 'Klasör Seç' }}</span>
                  </button>

                  <button
                    type="button"
                    @click="resetToDefault"
                    class="px-3.5 py-3 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 text-xs font-medium transition-colors border border-neutral-800 whitespace-nowrap cursor-pointer"
                    title="Varsayılan proje dizinine dön"
                  >
                    Sıfırla
                  </button>
                </div>
              </div>

              <div class="p-3.5 rounded-xl bg-neutral-900/60 border border-neutral-800 text-xs text-neutral-400 flex items-start gap-2.5">
                <svg class="w-4 h-4 text-accent flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="space-y-1">
                  <div>Farklı bir sürücü (örneğin <code class="text-accent font-mono text-[11px]">D:\AI_Models</code> veya harici SSD) seçerek işletim sistemi diskinizde yer tasarrufu sağlayabilirsiniz.</div>
                  <div class="text-[11px] text-neutral-500">Klasörü değiştirdiğinizde, Model Yöneticisi ve Metin Okuma motorları yeni klasördeki modelleri otomatik olarak tarayacaktır.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Hugging Face API Token Configuration Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                  <span class="text-base select-none">🤗</span>
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-neutral-100">Hugging Face API Anahtarı (Access Token)</h2>
                    <span v-if="form.hf_token" class="px-2 py-0.5 rounded-md bg-emerald-500/15 border border-emerald-500/30 text-[10px] font-semibold text-emerald-400 flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                      Aktif
                    </span>
                    <span v-else class="px-2 py-0.5 rounded-md bg-neutral-800 text-[10px] text-neutral-400">
                      Opsiyonel
                    </span>
                  </div>
                  <p class="text-xs text-neutral-500 mt-0.5">Hugging Face üzerinden model (XTTS, Bark, Whisper, Tortoise vb.) indirirken API istek sınırı ve kota engellerine takılmamak için kullanılır.</p>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1.5">User Access Token</label>
                <div class="relative flex items-center">
                  <input
                    v-model="form.hf_token"
                    :type="showToken ? 'text' : 'password'"
                    placeholder="hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 pr-24 text-xs font-mono text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                  />
                  <div class="absolute right-2.5 flex items-center gap-1">
                    <button
                      type="button"
                      @click="showToken = !showToken"
                      class="px-2.5 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs transition-colors cursor-pointer"
                      :title="showToken ? 'Gizle' : 'Göster'"
                    >
                      {{ showToken ? 'Gizle' : 'Göster' }}
                    </button>
                    <button
                      v-if="form.hf_token"
                      type="button"
                      @click="form.hf_token = ''"
                      class="px-2.5 py-1.5 rounded-lg text-neutral-400 hover:text-red-400 text-xs transition-colors cursor-pointer"
                      title="Temizle"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </div>

              <div class="p-3.5 rounded-xl bg-neutral-900/60 border border-neutral-800 text-xs text-neutral-400 flex items-start gap-2.5">
                <svg class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="space-y-1">
                  <div>
                    Hugging Face hesabınız yoksa veya token oluşturmak isterseniz
                    <a href="https://huggingface.co/settings/tokens" target="_blank" rel="noopener noreferrer" class="text-accent underline hover:text-accent-300 font-medium">huggingface.co/settings/tokens</a>
                    sayfasından <span class="text-neutral-200 font-semibold">"Read" (Okuma)</span> izinli ücretsiz bir token alabilirsiniz.
                  </div>
                  <div class="text-[11px] text-neutral-500">
                    Anahtar girildiğinde model indirme istekleri kimlik doğrulamalı yapılır; böylece anonim IP rate limitlerine takılmadan indirme gerçekleşir.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Default Engine Setting Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                <span class="text-base select-none">🎙️</span>
              </div>
              <div>
                <h2 class="text-base font-semibold text-neutral-100">Varsayılan Metin Okuma (TTS) Motoru</h2>
                <p class="text-xs text-neutral-500 mt-0.5">Yeni ses üretim sayfalarında varsayılan olarak seçili gelecek motor tercihi.</p>
              </div>
            </div>

            <div class="space-y-2">
              <select
                v-model="form.default_tts_engine"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              >
                <option value="piper-tr">Piper TTS (Türkçe - Ultra Hızlı & Düşük Kaynak)</option>
                <option value="xtts-v2">Coqui XTTS v2 (Yüksek Kalite / Ses Klonlama)</option>
                <option value="bark">Suno Bark (Doğal / İfadeli & Çok Dilli)</option>
              </select>
              <div class="text-[11px] text-neutral-500">
                Hafif cihazlar ve günlük okumalar için <strong>Piper TTS</strong>; kişisel ses klonlama için <strong>Coqui XTTS v2</strong> önerilir.
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== TAB 3: DONANIM & PERFORMANS ==================== -->
        <div v-show="activeTab === 'hardware'" class="space-y-6">
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                <span class="text-base select-none">⚡</span>
              </div>
              <div>
                <h2 class="text-base font-semibold text-neutral-100">Donanım Hızlandırma & GPU Ayarları</h2>
                <p class="text-xs text-neutral-500 mt-0.5">Yapay zeka modellerinin işletileceği işlemci ve grafik donanım konfigürasyonu.</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
              <!-- GPU Setting -->
              <div class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-2">
                <label class="block text-xs font-medium text-neutral-300">Hızlandırıcı / GPU Modu</label>
                <select
                  v-model="form.use_gpu"
                  class="w-full rounded-lg bg-neutral-800 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                >
                  <option value="auto">Otomatik Algıla (CUDA / MPS / CPU)</option>
                  <option value="cuda">NVIDIA CUDA (Zorla GPU)</option>
                  <option value="mps">Apple Metal (MPS)</option>
                  <option value="cpu">Yalnızca CPU</option>
                </select>
                <div class="text-[11px] text-neutral-500">Mevcut GPU donanımınıza göre çıkarım hızını optimize eder.</div>
              </div>

              <!-- Max CPU Threads -->
              <div class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-2">
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-medium text-neutral-300">Maksimum CPU Thread</label>
                  <span class="text-xs font-mono font-semibold text-accent">{{ form.max_cpu_threads }} Çekirdek</span>
                </div>
                <input
                  v-model.number="form.max_cpu_threads"
                  type="range"
                  min="1"
                  max="32"
                  step="1"
                  class="w-full accent-accent cursor-pointer"
                />
                <div class="text-[11px] text-neutral-500">PyTorch çıkarımlarında kullanılacak maksimum CPU çekirdek sayısı.</div>
              </div>
            </div>

            <!-- Hardware Guidelines Info Box -->
            <div class="p-4 rounded-xl bg-neutral-900/60 border border-neutral-800 text-xs text-neutral-400 space-y-2">
              <div class="font-medium text-neutral-200 flex items-center gap-1.5">
                <span>💡 Donanım & Model Bellek Tavsiyesi</span>
              </div>
              <ul class="text-[11px] text-neutral-400 space-y-1.5 list-disc list-inside">
                <li><strong class="text-neutral-200">Piper TTS:</strong> Çok hafif modeldir (~50MB RAM). CPU üzerinde neredeyse anında (real-time) ses üretir.</li>
                <li><strong class="text-neutral-200">Coqui XTTS v2:</strong> En az 3-4 GB VRAM'e sahip NVIDIA GPU (CUDA) ile saniyeler içinde klonlama yapar. CPU'da daha uzun sürebilir.</li>
                <li><strong class="text-neutral-200">Suno Bark:</strong> Doğal tonlamalar ve ses efektleri üretir; en iyi performansı NVIDIA GPU ile verir.</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- ==================== TAB 4: REST API & ENTEGRASYON ==================== -->
        <div v-show="activeTab === 'api'" class="space-y-6">
          <!-- Top Info Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                  <span class="text-base select-none">🔌</span>
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-neutral-100">REST API Servisi ve Entegrasyon</h2>
                    <span v-if="isApiProtected" class="px-2 py-0.5 rounded-md bg-emerald-500/15 border border-emerald-500/30 text-[10px] font-semibold text-emerald-400 flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                      Korumalı Mod ({{ activeKeysCount }} Aktif Anahtar)
                    </span>
                    <span v-else class="px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-[10px] font-semibold text-amber-400 flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                      Açık Mod (Yetkilendirme Yok)
                    </span>
                  </div>
                  <p class="text-xs text-neutral-500 mt-0.5">
                    Mobil uygulamalar, otomasyon botları ve harici yazılımlar için HTTP uç noktaları ve isimlendirilmiş anahtar yönetimi.
                  </p>
                </div>
              </div>
              <a
                href="/api/v1/health"
                target="_blank"
                class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-neutral-900 border border-neutral-700 hover:border-neutral-600 text-neutral-300 text-xs font-medium transition-colors"
                title="API Sağlık Kontrolünü Yeni Sekmede Aç"
              >
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>API Sağlık Durumu</span>
              </a>
            </div>

            <!-- Base API URL Info -->
            <div class="p-3.5 rounded-xl bg-neutral-900/80 border border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <div class="text-[11px] text-neutral-400 font-medium">Temel API URL'si (Base Endpoint)</div>
                <code class="text-xs font-mono text-accent font-semibold select-all">{{ currentApiUrl }}</code>
              </div>
              <button
                type="button"
                @click="copyToClipboard(currentApiUrl, 'url')"
                class="self-start sm:self-center px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs transition-colors flex items-center gap-1.5 border border-neutral-700/60 cursor-pointer"
              >
                <span>{{ copiedUrl ? '✓ Kopyalandı' : '📋 URL Kopyala' }}</span>
              </button>
            </div>
          </div>

          <!-- Multi-Key Management Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-neutral-100 flex items-center gap-2">
                  <span>🔑 Yetkilendirilmiş API Anahtarları</span>
                  <span class="px-2 py-0.2 rounded-full bg-neutral-800 text-[10px] text-neutral-300 font-mono">
                    {{ localApiKeys.length }}
                  </span>
                </h3>
                <p class="text-xs text-neutral-500 mt-0.5">
                  Farklı istemciler için isimlendirilmiş anahtarlar tanımlayın ve işlemlerini takip edin.
                </p>
              </div>

              <button
                type="button"
                @click="showCreateForm = !showCreateForm"
                class="px-3.5 py-2 rounded-xl bg-accent text-bg hover:opacity-90 text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm shadow-accent/10"
              >
                <span>{{ showCreateForm ? '✕ İptal' : '+ Yeni Anahtar Ekle' }}</span>
              </button>
            </div>

            <!-- Inline Create New Key Form -->
            <transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="opacity-0 -translate-y-2"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-2"
            >
              <div v-if="showCreateForm" class="p-4 rounded-xl bg-neutral-900 border border-neutral-700/80 space-y-3.5">
                <div class="text-xs font-semibold text-neutral-200">Yeni API Anahtarı Oluştur</div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <!-- Name input -->
                  <div>
                    <label class="block text-[11px] font-medium text-neutral-400 mb-1">Anahtar Adı / İstemci Tanımı</label>
                    <input
                      v-model="newKeyForm.name"
                      type="text"
                      placeholder="Örn: Sureler Mobil Uygulama, N8N Bot..."
                      class="w-full rounded-xl bg-neutral-800 border border-neutral-700 p-2.5 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                    />
                  </div>

                  <!-- Key string input -->
                  <div>
                    <div class="flex items-center justify-between mb-1">
                      <label class="block text-[11px] font-medium text-neutral-400">Gizli Anahtar (Key)</label>
                      <button
                        type="button"
                        @click="generateRandomNewKey"
                        class="text-[10.5px] text-accent hover:text-accent-300 transition-colors cursor-pointer"
                      >
                        🎲 Yeniden Üret
                      </button>
                    </div>
                    <input
                      v-model="newKeyForm.key"
                      type="text"
                      placeholder="vc_live_..."
                      class="w-full rounded-xl bg-neutral-800 border border-neutral-700 p-2.5 text-xs font-mono text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                    />
                  </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                  <button
                    type="button"
                    @click="showCreateForm = false"
                    class="px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs transition-colors cursor-pointer"
                  >
                    Vazgeç
                  </button>
                  <button
                    type="button"
                    @click="createApiKey"
                    :disabled="isCreatingKey || !newKeyForm.name.trim()"
                    class="px-4 py-1.5 rounded-lg bg-accent text-bg hover:opacity-90 text-xs font-semibold transition-opacity disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
                  >
                    <span v-if="isCreatingKey" class="w-3 h-3 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
                    <span>{{ isCreatingKey ? 'Ekleniyor...' : 'Anahtarı Kaydet' }}</span>
                  </button>
                </div>
              </div>
            </transition>

            <!-- API Keys Table / List -->
            <div v-if="localApiKeys.length === 0" class="p-8 text-center rounded-xl bg-neutral-900/40 border border-neutral-800 space-y-2">
              <div class="text-2xl">📭</div>
              <div class="text-xs font-medium text-neutral-300">Kayıtlı API Anahtarı Bulunmuyor</div>
              <div class="text-[11px] text-neutral-500 max-w-sm mx-auto">
                Henüz özel bir anahtar eklemediniz. İstemcilerinizin isteklerini takip etmek ve güvenliği sağlamak için yukarıdaki <strong>"+ Yeni Anahtar Ekle"</strong> düğmesini kullanabilirsiniz.
              </div>
            </div>

            <div v-else class="rounded-xl border border-neutral-800 overflow-hidden bg-neutral-900/30">
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead class="bg-neutral-900 border-b border-neutral-800 text-[11px] text-neutral-400 uppercase font-semibold tracking-wider">
                    <tr>
                      <th class="py-3 px-4">Anahtar Adı</th>
                      <th class="py-3 px-3">Gizli Anahtar</th>
                      <th class="py-3 px-3 text-center">İstek Sayısı</th>
                      <th class="py-3 px-3">Son Kullanım</th>
                      <th class="py-3 px-3 text-center">Durum</th>
                      <th class="py-3 px-4 text-right">İşlemler</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-neutral-800/60">
                    <tr
                      v-for="key in localApiKeys"
                      :key="key.id"
                      class="hover:bg-neutral-800/40 transition-colors"
                    >
                      <!-- Key Name -->
                      <td class="py-3 px-4 whitespace-nowrap">
                        <div class="font-semibold text-neutral-100 flex items-center gap-1.5">
                          <span>{{ key.name }}</span>
                        </div>
                        <div class="text-[10px] text-neutral-500">Eklenme: {{ formatDate(key.created_at) }}</div>
                      </td>

                      <!-- Key Value (Masked) -->
                      <td class="py-3 px-3 font-mono text-[11px] whitespace-nowrap">
                        <div class="flex items-center gap-1.5">
                          <span>{{ showKeyMap[key.id] ? key.key : maskKey(key.key) }}</span>
                          <button
                            type="button"
                            @click="toggleKeyVisibility(key.id)"
                            class="text-neutral-400 hover:text-neutral-200 p-0.5 cursor-pointer text-[10px]"
                            :title="showKeyMap[key.id] ? 'Gizle' : 'Göster'"
                          >
                            {{ showKeyMap[key.id] ? '👁️‍🗨️' : '👁️' }}
                          </button>
                          <button
                            type="button"
                            @click="copyKey(key.key, key.id)"
                            class="text-neutral-400 hover:text-accent p-0.5 cursor-pointer text-[10px]"
                            title="Kopyala"
                          >
                            {{ copiedKeyId === key.id ? '✓' : '📋' }}
                          </button>
                        </div>
                      </td>

                      <!-- Requests Count -->
                      <td class="py-3 px-3 text-center whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-neutral-800 text-neutral-300">
                          {{ key.requests_count || key.logs_count || 0 }} istek
                        </span>
                      </td>

                      <!-- Last Used -->
                      <td class="py-3 px-3 text-[11px] text-neutral-400 whitespace-nowrap">
                        {{ formatRelativeTime(key.last_used_at) || 'Hiç kullanılmadı' }}
                      </td>

                      <!-- Active Toggle -->
                      <td class="py-3 px-3 text-center whitespace-nowrap">
                        <button
                          type="button"
                          @click="toggleKeyActive(key)"
                          :class="[
                            'px-2.5 py-0.5 rounded-full text-[10px] font-semibold transition-colors cursor-pointer border',
                            key.is_active
                              ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/25'
                              : 'bg-neutral-800 border-neutral-700 text-neutral-400 hover:bg-neutral-700'
                          ]"
                          :title="key.is_active ? 'Devre dışı bırakmak için tıkla' : 'Etkinleştirmek için tıkla'"
                        >
                          {{ key.is_active ? 'Aktif' : 'Pasif' }}
                        </button>
                      </td>

                      <!-- Actions -->
                      <td class="py-3 px-4 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1.5">
                          <!-- History / Logs Button -->
                          <button
                            type="button"
                            @click="openLogsModal(key)"
                            class="px-2.5 py-1 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/25 text-xs font-medium transition-colors flex items-center gap-1 cursor-pointer"
                            title="Bu anahtara ait işlem kayıtlarını görüntüle"
                          >
                            <span>📜</span>
                            <span>İşlem Geçmişi</span>
                          </button>

                          <!-- Delete Button -->
                          <button
                            type="button"
                            @click="deleteApiKey(key)"
                            class="p-1 rounded-lg text-neutral-500 hover:text-red-400 hover:bg-red-500/10 transition-colors cursor-pointer"
                            title="Anahtarı Sil"
                          >
                            🗑️
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Master Key / .env notice -->
            <div class="p-3.5 rounded-xl bg-neutral-900/60 border border-neutral-800 text-xs text-neutral-400 space-y-2">
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-blue-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="space-y-1 text-[11px] leading-relaxed">
                  <div>
                    İsteklerinizi yetkilendirmek için HTTP başlığı olarak <code class="text-neutral-200 font-mono">X-API-Key: &lt;anahtar&gt;</code> veya <code class="text-neutral-200 font-mono">Authorization: Bearer &lt;anahtar&gt;</code> gönderin.
                  </div>
                  <div class="text-neutral-500">
                    Birden fazla anahtar oluşturduğunuzda her bir istemci kendi anahtarını kullanır. Bir anahtarın yanındaki <strong>"İşlem Geçmişi"</strong> düğmesine tıklayarak o anahtarla yapılan tüm TTS, STT ve model işlemlerini inceleyebilirsiniz.
                  </div>
                </div>
              </div>

              <!-- Quick cURL Example -->
              <div class="pt-2 border-t border-neutral-800/80">
                <div class="flex items-center justify-between text-[11px] text-neutral-300 mb-1.5">
                  <span class="font-medium">Örnek TTS İstek Kodu (cURL):</span>
                  <button
                    type="button"
                    @click="copyToClipboard(sampleCurlCommand, 'curl')"
                    class="text-accent hover:text-accent-300 font-medium transition-colors cursor-pointer"
                  >
                    {{ copiedCurl ? '✓ Kopyalandı' : 'Kodu Kopyala' }}
                  </button>
                </div>
                <pre class="p-2.5 rounded-lg bg-black/40 border border-neutral-800 font-mono text-[10.5px] text-neutral-300 overflow-x-auto select-all whitespace-pre">{{ sampleCurlCommand }}</pre>
              </div>
            </div>
          </div>
        </div>

        <!-- Sticky Bottom Action Bar -->
        <div class="sticky bottom-4 z-20 p-4 rounded-2xl bg-surface/95 backdrop-blur-md border border-neutral-800 flex items-center justify-between gap-4 shadow-2xl shadow-black/60">
          <div class="flex items-center gap-2.5 text-xs text-neutral-400 min-w-0">
            <span class="w-2 h-2 rounded-full bg-accent animate-pulse shrink-0"></span>
            <span class="truncate">Tüm sekmelerdeki değişiklikler kaydedilmeye hazırdır.</span>
          </div>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2.5 rounded-xl font-semibold text-xs bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2 shadow-lg shadow-accent/20 cursor-pointer shrink-0"
          >
            <span v-if="form.processing" class="w-3.5 h-3.5 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
            <span>{{ form.processing ? 'Kaydediliyor...' : 'Ayarları Kaydet' }}</span>
          </button>
        </div>
      </form>

      <!-- API Key Activity Logs Modal -->
      <ApiKeyLogsModal
        :show="showLogsModal"
        :api-key="selectedKeyForLogs"
        @close="showLogsModal = false"
        @logs-cleared="onLogsCleared"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import ApiKeyLogsModal from '../../Components/ApiKeyLogsModal.vue'
import { useI18n } from '../../i18n'

const props = defineProps({
  settings: Object,
  default_models_dir: String,
  api_keys: Array,
})

const { locale, setLocale, t, languages } = useI18n()

// Tab definitions
const tabs = [
  { id: 'general', label: 'Genel & Arayüz', icon: '🌐', desc: 'Dil ve Sistem' },
  { id: 'models', label: 'AI & Depolama', icon: '🤖', desc: 'Modeller & Token' },
  { id: 'hardware', label: 'Donanım & GPU', icon: '⚡', desc: 'GPU ve CPU Sınırları' },
  { id: 'api', label: 'REST API', icon: '🔌', desc: 'Anahtar & Geçmiş' },
]

const validTabIds = ['general', 'models', 'hardware', 'api']
const getInitialTab = () => {
  if (typeof window !== 'undefined' && window.location.hash) {
    const hash = window.location.hash.replace('#', '')
    if (validTabIds.includes(hash)) return hash
  }
  return 'general'
}

const activeTab = ref(getInitialTab())

const setTab = (tabId) => {
  activeTab.value = tabId
  if (typeof window !== 'undefined' && window.history) {
    window.history.replaceState(null, '', `#${tabId}`)
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    const handleHash = () => {
      const hash = window.location.hash.replace('#', '')
      if (validTabIds.includes(hash)) {
        activeTab.value = hash
      }
    }
    window.addEventListener('hashchange', handleHash)
  }
})

const isBrowsing = ref(false)
const saveSuccess = ref(false)
const showToken = ref(false)

const copiedUrl = ref(false)
const copiedCurl = ref(false)
const copiedKeyId = ref(null)

// Multi-key state
const localApiKeys = ref([...(props.api_keys || [])])
const showCreateForm = ref(false)
const isCreatingKey = ref(false)
const showKeyMap = ref({})

const generateRandomKeyString = () => {
  const chars = 'abcdef0123456789'
  let result = 'vc_live_'
  if (typeof window !== 'undefined' && window.crypto) {
    const array = new Uint8Array(24)
    window.crypto.getRandomValues(array)
    for (let i = 0; i < array.length; i++) {
      result += chars[array[i] % chars.length]
    }
  } else {
    for (let i = 0; i < 32; i++) {
      result += chars[Math.floor(Math.random() * chars.length)]
    }
  }
  return result
}

const newKeyForm = ref({
  name: '',
  key: generateRandomKeyString(),
})

const generateRandomNewKey = () => {
  newKeyForm.value.key = generateRandomKeyString()
}

// Logs modal state
const showLogsModal = ref(false)
const selectedKeyForLogs = ref(null)

const openLogsModal = (key) => {
  selectedKeyForLogs.value = key
  showLogsModal.value = true
}

const onLogsCleared = (keyId) => {
  const item = localApiKeys.value.find(k => k.id === keyId)
  if (item) {
    item.requests_count = 0
    item.logs_count = 0
  }
}

const createApiKey = async () => {
  if (!newKeyForm.value.name.trim()) return
  isCreatingKey.value = true
  try {
    const res = await fetch('/settings/api-keys', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        name: newKeyForm.value.name.trim(),
        key: newKeyForm.value.key.trim() || generateRandomKeyString(),
      })
    })
    if (res.ok) {
      const data = await res.json()
      if (data.success && data.api_key) {
        localApiKeys.value.unshift(data.api_key)
        newKeyForm.value.name = ''
        newKeyForm.value.key = generateRandomKeyString()
        showCreateForm.value = false
      }
    }
  } catch (err) {
    console.error('API anahtarı oluşturma hatası:', err)
  } finally {
    isCreatingKey.value = false
  }
}

const toggleKeyActive = async (key) => {
  try {
    const res = await fetch(`/settings/api-keys/${key.id}/toggle`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    })
    if (res.ok) {
      const data = await res.json()
      if (data.success) {
        key.is_active = data.is_active
      }
    }
  } catch (err) {
    console.error('Anahtar durum güncelleme hatası:', err)
  }
}

const deleteApiKey = async (key) => {
  if (!confirm(`"${key.name}" isimli API anahtarını silmek istediğinize emin misiniz?`)) {
    return
  }
  try {
    const res = await fetch(`/settings/api-keys/${key.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    })
    if (res.ok) {
      localApiKeys.value = localApiKeys.value.filter(k => k.id !== key.id)
    }
  } catch (err) {
    console.error('Anahtar silme hatası:', err)
  }
}

const toggleKeyVisibility = (keyId) => {
  showKeyMap.value[keyId] = !showKeyMap.value[keyId]
}

const copyKey = async (keyString, keyId) => {
  if (!keyString) return
  try {
    await navigator.clipboard.writeText(keyString)
    copiedKeyId.value = keyId
    setTimeout(() => { copiedKeyId.value = null }, 2000)
  } catch (err) {
    console.error('Kopyalama hatası:', err)
  }
}

const maskKey = (key) => {
  if (!key) return ''
  if (key.length <= 12) return key
  return key.substring(0, 8) + '••••••••••••' + key.substring(key.length - 4)
}

const activeKeysCount = computed(() => {
  return localApiKeys.value.filter(k => k.is_active).length
})

const isApiProtected = computed(() => {
  return activeKeysCount.value > 0 || !!form.voice_core_api_key
})

const form = useForm({
  models_dir: props.settings?.models_dir || props.default_models_dir || '',
  use_gpu: props.settings?.use_gpu || 'auto',
  max_cpu_threads: props.settings?.max_cpu_threads || 4,
  default_tts_engine: props.settings?.default_tts_engine || 'piper-tr',
  hf_token: props.settings?.hf_token || '',
  voice_core_api_key: props.settings?.voice_core_api_key || '',
})

const currentApiUrl = computed(() => {
  if (typeof window !== 'undefined' && window.location) {
    return `${window.location.origin}/api/v1`
  }
  return 'http://localhost:8000/api/v1'
})

const firstActiveKey = computed(() => {
  const active = localApiKeys.value.find(k => k.is_active)
  return active ? active.key : (form.voice_core_api_key || 'vc_live_...')
})

const sampleCurlCommand = computed(() => {
  const url = currentApiUrl.value
  const keyToUse = firstActiveKey.value
  const keyHeader = isApiProtected.value
    ? ` \\\n  -H "X-API-Key: ${keyToUse}"`
    : ''
  return `curl -X POST ${url}/tts/generate${keyHeader} \\
  -H "Content-Type: application/json" \\
  -d '{"text": "Merhaba dünya!", "engine": "piper-tr", "language": "tr"}'`
})

const copyToClipboard = async (text, type) => {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    if (type === 'url') {
      copiedUrl.value = true
      setTimeout(() => { copiedUrl.value = false }, 2000)
    } else if (type === 'curl') {
      copiedCurl.value = true
      setTimeout(() => { copiedCurl.value = false }, 2000)
    }
  } catch (err) {
    console.error('Kopyalama hatası:', err)
  }
}

const browseFolder = async () => {
  isBrowsing.value = true
  try {
    const res = await fetch('/settings/browse-folder', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      }
    })
    if (res.ok) {
      const data = await res.json()
      if (data.success && data.path) {
        form.models_dir = data.path
      }
    }
  } catch (err) {
    console.error('Klasör seçilirken hata:', err)
  } finally {
    isBrowsing.value = false
  }
}

const resetToDefault = () => {
  if (props.default_models_dir) {
    form.models_dir = props.default_models_dir
  }
}

const saveSettings = () => {
  form.post('/settings', {
    onSuccess: () => {
      saveSuccess.value = true
      setTimeout(() => {
        saveSuccess.value = false
      }, 4000)
    }
  })
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('tr-TR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

const formatRelativeTime = (dateStr) => {
  if (!dateStr) return null
  const d = new Date(dateStr)
  const diffSec = Math.round((new Date() - d) / 1000)
  if (diffSec < 60) return `${diffSec} saniye önce`
  const diffMin = Math.round(diffSec / 60)
  if (diffMin < 60) return `${diffMin} dk önce`
  const diffHour = Math.round(diffMin / 60)
  if (diffHour < 24) return `${diffHour} saat önce`
  const diffDay = Math.round(diffHour / 24)
  return `${diffDay} gün önce`
}
</script>
