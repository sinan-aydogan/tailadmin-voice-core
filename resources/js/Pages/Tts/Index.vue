<template>
  <AppLayout title="Metin Okuma (TTS)">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Input Form -->
      <div class="lg:col-span-2 space-y-6">
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
          <h2 class="text-base font-semibold text-neutral-100">Yeni Ses Üretimi</h2>

          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-2">
                  <label class="block text-xs font-medium text-neutral-400">Metin</label>
                  <button
                    v-if="hasFormattingToClean"
                    type="button"
                    @click="cleanTextManually"
                    class="px-2.5 py-0.5 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 text-[11px] font-medium transition-all flex items-center gap-1 cursor-pointer"
                    title="Metindeki yıldızları (**), başlıkları (###), emojileri ve seslendirme notlarını temizle"
                  >
                    <span>🧹 Yıldız & Notları Arındır</span>
                  </button>
                </div>
                <button
                  type="button"
                  @click="openAiModal('custom')"
                  class="px-3 py-1 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 border border-purple-500/30 text-xs font-medium transition-all flex items-center gap-1.5 shadow-sm shadow-purple-500/5 cursor-pointer"
                  title="Yapay zeka ile yaratıcı seslendirme metni üret"
                >
                  <span>✨ AI ile Metin Üret</span>
                </button>
              </div>

              <!-- Suno-style Voiceover & Director Directive Card -->
              <div v-if="voiceoverDirective" class="mb-3 p-3.5 rounded-2xl bg-gradient-to-r from-purple-950/40 via-neutral-900 to-amber-950/30 border border-purple-500/30 text-xs space-y-1.5 shadow-lg shadow-purple-500/5">
                <div class="flex items-center justify-between text-[11px] font-semibold text-purple-300">
                  <div class="flex items-center gap-2">
                    <span class="text-sm">🎭</span>
                    <span>Seslendirme & Yönetmen Talimatı (Suno Tarzı)</span>
                    <span class="px-1.5 py-0.2 rounded bg-purple-500/20 text-purple-300 text-[10px] font-mono">Ayrıştırıldı</span>
                  </div>
                  <button type="button" @click="voiceoverDirective = ''" class="text-neutral-500 hover:text-neutral-300 text-xs cursor-pointer p-0.5" title="Talimatı Kaldır">✕</button>
                </div>
                <div class="text-neutral-200 text-xs italic font-sans leading-relaxed">
                  "{{ voiceoverDirective }}"
                </div>
                <div class="text-[10px] text-neutral-400 flex items-center gap-1 pt-0.5">
                  <span class="text-emerald-400 font-bold">✓ Korundu:</span>
                  <span>Bu talimat seslendirme metninden ayrılmıştır; ses motoru "yıldız" veya talimatı seslendirmez.</span>
                </div>
              </div>
              <textarea
                v-model="form.text"
                rows="5"
                required
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3.5 text-sm text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                placeholder="Seslendirilmesini istediğiniz metni buraya yazın..."
              ></textarea>
              <div class="flex justify-between text-[11px] text-neutral-500 mt-1">
                <span>Karakter: {{ form.text.length }} / 5000</span>
                <span>Worker izole process'te çalışır, UI donmaz.</span>
              </div>

              <!-- Freya Voice Expressive Tags -->
              <div v-if="isFreyaEngine" class="mt-2.5 p-2.5 rounded-xl bg-purple-950/20 border border-purple-500/30 flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="text-[11px] text-purple-300 font-semibold flex items-center gap-1.5">
                    <span class="text-sm">✨</span>
                    <span>Freya İnsansı Duygu Etiketleri:</span>
                  </span>
                  <button
                    v-for="tag in freyaExpressiveTags"
                    :key="tag.code"
                    type="button"
                    @click="insertTag(tag.code)"
                    class="px-2 py-0.5 rounded-lg bg-purple-500/15 hover:bg-purple-500/25 border border-purple-500/30 text-[11px] text-purple-200 transition-colors cursor-pointer flex items-center gap-1 shadow-sm"
                    :title="tag.desc"
                  >
                    <span>{{ tag.icon }}</span>
                    <span class="font-mono font-medium">{{ tag.code }}</span>
                    <span class="text-neutral-400 text-[10px]">({{ tag.label }})</span>
                  </button>
                </div>
                <span class="text-[10px] text-purple-400/80 font-mono">AudioRealismBench #1</span>
              </div>
            </div>

            <div class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Engine -->
                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-medium text-neutral-400">TTS Motoru</label>
                    <span v-if="selectedModelInfo?.is_downloaded" class="text-[10px] text-emerald-400 font-semibold flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Hazır
                    </span>
                    <span v-else-if="selectedModelInfo" class="text-[10px] text-amber-400 font-semibold flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> İndirilmedi
                    </span>
                  </div>
                  <select
                    v-model="form.engine"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
                  >
                    <optgroup v-if="downloadedLocalModels.length > 0" label="💾 Kurulu Yerel / Lokal Modeller">
                      <option v-for="m in downloadedLocalModels" :key="m.id" :value="m.id">
                        ✓ [Lokal] {{ m.name }}
                      </option>
                    </optgroup>
                    <optgroup v-if="cloudReadyModels.length > 0" label="☁️ Hazır Bulut API Modelleri">
                      <option v-for="m in cloudReadyModels" :key="m.id" :value="m.id">
                        ⚡ [Bulut API] {{ m.name }}
                      </option>
                    </optgroup>
                    <optgroup v-if="cloudPendingModels.length > 0" label="☁️ API Anahtarı Bekleyen Bulut Modelleri">
                      <option v-for="m in cloudPendingModels" :key="m.id" :value="m.id">
                        🔑 [Bulut API] {{ m.name }} (Yapılandırma Gerekli)
                      </option>
                    </optgroup>
                    <optgroup v-if="notDownloadedLocalModels.length > 0" label="⬇️ İndirme Bekleyen Yerel Modeller">
                      <option v-for="m in notDownloadedLocalModels" :key="m.id" :value="m.id">
                        ⚠ [Lokal] {{ m.name }} (İndirilmedi)
                      </option>
                    </optgroup>
                  </select>
                </div>

                <!-- Language -->
                <div>
                  <label class="block text-xs font-medium text-neutral-400 mb-1.5">Dil</label>
                  <select
                    v-model="form.language"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
                  >
                    <option v-for="lang in VOICE_LANGUAGES" :key="lang.code" :value="lang.code">
                      {{ lang.flag }} {{ lang.name }} ({{ lang.code }})
                    </option>
                  </select>
                </div>

                <!-- Profile (for voice cloning) -->
                <div>
                  <label class="block text-xs font-medium text-neutral-400 mb-1.5">Ses Profili (Klonlama)</label>
                  <select
                    v-model="form.profile_id"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
                  >
                    <option :value="null">Varsayılan Ses</option>
                    <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
              </div>

              <!-- Advanced Voice Tuning (Stability & Speed) -->
              <div class="p-3.5 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-3">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-xs font-semibold text-neutral-300 flex items-center gap-1.5">
                    <span>🎛️ Ses Dinamiği & İfade Ayarları</span>
                  </span>
                  <div class="flex items-center gap-3">
                    <span class="text-[11px] text-neutral-400 hidden sm:inline">
                      Seçili Motor: <strong class="text-neutral-200">{{ selectedModelInfo?.name || form.engine }}</strong>
                    </span>
                    <button
                      type="button"
                      @click="showStabilityModal = true"
                      class="px-2.5 py-1 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 hover:text-white border border-neutral-700 hover:border-accent/40 text-xs font-medium flex items-center gap-1.5 transition-all cursor-pointer shadow-sm group"
                      title="Seslendirme Dökümantasyonu & Akustik Rehber"
                    >
                      <svg class="w-3.5 h-3.5 text-accent group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                      </svg>
                      <span>Seslendirme Dökümantasyonu</span>
                    </button>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                  <!-- Stability Slider -->
                  <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                      <label class="text-xs font-medium text-neutral-400">Duygu & Kararlılık (Stability)</label>
                      <div class="flex items-center gap-1.5">
                        <span
                          class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                          :class="form.stability <= 0.35 
                            ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' 
                            : (form.stability <= 0.65 
                              ? 'bg-accent/20 text-accent border border-accent/30' 
                              : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30')"
                        >
                          {{ form.stability <= 0.35 ? 'Dramatik / Gülüşlü' : (form.stability <= 0.65 ? 'Doğal Anlatı' : 'Resmi / Spiker') }}
                        </span>
                        <span class="font-mono text-xs text-white font-semibold">%{{ Math.round(form.stability * 100) }}</span>
                      </div>
                    </div>
                    <input
                      type="range"
                      min="0.10"
                      max="1.00"
                      step="0.05"
                      v-model.number="form.stability"
                      class="w-full accent-accent h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
                    />
                    <div class="flex justify-between text-[10px] text-neutral-500 font-mono">
                      <span>%10 (Çok Duygusal/Aktör)</span>
                      <span>%50 (Dengeli)</span>
                      <span>%100 (Monoton/Spiker)</span>
                    </div>
                  </div>

                  <!-- Speech Speed Slider -->
                  <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                      <label class="text-xs font-medium text-neutral-400">Konuşma Hızı (Tempo)</label>
                      <div class="flex items-center gap-1.5">
                        <span
                          class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                          :class="form.speed === 1.0 
                            ? 'bg-neutral-800 text-neutral-300 border border-neutral-700' 
                            : (form.speed > 1.0 
                              ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' 
                              : 'bg-blue-500/20 text-blue-300 border border-blue-500/30')"
                        >
                          {{ form.speed === 1.0 ? 'Normal Hız' : (form.speed > 1.0 ? 'Hızlı' : 'Yavaş / Sakin') }}
                        </span>
                        <span class="font-mono text-xs text-white font-semibold">{{ Number(form.speed).toFixed(2) }}x</span>
                      </div>
                    </div>
                    <input
                      type="range"
                      min="0.50"
                      max="2.00"
                      step="0.05"
                      v-model.number="form.speed"
                      class="w-full accent-accent h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
                    />
                    <div class="flex justify-between text-[10px] text-neutral-500 font-mono">
                      <span>0.50x (Yavaş)</span>
                      <span>1.00x (Normal)</span>
                      <span>2.00x (Hızlı)</span>
                    </div>
                  </div>
                </div>

                <!-- Expandable More Audio Controls Button -->
                <div class="pt-1.5 border-t border-neutral-800/80">
                  <button
                    type="button"
                    @click="showMoreAudioParams = !showMoreAudioParams"
                    class="text-[11px] text-neutral-400 hover:text-accent transition-colors flex items-center gap-1.5 cursor-pointer"
                  >
                    <svg class="w-3.5 h-3.5 transition-transform" :class="showMoreAudioParams ? 'rotate-90 text-accent' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span>{{ showMoreAudioParams ? 'Daha Az Akustik Ayar Göster' : 'İleri Düzey Akustik Ayarları Göster (Perde, Benzerlik, Üslup, Es Süresi)' }}</span>
                  </button>

                  <!-- Expanded Audio Parameters Grid -->
                  <div v-if="showMoreAudioParams" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 pt-3 mt-2 border-t border-neutral-800/50">
                    <!-- Pitch (Ses Perdesi) -->
                    <div class="space-y-1">
                      <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-neutral-400">🎵 Ses Perdesi (Pitch)</label>
                        <span class="font-mono text-[10px] text-emerald-400 font-semibold">
                          {{ form.pitch > 0 ? '+' + form.pitch : form.pitch }} st
                        </span>
                      </div>
                      <input
                        type="range"
                        min="-6"
                        max="6"
                        step="1"
                        v-model.number="form.pitch"
                        class="w-full accent-emerald-500 h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
                      />
                      <div class="flex justify-between text-[9px] text-neutral-500 font-mono">
                        <span>-6 (Kalın/Tok)</span>
                        <span>0 (Doğal)</span>
                        <span>+6 (İnce/Genç)</span>
                      </div>
                    </div>

                    <!-- Similarity Boost -->
                    <div class="space-y-1">
                      <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-neutral-400">🎯 Benzerlik (Clarity)</label>
                        <span class="font-mono text-[10px] text-blue-400 font-semibold">%{{ Math.round(form.similarity_boost * 100) }}</span>
                      </div>
                      <input
                        type="range"
                        min="0.10"
                        max="1.00"
                        step="0.05"
                        v-model.number="form.similarity_boost"
                        class="w-full accent-blue-500 h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
                      />
                      <div class="flex justify-between text-[9px] text-neutral-500 font-mono">
                        <span>%10 (Pürüzsüz)</span>
                        <span>%75 (İdeal)</span>
                        <span>%100 (Birebir)</span>
                      </div>
                    </div>

                    <!-- Style Exaggeration -->
                    <div class="space-y-1">
                      <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-neutral-400">🎭 Üslup Abartısı (Style)</label>
                        <span class="font-mono text-[10px] text-amber-400 font-semibold">%{{ Math.round(form.style * 100) }}</span>
                      </div>
                      <input
                        type="range"
                        min="0.00"
                        max="1.00"
                        step="0.05"
                        v-model.number="form.style"
                        class="w-full accent-amber-500 h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
                      />
                      <div class="flex justify-between text-[9px] text-neutral-500 font-mono">
                        <span>%0 (Doğal)</span>
                        <span>%40 (Dramatik)</span>
                        <span>%100 (Tiyatral)</span>
                      </div>
                    </div>

                    <!-- Default Pause Duration -->
                    <div class="space-y-1">
                      <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-neutral-400">⏸️ Varsayılan Es Süresi</label>
                        <span class="font-mono text-[10px] text-purple-400 font-semibold">{{ Number(form.default_pause_sec).toFixed(1) }}s</span>
                      </div>
                      <input
                        type="range"
                        min="0.3"
                        max="2.5"
                        step="0.1"
                        v-model.number="form.default_pause_sec"
                        class="w-full accent-purple-500 h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
                      />
                      <div class="flex justify-between text-[9px] text-neutral-500 font-mono">
                        <span>0.3s (Seri)</span>
                        <span>1.0s (Doğal)</span>
                        <span>2.5s (Uzun Es)</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Warning banner if selected model is not downloaded -->
              <div v-if="selectedModelInfo && !selectedModelInfo.is_downloaded"
                   class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                  <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <div>
                    <div class="font-semibold text-amber-200">
                      {{ selectedModelInfo.is_cloud ? getProviderDisplayName(selectedModelInfo.cloud_provider) + ' API Anahtarı Gerekli' : 'Model Henüz Bilgisayarınızda Kurulu Değil' }}
                    </div>
                    <div class="text-[11px] text-amber-300/90 mt-0.5">
                      <template v-if="selectedModelInfo.is_cloud">
                        <strong>{{ selectedModelInfo.name }}</strong> modelini kullanabilmek için Model Yöneticisi veya Ayarlar sayfasından {{ getProviderDisplayName(selectedModelInfo.cloud_provider) }} API anahtarınızı tanımlamalısınız (0 MB anında hazır).
                      </template>
                      <template v-else>
                        <strong>{{ selectedModelInfo.name }}</strong> modelini kullanabilmek için önce Model Yöneticisi'nden indirmeniz gerekmektedir.
                      </template>
                    </div>
                  </div>
                </div>
                <a :href="selectedModelInfo.is_cloud ? '/models' : '/models'" class="px-3.5 py-1.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 font-medium text-xs whitespace-nowrap transition-colors flex items-center gap-1.5 self-start sm:self-auto">
                  <span>{{ selectedModelInfo.is_cloud ? 'API Anahtarı Tanımla' : 'Model Yöneticisi\'ne Git' }}</span>
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                  </svg>
                </a>
              </div>
            </div>

            <div class="pt-2 flex justify-end">
              <button
                type="submit"
                :disabled="form.processing || !form.text.trim() || !isCurrentEngineReady"
                class="px-6 py-2.5 rounded-xl font-semibold text-sm bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2 cursor-pointer"
              >
                <span v-if="form.processing" class="w-4 h-4 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
                <span v-if="!isCurrentEngineReady">{{ selectedModelInfo?.is_cloud ? 'API Anahtarı Gerekli' : 'Model İndirilmeli' }}</span>
                <span v-else-if="form.processing">Kuyruğa Gönderiliyor...</span>
                <span v-else>Sesi Üret</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Quick Info / Prompts & Status -->
      <div class="space-y-6">
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
          <!-- Dual Tab Switcher -->
          <div class="flex items-center justify-between border-b border-neutral-800/80 pb-2.5">
            <div class="flex items-center gap-1 bg-neutral-900 p-1 rounded-xl border border-neutral-800">
              <button
                type="button"
                @click="activeRightTab = 'prompts'"
                :class="[
                  'px-3 py-1 rounded-lg text-xs font-medium transition-all cursor-pointer flex items-center gap-1.5',
                  activeRightTab === 'prompts'
                    ? 'bg-purple-500/20 text-purple-300 font-semibold border border-purple-500/30 shadow-sm'
                    : 'text-neutral-400 hover:text-neutral-200'
                ]"
              >
                <span>💡 Promptlar</span>
                <span v-if="savedPrompts.length > 0" class="text-[10px] px-1.5 py-0.2 rounded-full bg-neutral-800 text-neutral-400">
                  {{ savedPrompts.length }}
                </span>
              </button>

              <button
                type="button"
                @click="activeRightTab = 'models'"
                :class="[
                  'px-3 py-1 rounded-lg text-xs font-medium transition-all cursor-pointer flex items-center gap-1.5',
                  activeRightTab === 'models'
                    ? 'bg-accent/20 text-accent-300 font-semibold border border-accent/30 shadow-sm'
                    : 'text-neutral-400 hover:text-neutral-200'
                ]"
              >
                <span>🎙️ Modeller</span>
              </button>
            </div>

            <Link
              :href="activeRightTab === 'prompts' ? '/prompts' : '/models'"
              class="text-xs text-accent-400 hover:underline"
            >
              {{ activeRightTab === 'prompts' ? 'Şablonları Yönet' : 'Modelleri Yönet' }}
            </Link>
          </div>

          <!-- TAB 1: SAVED PROMPTS -->
          <div v-if="activeRightTab === 'prompts'" class="space-y-2.5">
            <div class="text-[11px] text-neutral-400 flex items-center justify-between">
              <span>Kayıtlı Şablonlar:</span>
              <button
                type="button"
                @click="openAiModal('custom')"
                class="text-purple-400 hover:text-purple-300 font-medium cursor-pointer"
              >
                + Serbest Prompt
              </button>
            </div>

            <div v-if="savedPrompts.length > 0" class="space-y-2 max-h-96 overflow-y-auto pr-1">
              <div
                v-for="p in savedPrompts"
                :key="p.id"
                class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 hover:border-neutral-700 transition-all flex flex-col justify-between gap-2.5 group"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <div class="font-medium text-xs text-neutral-200 group-hover:text-purple-300 transition-colors flex items-center gap-1.5 truncate">
                      <span v-if="p.is_favorite" class="text-amber-400 text-xs">★</span>
                      <span>{{ p.title }}</span>
                    </div>
                    <div class="text-[11px] text-neutral-500 line-clamp-1 mt-0.5">
                      {{ p.description || p.content }}
                    </div>
                  </div>
                  <span class="px-2 py-0.5 rounded-md bg-neutral-800 text-[10px] text-neutral-400 shrink-0">
                    {{ p.category || 'Genel' }}
                  </span>
                </div>

                <div class="flex items-center justify-between gap-2 pt-1 border-t border-neutral-800/60">
                  <div class="flex items-center gap-1 flex-wrap">
                    <span
                      v-for="v in (p.extracted_variables || []).slice(0, 3)"
                      :key="v"
                      class="px-1.5 py-0.2 rounded bg-purple-500/15 text-purple-300 font-mono text-[9px]"
                    >
                      {{ v }}
                    </span>
                  </div>

                  <button
                    type="button"
                    @click="openAiModal(p.id)"
                    class="px-2.5 py-1 rounded-lg bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 text-[11px] font-semibold transition-colors flex items-center gap-1 cursor-pointer"
                  >
                    <span>Kullan</span>
                    <span>✨</span>
                  </button>
                </div>
              </div>
            </div>

            <div v-else class="p-4 rounded-xl bg-neutral-900/40 border border-neutral-800 text-center text-xs text-neutral-500">
              Henüz kayıtlı prompt yok.
              <Link href="/prompts" class="text-accent block mt-1 hover:underline">İlk şablonu oluşturun</Link>
            </div>
          </div>

          <!-- TAB 2: MODEL STATUSES -->
          <div v-else class="text-xs text-neutral-400 space-y-2.5 max-h-80 overflow-y-auto pr-1">
            <div v-for="m in ttsModels" :key="m.id"
                 class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 flex items-center justify-between gap-2">
              <div class="min-w-0 flex-1">
                <div class="font-medium text-neutral-200 truncate">{{ m.name }}</div>
                <div class="text-[11px] text-neutral-500 truncate mt-0.5">{{ m.description }}</div>
              </div>
              <span v-if="m.is_downloaded" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 whitespace-nowrap">
                Hazır
              </span>
              <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-neutral-800 text-neutral-500 whitespace-nowrap">
                İndirilmedi
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- History & Audio Player -->
      <div class="lg:col-span-3">
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <h3 class="text-sm font-semibold text-neutral-200">Son TTS Üretimleri</h3>
              <span v-if="hasActiveTasks" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                <span>İşleniyor</span>
              </span>
              <span v-else class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                <span>Canlı Senkronize</span>
              </span>
            </div>
            <button
              @click="fetchTasks(true)"
              :disabled="isRefreshing"
              class="text-xs text-accent-400 hover:text-accent-300 flex items-center gap-1.5 transition-colors disabled:opacity-50"
            >
              <svg :class="['w-3.5 h-3.5', { 'animate-spin': isRefreshing }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>{{ isRefreshing ? 'Güncelleniyor...' : 'Yenile' }}</span>
            </button>
          </div>

          <div class="divide-y divide-neutral-800/60">
            <div v-if="tasksList.length === 0" class="p-8 text-center text-sm text-neutral-500">
              Henüz üretilmiş bir ses bulunmuyor.
            </div>
            <div v-for="t in tasksList" :key="t.id" class="py-4 space-y-2.5">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <div class="text-sm text-neutral-200 font-medium break-words">{{ t.payload?.text }}</div>
                  <div class="flex flex-wrap items-center gap-2 text-xs text-neutral-500 mt-1">
                    <span>{{ formatDateTime(t.created_at) }}</span>
                    <span>·</span>
                    <span class="px-1.5 py-0.5 rounded bg-neutral-900 border border-neutral-800 text-neutral-300 font-mono text-[11px]">
                      {{ t.payload?.engine || 'motor' }}
                    </span>
                    <span class="px-1.5 py-0.5 rounded bg-neutral-900 border border-neutral-800 text-neutral-300 uppercase text-[11px]">
                      {{ t.payload?.language || 'tr' }}
                    </span>
                  </div>
                </div>

                <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap">
                  <!-- Status Badges -->
                  <span v-if="t.status === 'pending'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    <span>Kuyrukta Bekliyor</span>
                  </span>

                  <span v-else-if="t.status === 'running'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                    <svg class="w-3 h-3 animate-spin text-cyan-400" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Üretiliyor...</span>
                  </span>

                  <span v-else-if="t.status === 'completed'"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Tamamlandı</span>
                  </span>

                  <span v-else-if="t.status === 'failed'"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>Başarısız</span>
                  </span>

                  <!-- Custom Play Button (Opens Modal) -->
                  <button
                    v-if="t.status === 'completed' && getAudioFilename(t)"
                    @click="openPlayerModal(t)"
                    class="p-2 rounded-xl bg-neutral-800 hover:bg-accent/20 hover:text-accent text-neutral-300 transition-colors flex items-center justify-center group"
                    title="Sesi Dinle (Oynatıcıyı Aç)"
                  >
                    <svg class="w-4 h-4 fill-current transition-transform group-hover:scale-110 text-accent" viewBox="0 0 24 24">
                      <path d="M8 5v14l11-7z" />
                    </svg>
                  </button>

                  <!-- Download Button -->
                  <a v-if="t.status === 'completed' && getAudioFilename(t)"
                     :href="'/api/audio/' + getAudioFilename(t)"
                     download
                     class="p-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors"
                     title="Sesi İndir">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </a>

                  <!-- Retry Button (Farklı Model ile Yeniden Üret / Yeniden Dene) -->
                  <button
                    v-if="t.status === 'completed' || t.status === 'failed'"
                    @click="openRetryModal(t)"
                    class="p-2 rounded-xl transition-colors"
                    :class="t.status === 'completed' 
                      ? 'bg-neutral-800 hover:bg-accent/20 hover:text-accent text-neutral-300' 
                      : 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 hover:text-amber-300'"
                    :title="t.status === 'completed' ? 'Farklı Model / Ses ile Yeniden Üret' : 'Farklı Model ile Yeniden Dene'"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                  </button>

                  <!-- Delete Button -->
                  <button
                    @click="deleteTask(t.id)"
                    class="p-2 rounded-xl bg-neutral-800/60 hover:bg-red-500/20 text-neutral-400 hover:text-red-400 transition-colors"
                    title="İşlemi Sil"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Error details box for failed tasks -->
              <div v-if="t.status === 'failed' && t.error_message"
                   class="p-3 rounded-xl bg-red-950/30 border border-red-900/40 text-xs text-red-300 flex items-start gap-2.5">
                <svg class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="space-y-1 overflow-hidden">
                  <div class="font-semibold text-red-200">Hata Oluştu:</div>
                  <div class="font-mono text-[11px] text-red-300/90 break-words">{{ t.error_message }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Custom Audio Player Modal -->
    <AudioPlayerModal
      :show="showPlayerModal"
      :task="selectedTask"
      @close="showPlayerModal = false"
    />

    <!-- Retry Task Modal -->
    <RetryTaskModal
      :show="showRetryModal"
      :task="selectedRetryTask"
      :available-models="models"
      :profiles="profiles"
      @close="showRetryModal = false"
      @retried="onTaskRetried"
    />

    <!-- AI Generate Text Modal -->
    <AiGenerateModal
      :show="showAiModal"
      :initial-template-id="aiModalTemplateId"
      :initial-engine="form.engine"
      @close="showAiModal = false"
      @apply="onAiTextApplied"
    />

    <!-- Stability Info Modal -->
    <StabilityInfoModal
      :show="showStabilityModal"
      @close="showStabilityModal = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import AudioPlayerModal from '../../Components/AudioPlayerModal.vue'
import RetryTaskModal from '../../Components/RetryTaskModal.vue'
import AiGenerateModal from '../../Components/AiGenerateModal.vue'
import StabilityInfoModal from '../../Components/StabilityInfoModal.vue'
import { VOICE_LANGUAGES } from '../../i18n'
import { parseVoiceoverText } from '../../Utils/textSanitizer'

const props = defineProps({
  profiles: Array,
  tasks: Array,
  models: Array,
  default_engine: String,
})

const tasksList = ref(props.tasks ? [...props.tasks] : [])
const isRefreshing = ref(false)

// AI Modal & Right tab state
const showAiModal = ref(false)
const showStabilityModal = ref(false)
const showMoreAudioParams = ref(false)
const aiModalTemplateId = ref('custom')
const savedPrompts = ref([])
const activeRightTab = ref('prompts') // 'prompts' | 'models'
const voiceoverDirective = ref('')

const openAiModal = (templateId = 'custom') => {
  aiModalTemplateId.value = templateId
  showAiModal.value = true
}

const onAiTextApplied = (payload) => {
  if (typeof payload === 'object' && payload !== null) {
    form.text = payload.text || ''
    if (payload.directive) {
      voiceoverDirective.value = payload.directive
    }
    if (payload.engine) {
      form.engine = payload.engine
    }
  } else {
    const parsed = parseVoiceoverText(payload)
    form.text = parsed.cleanText
    if (parsed.directive) {
      voiceoverDirective.value = parsed.directive
    }
  }
}

const hasFormattingToClean = computed(() => {
  if (!form.text) return false
  const t = form.text
  return t.includes('*') || 
         t.includes('#') || 
         t.toLowerCase().includes('seslendirme notu') || 
         t.toLowerCase().includes('yönetmen notu') ||
         /\*\*\([^\)]+\)\*\*/.test(t) ||
         stageRegexCheck(t) ||
         /[\u{1F300}-\u{1F9FF}]/u.test(t)
})

const stageRegexCheck = (str) => {
  return /[\[\(](?:giriş|gelişme|bülten|kapanış|anons|müzik|es|ton|arka\s*plan|efekt|enerjik|dinamik|tarafsız|güven|selamlayıcı|seslendirme|spiker|not|talimat)[^\]\)]*[\]\)]/i.test(str)
}

const cleanTextManually = () => {
  const parsed = parseVoiceoverText(form.text)
  form.text = parsed.cleanText
  if (parsed.directive) {
    voiceoverDirective.value = parsed.directive
  }
}

const fetchSavedPrompts = async () => {
  try {
    const res = await fetch('/api/prompts')
    if (res.ok) {
      const data = await res.json()
      savedPrompts.value = data.templates || []
    }
  } catch (e) {
    console.warn('Promptlar alınamadı:', e)
  }
}

const showPlayerModal = ref(false)
const selectedTask = ref(null)

const showRetryModal = ref(false)
const selectedRetryTask = ref(null)

const openPlayerModal = (task) => {
  selectedTask.value = task
  showPlayerModal.value = true
}

const openRetryModal = (task) => {
  selectedRetryTask.value = task
  showRetryModal.value = true
}

const onTaskRetried = async () => {
  await fetchTasks(true)
}

const ttsModels = computed(() => {
  return (props.models || []).filter(m => m.type === 'tts' || m.type === 'music')
})

const downloadedModels = computed(() => {
  return ttsModels.value.filter(m => m.is_downloaded)
})

const notDownloadedModels = computed(() => {
  return ttsModels.value.filter(m => !m.is_downloaded)
})

const downloadedLocalModels = computed(() => {
  return ttsModels.value.filter(m => !m.is_cloud && m.is_downloaded)
})

const cloudReadyModels = computed(() => {
  return ttsModels.value.filter(m => m.is_cloud && m.is_downloaded)
})

const cloudPendingModels = computed(() => {
  return ttsModels.value.filter(m => m.is_cloud && !m.is_downloaded)
})

const notDownloadedLocalModels = computed(() => {
  return ttsModels.value.filter(m => !m.is_cloud && !m.is_downloaded)
})

const form = useForm({
  text: '',
  engine: props.default_engine || 'piper-tr',
  language: 'tr',
  profile_id: null,
  stability: 0.50,
  speed: 1.00,
  pitch: 0,
  similarity_boost: 0.75,
  style: 0.00,
  default_pause_sec: 1.0,
})

const selectedModelInfo = computed(() => {
  return ttsModels.value.find(m => m.id === form.engine || m.engine === form.engine)
})

const getProviderDisplayName = (p) => {
  const map = {
    openai: 'OpenAI',
    elevenlabs: 'ElevenLabs',
    google: 'Google Cloud',
    groq: 'Groq',
    freya: 'Freya Voice',
  }
  return map[p] || (p ? p.toUpperCase() : 'Bulut')
}

const isCurrentEngineReady = computed(() => {
  return selectedModelInfo.value ? Boolean(selectedModelInfo.value.is_downloaded) : true
})

const isFreyaEngine = computed(() => {
  return form.engine && form.engine.startsWith('freya')
})

const freyaExpressiveTags = [
  { code: '[pause]', label: 'Es / Duraklama', icon: '⏸️', desc: 'Konuşmada doğal insansı duraklama ekler' },
  { code: '[laughter]', label: 'Gülüş', icon: '😄', desc: 'İnsansı hafif gülüş veya neşeli ton ekler' },
  { code: '[deep breath]', label: 'Derin Nefes', icon: '🫁', desc: 'Cümle öncesi veya arasında insansı nefes sesi ekler' },
  { code: '[sigh]', label: 'İç Çekiş', icon: '💨', desc: 'Duygulu iç çekme efekti ekler' },
]

const insertTag = (tagCode) => {
  form.text = (form.text ? form.text.trim() + ' ' : '') + tagCode + ' '
}

const hasActiveTasks = computed(() => {
  return tasksList.value.some(t => t.status === 'pending' || t.status === 'running')
})

const getAudioFilename = (t) => {
  if (t.payload?.filename) return t.payload.filename
  if (t.result?.filename) return t.result.filename
  if (t.output_path) {
    const parts = t.output_path.split(/[\\/]/)
    return parts[parts.length - 1]
  }
  return null
}

const formatDateTime = (dateStr) => {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    return d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  } catch (e) {
    return dateStr
  }
}

const fetchTasks = async (showLoading = false) => {
  if (showLoading) isRefreshing.value = true
  try {
    const res = await fetch('/api/tasks/tts')
    if (res.ok) {
      const data = await res.json()
      tasksList.value = data
    }
  } catch (err) {
    console.warn('Görevler güncellenirken hata:', err)
  } finally {
    if (showLoading) isRefreshing.value = false
  }
}

const deleteTask = async (id) => {
  if (!confirm('Bu üretim kaydını silmek istediğinize emin misiniz?')) {
    return
  }
  try {
    const res = await fetch(`/api/tasks/${id}`, { method: 'DELETE' })
    if (res.ok) {
      tasksList.value = tasksList.value.filter(t => t.id !== id)
    }
  } catch (err) {
    console.error('Silme hatası:', err)
  }
}

const submit = () => {
  form.post('/tts/generate', {
    onSuccess: () => {
      form.text = ''
      voiceoverDirective.value = ''
      window.dispatchEvent(new CustomEvent('voice-task-created'))
      // Immediately pull fresh list
      fetchTasks()
      // Also schedule fast checks to catch the newly queued task immediately
      setTimeout(() => fetchTasks(), 600)
      setTimeout(() => fetchTasks(), 1600)
    }
  })
}

/* ── Live Adaptive Polling ── */
let pollTimer = null

const runAdaptivePoll = async () => {
  await fetchTasks()
  // Active tasks poll every 2s, idle tasks poll every 5s
  const nextInterval = hasActiveTasks.value ? 2000 : 5000
  pollTimer = setTimeout(runAdaptivePoll, nextInterval)
}

onMounted(() => {
  if (downloadedModels.value.length > 0) {
    const isCurrentDownloaded = downloadedModels.value.some(m => m.id === form.engine || m.engine === form.engine)
    if (!isCurrentDownloaded) {
      form.engine = downloadedModels.value[0].id
    }
  }
  fetchSavedPrompts()

  // If URL contains ?prompt_id=... open AI modal automatically
  if (typeof window !== 'undefined' && window.location.search) {
    const params = new URLSearchParams(window.location.search)
    const promptId = params.get('prompt_id')
    if (promptId) {
      openAiModal(promptId)
    }
  }

  pollTimer = setTimeout(runAdaptivePoll, 2500)
})

onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
})
</script>
