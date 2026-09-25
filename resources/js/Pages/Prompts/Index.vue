<template>
  <AppLayout title="Prompt Şablonları & Hikaye Makinesi">
    <div class="space-y-6 max-w-6xl pb-16">

      <!-- Navigation Tabs: Story Director Studio vs Classical Templates -->
      <div class="p-1.5 rounded-2xl bg-surface border border-neutral-800 flex items-center justify-between gap-2 shadow-sm">
        <div class="flex items-center gap-1.5 w-full sm:w-auto">
          <button
            type="button"
            @click="activeMainTab = 'director'"
            :class="[
              'flex-1 sm:flex-initial px-4 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-center gap-2 cursor-pointer select-none',
              activeMainTab === 'director'
                ? 'bg-gradient-to-r from-purple-600 via-indigo-600 to-accent text-white shadow-md shadow-purple-500/20'
                : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/60'
            ]"
          >
            <span class="text-base">🎬</span>
            <span>AI Hikaye & Ses Tiyatrosu Makinesi</span>
            <span class="px-2 py-0.5 rounded-full bg-white/20 text-[10px] uppercase font-mono tracking-wider font-bold">YENİ</span>
          </button>

          <button
            type="button"
            @click="activeMainTab = 'templates'"
            :class="[
              'flex-1 sm:flex-initial px-4 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-center gap-2 cursor-pointer select-none',
              activeMainTab === 'templates'
                ? 'bg-neutral-800 text-neutral-100 border border-neutral-700 shadow-sm'
                : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/60'
            ]"
          >
            <span class="text-base">📝</span>
            <span>Klasik Prompt Şablonları</span>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-neutral-900 text-neutral-400 border border-neutral-800 font-mono">
              {{ filteredTemplates.length }}
            </span>
          </button>
        </div>

        <!-- Quick Info / Help or Action Button -->
        <div class="hidden md:flex items-center gap-2 pr-2">
          <button
            v-if="activeMainTab === 'director'"
            type="button"
            @click="showSfxModal = true"
            class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 border border-neutral-700/80 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer"
          >
            <span>🔊 SFX Efekt Kütüphanesi</span>
            <span class="text-[10px] px-1.5 py-0.2 rounded bg-neutral-900 text-neutral-400 font-mono">{{ sfxCatalogList.length }}</span>
          </button>

          <button
            v-if="activeMainTab === 'templates'"
            type="button"
            @click="openCreateModal"
            class="px-3.5 py-1.5 rounded-xl bg-accent text-bg font-semibold text-xs hover:opacity-90 transition-opacity flex items-center gap-1.5 shadow-sm cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span>Yeni Şablon</span>
          </button>
        </div>
      </div>

      <!-- ======================================================== -->
      <!-- TAB 1: AI HİKAYE & SES TİYATROSU MAKİNESİ (DIRECTOR) -->
      <!-- ======================================================== -->
      <div v-if="activeMainTab === 'director'" class="space-y-6">

        <!-- Hero Card -->
        <div class="p-6 rounded-3xl bg-gradient-to-br from-neutral-900 via-purple-950/20 to-neutral-900 border border-purple-500/20 shadow-xl relative overflow-hidden">
          <div class="absolute -right-16 -top-16 w-64 h-64 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>
          <div class="absolute right-32 -bottom-20 w-52 h-52 bg-accent/10 rounded-full blur-3xl pointer-events-none"></div>

          <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
              <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/15 border border-purple-500/30 text-purple-300 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                <span>Çok Kanallı AI Yönetmen & Ses Tiyatrosu</span>
              </div>
              <h1 class="text-xl sm:text-2xl font-bold text-neutral-100 tracking-tight flex items-center gap-2.5">
                <span>Hikaye & Ses Tiyatrosu Makinesi</span>
                <span class="text-2xl">🎭</span>
              </h1>
              <p class="text-xs sm:text-sm text-neutral-300 leading-relaxed font-sans">
                Yalnızca metin değil; <span class="text-purple-300 font-medium">Karakter Vokalleri</span>,
                <span class="text-indigo-300 font-medium">MusicGen Dinamik Fon Müziği (-14 dB Ducking)</span>,
                <span class="text-amber-300 font-medium">Kelime Zaman Hizalı Ses Efektleri (SFX)</span> ve
                <span class="text-emerald-300 font-medium">Animasyon Direktiflerini</span> tek bir zaman çizelgesinde (Timeline) üreten profesyonel hikaye fabrikası.
              </p>
            </div>

            <!-- Fast Stats / Quick Actions -->
            <div class="flex items-center gap-3">
              <div class="p-3.5 rounded-2xl bg-neutral-900/80 border border-neutral-800 text-center min-w-[100px]">
                <div class="text-lg font-bold text-neutral-100 font-mono">{{ storyProjectsList.length }}</div>
                <div class="text-[11px] text-neutral-400">Kayıtlı Proje</div>
              </div>
              <div class="p-3.5 rounded-2xl bg-neutral-900/80 border border-neutral-800 text-center min-w-[100px]">
                <div class="text-lg font-bold text-purple-300 font-mono">0 ms</div>
                <div class="text-[11px] text-neutral-400">SFX Gecikmesi</div>
              </div>
              <div class="p-3.5 rounded-2xl bg-neutral-900/80 border border-neutral-800 text-center min-w-[100px]">
                <div class="text-lg font-bold text-accent font-mono">-14 dB</div>
                <div class="text-[11px] text-neutral-400">Oto Ducking</div>
              </div>
            </div>
          </div>

          <!-- Quick Preset Chips -->
          <div class="mt-6 pt-5 border-t border-neutral-800/80 space-y-2">
            <div class="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider flex items-center gap-2">
              <span>Hızlı Başlangıç Konseptleri (Tek Tıkla Dene)</span>
              <span class="text-neutral-500 text-xs">⚡</span>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
              <button
                v-for="(preset, idx) in presetConcepts"
                :key="idx"
                type="button"
                @click="applyPreset(preset)"
                class="px-3 py-1.5 rounded-xl bg-neutral-800/80 hover:bg-purple-900/40 text-neutral-200 border border-neutral-700 hover:border-purple-500/50 text-xs font-medium transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 shadow-sm"
              >
                <span>{{ preset.emoji }}</span>
                <span>{{ preset.title }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Creation & Refinement Studio Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

          <!-- Left: Prompt Generator & Options (5 cols) -->
          <div class="lg:col-span-5 space-y-6">
            <div class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-5 shadow-sm">
              <div class="flex items-center justify-between flex-wrap gap-2">
                <h2 class="text-sm font-semibold text-neutral-100 flex items-center gap-2">
                  <span>1. Hikaye Teması & Yönetmen Talimatı</span>
                </h2>
                
                <button
                  type="button"
                  @click="openLlmModal"
                  class="group flex items-center gap-2 px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-700/80 hover:border-purple-500/80 text-[11px] text-neutral-200 transition-all cursor-pointer shadow-sm hover:bg-neutral-850"
                  :title="'Aktif LLM: ' + currentLlm.provider + ' (' + currentLlm.model + ') - Durum: ' + (llmOnlineStatus === true ? 'Bağlantı Başarılı' : (llmOnlineStatus === false ? 'Bağlantı Kurulamadı / Çevrimdışı' : 'Kontrol Ediliyor...')) + ' (Ayarları değiştirmek için tıklayın)'"
                >
                  <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="[
                      llmOnlineStatus === true ? 'bg-emerald-400 shadow-sm shadow-emerald-400/50' :
                      llmOnlineStatus === false ? 'bg-red-400 shadow-sm shadow-red-400/50' :
                      'bg-amber-400 animate-pulse'
                    ]"
                  ></span>
                  <span class="font-medium text-purple-300 capitalize">{{ currentLlm.provider }}</span>
                  <span class="text-neutral-400 font-mono text-[10px] hidden sm:inline max-w-[120px] truncate">
                    {{ currentLlm.model }}
                  </span>
                  <span class="text-neutral-400 group-hover:text-purple-300 text-xs ml-0.5">⚙️</span>
                </button>
              </div>

              <!-- Theme Prompt Input -->
              <div class="space-y-1.5">
                <label class="block text-xs font-medium text-neutral-400">
                  Hikaye Konsepti veya Amacı <span class="text-red-400">*</span>
                </label>
                <textarea
                  v-model="directorTheme"
                  rows="4"
                  placeholder="Örn: 7-9 yaş çocuklara sigaranın ve dumanın akciğerlere zararlarını anlatan, küçük Can ve sevimli köpeği Karabaş'ın parktaki macerasını içeren, öksürük ve rüzgar efektleriyle desteklenmiş eğitici bir masal oluştur..."
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent leading-relaxed"
                ></textarea>
                <div class="flex items-center justify-between text-[11px] text-neutral-500">
                  <span>Örnek: Sigaranın zararları, kayıp kedi vb.</span>
                  <span>{{ directorTheme.length }} karakter</span>
                </div>
              </div>

              <!-- LLM Generation Error Alert Banner -->
              <div v-if="scriptGenerationError" class="p-3.5 rounded-xl bg-red-950/40 border border-red-500/50 space-y-2.5 text-xs text-red-200 shadow-xl">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2 text-red-400 font-semibold">
                    <span class="text-base">⚠️</span>
                    <span>Yapay Zeka (LLM) Bağlantı / Üretim Hatası</span>
                  </div>
                  <button
                    type="button"
                    @click="scriptGenerationError = ''"
                    class="text-neutral-400 hover:text-neutral-200 p-0.5 text-xs cursor-pointer"
                    title="Kapat"
                  >
                    ✕
                  </button>
                </div>

                <div class="text-[11px] leading-relaxed text-red-300/90 font-mono bg-neutral-950/80 p-2.5 rounded-lg border border-red-900/50 break-words whitespace-pre-wrap">
                  {{ scriptGenerationError }}
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-1">
                  <button
                    type="button"
                    @click="openLlmModal"
                    class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-medium text-[11px] transition-all flex items-center gap-1.5 cursor-pointer shadow-md shadow-purple-500/20"
                  >
                    <span>⚙️ Modeli / API Anahtarını Değiştir</span>
                  </button>
                  <button
                    type="button"
                    @click="generateDirectorScript(true)"
                    :disabled="isGeneratingScript || !directorTheme.trim()"
                    class="px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-amber-300 border border-amber-500/30 font-medium text-[11px] transition-all flex items-center gap-1.5 cursor-pointer"
                    title="LLM bağlantısı olmadan, girdiğiniz temaya göre akıllı yerel simülasyon kurgusu üretir."
                  >
                    <span>⚡ Çevrimdışı Simülasyonla Üret (Test Modu)</span>
                  </button>
                </div>
              </div>

              <!-- Target Audience & Options -->
              <div class="space-y-3 pt-2 border-t border-neutral-800">
                <label class="block text-xs font-medium text-neutral-400">Hedef Kitle / Anlatım Tonu</label>
                <div class="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    v-for="aud in audienceOptions"
                    :key="aud.key"
                    @click="directorOptions.audience = aud.key"
                    :class="[
                      'p-2.5 rounded-xl border text-left transition-all cursor-pointer flex flex-col gap-0.5',
                      directorOptions.audience === aud.key
                        ? 'bg-purple-500/15 border-purple-500/50 text-neutral-100'
                        : 'bg-neutral-900 border-neutral-800 text-neutral-400 hover:text-neutral-200'
                    ]"
                  >
                    <div class="text-xs font-semibold flex items-center justify-between">
                      <span>{{ aud.title }}</span>
                      <span class="text-xs">{{ aud.emoji }}</span>
                    </div>
                    <span class="text-[10px] text-neutral-500">{{ aud.desc }}</span>
                  </button>
                </div>
              </div>

              <!-- Output Layer Toggles -->
              <div class="space-y-3 pt-2 border-t border-neutral-800">
                <label class="block text-xs font-medium text-neutral-400">Üretilecek Çıktı Kanalları & Seçenekler</label>
                <div class="space-y-2">

                  <!-- Kanal 1: Seslendirme & Karakter Vokalleri -->
                  <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 hover:border-neutral-700/80 transition-all flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                      <div class="w-8 h-8 rounded-lg bg-purple-500/15 text-purple-400 flex items-center justify-center text-sm font-semibold shrink-0">
                        🎙️
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-semibold text-neutral-200 truncate flex items-center gap-1.5">
                          <span>Kanal 1: Seslendirme (Vokal)</span>
                        </div>
                        <div class="text-[10px] text-neutral-400 truncate">Karakter vokalleri ve hikaye anlatıcısı</div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <select
                        v-model="directorOptions.tts_engine"
                        :disabled="!directorOptions.has_voice || installedTtsModels.length === 0"
                        class="rounded-lg bg-neutral-950 border border-neutral-700/80 px-2 py-1 text-[11px] text-neutral-200 focus:outline-none focus:border-purple-500 disabled:opacity-35 disabled:cursor-not-allowed max-w-[145px] sm:max-w-[175px] truncate cursor-pointer transition-colors shadow-inner font-medium"
                        title="Yalnızca kurulu ve kullanıma hazır vokal motorları listelenir"
                      >
                        <option v-if="installedTtsModels.length === 0" value="" disabled>
                          ⚠️ Kurulu Model Yok (Model Yöneticisi)
                        </option>
                        <optgroup v-if="installedCloudTtsModels.length > 0" label="── ☁️ Hazır Bulut API Modelleri ──">
                          <option v-for="m in installedCloudTtsModels" :key="m.id" :value="m.id">
                            ⚡ {{ m.name }}
                          </option>
                        </optgroup>
                        <optgroup v-if="installedLocalTtsModels.length > 0" label="── 💾 Kurulu Yerel Modeller ──">
                          <option v-for="m in installedLocalTtsModels" :key="m.id" :value="m.id">
                            ✓ {{ m.name }}
                          </option>
                        </optgroup>
                      </select>

                      <button
                        type="button"
                        @click="openCloudKeyModal('freya')"
                        class="p-1 px-1.5 rounded-lg bg-neutral-950 border border-neutral-700/80 hover:border-purple-500/80 text-neutral-400 hover:text-purple-300 text-xs transition-colors cursor-pointer"
                        title="Bulut Vokal API Anahtarlarını Yönet (Freya, OpenAI, ElevenLabs)"
                      >
                        🔑
                      </button>

                      <input
                        v-model="directorOptions.has_voice"
                        type="checkbox"
                        class="w-4 h-4 rounded bg-neutral-800 border-neutral-700 text-purple-600 focus:ring-0 cursor-pointer"
                        title="Vokal Kanalını Aç / Kapat"
                      />
                    </div>
                  </div>

                  <!-- Kanal 2: Dinamik Fon Müziği & Atmosfer (BGM) -->
                  <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 hover:border-neutral-700/80 transition-all flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                      <div class="w-8 h-8 rounded-lg bg-indigo-500/15 text-indigo-400 flex items-center justify-center text-sm font-semibold shrink-0">
                        🎵
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-semibold text-neutral-200 truncate flex items-center gap-1.5">
                          <span>Kanal 2: Fon Müziği & Atmosfer</span>
                          <Link href="/music" class="text-[9px] px-1.5 py-0.5 rounded bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 font-medium transition-colors" title="Müzik Stüdyosunu Aç">
                            Stüdyo ↗
                          </Link>
                        </div>
                        <div class="text-[10px] text-neutral-400 truncate">Konuşma arkasında otomatik -14dB Ducking ile kısılır</div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <select
                        v-model="directorOptions.bgm_mode"
                        :disabled="!directorOptions.has_bgm"
                        class="rounded-lg bg-neutral-950 border border-neutral-700/80 px-2 py-1 text-[11px] text-neutral-200 focus:outline-none focus:border-purple-500 disabled:opacity-35 disabled:cursor-not-allowed max-w-[145px] sm:max-w-[175px] truncate cursor-pointer transition-colors shadow-inner font-medium"
                        title="Fon müziği motorunu seçin"
                      >
                        <option value="ambient">⚡ Hızlı Ambiyans (~3 sn, Hazır)</option>
                        <optgroup v-if="installedMusicModels.length > 0" label="── 🎵 Kurulu MusicGen Modelleri ──">
                          <option v-for="m in installedMusicModels" :key="m.id" :value="m.id">
                            ✓ {{ m.name }}
                          </option>
                        </optgroup>
                      </select>

                      <input
                        v-model="directorOptions.has_bgm"
                        type="checkbox"
                        class="w-4 h-4 rounded bg-neutral-800 border-neutral-700 text-purple-600 focus:ring-0 cursor-pointer"
                        title="Fon Müziği Kanalını Aç / Kapat"
                      />
                    </div>
                  </div>

                  <!-- Kanal 3: Kelimeye Duyarlı Ses Efektleri (SFX) -->
                  <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 hover:border-neutral-700/80 transition-all flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                      <div class="w-8 h-8 rounded-lg bg-amber-500/15 text-amber-400 flex items-center justify-center text-sm font-semibold shrink-0">
                        🔊
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-semibold text-neutral-200 truncate flex items-center gap-1.5">
                          <span>Kanal 3: Ses Efektleri (SFX)</span>
                          <Link href="/sfx" class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 font-medium transition-colors" title="SFX Stüdyosunu Aç">
                            Stüdyo ↗
                          </Link>
                        </div>
                        <div class="text-[10px] text-neutral-400 truncate">Whisper kelime damgalarıyla kedi, öksürük vb. çalar</div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <select
                        v-model="directorOptions.sfx_model"
                        :disabled="!directorOptions.has_sfx || installedSttModels.length === 0"
                        class="rounded-lg bg-neutral-950 border border-neutral-700/80 px-2 py-1 text-[11px] text-neutral-200 focus:outline-none focus:border-purple-500 disabled:opacity-35 disabled:cursor-not-allowed max-w-[145px] sm:max-w-[175px] truncate cursor-pointer transition-colors shadow-inner font-medium"
                        title="Ses efekti hizalama motorunu seçin"
                      >
                        <option v-if="installedSttModels.length === 0" value="" disabled>
                          ⚠️ Kurulu Whisper Yok
                        </option>
                        <option v-for="m in installedSttModels" :key="m.id" :value="m.id">
                          ⚡ {{ m.name }}
                        </option>
                      </select>

                      <input
                        v-model="directorOptions.has_sfx"
                        type="checkbox"
                        class="w-4 h-4 rounded bg-neutral-800 border-neutral-700 text-purple-600 focus:ring-0 cursor-pointer"
                        title="SFX Kanalını Aç / Kapat"
                      />
                    </div>
                  </div>

                  <!-- Kanal 4: Görsel & Animasyon Direktifi (Prompt Üretimi) -->
                  <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 hover:border-neutral-700/80 transition-all flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                      <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-sm font-semibold shrink-0">
                        🎨
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-semibold text-neutral-200 truncate flex items-center gap-1.5">
                          <span>Kanal 4: Görsel & Animasyon Direktifi</span>
                          <span class="text-[9px] px-1.5 py-0.2 rounded bg-neutral-800 text-neutral-400 font-mono">Prompt</span>
                        </div>
                        <div class="text-[10px] text-neutral-400 truncate">Görsel üretimi yapılmaz; Midjourney/DALL-E için prompt üretilir</div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <select
                        v-model="directorOptions.visuals_model"
                        :disabled="!directorOptions.has_visuals"
                        class="rounded-lg bg-neutral-950 border border-neutral-700/80 px-2 py-1 text-[11px] text-neutral-200 focus:outline-none focus:border-purple-500 disabled:opacity-35 disabled:cursor-not-allowed max-w-[145px] sm:max-w-[175px] truncate cursor-pointer transition-colors shadow-inner font-medium"
                        title="Harici görsel üreticisi için prompt şablonu formatı"
                      >
                        <option value="midjourney-v6">📝 Midjourney v6.1 (Sinematik Prompt)</option>
                        <option value="dalle-3">📝 DALL-E 3 (Detaylı Tasvir Promptu)</option>
                        <option value="flux-1">📝 Flux.1 (Fotogerçekçi Prompt)</option>
                        <option value="stable-diffusion">📝 Stable Diffusion (SDXL Formatı)</option>
                      </select>

                      <input
                        v-model="directorOptions.has_visuals"
                        type="checkbox"
                        class="w-4 h-4 rounded bg-neutral-800 border-neutral-700 text-purple-600 focus:ring-0 cursor-pointer"
                        title="Görsel Direktif Kanalını Aç / Kapat"
                      />
                    </div>
                  </div>

                  <!-- Kanal 5: Duygu & Prosodi Etiketleri -->
                  <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 hover:border-neutral-700/80 transition-all flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                      <div class="w-8 h-8 rounded-lg bg-rose-500/15 text-rose-400 flex items-center justify-center text-sm font-semibold shrink-0">
                        🎭
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-semibold text-neutral-200 truncate flex items-center gap-1.5">
                          <span>Kanal 5: Duygu & Prosodi</span>
                          <span class="text-[9px] px-1.5 py-0.2 rounded bg-neutral-800 text-neutral-400 font-mono">LLM Metin Notu</span>
                        </div>
                        <div class="text-[10px] text-neutral-400 truncate">[whisper], [cough], [slow] sahne direktifleri</div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <select
                        v-model="directorOptions.prosody_mode"
                        :disabled="!directorOptions.has_prosody"
                        class="rounded-lg bg-neutral-950 border border-neutral-700/80 px-2 py-1 text-[11px] text-neutral-200 focus:outline-none focus:border-purple-500 disabled:opacity-35 disabled:cursor-not-allowed max-w-[145px] sm:max-w-[175px] truncate cursor-pointer transition-colors shadow-inner font-medium"
                        title="Prosodi ve duygu şablonunu seçin"
                      >
                        <option value="freya-prosody">✨ Freya Ses Etiketleri ([laugh], [sigh])</option>
                        <option value="standard-prosody">🎙️ Standart Prosodi ([slow], [pause])</option>
                        <option value="cinematic-stage">🎬 Sinematik Sahne & Vurgu Notları</option>
                      </select>

                      <input
                        v-model="directorOptions.has_prosody"
                        type="checkbox"
                        class="w-4 h-4 rounded bg-neutral-800 border-neutral-700 text-purple-600 focus:ring-0 cursor-pointer"
                        title="Prosodi Kanalını Aç / Kapat"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Generate Button -->
              <div class="pt-2">
                <button
                  type="button"
                  @click="generateDirectorScript"
                  :disabled="isGeneratingScript || !directorTheme.trim()"
                  class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-purple-600 via-indigo-600 to-accent text-white font-semibold text-xs hover:opacity-95 transition-all shadow-lg shadow-purple-500/20 disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                >
                  <span v-if="isGeneratingScript" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  <span v-else class="text-base">🚀</span>
                  <span>{{ isGeneratingScript ? 'Yapay Zeka Çok Kanallı Senaryoyu Üretiyor...' : 'Çok Kanallı Senaryoyu & Çizelgeyi Oluştur' }}</span>
                </button>
              </div>
            </div>

            <!-- Recent Projects Card -->
            <div class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3 shadow-sm">
              <div class="flex items-center justify-between">
                <h3 class="text-xs font-semibold text-neutral-300 flex items-center gap-1.5">
                  <span>📂 Kayıtlı Hikaye Projeleri</span>
                </h3>
                <span class="text-[10px] text-neutral-500 font-mono">{{ storyProjectsList.length }} Adet</span>
              </div>

              <div v-if="storyProjectsList.length === 0" class="p-4 rounded-xl bg-neutral-900/60 border border-neutral-800/80 text-center text-xs text-neutral-500">
                Henüz kayıtlı proje yok. Yeni bir hikaye oluşturun.
              </div>

              <div v-else class="space-y-2 max-h-64 overflow-y-auto pr-1">
                <div
                  v-for="proj in storyProjectsList"
                  :key="proj.id"
                  @click="selectProject(proj)"
                  :class="[
                    'p-3 rounded-xl border text-xs cursor-pointer transition-all flex items-center justify-between gap-3 group',
                    selectedProject?.id === proj.id
                      ? 'bg-purple-500/15 border-purple-500/40 text-neutral-100'
                      : 'bg-neutral-900/80 border-neutral-800/80 text-neutral-300 hover:border-neutral-700'
                  ]"
                >
                  <div class="min-w-0 flex-1">
                    <div class="font-semibold truncate text-xs flex items-center gap-1.5">
                      <span>{{ proj.title || 'İsimsiz Hikaye' }}</span>
                      <span v-if="proj.status === 'completed'" class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">Hazır</span>
                      <span v-else-if="proj.status === 'processing'" class="text-[10px] px-1.5 py-0.2 rounded bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 animate-pulse flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-ping"></span>
                        <span>Üretiliyor</span>
                      </span>
                      <span v-else-if="proj.status === 'failed'" class="text-[10px] px-1.5 py-0.2 rounded bg-red-500/15 text-red-400 border border-red-500/30">Hata</span>
                      <span v-else class="text-[10px] px-1.5 py-0.2 rounded bg-neutral-800 text-neutral-400">Taslak</span>
                    </div>
                    <div class="text-[11px] text-neutral-500 truncate mt-0.5">
                      <span v-if="proj.status === 'processing'" class="text-indigo-300 font-mono text-[10px]">
                        ⏳ {{ proj.progress_step || 'Kuyrukta işleniyor...' }}
                      </span>
                      <span v-else>
                        {{ proj.theme }}
                      </span>
                    </div>
                  </div>

                  <div class="flex items-center gap-1 shrink-0">
                    <button
                      v-if="proj.master_audio_url"
                      type="button"
                      @click.stop="quickPlayMaster(proj.master_audio_url)"
                      class="p-1.5 rounded-lg bg-neutral-800 hover:bg-accent hover:text-bg text-neutral-300 transition-colors cursor-pointer"
                      title="Dinle"
                    >
                      ▶
                    </button>
                    <button
                      type="button"
                      @click.stop="deleteProject(proj.id)"
                      class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-500 hover:text-red-400 transition-colors cursor-pointer"
                      title="Sil"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Interactive Multi-Track Timeline & Production Studio (7 cols) -->
          <div class="lg:col-span-7 space-y-6">

            <!-- Empty State if no project selected -->
            <div v-if="!selectedProject && !isGeneratingScript" class="p-12 rounded-3xl bg-surface border border-neutral-800 text-center space-y-4 shadow-sm">
              <div class="w-16 h-16 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-3xl mx-auto">
                🎬
              </div>
              <div class="space-y-1">
                <h3 class="text-sm font-semibold text-neutral-200">Zaman Çizelgesi & Prodüksiyon Stüdyosu</h3>
                <p class="text-xs text-neutral-400 max-w-md mx-auto">
                  Sol taraftan bir tema yazıp <span class="text-purple-300 font-medium">"Çok Kanallı Senaryoyu Oluştur"</span> butonuna basın veya hazır konseptlerden birini seçin.
                </p>
              </div>
              <div class="pt-2">
                <button
                  type="button"
                  @click="applyPreset(presetConcepts[0])"
                  class="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-medium transition-colors inline-flex items-center gap-2 cursor-pointer border border-neutral-700"
                >
                  <span>🚭 Sigaranın Zararları Konseptini Doldur</span>
                </button>
              </div>
            </div>

            <!-- Loading State -->
            <div v-else-if="isGeneratingScript" class="p-12 rounded-3xl bg-surface border border-purple-500/20 text-center space-y-4 shadow-sm">
              <div class="w-16 h-16 rounded-2xl bg-purple-500/15 border border-purple-500/30 text-purple-400 flex items-center justify-center text-3xl mx-auto animate-bounce">
                ✨
              </div>
              <div class="space-y-1">
                <h3 class="text-base font-semibold text-neutral-100">AI Yönetmen Senaryoyu Kurguluyor...</h3>
                <p class="text-xs text-neutral-400 max-w-sm mx-auto">
                  Sahneler, vokal tonlamaları, MusicGen fon müziği direktifleri ve kelimeye duyarlı ses efektleri zaman çizelgesine diziliyor.
                </p>
              </div>
              <div class="flex items-center justify-center gap-2 pt-2">
                <span class="w-2.5 h-2.5 rounded-full bg-purple-400 animate-pulse"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 animate-pulse delay-150"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-accent animate-pulse delay-300"></span>
              </div>
            </div>

            <!-- Active Project & Multi-Track Studio -->
            <div v-else-if="selectedProject" class="space-y-6">

              <!-- Project Header & Produce Audio Action -->
              <div class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-4 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-neutral-800">
                  <div class="space-y-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-purple-500/15 text-purple-300 border border-purple-500/30">
                        {{ selectedProject.target_audience || 'Çocuk Masalı' }}
                      </span>
                      <span class="text-xs text-neutral-400 font-mono">
                        {{ selectedProject.script_data?.scenes?.length || 0 }} Sahne
                      </span>
                      <span v-if="selectedProject.duration_sec" class="text-xs text-accent font-mono">
                        ⏱ ~{{ Math.round(selectedProject.duration_sec) }} sn
                      </span>
                      <span v-if="selectedProject.script_data?.is_simulation" class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-300 border border-amber-500/30 flex items-center gap-1" title="LLM çevrimdışı olduğu için yerel simülasyon motoru tarafından üretildi">
                        ⚡ Akıllı Simülasyon
                      </span>
                    </div>
                    <h2 class="text-base font-bold text-neutral-100 truncate">
                      {{ selectedProject.title || selectedProject.script_data?.title || 'Sesli Hikaye Senaryosu' }}
                    </h2>
                  </div>

                  <!-- Actions: Produce Audio & Raw JSON -->
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      @click="showScriptJsonModal = true"
                      class="px-3 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 border border-neutral-700 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer"
                      title="Yönetmen JSON Çıktısını İncele"
                    >
                      <span>📋 JSON</span>
                    </button>

                    <button
                      type="button"
                      @click="startAudioProduction"
                      :disabled="isProducingAudio"
                      class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 text-white font-semibold text-xs hover:opacity-95 transition-all shadow-md shadow-emerald-500/20 disabled:opacity-50 flex items-center gap-2 cursor-pointer"
                    >
                      <span v-if="isProducingAudio" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                      <span v-else class="text-sm">🎙️</span>
                      <span>{{ isProducingAudio ? 'Prodüksiyon Çalışıyor...' : 'Prodüksiyonu Başlat (Tüm Kanalları Miksle)' }}</span>
                    </button>
                  </div>
                </div>

                <!-- Production Progress or Status Banner -->
                <div v-if="isProducingAudio" class="p-4 rounded-xl bg-gradient-to-r from-indigo-950/50 via-purple-950/30 to-indigo-950/40 border border-indigo-500/40 space-y-2.5 shadow-lg">
                  <div class="flex items-center justify-between text-xs font-medium text-indigo-200">
                    <div class="flex items-center gap-2">
                      <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 animate-ping"></span>
                      <span class="font-semibold text-white">{{ productionStatusMessage || 'Sahnelerin seslendirmesi, fon müziği ve SFX miksajı yapılıyor...' }}</span>
                    </div>
                    <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono text-[10px] border border-indigo-500/30">Arka Plan Kuyruğu Aktif</span>
                  </div>
                  <div class="w-full bg-neutral-900 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 via-purple-500 to-accent h-full w-full animate-pulse"></div>
                  </div>
                  <div class="flex items-center gap-2 text-[11px] text-indigo-300/80 pt-0.5">
                    <span>⚡</span>
                    <span><strong>Kuyruk Garantisi:</strong> Sayfadan ayrılsanız veya tarayıcıyı kapatsanız bile işlemler sunucu arka planında devam eder. Geri döndüğünüzde otomatik senkronize olur.</span>
                  </div>
                </div>

                <!-- Master Audio Player (when completed) -->
                <div v-if="(selectedProject.status === 'completed' || selectedProject.master_audio_path) && masterAudioSrc" class="p-4 rounded-2xl bg-gradient-to-r from-purple-950/40 via-neutral-900 to-emerald-950/30 border border-purple-500/30 space-y-3 shadow-lg">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs font-semibold text-neutral-200">
                      <span class="text-emerald-400 text-sm">✓</span>
                      <span>Tamamlanan Master Prodüksiyon (Vokal + Ducked BGM + SFX)</span>
                    </div>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 font-mono">
                      24kHz Ducked Master Mix
                    </span>
                  </div>

                  <!-- Audio Waveform / Scrubber Bar -->
                  <div class="p-3 rounded-xl bg-neutral-950/70 border border-neutral-800 flex items-center gap-4">
                    <button
                      type="button"
                      @click="toggleMasterAudio(masterAudioSrc)"
                      class="w-10 h-10 rounded-full bg-accent text-bg flex items-center justify-center font-bold text-sm hover:opacity-90 transition-opacity cursor-pointer shrink-0 shadow-md"
                    >
                      <span v-if="isPlayingMaster">⏸</span>
                      <span v-else class="ml-0.5">▶</span>
                    </button>

                    <div class="flex-1 space-y-1">
                      <div class="flex items-center justify-between text-[11px] font-mono text-neutral-400">
                        <span>{{ formatTime(masterCurrentTime) }}</span>
                        <span>{{ formatTime(masterDuration) }}</span>
                      </div>
                      <input
                        type="range"
                        min="0"
                        :max="masterDuration || 100"
                        step="0.1"
                        :value="masterCurrentTime"
                        @input="seekMasterAudio"
                        class="w-full accent-accent bg-neutral-800 h-1.5 rounded-lg cursor-pointer"
                      />
                    </div>

                    <!-- Direct Download Button -->
                    <a
                      :href="masterAudioSrc"
                      download
                      class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer shrink-0"
                    >
                      <span>⬇ İndir</span>
                    </a>
                  </div>

                  <!-- Stem Channel Controls & Information -->
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-1 text-[11px]">
                    <div class="p-2 rounded-lg bg-neutral-900/60 border border-neutral-800 flex items-center justify-between text-neutral-300">
                      <span class="flex items-center gap-1.5">
                        <span>🎤</span>
                        <span>Vokal Kanalı</span>
                      </span>
                      <span class="text-neutral-500 font-mono">0 dB (Ana)</span>
                    </div>

                    <div class="p-2 rounded-lg bg-neutral-900/60 border border-neutral-800 flex items-center justify-between text-indigo-300">
                      <span class="flex items-center gap-1.5">
                        <span>🎵</span>
                        <span>MusicGen Fon</span>
                      </span>
                      <span class="text-indigo-400 font-mono">-14 dB Ducking</span>
                    </div>

                    <div class="p-2 rounded-lg bg-neutral-900/60 border border-neutral-800 flex items-center justify-between text-amber-300">
                      <span class="flex items-center gap-1.5">
                        <span>🔊</span>
                        <span>SFX Efektleri</span>
                      </span>
                      <span class="text-amber-400 font-mono">Zaman Hizalı</span>
                    </div>
                  </div>
                </div>

                <!-- Error Message if failed -->
                <div v-if="selectedProject.status === 'failed'" class="p-4 rounded-xl bg-red-950/40 border border-red-500/40 text-xs text-red-200 space-y-3 shadow-md">
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                      <span class="text-base leading-none">⚠️</span>
                      <div>
                        <div class="font-bold text-red-300 mb-0.5">Prodüksiyon Hatası</div>
                        <div class="text-red-200/90 leading-relaxed font-sans">
                          {{ selectedProject.error_message || 'Bilinmeyen bir hata oluştu.' }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Quick Action Solutions -->
                  <div class="pt-2 border-t border-red-500/20 flex items-center justify-end gap-2 flex-wrap">
                    <button
                      v-if="selectedProject.error_message && (selectedProject.error_message.includes('Freya') || selectedProject.error_message.includes('API') || selectedProject.error_message.includes('403'))"
                      type="button"
                      @click="openCloudKeyModal('freya')"
                      class="px-3 py-1.5 rounded-lg bg-purple-600/30 hover:bg-purple-600/50 border border-purple-500/50 text-purple-200 text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer shadow-sm"
                    >
                      <span>🔑</span>
                      <span>Freya API Anahtarını Gir</span>
                    </button>

                    <button
                      type="button"
                      @click="retryWithPiperTr"
                      class="px-3 py-1.5 rounded-lg bg-emerald-600/30 hover:bg-emerald-600/50 border border-emerald-500/50 text-emerald-200 text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer shadow-sm"
                      title="İnternet veya API anahtarı gerektirmeden yerel Piper TR motoru ile hemen üretin"
                    >
                      <span>🎙️</span>
                      <span>Piper TR (Yerel) ile Hemen Üret</span>
                    </button>

                    <button
                      type="button"
                      @click="startAudioProduction"
                      class="px-3 py-1.5 rounded-lg bg-red-500/30 hover:bg-red-500/40 text-red-100 text-xs font-semibold transition-all cursor-pointer"
                    >
                      Tekrar Dene
                    </button>
                  </div>
                </div>
              </div>

              <!-- Multi-Track Timeline Scenes (Suno / DAW Card Layout) -->
              <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                  <h3 class="text-xs font-semibold text-neutral-300 uppercase tracking-wider flex items-center gap-2">
                    <span>🎬 Sahne Zaman Çizelgesi (Timeline Tracks)</span>
                    <span class="text-neutral-500 text-[11px] font-normal normal-case">
                      Her sahne bağımsız olarak kanallara ayrılmıştır
                    </span>
                  </h3>
                </div>

                <!-- Scene Cards -->
                <div
                  v-for="(scene, sIdx) in (selectedProject.script_data?.scenes || [])"
                  :key="sIdx"
                  class="p-5 rounded-2xl bg-surface border border-neutral-800 hover:border-neutral-700/80 transition-all space-y-4 shadow-sm"
                >
                  <!-- Scene Title & Metadata Bar -->
                  <div class="flex items-center justify-between gap-3 border-b border-neutral-800/80 pb-3">
                    <div class="flex items-center gap-2.5">
                      <div class="w-7 h-7 rounded-lg bg-purple-500/15 border border-purple-500/30 text-purple-300 flex items-center justify-center font-bold text-xs">
                        {{ sIdx + 1 }}
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-neutral-100 flex items-center gap-2">
                          <span>{{ scene.name || `Sahne ${sIdx + 1}` }}</span>
                          <span v-if="scene.voiceover?.emotion" class="text-[10px] px-2 py-0.2 rounded-md bg-neutral-900 text-purple-300 border border-purple-500/20 font-mono">
                            {{ scene.voiceover.emotion }}
                          </span>
                        </div>
                        <div class="text-[10px] text-neutral-500">
                          Konuşmacı: <span class="text-neutral-300 font-medium">{{ scene.voiceover?.speaker || 'Anlatıcı' }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- TRACK 1: Voice & Character Narration -->
                  <div class="p-3.5 rounded-xl bg-neutral-900/90 border border-neutral-800/80 space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-neutral-400">
                      <div class="flex items-center gap-1.5">
                        <span class="text-purple-400">🎤</span>
                        <span>Kanal 1: Seslendirme Metni (Vokal)</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="text-[10px] px-2 py-0.5 rounded bg-purple-500/15 text-purple-300 border border-purple-500/30 font-mono">
                          {{ getTtsModelLabel(selectedProject.options?.tts_engine || directorOptions.tts_engine) }}
                        </span>
                        <span class="text-[10px] text-neutral-500 font-mono">TTS + Prosodi</span>
                      </div>
                    </div>

                    <!-- Highlighted Text with Shortcodes -->
                    <div class="text-xs text-neutral-200 leading-relaxed font-sans select-text">
                      <span v-html="highlightShortcodes(scene.voiceover?.text)"></span>
                    </div>

                    <!-- Word-Anchor Cues Preview -->
                    <div v-if="scene.voiceover?.cues?.length > 0" class="flex items-center gap-2 flex-wrap pt-1 border-t border-neutral-800/60">
                      <span class="text-[10px] text-neutral-500">Hizalanan SFX Çapaları:</span>
                      <span
                        v-for="(cue, cIdx) in scene.voiceover.cues"
                        :key="cIdx"
                        class="px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-300 text-[10px] font-mono flex items-center gap-1"
                      >
                        <span>🎯 "{{ cue.word_anchor }}"</span>
                        <span>→ {{ cue.sfx_id }} ({{ cue.offset_ms || 0 }}ms)</span>
                      </span>
                    </div>
                  </div>

                  <!-- TRACK 2: Background Music (MusicGen) -->
                  <div v-if="scene.bgm" class="p-3.5 rounded-xl bg-indigo-950/20 border border-indigo-500/20 space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-indigo-300">
                      <div class="flex items-center gap-1.5">
                        <span>🎵</span>
                        <span>Kanal 2: Dinamik Fon Müziği (BGM)</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="text-[10px] px-2 py-0.5 rounded bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 font-mono">
                          {{ getBgmModelLabel(selectedProject.options?.bgm_mode || directorOptions.bgm_mode) }}
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 font-mono">
                          -14 dB Ducking
                        </span>
                      </div>
                    </div>

                    <div class="text-xs text-indigo-100 font-mono bg-neutral-900/80 p-2.5 rounded-lg border border-neutral-800/80 flex items-center justify-between gap-3">
                      <span class="truncate">"{{ scene.bgm.prompt }}"</span>
                      <span class="text-[10px] text-indigo-400 shrink-0 font-sans">Ses: %{{ Math.round((scene.bgm.volume || 0.22) * 100) }}</span>
                    </div>
                  </div>

                  <!-- TRACK 3: Sound Effects (SFX) -->
                  <div v-if="scene.sfx?.length > 0" class="p-3.5 rounded-xl bg-amber-950/20 border border-amber-500/20 space-y-2.5">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-amber-300">
                      <div class="flex items-center gap-1.5">
                        <span>🔊</span>
                        <span>Kanal 3: Zaman Ayarlı Ses Efektleri (SFX)</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/15 text-amber-300 border border-amber-500/30 font-mono">
                          {{ getSfxModelLabel(selectedProject.options?.sfx_model || directorOptions.sfx_model) }}
                        </span>
                        <span class="text-[10px] text-amber-400 font-mono">{{ scene.sfx.length }} Efekt</span>
                      </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      <div
                        v-for="(sfxItem, fIdx) in scene.sfx"
                        :key="fIdx"
                        class="p-2.5 rounded-lg bg-neutral-900/90 border border-neutral-800 flex items-center justify-between gap-2"
                      >
                        <div class="min-w-0">
                          <div class="text-xs font-semibold text-neutral-200 truncate flex items-center gap-1.5">
                            <span>{{ sfxItem.sound_name || sfxItem.id }}</span>
                          </div>
                          <div class="text-[10px] text-neutral-500 truncate">
                            Konum: <span class="text-amber-300 font-mono">{{ sfxItem.relative_to || 'Zaman Çizelgesi' }}</span>
                          </div>
                        </div>

                        <!-- Instant SFX Play Button -->
                        <button
                          type="button"
                          @click="playSfx(sfxItem.id)"
                          class="px-2.5 py-1 rounded-md bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 text-[10px] font-semibold transition-all flex items-center gap-1 cursor-pointer shrink-0"
                          title="Efekti Dinle"
                        >
                          <span>🔊</span>
                          <span>Dinle</span>
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- TRACK 4: Visual & Animation Directives -->
                  <div v-if="scene.visual_direction" class="p-3.5 rounded-xl bg-emerald-950/20 border border-emerald-500/20 space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-emerald-300">
                      <div class="flex items-center gap-1.5">
                        <span>🎨</span>
                        <span>Kanal 4: Görsel & Animasyon Direktifi</span>
                        <span class="text-[9px] px-1.5 py-0.2 rounded bg-emerald-950/80 text-emerald-400 font-mono border border-emerald-500/30">Harici AI Promptu</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <button
                          type="button"
                          @click="copyPromptText(scene.visual_direction.scene_prompt)"
                          class="px-2 py-0.5 rounded bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 border border-emerald-500/40 text-[10px] font-medium transition-all flex items-center gap-1 cursor-pointer"
                          title="Bu sahnenin görsel promptunu panoya kopyala"
                        >
                          <span>📋</span>
                          <span>Promptu Kopyala</span>
                        </button>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 font-mono">
                          {{ getVisualsModelLabel(selectedProject.options?.visuals_model || directorOptions.visuals_model) }}
                        </span>
                        <span class="text-[10px] text-emerald-400 font-mono">
                          {{ scene.visual_direction.camera || 'Sinematik Açı' }}
                        </span>
                      </div>
                    </div>

                    <div class="text-xs text-neutral-300 leading-relaxed font-sans bg-neutral-900/80 p-2.5 rounded-lg border border-neutral-800/80 space-y-1">
                      <div><span class="text-neutral-500 text-[10px]">İllüstrasyon:</span> {{ scene.visual_direction.scene_prompt }}</div>
                      <div v-if="scene.visual_direction.action" class="text-[11px] text-emerald-400">
                        <span class="text-neutral-500 text-[10px]">Aksiyon:</span> {{ scene.visual_direction.action }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ======================================================== -->
      <!-- TAB 2: KLASİK PROMPT ŞABLONLARI (EXISTING FUNCTIONALITY) -->
      <!-- ======================================================== -->
      <div v-else-if="activeMainTab === 'templates'" class="space-y-6">

        <!-- Search & Category Filters -->
        <div class="p-4 rounded-2xl bg-surface border border-neutral-800 flex flex-col md:flex-row items-center justify-between gap-3 shadow-sm">
          <!-- Search Input -->
          <div class="relative w-full md:w-80">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Şablon başlığı veya içeriğinde ara..."
              class="w-full rounded-xl bg-neutral-900 border border-neutral-700 pl-9 pr-3.5 py-2 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
            />
            <svg class="w-4 h-4 text-neutral-500 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Category Pills -->
          <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0 scrollbar-none">
            <button
              type="button"
              @click="selectedCategory = 'all'"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-medium transition-all whitespace-nowrap cursor-pointer border',
                selectedCategory === 'all'
                  ? 'bg-accent/15 border-accent text-accent-300 font-semibold'
                  : 'bg-neutral-900/60 border-neutral-800 text-neutral-400 hover:text-neutral-200'
              ]"
            >
              Tümü
            </button>
            <button
              v-for="cat in availableCategories"
              :key="cat"
              type="button"
              @click="selectedCategory = cat"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-medium transition-all whitespace-nowrap cursor-pointer border',
                selectedCategory === cat
                  ? 'bg-accent/15 border-accent text-accent-300 font-semibold'
                  : 'bg-neutral-900/60 border-neutral-800 text-neutral-400 hover:text-neutral-200'
              ]"
            >
              {{ cat }}
            </button>
          </div>
        </div>

        <!-- Templates Grid -->
        <div v-if="filteredTemplates.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="template in filteredTemplates"
            :key="template.id"
            class="p-5 rounded-2xl bg-surface border border-neutral-800 hover:border-neutral-700 transition-all flex flex-col justify-between space-y-4 group shadow-sm"
          >
            <!-- Top Row: Category + Title + Actions -->
            <div class="space-y-2">
              <div class="flex items-center justify-between gap-2">
                <span class="px-2.5 py-0.5 rounded-lg bg-neutral-900 border border-neutral-800 text-[11px] font-medium text-neutral-400">
                  {{ template.category || 'Genel' }}
                </span>

                <div class="flex items-center gap-1">
                  <!-- Favorite Toggle -->
                  <button
                    type="button"
                    @click="toggleFavorite(template.id)"
                    class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-400 hover:text-amber-400 transition-colors cursor-pointer"
                    :title="template.is_favorite ? 'Favorilerden çıkar' : 'Favorilere ekle'"
                  >
                    <span v-if="template.is_favorite" class="text-amber-400 text-sm">★</span>
                    <span v-else class="text-neutral-600 hover:text-amber-400 text-sm">☆</span>
                  </button>

                  <!-- Edit Button -->
                  <button
                    type="button"
                    @click="openEditModal(template)"
                    class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 transition-colors cursor-pointer"
                    title="Düzenle"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>

                  <!-- Delete Button -->
                  <button
                    type="button"
                    @click="deleteTemplate(template.id, template.title)"
                    class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-500 hover:text-red-400 transition-colors cursor-pointer"
                    title="Sil"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Title & Description -->
              <div>
                <h2 class="text-sm font-semibold text-neutral-100 group-hover:text-accent-300 transition-colors">
                  {{ template.title }}
                </h2>
                <p v-if="template.description" class="text-xs text-neutral-400 line-clamp-2 mt-0.5">
                  {{ template.description }}
                </p>
              </div>
            </div>

            <!-- Prompt Content Preview Box with Highlighted Variables -->
            <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 text-xs text-neutral-300 font-sans leading-relaxed">
              <span v-html="highlightVariables(template.content)"></span>
            </div>

            <!-- Variables Badges & Action -->
            <div class="flex items-center justify-between gap-3 pt-1 border-t border-neutral-800/80">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span v-if="!template.extracted_variables || template.extracted_variables.length === 0" class="text-[11px] text-neutral-500">
                  Sabit Prompt (Değişken Yok)
                </span>
                <span
                  v-for="v in template.extracted_variables"
                  :key="v"
                  class="px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30 text-[10px] font-mono font-semibold text-purple-300"
                >
                  {{ v }}
                </span>
              </div>

              <Link
                :href="`/tts?prompt_id=${template.id}`"
                class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-accent hover:text-bg text-neutral-200 text-xs font-semibold transition-all flex items-center gap-1.5 shrink-0 shadow-sm"
              >
                <span>Metin Okuma'da Kullan</span>
                <span class="text-xs">✨</span>
              </Link>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="p-12 rounded-2xl bg-surface border border-neutral-800 text-center space-y-3">
          <div class="text-3xl select-none">🔍</div>
          <div class="text-sm font-medium text-neutral-200">Eşleşen prompt şablonu bulunamadı</div>
          <p class="text-xs text-neutral-500 max-w-sm mx-auto">
            Arama kriterlerinizi değiştirebilir veya yeni bir prompt şablonu oluşturabilirsiniz.
          </p>
          <button
            type="button"
            @click="openCreateModal"
            class="mt-2 px-4 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity inline-flex items-center gap-2 cursor-pointer"
          >
            <span>Yeni Şablon Ekle</span>
          </button>
        </div>
      </div>

      <!-- ======================================================== -->
      <!-- MODALS & DRAWERS -->
      <!-- ======================================================== -->

      <!-- 1. SFX Catalog Modal -->
      <div v-if="showSfxModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="w-full max-w-2xl rounded-3xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-5 max-h-[85vh] overflow-y-auto">
          <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-400 flex items-center justify-center text-sm">
                🔊
              </div>
              <div>
                <h3 class="text-sm font-semibold text-neutral-100">Hazır Ses Efektleri Kütüphanesi (0 ms Gecikme)</h3>
                <p class="text-[11px] text-neutral-400">Whisper ile kelimelerle milisaniye düzeyinde eşleştirilen yerel efektler</p>
              </div>
            </div>
            <button @click="showSfxModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div
              v-for="sfx in sfxCatalogList"
              :key="sfx.id"
              class="p-3.5 rounded-xl bg-neutral-900 border border-neutral-800 flex items-center justify-between gap-3"
            >
              <div>
                <div class="text-xs font-semibold text-neutral-200">{{ sfx.name }}</div>
                <div class="text-[10px] text-neutral-500 font-mono">id: {{ sfx.id }} • {{ sfx.category || 'genel' }}</div>
              </div>
              <button
                type="button"
                @click="playSfx(sfx.id)"
                class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-accent hover:text-bg text-neutral-300 text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer"
              >
                <span>▶ Dinle</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Raw JSON Director Output Modal -->
      <div v-if="showScriptJsonModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="w-full max-w-3xl rounded-3xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-4 max-h-[85vh] flex flex-col">
          <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-base">📋</span>
              <h3 class="text-sm font-semibold text-neutral-100">AI Yönetmen Çıktı Nesnesi (Director Script JSON)</h3>
            </div>
            <button @click="showScriptJsonModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
          </div>

          <div class="flex-1 overflow-y-auto bg-neutral-950 p-4 rounded-xl border border-neutral-800">
            <pre class="text-xs text-purple-300 font-mono leading-relaxed select-text">{{ JSON.stringify(selectedProject?.script_data, null, 2) }}</pre>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
            <button
              type="button"
              @click="copyJsonOutput"
              class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer"
            >
              <span>{{ copyStatusText }}</span>
            </button>
            <button
              type="button"
              @click="showScriptJsonModal = false"
              class="px-4 py-2 rounded-xl bg-neutral-800 text-neutral-300 text-xs font-medium cursor-pointer"
            >
              Kapat
            </button>
          </div>
        </div>
      </div>

      <!-- 3. Create / Edit Classical Template Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="w-full max-w-xl rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-5 max-h-[90vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <h2 class="text-base font-semibold text-neutral-100 flex items-center gap-2">
              <span>{{ isEditing ? 'Prompt Şablonunu Düzenle' : 'Yeni Prompt Şablonu' }}</span>
            </h2>
            <button @click="showModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
          </div>

          <!-- Modal Form -->
          <form @submit.prevent="submitModal" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Title -->
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1">Şablon Başlığı *</label>
                <input
                  v-model="modalForm.title"
                  type="text"
                  required
                  placeholder="Örn: Çocuk Hikayesi"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>

              <!-- Category -->
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1">Kategori</label>
                <input
                  v-model="modalForm.category"
                  type="text"
                  placeholder="Hikaye, Haber, Reklam vb."
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1">Açıklama (İsteğe bağlı)</label>
              <input
                v-model="modalForm.description"
                type="text"
                placeholder="Bu şablonun ne amaçla kullanıldığını belirten kısa not..."
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              />
            </div>

            <!-- Prompt Content -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-medium text-neutral-400">Prompt Şablonu Metni *</label>
                <span class="text-[10px] text-accent font-medium">
                  $1, $2, $3 yazarak dinamik alan tanımlayın
                </span>
              </div>
              <textarea
                v-model="modalForm.content"
                rows="4"
                required
                placeholder="Örn: $1 yaş grubu çocuklar için mini bir hikaye yaz. Hikaye $2 hakkında olsun ve ana karakterler $3 olsun..."
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 focus:outline-none focus:border-accent font-sans"
              ></textarea>

              <!-- Live Detected Variables Preview -->
              <div class="mt-2 p-2.5 rounded-xl bg-neutral-900/80 border border-neutral-800 flex items-center gap-2 flex-wrap text-xs">
                <span class="text-neutral-500 text-[11px]">Tespit Edilen Değişkenler:</span>
                <span v-if="detectedModalVariables.length === 0" class="text-neutral-500 text-[11px] italic">
                  Henüz değişken yok (Sabit metin)
                </span>
                <span
                  v-for="v in detectedModalVariables"
                  :key="v"
                  class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono text-[10px] font-semibold border border-purple-500/30"
                >
                  {{ v }}
                </span>
              </div>
            </div>

            <!-- System Prompt (Optional) -->
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1">Özel Sistem Talimatı (İsteğe bağlı)</label>
              <textarea
                v-model="modalForm.system_prompt"
                rows="2"
                placeholder="Bu şablon çalışırken LLM'e verilecek özel sistem talimatı..."
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              ></textarea>
            </div>

            <!-- Favorite Checkbox -->
            <label class="flex items-center gap-2.5 text-xs text-neutral-300 cursor-pointer select-none">
              <input
                v-model="modalForm.is_favorite"
                type="checkbox"
                class="rounded bg-neutral-900 border-neutral-700 text-accent focus:ring-0 cursor-pointer"
              />
              <span>Bu şablonu favorilere ekle (en üstte gösterilsin)</span>
            </label>

            <!-- Actions -->
            <div class="pt-3 border-t border-neutral-800 flex justify-end gap-2.5">
              <button
                type="button"
                @click="showModal = false"
                class="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors cursor-pointer"
              >
                İptal
              </button>
              <button
                type="submit"
                :disabled="isSubmittingModal || !modalForm.title.trim() || !modalForm.content.trim()"
                class="px-5 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
              >
                <span v-if="isSubmittingModal" class="w-3.5 h-3.5 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
                <span>{{ isEditing ? 'Güncelle' : 'Kaydet' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- 4. Quick LLM Settings & Provider Modal -->
      <div v-if="showLlmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-5 max-h-[92vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-purple-500/15 text-purple-400 flex items-center justify-center text-base">
                🤖
              </div>
              <div>
                <h3 class="text-sm font-semibold text-neutral-100">Yönetmen LLM Motoru & API Ayarları</h3>
                <p class="text-[11px] text-neutral-400">Hikaye kurgusu ve diyalogların üretileceği yapay zeka motoru.</p>
              </div>
            </div>
            <button @click="showLlmModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
          </div>

          <!-- Provider Select -->
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1.5">Sağlayıcı (Provider)</label>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                <button
                  type="button"
                  v-for="prov in availableLlmProviders"
                  :key="prov.key"
                  @click="selectLlmProvider(prov.key)"
                  :class="[
                    'p-2.5 rounded-xl border text-left transition-all cursor-pointer flex flex-col gap-0.5',
                    llmModalForm.provider === prov.key
                      ? 'bg-purple-500/15 border-purple-500/60 text-purple-200 ring-1 ring-purple-500/30'
                      : 'bg-neutral-900 border-neutral-800 text-neutral-400 hover:text-neutral-200 hover:border-neutral-700'
                  ]"
                >
                  <span class="text-xs font-semibold flex items-center gap-1.5">
                    <span>{{ prov.icon }}</span>
                    <span>{{ prov.name }}</span>
                  </span>
                  <span class="text-[10px] text-neutral-500 truncate">{{ prov.desc }}</span>
                </button>
              </div>
            </div>

            <!-- Model Selector -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-medium text-neutral-400">Model Adı</label>
                <span class="text-[10px] text-neutral-500">Önerilen listeden seçin veya yazın</span>
              </div>
              <div class="flex items-center gap-2">
                <input
                  v-model="llmModalForm.model"
                  type="text"
                  required
                  placeholder="Örn: gemini-2.0-flash, gpt-4o, llama3..."
                  class="flex-1 rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 font-mono focus:outline-none focus:border-purple-500"
                />
                <select
                  @change="llmModalForm.model = $event.target.value"
                  class="rounded-xl bg-neutral-900 border border-neutral-700 px-2.5 py-2.5 text-xs text-neutral-300 focus:outline-none focus:border-purple-500 cursor-pointer max-w-[140px] truncate"
                >
                  <option value="" disabled selected>Önerilenler</option>
                  <option v-for="m in currentProviderPresetModels" :key="m" :value="m">{{ m }}</option>
                </select>
              </div>
            </div>

            <!-- API Key Input (if not local ollama) -->
            <div v-if="llmModalForm.provider !== 'ollama'">
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-medium text-neutral-400">API Anahtarı (API Key)</label>
                <span v-if="hasKeyForCurrentProvider && !llmModalForm.api_key" class="text-[10px] text-emerald-400">
                  ✓ Kayıtlı anahtar mevcut
                </span>
              </div>
              <div class="relative">
                <input
                  v-model="llmModalForm.api_key"
                  :type="showApiKeyText ? 'text' : 'password'"
                  :placeholder="hasKeyForCurrentProvider ? 'Mevcut anahtarı değiştirmek için yeni anahtar girin...' : 'API anahtarınızı buraya yapıştırın...'"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 pr-10 text-xs text-neutral-100 font-mono focus:outline-none focus:border-purple-500"
                />
                <button
                  type="button"
                  @click="showApiKeyText = !showApiKeyText"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-200 text-xs cursor-pointer p-1"
                  title="Gizle / Göster"
                >
                  {{ showApiKeyText ? '🙈' : '👁️' }}
                </button>
              </div>
              <p class="text-[10px] text-neutral-500 mt-1">
                {{ getApiKeyHint(llmModalForm.provider) }}
              </p>
            </div>

            <!-- Base URL (for Ollama or custom) -->
            <div v-if="llmModalForm.provider === 'ollama' || llmModalForm.provider === 'custom'">
              <label class="block text-xs font-medium text-neutral-400 mb-1.5">Yerel Sunucu URL (Base URL)</label>
              <input
                v-model="llmModalForm.base_url"
                type="text"
                placeholder="http://127.0.0.1:11434"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 font-mono focus:outline-none focus:border-purple-500"
              />
              <p class="text-[10px] text-neutral-500 mt-1">
                Ollama varsayılan portu: <code class="text-neutral-400">http://127.0.0.1:11434</code>. Ollama servisinin açık olduğundan emin olun.
              </p>
            </div>

            <!-- Connection Test Result -->
            <div v-if="llmTestResult" :class="['p-3 rounded-xl border text-xs leading-relaxed', llmTestResult.success ? 'bg-emerald-950/40 border-emerald-500/40 text-emerald-200' : 'bg-red-950/40 border-red-500/40 text-red-200']">
              <div class="flex items-center gap-1.5 font-semibold mb-0.5">
                <span>{{ llmTestResult.success ? '✓' : '⚠️' }}</span>
                <span>{{ llmTestResult.success ? 'Bağlantı Başarılı' : 'Bağlantı Başarısız' }}</span>
              </div>
              <div class="text-[11px] opacity-90">{{ llmTestResult.message }}</div>
              <div v-if="llmTestResult.models && llmTestResult.models.length > 0" class="mt-2 text-[10px] text-neutral-300">
                <span class="text-neutral-400 font-semibold">Bulunan Modeller (Seçmek için tıklayın):</span>
                <div class="flex flex-wrap gap-1 mt-1">
                  <span
                    v-for="m in llmTestResult.models.slice(0, 10)"
                    :key="m"
                    @click="llmModalForm.model = m"
                    class="px-1.5 py-0.5 rounded bg-neutral-900 border border-neutral-700 cursor-pointer hover:border-purple-400 hover:text-purple-300 transition-colors font-mono text-[10px]"
                    title="Bu modeli seç"
                  >
                    {{ m }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="pt-3 border-t border-neutral-800 flex items-center justify-between gap-2">
            <button
              type="button"
              @click="testLlmConnectionInModal"
              :disabled="isTestingLlm"
              class="px-3.5 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
              <span v-if="isTestingLlm" class="w-3.5 h-3.5 border-2 border-neutral-300 border-t-transparent rounded-full animate-spin"></span>
              <span v-else>🔌</span>
              <span>{{ isTestingLlm ? 'Test Ediliyor...' : 'Bağlantıyı Test Et' }}</span>
            </button>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="showLlmModal = false"
                class="px-4 py-2 rounded-xl bg-neutral-800 text-neutral-300 hover:bg-neutral-700 text-xs font-medium cursor-pointer"
              >
                İptal
              </button>
              <button
                type="button"
                @click="saveLlmSettingsFromModal"
                :disabled="isSavingLlm || !llmModalForm.provider || !llmModalForm.model"
                class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-md shadow-purple-500/20"
              >
                <span v-if="isSavingLlm" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>Kaydet & Kullan</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Cloud Voice Key Modal (Freya, OpenAI, ElevenLabs) -->
      <div
        v-if="showVoiceKeyModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
      >
        <div class="w-full max-w-md rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-5">
          <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-xl">🎙️</span>
              <div>
                <h3 class="text-sm font-semibold text-neutral-100">Bulut Vokal API Anahtarı</h3>
                <p class="text-[11px] text-neutral-400">Freya Voice, OpenAI TTS veya ElevenLabs anahtarı</p>
              </div>
            </div>
            <button
              type="button"
              @click="showVoiceKeyModal = false"
              class="text-neutral-400 hover:text-neutral-200 text-lg cursor-pointer p-1"
            >
              ✕
            </button>
          </div>

          <div class="space-y-4">
            <!-- Provider Selector Tabs -->
            <div class="flex items-center gap-2 p-1 bg-neutral-900 rounded-xl border border-neutral-800">
              <button
                type="button"
                @click="voiceKeyProvider = 'freya'"
                :class="['flex-1 py-1.5 text-xs font-semibold rounded-lg transition-colors cursor-pointer text-center', voiceKeyProvider === 'freya' ? 'bg-purple-600 text-white shadow-sm' : 'text-neutral-400 hover:text-neutral-200']"
              >
                ⚡ Freya Voice
              </button>
              <button
                type="button"
                @click="voiceKeyProvider = 'openai'"
                :class="['flex-1 py-1.5 text-xs font-semibold rounded-lg transition-colors cursor-pointer text-center', voiceKeyProvider === 'openai' ? 'bg-purple-600 text-white shadow-sm' : 'text-neutral-400 hover:text-neutral-200']"
              >
                🧠 OpenAI
              </button>
              <button
                type="button"
                @click="voiceKeyProvider = 'elevenlabs'"
                :class="['flex-1 py-1.5 text-xs font-semibold rounded-lg transition-colors cursor-pointer text-center', voiceKeyProvider === 'elevenlabs' ? 'bg-purple-600 text-white shadow-sm' : 'text-neutral-400 hover:text-neutral-200']"
              >
                ✨ ElevenLabs
              </button>
            </div>

            <!-- Key Input -->
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-neutral-300">
                {{ voiceKeyProvider === 'freya' ? 'Freya Voice API Anahtarı' : (voiceKeyProvider === 'openai' ? 'OpenAI API Anahtarı' : 'ElevenLabs API Anahtarı') }}
              </label>
              <div class="relative">
                <input
                  v-model="voiceKeyInput"
                  :type="showVoiceKeyText ? 'text' : 'password'"
                  placeholder="API anahtarınızı buraya yapıştırın..."
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 pr-10 text-xs text-neutral-100 font-mono focus:outline-none focus:border-purple-500"
                />
                <button
                  type="button"
                  @click="showVoiceKeyText = !showVoiceKeyText"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-200 text-xs cursor-pointer p-1"
                  title="Gizle / Göster"
                >
                  {{ showVoiceKeyText ? '🙈' : '👁️' }}
                </button>
              </div>
              <p class="text-[10px] text-neutral-400">
                {{ voiceKeyProvider === 'freya' ? 'Freya Voice portalınızdan aldığınız API anahtarı.' : 'Sağlayıcı panelinden aldığınız API anahtarı.' }}
              </p>
            </div>

            <!-- Test Result / Message -->
            <div v-if="voiceKeyResult" :class="['p-3 rounded-xl border text-xs leading-relaxed', voiceKeyResult.success ? 'bg-emerald-950/40 border-emerald-500/40 text-emerald-200' : 'bg-red-950/40 border-red-500/40 text-red-200']">
              <div class="flex items-center gap-1.5 font-semibold mb-0.5">
                <span>{{ voiceKeyResult.success ? '✓' : '⚠️' }}</span>
                <span>{{ voiceKeyResult.success ? 'Bağlantı Başarılı' : 'Doğrulama Başarısız' }}</span>
              </div>
              <div class="text-[11px] opacity-90">{{ voiceKeyResult.message }}</div>
            </div>
          </div>

          <!-- Actions -->
          <div class="pt-3 border-t border-neutral-800 flex items-center justify-between gap-2">
            <button
              type="button"
              @click="testVoiceKey"
              :disabled="isTestingVoiceKey || !voiceKeyInput.trim()"
              class="px-3.5 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
              <span v-if="isTestingVoiceKey" class="w-3.5 h-3.5 border-2 border-neutral-300 border-t-transparent rounded-full animate-spin"></span>
              <span v-else>🔌</span>
              <span>{{ isTestingVoiceKey ? 'Test Ediliyor...' : 'Test Et' }}</span>
            </button>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="showVoiceKeyModal = false"
                class="px-4 py-2 rounded-xl bg-neutral-800 text-neutral-300 hover:bg-neutral-700 text-xs font-medium cursor-pointer"
              >
                İptal
              </button>
              <button
                type="button"
                @click="saveVoiceKeyAndClose"
                :disabled="isSavingVoiceKey || !voiceKeyInput.trim()"
                class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-md shadow-purple-500/20"
              >
                <span v-if="isSavingVoiceKey" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>Kaydet & Kullan</span>
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  templates: {
    type: Array,
    default: () => [],
  },
  categories: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  initial_projects: {
    type: Array,
    default: () => [],
  },
  sfx_catalog: {
    type: Array,
    default: () => [],
  },
  llm_info: {
    type: Object,
    default: () => ({}),
  },
  available_models: {
    type: Array,
    default: () => [],
  },
})

// Tab Switcher
const activeMainTab = ref('director')

// ==========================================
// 0. ACTIVE LLM DIRECTOR CONFIG & MODAL STATE
// ==========================================
const currentLlm = ref({
  provider: props.llm_info?.provider || 'gemini',
  model: props.llm_info?.model || 'gemini-2.0-flash',
  base_url: props.llm_info?.base_url || 'http://127.0.0.1:11434',
  has_api_key: !!props.llm_info?.has_api_key,
  api_key: props.llm_info?.api_key || '',
  providers_config: props.llm_info?.providers_config || {},
})

const scriptGenerationError = ref('')
const showLlmModal = ref(false)
const showApiKeyText = ref(false)
const isTestingLlm = ref(false)
const isSavingLlm = ref(false)
const llmTestResult = ref(null)

const llmModalForm = ref({
  provider: currentLlm.value.provider,
  model: currentLlm.value.model,
  api_key: '',
  base_url: currentLlm.value.base_url,
})

const availableLlmProviders = [
  { key: 'gemini', name: 'Gemini', icon: '✨', desc: 'Google Flash & Pro (Hızlı)' },
  { key: 'groq', name: 'Groq', icon: '⚡', desc: 'Llama 3.3 Ultra Hızlı Cloud' },
  { key: 'openai', name: 'OpenAI', icon: '🧠', desc: 'GPT-4o & GPT-4o-mini' },
  { key: 'ollama', name: 'Ollama', icon: '🦙', desc: 'Yerel PC (11434)' },
  { key: 'anthropic', name: 'Claude', icon: '🎭', desc: 'Claude 3.5 Sonnet' },
  { key: 'deepseek', name: 'DeepSeek', icon: '🐋', desc: 'DeepSeek Chat & V3' },
  { key: 'openrouter', name: 'OpenRouter', icon: '🌐', desc: 'Çoklu Model Havuzu' },
]

const providerPresetModels = {
  gemini: ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-pro'],
  groq: ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768'],
  openai: ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
  ollama: ['llama3:latest', 'mistral:latest', 'qwen2.5:latest'],
  anthropic: ['claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022'],
  deepseek: ['deepseek-chat', 'deepseek-reasoner'],
  openrouter: ['deepseek/deepseek-r1', 'meta-llama/llama-3.3-70b-instruct'],
}

const currentProviderPresetModels = computed(() => {
  return providerPresetModels[llmModalForm.value.provider] || []
})

const hasKeyForCurrentProvider = computed(() => {
  const prov = llmModalForm.value.provider
  if (prov === currentLlm.value.provider && currentLlm.value.has_api_key) return true
  const conf = currentLlm.value.providers_config?.[prov]
  return !!conf?.api_key
})

const llmOnlineStatus = ref(null)

const checkLlmOnline = async () => {
  try {
    const { data } = await axios.post('/api/llm/test-connection', {
      provider: currentLlm.value.provider,
      base_url: currentLlm.value.base_url,
      api_key: currentLlm.value.api_key || '',
    })
    llmOnlineStatus.value = !!data.success
  } catch (err) {
    llmOnlineStatus.value = false
  }
}

const isLlmConfigured = computed(() => {
  if (llmOnlineStatus.value !== null) {
    return llmOnlineStatus.value
  }
  if (currentLlm.value.provider === 'ollama') {
    return !!currentLlm.value.base_url
  }
  return currentLlm.value.has_api_key || !!currentLlm.value.api_key
})

const getApiKeyHint = (provider) => {
  const hints = {
    gemini: 'Google AI Studio üzerinden ücretsiz API anahtarı alabilirsiniz (AIzaSy...).',
    groq: 'console.groq.com adresinden ücretsiz yüksek hızlı API anahtarı alabilirsiniz (gsk_...).',
    openai: 'platform.openai.com üzerinden OpenAI API anahtarı alın (sk-...).',
    anthropic: 'console.anthropic.com adresinden Claude API anahtarı alın (sk-ant-...).',
    deepseek: 'platform.deepseek.com adresinden DeepSeek API anahtarı alın (sk-...).',
    openrouter: 'openrouter.ai/keys adresinden API anahtarı alın (sk-or-...).',
  }
  return hints[provider] || 'Seçtiğiniz servis için geçerli API anahtarınızı girin.'
}

const openLlmModal = () => {
  const prov = currentLlm.value.provider
  const conf = currentLlm.value.providers_config?.[prov]
  llmModalForm.value = {
    provider: prov,
    model: conf?.model || currentLlm.value.model,
    api_key: conf?.api_key || currentLlm.value.api_key || '',
    base_url: conf?.base_url || currentLlm.value.base_url || (prov === 'ollama' ? 'http://127.0.0.1:11434' : ''),
  }
  llmTestResult.value = null
  showLlmModal.value = true
}

const selectLlmProvider = (provKey) => {
  // Stash current inputs so switching providers doesn't discard what the user typed
  const prevProv = llmModalForm.value.provider
  if (prevProv) {
    if (!currentLlm.value.providers_config) currentLlm.value.providers_config = {}
    currentLlm.value.providers_config[prevProv] = {
      ...(currentLlm.value.providers_config[prevProv] || {}),
      provider: prevProv,
      model: llmModalForm.value.model,
      base_url: llmModalForm.value.base_url,
      api_key: llmModalForm.value.api_key || currentLlm.value.providers_config[prevProv]?.api_key || '',
    }
  }

  llmModalForm.value.provider = provKey
  const saved = currentLlm.value.providers_config?.[provKey]
  if (saved && saved.model) {
    llmModalForm.value.model = saved.model
    llmModalForm.value.base_url = saved.base_url || (provKey === 'ollama' ? 'http://127.0.0.1:11434' : '')
    llmModalForm.value.api_key = saved.api_key || ''
  } else if (provKey === currentLlm.value.provider) {
    llmModalForm.value.model = currentLlm.value.model
    llmModalForm.value.base_url = currentLlm.value.base_url
    llmModalForm.value.api_key = currentLlm.value.api_key || ''
  } else {
    const presets = providerPresetModels[provKey] || []
    llmModalForm.value.model = presets[0] || ''
    llmModalForm.value.base_url = provKey === 'ollama' ? 'http://127.0.0.1:11434' : ''
    llmModalForm.value.api_key = ''
  }
  llmTestResult.value = null
}

const testLlmConnectionInModal = async () => {
  isTestingLlm.value = true
  llmTestResult.value = null
  try {
    const { data } = await axios.post('/api/llm/test-connection', {
      provider: llmModalForm.value.provider,
      base_url: llmModalForm.value.base_url,
      api_key: llmModalForm.value.api_key || (hasKeyForCurrentProvider.value ? currentLlm.value.api_key : ''),
    })
    llmTestResult.value = data
  } catch (err) {
    llmTestResult.value = {
      success: false,
      message: 'Bağlantı hatası: ' + (err.response?.data?.message || err.message),
    }
  } finally {
    isTestingLlm.value = false
  }
}

const saveLlmSettingsFromModal = async () => {
  isSavingLlm.value = true
  try {
    const prov = llmModalForm.value.provider
    const updatedProvidersConfig = { ...(currentLlm.value.providers_config || {}) }
    updatedProvidersConfig[prov] = {
      provider: prov,
      model: llmModalForm.value.model,
      base_url: llmModalForm.value.base_url,
      api_key: llmModalForm.value.api_key || (updatedProvidersConfig[prov]?.api_key || ''),
    }

    const payload = {
      llm_provider: prov,
      llm_model: llmModalForm.value.model,
      llm_base_url: llmModalForm.value.base_url,
      llm_providers_config: updatedProvidersConfig,
    }
    if (llmModalForm.value.api_key) {
      payload.llm_api_key = llmModalForm.value.api_key
    }

    await axios.post('/settings', payload)

    // Update local state
    currentLlm.value.provider = prov
    currentLlm.value.model = llmModalForm.value.model
    currentLlm.value.base_url = llmModalForm.value.base_url
    currentLlm.value.providers_config = updatedProvidersConfig
    if (llmModalForm.value.api_key) {
      currentLlm.value.api_key = llmModalForm.value.api_key
      currentLlm.value.has_api_key = true
    }

    checkLlmOnline()
    scriptGenerationError.value = ''
    showLlmModal.value = false
  } catch (err) {
    alert('Ayarlar kaydedilemedi: ' + (err.response?.data?.message || err.message))
  } finally {
    isSavingLlm.value = false
  }
}

// ==========================================
// 1. AI STORY DIRECTOR STUDIO STATE
// ==========================================
const directorTheme = ref('')
const isGeneratingScript = ref(false)
const isProducingAudio = ref(false)
const productionStatusMessage = ref('')
const selectedProject = ref(props.initial_projects && props.initial_projects.length > 0 ? props.initial_projects[0] : null)
const storyProjectsList = ref([...(props.initial_projects || [])])
const sfxCatalogList = ref([...(props.initial_catalog || props.sfx_catalog || [])])

const showSfxModal = ref(false)
const showScriptJsonModal = ref(false)
const copyStatusText = ref('JSON Kopyala')

// Filtered models: Only installed local models or configured cloud models
const installedTtsModels = computed(() => {
  return (props.available_models || []).filter(m => m.type === 'tts' && m.is_configured)
})

const installedLocalTtsModels = computed(() => {
  return (props.available_models || []).filter(m => m.type === 'tts' && !m.is_cloud && m.is_downloaded)
})

const installedCloudTtsModels = computed(() => {
  return (props.available_models || []).filter(m => m.type === 'tts' && m.is_cloud && m.is_configured)
})

const installedMusicModels = computed(() => {
  return (props.available_models || []).filter(m => m.type === 'music' && m.is_downloaded)
})

const installedSttModels = computed(() => {
  return (props.available_models || []).filter(m => m.type === 'stt' && (m.is_downloaded || m.is_configured))
})

const defaultTtsEngine = computed(() => {
  if (installedLocalTtsModels.value.length > 0) {
    const tr = installedLocalTtsModels.value.find(m => m.id === 'piper-tr')
    return tr ? tr.id : installedLocalTtsModels.value[0].id
  }
  if (installedCloudTtsModels.value.length > 0) {
    return installedCloudTtsModels.value[0].id
  }
  return 'piper-tr'
})

// Cloud Voice API Key Modal State & Methods
const showVoiceKeyModal = ref(false)
const voiceKeyProvider = ref('freya')
const voiceKeyInput = ref('')
const showVoiceKeyText = ref(false)
const isTestingVoiceKey = ref(false)
const isSavingVoiceKey = ref(false)
const voiceKeyResult = ref(null)

const openCloudKeyModal = (provider = 'freya') => {
  voiceKeyProvider.value = provider
  voiceKeyInput.value = ''
  voiceKeyResult.value = null
  showVoiceKeyModal.value = true
}

const testVoiceKey = async () => {
  if (!voiceKeyInput.value.trim() || isTestingVoiceKey.value) return
  isTestingVoiceKey.value = true
  voiceKeyResult.value = null
  try {
    const { data } = await axios.post('/api/models/test-cloud-connection', {
      provider: voiceKeyProvider.value,
      key: voiceKeyInput.value.trim(),
    })
    voiceKeyResult.value = data
  } catch (err) {
    voiceKeyResult.value = {
      success: false,
      message: err.response?.data?.message || err.message,
    }
  } finally {
    isTestingVoiceKey.value = false
  }
}

const saveVoiceKeyAndClose = async () => {
  if (!voiceKeyInput.value.trim() || isSavingVoiceKey.value) return
  isSavingVoiceKey.value = true
  try {
    const { data } = await axios.post('/api/models/cloud-keys', {
      provider: voiceKeyProvider.value,
      key: voiceKeyInput.value.trim(),
    })
    if (data.success) {
      // Mark relevant models as configured and downloaded reactively
      (props.available_models || []).forEach(m => {
        if (m.cloud_provider === voiceKeyProvider.value) {
          m.is_configured = true
          m.is_downloaded = true
        }
      })

      if (voiceKeyProvider.value === 'freya') {
        directorOptions.value.tts_engine = 'freya-eve'
      }

      showVoiceKeyModal.value = false
      if (selectedProject.value && selectedProject.value.status === 'failed') {
        startAudioProduction()
      } else {
        alert(data.message)
      }
    } else {
      alert(data.message)
    }
  } catch (err) {
    alert('Kaydedilemedi: ' + (err.response?.data?.message || err.message))
  } finally {
    isSavingVoiceKey.value = false
  }
}

const retryWithPiperTr = () => {
  directorOptions.value.tts_engine = 'piper-tr'
  directorOptions.value.has_voice = true
  startAudioProduction()
}

const copyPromptText = async (text) => {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    alert('✓ Sahne promptu panoya kopyalandı! Midjourney veya DALL-E üzerinde kullanabilirsiniz:\n\n' + text)
  } catch (err) {
    console.warn('Copy prompt failed:', err)
  }
}

const directorOptions = ref({
  has_voice: true,
  tts_engine: 'piper-tr',
  has_bgm: true,
  bgm_mode: 'ambient',
  has_sfx: true,
  sfx_model: 'whisper-medium',
  has_visuals: true,
  visuals_model: 'midjourney-v6',
  has_prosody: true,
  prosody_mode: 'freya-prosody',
  audience: 'children_5_10',
  ducking: true,
})

const getTtsModelLabel = (key) => {
  const map = {
    'alania': 'Patientdesk (Alania)',
    'antalia-1': 'Patientdesk (Antalia-1)',
    'freya-adam': 'Freya (Adam)',
    'freya-eve': 'Freya (Eve)',
    'openai-tts-1': 'OpenAI (tts-1)',
    'openai-tts-hd': 'OpenAI (tts-1-hd)',
    'elevenlabs-multilingual': 'ElevenLabs (v2)',
    'elevenlabs-flash': 'ElevenLabs (Flash)',
    'google-cloud-tts': 'Google Cloud TTS',
    'freya-tts': 'FreyaTTS (DiT)',
    'piper-tr': 'Piper TR',
    'xtts-v2': 'Coqui XTTS v2',
    'bark': 'Suno Bark',
  }
  return map[key] || key || 'TTS'
}

const getBgmModelLabel = (key) => {
  const map = {
    'ambient': 'Hızlı Ambiyans',
    'musicgen-medium': 'MusicGen Medium',
    'musicgen-small': 'MusicGen Small',
    'musicgen-melody': 'MusicGen Melody',
    'musicgen-large': 'MusicGen Large',
    'musicgen': 'MusicGen Medium',
  }
  return map[key] || key || 'BGM'
}

const getSfxModelLabel = (key) => {
  const map = {
    'whisper-medium': 'Whisper Medium',
    'whisper-small': 'Whisper Small',
    'whisper-base': 'Whisper Base',
    'whisper-tiny': 'Whisper Tiny',
    'whisper-large-v3': 'Whisper Large-v3',
  }
  return map[key] || key || 'Whisper'
}

const getVisualsModelLabel = (key) => {
  const map = {
    'midjourney-v6': 'Midjourney v6.1',
    'dalle-3': 'DALL-E 3',
    'flux-1': 'Flux.1',
    'stable-diffusion': 'SDXL',
  }
  return map[key] || key || 'Görsel'
}

const audienceOptions = [
  { key: 'children_5_10', title: '5-10 Yaş Çocuklar', emoji: '🧒', desc: 'Eğitici, neşeli ve masalsı ton' },
  { key: 'preschool', title: 'Okul Öncesi (3-5)', emoji: '🧸', desc: 'Sakin, yumuşak ve melodi ağırlıklı' },
  { key: 'teens', title: 'Gençlik & Macera', emoji: '⚡', desc: 'Dinamik, sürükleyici tempo' },
  { key: 'radio_drama', title: 'Radyo Tiyatrosu', emoji: '📻', desc: 'Yoğun ses efektleri ve dramatik kurgu' },
]

const presetConcepts = [
  {
    emoji: '🚭',
    title: 'Sigaranın Zararları (Çocuk Masalı)',
    theme: '7-9 yaş çocuklara sigara dumanının akciğerlere ve doğaya zararlarını anlatan, küçük Can ve sevimli köpeği Karabaş\'ın parktaki macerasını konu alan eğitici bir hikaye. Duman ve öksürük sahnelerinde ses efektleri kullanılsın.',
    audience: 'children_5_10',
  },
  {
    emoji: '🐱',
    title: 'Kayıp Yavru Kedi ve Ahşap Ev',
    theme: 'Yağmurlu bir sonbahar akşamında kaybolan ve eski ahşap evin tavan arasında mahsur kalan yavru kedi Pati ile onu arayan Zeynep\'in sıcacık, duygusal masalı.',
    audience: 'children_5_10',
  },
  {
    emoji: '🚀',
    title: 'Mars İstasyonunda Gizemli Sinyal',
    theme: '2085 yılında Mars araştırma istasyonunda çalışan genç astronot Deniz\'in, şiddetli fırtına sırasında dışarıdan gelen gizemli bir tıkırtı ve sinyali araştırmasını anlatan gerilim dolu radyo tiyatrosu.',
    audience: 'radio_drama',
  },
  {
    emoji: '🌲',
    title: 'Sihirli Ormanın Kuşları',
    theme: 'Baharın gelişiyle neşeli kuş cıvıltılarıyla uyanan masalsı ormanda, kuraklık tehlikesine karşı hayvanların bilge meşe ağacının önderliğinde bir araya gelmesini anlatan doğa masalı.',
    audience: 'preschool',
  },
]

const applyPreset = (preset) => {
  directorTheme.value = preset.theme
  directorOptions.value.audience = preset.audience
}

// Generate Script via LLM
const generateDirectorScript = async (allowSimulation = false) => {
  if (!directorTheme.value.trim() || isGeneratingScript.value) return

  isGeneratingScript.value = true
  scriptGenerationError.value = ''

  try {
    const { data } = await axios.post('/api/story/generate-script', {
      theme: directorTheme.value.trim(),
      options: {
        ...directorOptions.value,
        llm_provider: currentLlm.value.provider,
        llm_model: currentLlm.value.model,
        llm_api_key: currentLlm.value.api_key,
        llm_base_url: currentLlm.value.base_url,
        allow_simulation: allowSimulation,
      },
      project_id: selectedProject.value ? selectedProject.value.id : null,
    })

    if (data.success && data.project) {
      selectedProject.value = data.project
      // Add or update in list
      const idx = storyProjectsList.value.findIndex(p => p.id === data.project.id)
      if (idx >= 0) {
        storyProjectsList.value[idx] = data.project
      } else {
        storyProjectsList.value.unshift(data.project)
      }
      scriptGenerationError.value = ''
    }
  } catch (err) {
    console.error('Director script generation failed:', err)
    scriptGenerationError.value = err.response?.data?.error || err.response?.data?.message || err.message
  } finally {
    isGeneratingScript.value = false
  }
}

// Select previous project
// Audio Player for Master
const masterAudio = ref(null)
const isPlayingMaster = ref(false)
const masterCurrentTime = ref(0)
const masterDuration = ref(0)

const masterAudioSrc = computed(() => {
  if (!selectedProject.value) return null
  if (selectedProject.value.status === 'completed' || selectedProject.value.master_audio_path) {
    return `/api/story/audio/${selectedProject.value.id}/master`
  }
  return null
})

// Polling interval for background audio production
const pollingInterval = ref(null)

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

const startPolling = (projectId) => {
  stopPolling()
  pollingInterval.value = setInterval(async () => {
    try {
      const { data } = await axios.get(`/api/story/projects/${projectId}`)
      if (data.success && data.project) {
        selectedProject.value = data.project
        productionStatusMessage.value = data.project.progress_step || 'Prodüksiyon devam ediyor...'

        const idx = storyProjectsList.value.findIndex(p => p.id === data.project.id)
        if (idx >= 0) {
          storyProjectsList.value[idx] = data.project
        } else {
          storyProjectsList.value.unshift(data.project)
        }

        if (data.project.status === 'completed') {
          stopPolling()
          isProducingAudio.value = false
          productionStatusMessage.value = ''
        } else if (data.project.status === 'failed') {
          stopPolling()
          isProducingAudio.value = false
          productionStatusMessage.value = ''
        }
      }
    } catch (err) {
      console.warn('Polling error:', err)
    }
  }, 2500)
}

const selectProject = (proj) => {
  selectedProject.value = proj
  if (proj.theme) {
    directorTheme.value = proj.theme
  }
  if (proj.status === 'processing') {
    isProducingAudio.value = true
    productionStatusMessage.value = proj.progress_step || 'Prodüksiyon devam ediyor...'
    startPolling(proj.id)
  } else {
    stopPolling()
    isProducingAudio.value = false
    productionStatusMessage.value = ''
  }
}

// Delete project
const deleteProject = async (id) => {
  if (!confirm('Bu hikaye projesini silmek istediğinize emin misiniz?')) return

  try {
    await axios.delete(`/api/story/projects/${id}`)
    storyProjectsList.value = storyProjectsList.value.filter(p => p.id !== id)
    if (selectedProject.value?.id === id) {
      selectedProject.value = storyProjectsList.value[0] || null
    }
  } catch (err) {
    console.error('Failed to delete project:', err)
  }
}

// Produce Audio (Asynchronous Queue Job + Live Polling)
const startAudioProduction = async () => {
  if (!selectedProject.value || isProducingAudio.value) return

  isProducingAudio.value = true
  productionStatusMessage.value = 'Kuyruğa alındı, prodüksiyon başlatılıyor...'

  try {
    const { data } = await axios.post(`/api/story/produce/${selectedProject.value.id}`, {
      script_data: selectedProject.value.script_data,
      options: directorOptions.value,
    })

    if (data.project) {
      selectedProject.value = data.project
      const idx = storyProjectsList.value.findIndex(p => p.id === data.project.id)
      if (idx >= 0) {
        storyProjectsList.value[idx] = data.project
      }
    }

    // Start polling immediately for real-time progress updates
    startPolling(selectedProject.value.id)

  } catch (err) {
    console.error('Audio production request failed:', err)
    alert('Prodüksiyon isteği başlatılamadı: ' + (err.response?.data?.error || err.message))
    isProducingAudio.value = false
    productionStatusMessage.value = ''
  }
}

const toggleMasterAudio = (url) => {
  if (!masterAudio.value) {
    masterAudio.value = new Audio(url)
    masterAudio.value.ontimeupdate = () => {
      masterCurrentTime.value = masterAudio.value ? masterAudio.value.currentTime : 0
    }
    masterAudio.value.onloadedmetadata = () => {
      masterDuration.value = masterAudio.value ? masterAudio.value.duration : 0
    }
    masterAudio.value.onended = () => {
      isPlayingMaster.value = false
      masterCurrentTime.value = 0
    }
  }

  if (masterAudio.value.src !== window.location.origin + url && !masterAudio.value.src.endsWith(url)) {
    masterAudio.value.src = url
    masterAudio.value.load()
  }

  if (isPlayingMaster.value) {
    masterAudio.value.pause()
    isPlayingMaster.value = false
  } else {
    masterAudio.value.play().catch(e => console.warn(e))
    isPlayingMaster.value = true
  }
}

const seekMasterAudio = (e) => {
  const time = parseFloat(e.target.value)
  masterCurrentTime.value = time
  if (masterAudio.value) {
    masterAudio.value.currentTime = time
  }
}

const quickPlayMaster = (url) => {
  toggleMasterAudio(url)
}

const formatTime = (seconds) => {
  if (!seconds || isNaN(seconds)) return '0:00'
  const m = Math.floor(seconds / 60)
  const s = Math.floor(seconds % 60)
  return `${m}:${s < 10 ? '0' : ''}${s}`
}

// Instant SFX Playback
const sfxAudioPlayer = ref(null)
const playSfx = (sfxId) => {
  if (!sfxId) return
  const cleanId = sfxId.replace(/\.wav$/, '')
  const url = `/api/audio/${cleanId}.wav`

  if (sfxAudioPlayer.value) {
    sfxAudioPlayer.value.pause()
  }
  sfxAudioPlayer.value = new Audio(url)
  sfxAudioPlayer.value.play().catch(err => {
    console.warn('SFX audio play error:', err)
  })
}

// Copy JSON Output
const copyJsonOutput = async () => {
  if (!selectedProject.value?.script_data) return
  try {
    await navigator.clipboard.writeText(JSON.stringify(selectedProject.value.script_data, null, 2))
    copyStatusText.value = '✓ Kopyalandı!'
    setTimeout(() => {
      copyStatusText.value = 'JSON Kopyala'
    }, 2000)
  } catch (err) {
    console.error('Clipboard copy failed:', err)
  }
}

// Shortcode Highlighter
const highlightShortcodes = (text) => {
  if (!text) return ''
  return text
    .replace(/\[whisper\](.*?)\[\/whisper\]/gi, '<span class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 font-medium italic border border-purple-500/30">🤫 $1</span>')
    .replace(/\[cough\](.*?)\[\/cough\]/gi, '<span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-medium border border-amber-500/30">😷 $1</span>')
    .replace(/\[slow\](.*?)\[\/slow\]/gi, '<span class="px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-medium border border-indigo-500/30">⏳ $1</span>')
    .replace(/\[shout\](.*?)\[\/shout\]/gi, '<span class="px-1.5 py-0.5 rounded bg-red-500/20 text-red-300 font-bold border border-red-500/30">📢 $1</span>')
}

// Synchronize background queue state and resume polling if any project is processing
onMounted(async () => {
  // 1. Initial check from loaded props
  const initialActive = storyProjectsList.value.find(p => p.status === 'processing')
  if (initialActive) {
    selectedProject.value = initialActive
    isProducingAudio.value = true
    productionStatusMessage.value = initialActive.progress_step || 'Prodüksiyon kuyrukta işleniyor...'
    startPolling(initialActive.id)
  }

  // 2. Fetch fresh project states from server in case user returned after leaving or background completion
  try {
    const { data } = await axios.get('/api/story/projects')
    if (data.success && data.projects) {
      storyProjectsList.value = data.projects
      const activeProject = data.projects.find(p => p.status === 'processing')
      if (activeProject) {
        selectedProject.value = activeProject
        isProducingAudio.value = true
        productionStatusMessage.value = activeProject.progress_step || 'Prodüksiyon kuyrukta devam ediyor...'
        startPolling(activeProject.id)
      } else if (selectedProject.value) {
        const fresh = data.projects.find(p => p.id === selectedProject.value.id)
        if (fresh) {
          selectedProject.value = fresh
          if (fresh.status !== 'processing') {
            isProducingAudio.value = false
            stopPolling()
          }
        }
      }
    }
  } catch (err) {
    console.warn('Failed to sync projects on mount:', err)
  }

  // 3. Fallback default model selection if current tts_engine is uninstalled
  if (installedTtsModels.value.length > 0) {
    const isCurrentInstalled = installedTtsModels.value.some(m => m.id === directorOptions.value.tts_engine)
    if (!isCurrentInstalled) {
      directorOptions.value.tts_engine = defaultTtsEngine.value
    }
  }

  if (sfxCatalogList.value.length === 0) {
    try {
      const { data } = await axios.get('/api/story/sfx-catalog')
      if (data.success && data.sfx) {
        sfxCatalogList.value = data.sfx
      }
    } catch (err) {
      console.warn('Could not fetch SFX catalog:', err)
    }
  }

  // 4. Check active LLM online connectivity status
  checkLlmOnline()
})

onUnmounted(() => {
  stopPolling()
  if (masterAudio.value) {
    masterAudio.value.pause()
    masterAudio.value = null
  }
  if (sfxAudioPlayer.value) {
    sfxAudioPlayer.value.pause()
    sfxAudioPlayer.value = null
  }
})

// ==========================================
// 2. KLASİK PROMPT ŞABLONLARI LOGIC
// ==========================================
const searchQuery = ref('')
const selectedCategory = ref('all')
const localTemplates = ref([...(props.templates || [])])

const availableCategories = computed(() => {
  const cats = new Set()
  localTemplates.value.forEach(t => {
    if (t.category) cats.add(t.category)
  })
  return Array.from(cats)
})

const filteredTemplates = computed(() => {
  let list = localTemplates.value

  if (selectedCategory.value !== 'all') {
    list = list.filter(t => t.category === selectedCategory.value)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(t =>
      (t.title && t.title.toLowerCase().includes(q)) ||
      (t.content && t.content.toLowerCase().includes(q)) ||
      (t.description && t.description.toLowerCase().includes(q))
    )
  }

  return list
})

const highlightVariables = (text) => {
  if (!text) return ''
  return text.replace(/\$([a-zA-Z0-9_]+)/g, '<span class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono font-semibold border border-purple-500/30">$$$1</span>')
}

// Modal State
const showModal = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const isSubmittingModal = ref(false)

const modalForm = ref({
  title: '',
  category: 'Genel',
  description: '',
  content: '',
  system_prompt: '',
  is_favorite: false,
})

const detectedModalVariables = computed(() => {
  if (!modalForm.value.content) return []
  const matches = modalForm.value.content.match(/\$([a-zA-Z0-9_]+)/g)
  if (!matches) return []
  return Array.from(new Set(matches))
})

const openCreateModal = () => {
  isEditing.value = false
  editingId.value = null
  modalForm.value = {
    title: '',
    category: 'Genel',
    description: '',
    content: '',
    system_prompt: '',
    is_favorite: false,
  }
  showModal.value = true
}

const openEditModal = (template) => {
  isEditing.value = true
  editingId.value = template.id
  modalForm.value = {
    title: template.title,
    category: template.category || 'Genel',
    description: template.description || '',
    content: template.content,
    system_prompt: template.system_prompt || '',
    is_favorite: !!template.is_favorite,
  }
  showModal.value = true
}

const submitModal = () => {
  if (!modalForm.value.title.trim() || !modalForm.value.content.trim()) return

  isSubmittingModal.value = true

  const url = isEditing.value ? `/prompts/${editingId.value}` : '/prompts'
  const method = isEditing.value ? 'put' : 'post'

  router[method](url, modalForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      showModal.value = false
      isSubmittingModal.value = false
      router.reload({ only: ['templates', 'categories'] })
    },
    onError: () => {
      isSubmittingModal.value = false
    }
  })
}

const toggleFavorite = (id) => {
  router.post(`/prompts/${id}/favorite`, {}, {
    preserveScroll: true,
  })
  const item = localTemplates.value.find(t => t.id === id)
  if (item) item.is_favorite = !item.is_favorite
}

const deleteTemplate = (id, title) => {
  if (!confirm(`"${title}" adlı prompt şablonunu silmek istediğinize emin misiniz?`)) {
    return
  }
  router.delete(`/prompts/${id}`, {
    preserveScroll: true,
    onSuccess: () => {
      localTemplates.value = localTemplates.value.filter(t => t.id !== id)
    }
  })
}
</script>
