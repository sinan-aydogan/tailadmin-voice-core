<template>
  <AppLayout title="Ses Efektleri (SFX Studio)">
    <div class="space-y-6">
      <!-- Top Alert Notification -->
      <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="alertMessage"
             :class="['p-4 rounded-2xl border text-xs flex items-center justify-between shadow-lg',
                      alertType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' :
                      alertType === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-300' :
                      'bg-cyan-500/10 border-cyan-500/30 text-cyan-300']">
          <div class="flex items-center gap-2.5">
            <span class="text-base">{{ alertType === 'success' ? '✅' : alertType === 'error' ? '⚠️' : 'ℹ️' }}</span>
            <span class="font-medium">{{ alertMessage }}</span>
          </div>
          <button @click="alertMessage = null" class="opacity-70 hover:opacity-100 p-1 text-xs">✕</button>
        </div>
      </transition>

      <!-- Header & Stats Banner -->
      <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-purple-500/10 border border-neutral-800 p-6">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-[11px] font-semibold text-amber-300 mb-2">
              <span>⚡ Parametrik DSP & AI Efekt Motoru</span>
            </div>
            <h1 class="text-xl font-bold text-neutral-100 flex items-center gap-2.5">
              <span>Ses Efektleri Stüdyosu (SFX)</span>
            </h1>
            <p class="text-xs text-neutral-400 mt-1 max-w-2xl">
              Doğa, sinematik, fantezi ve mekanik ses efektlerini anında sentezleyin veya AI prompt ile üretin. Hikaye & Ses Tiyatrosu Makinesi ile tam entegre çalışır.
            </p>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <div class="bg-neutral-900/80 backdrop-blur border border-neutral-800 rounded-xl px-4 py-2.5 text-center">
              <div class="text-[10px] text-neutral-400 uppercase font-medium">Toplam Efekt</div>
              <div class="text-base font-bold text-amber-400">{{ sfxList.length }}</div>
            </div>
            <div class="bg-neutral-900/80 backdrop-blur border border-neutral-800 rounded-xl px-4 py-2.5 text-center">
              <div class="text-[10px] text-neutral-400 uppercase font-medium">Motor Hızı</div>
              <div class="text-base font-bold text-emerald-400">~15 ms</div>
            </div>
            <Link
              href="/prompts"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-semibold border border-neutral-700 transition-colors"
              title="Hikaye Makinesine Git"
            >
              <span>🎭 Hikaye Makinesi</span>
            </Link>
          </div>
        </div>
      </div>

      <!-- Main Layout: 2 Columns (Generator Form + SFX Library) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Column: Generator Form (5 cols) -->
        <div class="lg:col-span-5 space-y-5">
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
              <h2 class="text-sm font-bold text-neutral-100 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span>Yeni Ses Efekti Oluştur</span>
              </h2>
              <span class="text-[11px] text-neutral-500 font-mono">24kHz PCM</span>
            </div>

            <!-- Quick Template Category Chips -->
            <div>
              <label class="block text-[11px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Hızlı Efekt Şablonları</label>
              <div class="space-y-2">
                <div v-for="cat in categories" :key="cat.id" class="space-y-1.5">
                  <div class="text-[10px] text-neutral-400 font-medium">{{ cat.name }}</div>
                  <div class="flex flex-wrap gap-1.5">
                    <button
                      v-for="p in cat.presets"
                      :key="p.id"
                      type="button"
                      @click="selectPreset(p)"
                      :class="['px-2.5 py-1 rounded-lg text-xs font-medium transition-all cursor-pointer',
                               form.preset === p.id
                                 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40 shadow-sm'
                                 : 'bg-neutral-900 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800 border border-neutral-800']"
                    >
                      {{ p.name }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Custom Concept / Prompt with LLM Enhancer -->
            <div class="space-y-2 p-3.5 rounded-2xl bg-neutral-900/60 border border-amber-500/20">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs">✨</span>
                  <label class="text-[11px] font-bold text-neutral-200">Efekt Açıklaması / Prompt</label>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    v-if="previousPrompt !== null"
                    type="button"
                    @click="undoEnhance"
                    class="text-[10px] text-amber-400 hover:text-amber-300 underline cursor-pointer"
                    title="Önceki prompt metnine geri dön"
                  >
                    ↩️ Geri Al
                  </button>
                  <span class="text-[10px] font-mono" :class="form.prompt.length > 1800 ? 'text-rose-400 font-bold' : 'text-neutral-500'">{{ form.prompt.length }}/2000</span>
                </div>
              </div>

              <!-- LLM Model Selector and Enhance Action Bar -->
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-neutral-950/90 p-2 rounded-xl border border-neutral-800">
                <div class="flex-1 min-w-0 flex items-center gap-2">
                  <span class="text-xs text-neutral-400 shrink-0 pl-1">🤖</span>
                  <select
                    v-model="selectedLlmKey"
                    class="w-full bg-neutral-900 border border-neutral-800 text-[11px] text-neutral-200 py-1.5 px-2 rounded-lg focus:outline-none focus:border-amber-500/50 cursor-pointer truncate"
                    title="Promptu iyileştirmek için kullanılacak LLM modelini seçin"
                  >
                    <optgroup
                      v-for="prov in (props.llm_options?.providers || [])"
                      :key="prov.provider"
                      :label="prov.icon + ' ' + prov.name + (prov.has_key ? '' : ' (API Key Eksik)')"
                      class="bg-neutral-900 text-neutral-200 font-semibold"
                    >
                      <option
                        v-for="m in prov.models"
                        :key="prov.provider + ':::' + m"
                        :value="prov.provider + ':::' + m"
                        class="bg-neutral-900 text-neutral-300 font-normal"
                      >
                        {{ prov.icon }} {{ prov.name.split(' ')[0] }}: {{ m }}
                      </option>
                    </optgroup>
                  </select>
                </div>

                <button
                  type="button"
                  @click="handleEnhancePrompt"
                  :disabled="isEnhancingPrompt"
                  class="px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-neutral-950 font-bold text-[11px] flex items-center justify-center gap-1.5 shrink-0 shadow-md shadow-amber-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer"
                  title="Promptu seçili LLM ile Hollywood düzeyinde akustik foley detaylarıyla geliştir"
                >
                  <svg v-if="isEnhancingPrompt" class="w-3.5 h-3.5 animate-spin text-neutral-950" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                  <span v-else>✨</span>
                  <span>{{ isEnhancingPrompt ? 'İyileştiriliyor...' : 'Promptu İyileştir' }}</span>
                </button>
              </div>

              <textarea
                v-model="form.prompt"
                rows="3"
                placeholder="Örn: Yağmurlu ormanda uzak gök gürültüsü, ıslak yapraklara düşen damlalar ve hafif rüzgar hışırtısı..."
                :class="['w-full rounded-xl bg-neutral-950 p-3 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none transition-colors resize-none border',
                         formErrors.prompt ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-800 focus:border-amber-400']"
              ></textarea>
              <p v-if="formErrors.prompt" class="text-[10px] text-rose-400 flex items-center gap-1">
                <span>⚠</span> {{ formErrors.prompt }}
              </p>

              <!-- LLM Suggestion Feedback Tag -->
              <div v-if="lastEnhanceStats" class="flex flex-wrap items-center gap-2 pt-1 text-[10px] text-amber-300">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30">
                  <span>✨ {{ lastEnhanceStats.model }}</span>
                </span>
                <span class="text-neutral-400">Süre: <b class="text-amber-300">{{ lastEnhanceStats.duration }}s</b></span>
                <span class="text-neutral-500">•</span>
                <span class="text-neutral-400">Yankı: <b class="text-amber-300">{{ lastEnhanceStats.reverb }}</b></span>
                <span class="text-neutral-500">•</span>
                <span class="text-neutral-400">Ton: <b class="text-amber-300">{{ lastEnhanceStats.tone }}</b></span>
              </div>
            </div>

            <!-- Title Input -->
            <div class="space-y-1.5">
              <label class="text-[11px] font-semibold text-neutral-300">Efekt Başlığı (Opsiyonel)</label>
              <input
                v-model="form.title"
                type="text"
                placeholder="Örn: Gece Fırtınası"
                :class="['w-full rounded-xl bg-neutral-900 p-2.5 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none border',
                         formErrors.title ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-700/80 focus:border-amber-400']"
              />
              <p v-if="formErrors.title" class="text-[10px] text-rose-400 flex items-center gap-1 mt-1">
                <span>⚠</span> {{ formErrors.title }}
              </p>
            </div>

            <!-- Controls: Duration & Reverb & Tone -->
            <div class="grid grid-cols-2 gap-3 pt-1">
              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Süre: {{ form.duration }} sn</label>
                <div class="flex items-center gap-1">
                  <button
                    v-for="d in [0.8, 1.5, 2.5, 4.0]"
                    :key="d"
                    type="button"
                    @click="form.duration = d"
                    :class="['flex-1 py-1 rounded-lg text-[11px] font-medium transition-colors',
                             form.duration === d ? 'bg-amber-500 text-neutral-950 font-bold' : 'bg-neutral-900 text-neutral-400 hover:bg-neutral-800']"
                  >
                    {{ d }}s
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Akustik Yankı (Reverb)</label>
                <select
                  v-model="form.reverb"
                  :class="['w-full rounded-xl bg-neutral-900 p-2 text-xs text-neutral-200 focus:outline-none border', formErrors.reverb ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-700 focus:border-amber-400']"
                >
                  <option value="dry">Kuru (Stüdyo)</option>
                  <option value="room">Oda (Hafif)</option>
                  <option value="cave">Geniş Mağara</option>
                  <option value="hall">Büyük Katedral</option>
                </select>
                <p v-if="formErrors.reverb" class="text-[10px] text-rose-400 mt-1">⚠ {{ formErrors.reverb }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Ton Karakteri</label>
                <select
                  v-model="form.tone"
                  :class="['w-full rounded-xl bg-neutral-900 p-2 text-xs text-neutral-200 focus:outline-none border', formErrors.tone ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-700 focus:border-amber-400']"
                >
                  <option value="balanced">Dengeli Doğal</option>
                  <option value="bass">Derin Bas Ağırlıklı</option>
                  <option value="bright">Parlak & Kristal</option>
                </select>
                <p v-if="formErrors.tone" class="text-[10px] text-rose-400 mt-1">⚠ {{ formErrors.tone }}</p>
              </div>

              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Üretim Motoru</label>
                <select
                  v-model="form.engine"
                  :class="['w-full rounded-xl bg-neutral-900 p-2 text-xs text-neutral-200 focus:outline-none border',
                           formErrors.engine ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-700 focus:border-amber-400']"
                >
                  <option
                    v-for="eng in props.sfx_engines"
                    :key="eng.value"
                    :value="eng.value"
                    :disabled="!eng.available"
                    :class="eng.available ? '' : 'text-neutral-600'"
                    :title="eng.install_hint || ''"
                  >
                    {{ eng.label }}
                  </option>
                </select>
                <!-- Install hint for selected unavailable engine -->
                <p
                  v-if="props.sfx_engines.find(e => e.value === form.engine)?.install_hint"
                  class="text-[10px] text-amber-400 mt-1 flex items-center gap-1"
                >
                  ⚠
                  {{ props.sfx_engines.find(e => e.value === form.engine)?.install_hint }}
                  <a href="/models" class="underline hover:text-amber-300">Modeller →</a>
                </p>
                <p v-if="formErrors.engine" class="text-[10px] text-rose-400 mt-1">⚠ {{ formErrors.engine }}</p>
              </div>
            </div>

            <!-- Submit Button -->
            <button
              @click="handleGenerate"
              :disabled="isGenerating"
              class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-neutral-950 font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <svg v-if="isGenerating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              <span v-else>⚡</span>
              <span>{{ isGenerating ? 'Ses Efekti Sentezleniyor...' : 'Ses Efektini Üret' }}</span>
            </button>
          </div>
        </div>

        <!-- Right Column: SFX Library & Audio Player (7 cols) -->
        <div class="lg:col-span-7 space-y-4">
          <!-- Active Player Card if audio selected -->
          <div v-if="activeTrack" class="p-5 rounded-2xl bg-neutral-900/90 border border-amber-500/40 shadow-xl shadow-amber-500/5 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-300 font-bold text-sm">
                  SFX
                </div>
                <div>
                  <h3 class="text-sm font-bold text-neutral-100">{{ activeTrack.title }}</h3>
                  <div class="text-[11px] text-neutral-400 flex items-center gap-2">
                    <span class="px-1.5 py-0.5 rounded bg-neutral-800 text-[10px] text-neutral-300">{{ activeTrack.category }}</span>
                    <span>•</span>
                    <span>{{ activeTrack.duration_sec }} sn</span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <a
                  :href="activeTrack.audio_url"
                  download
                  class="p-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 hover:text-neutral-100 text-xs transition-colors"
                  title="WAV Dosyasını İndir"
                >
                  ⬇️ İndir
                </a>
              </div>
            </div>

            <!-- Custom Audio Scrubber -->
            <div class="space-y-1.5 pt-1">
              <div class="flex items-center gap-3">
                <button
                  @click="togglePlayActive"
                  class="w-10 h-10 rounded-full bg-amber-400 hover:bg-amber-300 text-neutral-950 flex items-center justify-center font-bold text-sm shrink-0 transition-transform active:scale-95 shadow-md shadow-amber-400/20"
                >
                  <span v-if="isPlaying">⏸</span>
                  <span v-else class="translate-x-0.5">▶</span>
                </button>

                <div class="flex-1 space-y-1">
                  <input
                    type="range"
                    min="0"
                    :max="audioDuration || 1"
                    step="0.01"
                    :value="currentTime"
                    @input="seekAudio($event.target.value)"
                    class="w-full accent-amber-400 h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
                  />
                  <div class="flex justify-between text-[10px] text-neutral-500 font-mono">
                    <span>{{ formatTime(currentTime) }}</span>
                    <span>{{ formatTime(audioDuration) }}</span>
                  </div>
                </div>

                <div class="flex items-center gap-1.5 text-neutral-400 text-xs shrink-0 pl-2">
                  <span>🔊</span>
                  <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    v-model="volume"
                    @input="changeVolume"
                    class="w-16 accent-amber-400 h-1 bg-neutral-800 rounded cursor-pointer"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- SFX Catalog List -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-neutral-800">
              <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-neutral-100">Ses Efekti Kütüphanesi</h2>
                <span class="px-2 py-0.5 rounded-full bg-neutral-800 text-[11px] text-neutral-300 font-medium">
                  {{ filteredList.length }}
                </span>
              </div>

              <!-- Search & Filter -->
              <div class="flex items-center gap-2">
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Efekt ara..."
                  class="rounded-xl bg-neutral-900 border border-neutral-700/80 px-3 py-1.5 text-xs text-neutral-200 placeholder-neutral-500 focus:outline-none focus:border-amber-400 w-36 sm:w-48"
                />
              </div>
            </div>

            <!-- List of Tracks -->
            <div v-if="filteredList.length === 0" class="py-12 text-center text-neutral-500 text-xs">
              <div class="text-2xl mb-2">🔍</div>
              <div>Aranan kriterlere uygun ses efekti bulunamadı.</div>
            </div>

            <div v-else class="space-y-2 max-h-[580px] overflow-y-auto pr-1">
              <div
                v-for="item in filteredList"
                :key="item.filename"
                :class="['p-3 rounded-xl border flex items-center justify-between gap-3 transition-all',
                         activeTrack?.filename === item.filename
                           ? 'bg-amber-500/10 border-amber-500/30'
                           : 'bg-neutral-900/60 hover:bg-neutral-800/60 border-neutral-800']"
              >
                <div class="flex items-center gap-3 min-w-0">
                  <button
                    @click="playTrack(item)"
                    class="w-8 h-8 rounded-lg bg-neutral-800 hover:bg-amber-400 hover:text-neutral-950 text-neutral-200 flex items-center justify-center text-xs shrink-0 transition-colors"
                  >
                    <span v-if="activeTrack?.filename === item.filename && isPlaying">⏸</span>
                    <span v-else class="translate-x-0.5">▶</span>
                  </button>

                  <div class="min-w-0">
                    <div class="text-xs font-semibold text-neutral-200 truncate flex items-center gap-2">
                      <span class="truncate">{{ item.title }}</span>
                      <span v-if="item.is_default" class="text-[9px] px-1 rounded bg-neutral-800 text-neutral-400 shrink-0">Yerleşik</span>
                    </div>
                    <div class="text-[10px] text-neutral-500 flex items-center gap-2 mt-0.5">
                      <span class="text-amber-400/80">{{ item.category }}</span>
                      <span>•</span>
                      <span>{{ item.duration_sec }}s</span>
                      <span>•</span>
                      <span class="font-mono">{{ (item.size_bytes / 1024).toFixed(0) }} KB</span>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                  <a
                    :href="item.audio_url"
                    download
                    class="p-1.5 rounded-lg bg-neutral-800/80 hover:bg-neutral-700 text-neutral-400 hover:text-neutral-200 text-xs transition-colors"
                    title="İndir"
                  >
                    ⬇️
                  </a>
                  <button
                    v-if="!item.is_default"
                    @click="handleDelete(item)"
                    class="p-1.5 rounded-lg bg-neutral-800/80 hover:bg-rose-500/20 text-neutral-400 hover:text-rose-400 text-xs transition-colors"
                    title="Sil"
                  >
                    🗑️
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '../../Layouts/AppLayout.vue'

const props = defineProps({
  library: {
    type: Array,
    default: () => []
  },
  categories: {
    type: Array,
    default: () => []
  },
  llm_options: {
    type: Object,
    default: () => ({ current: {}, providers: [] })
  },
  sfx_engines: {
    type: Array,
    default: () => [
      { value: 'smart_synth', label: '⚡ Hızlı Akıllı DSP Sentez', available: true }
    ]
  }
})

const sfxList = ref([...props.library])
const searchQuery = ref('')
const isGenerating = ref(false)
const alertMessage = ref(null)
const alertType = ref('success')
const formErrors = ref({})

// LLM Prompt Enhancement State
const selectedLlmKey = ref(
  props.llm_options?.current?.provider && props.llm_options?.current?.model
    ? `${props.llm_options.current.provider}:::${props.llm_options.current.model}`
    : 'ollama:::google/gemma-4-e4b'
)

const selectedLlm = computed(() => {
  const parts = (selectedLlmKey.value || '').split(':::')
  return {
    provider: parts[0] || 'ollama',
    model: parts[1] || 'google/gemma-4-e4b'
  }
})

const isEnhancingPrompt = ref(false)
const previousPrompt = ref(null)
const lastEnhanceStats = ref(null)

const handleEnhancePrompt = async () => {
  if (isEnhancingPrompt.value) return
  isEnhancingPrompt.value = true
  alertMessage.value = null

  try {
    const { provider, model } = selectedLlm.value
    const res = await axios.post('/api/sfx/enhance-prompt', {
      prompt: form.value.prompt,
      preset: form.value.preset,
      duration: form.value.duration,
      provider,
      model
    })

    if (res.data && res.data.success) {
      previousPrompt.value = form.value.prompt
      form.value.prompt = res.data.enhanced_prompt

      if (res.data.suggested_duration) {
        form.value.duration = res.data.suggested_duration
      }
      if (res.data.suggested_reverb) {
        form.value.reverb = res.data.suggested_reverb
      }
      if (res.data.suggested_tone) {
        form.value.tone = res.data.suggested_tone
      }
      if (res.data.suggested_preset) {
        form.value.preset = res.data.suggested_preset
      }

      lastEnhanceStats.value = {
        model: res.data.model_used || model,
        provider: res.data.provider_used || provider,
        duration: res.data.suggested_duration,
        reverb: res.data.suggested_reverb,
        tone: res.data.suggested_tone
      }

      alertType.value = 'success'
      alertMessage.value = `Ses efekti promptu "${res.data.model_used || model}" ile başarıyla zenginleştirildi!`
    } else {
      throw new Error(res.data?.error || 'Prompt geliştirilemedi.')
    }
  } catch (err) {
    alertType.value = 'error'
    alertMessage.value = err.response?.data?.error || err.message || 'LLM bağlantı hatası oluştu.'
  } finally {
    isEnhancingPrompt.value = false
  }
}

const undoEnhance = () => {
  if (previousPrompt.value !== null) {
    form.value.prompt = previousPrompt.value
    previousPrompt.value = null
    lastEnhanceStats.value = null
  }
}

const form = ref({
  prompt: '',
  preset: 'birds_chirping',
  title: '',
  duration: 2.0,
  reverb: 'room',
  tone: 'balanced',
  engine: 'smart_synth'
})

// Audio Player State
const activeTrack = ref(null)
const isPlaying = ref(false)
const currentTime = ref(0)
const audioDuration = ref(0)
const volume = ref(0.85)
let audioElement = null

const initAudio = () => {
  if (!audioElement) {
    audioElement = new Audio()
    audioElement.volume = volume.value
    audioElement.ontimeupdate = () => {
      currentTime.value = audioElement.currentTime
    }
    audioElement.onloadedmetadata = () => {
      audioDuration.value = audioElement.duration
    }
    audioElement.onended = () => {
      isPlaying.value = false
      currentTime.value = 0
    }
  }
}

const playTrack = (track) => {
  initAudio()
  if (activeTrack.value?.filename === track.filename && isPlaying.value) {
    audioElement.pause()
    isPlaying.value = false
    return
  }

  activeTrack.value = track
  audioElement.src = track.audio_url
  audioElement.play().then(() => {
    isPlaying.value = true
  }).catch(e => {
    console.warn('Audio play error:', e)
  })
}

const togglePlayActive = () => {
  if (!audioElement || !activeTrack.value) return
  if (isPlaying.value) {
    audioElement.pause()
    isPlaying.value = false
  } else {
    audioElement.play()
    isPlaying.value = true
  }
}

const seekAudio = (time) => {
  if (audioElement) {
    audioElement.currentTime = time
    currentTime.value = time
  }
}

const changeVolume = () => {
  if (audioElement) {
    audioElement.volume = volume.value
  }
}

const formatTime = (secs) => {
  if (!secs || isNaN(secs)) return '0:00'
  const m = Math.floor(secs / 60)
  const s = Math.floor(secs % 60)
  return `${m}:${s < 10 ? '0' : ''}${s}`
}

const selectPreset = (preset) => {
  form.value.preset = preset.id
  form.value.prompt = preset.prompt
  form.value.title = preset.name
  form.value.duration = preset.duration
}

const filteredList = computed(() => {
  if (!searchQuery.value) return sfxList.value
  const q = searchQuery.value.toLowerCase()
  return sfxList.value.filter(item =>
    item.title.toLowerCase().includes(q) ||
    item.category.toLowerCase().includes(q) ||
    (item.prompt && item.prompt.toLowerCase().includes(q))
  )
})

const handleGenerate = async () => {
  isGenerating.value = true
  alertMessage.value = null
  formErrors.value = {}

  try {
    const res = await axios.post('/api/sfx/generate', form.value)
    if (res.data.success) {
      alertType.value = 'success'
      alertMessage.value = `"${res.data.data.title}" ses efekti başarıyla üretildi!`
      sfxList.value.unshift(res.data.data)
      playTrack(res.data.data)
    } else {
      throw new Error(res.data.error || 'Üretim başarısız.')
    }
  } catch (err) {
    alertType.value = 'error'
    if (err.response?.status === 422 && err.response?.data?.errors) {
      const errors = err.response.data.errors
      formErrors.value = Object.fromEntries(
        Object.entries(errors).map(([field, msgs]) => [field, Array.isArray(msgs) ? msgs[0] : msgs])
      )
      const firstError = Object.values(formErrors.value)[0]
      alertMessage.value = `⚠ Doğrulama hatası: ${firstError}`
    } else {
      alertMessage.value = err.response?.data?.error || err.response?.data?.message || err.message || 'Hata oluştu.'
    }
  } finally {
    isGenerating.value = false
  }
}

const handleDelete = async (item) => {
  if (!confirm(`"${item.title}" ses efektini silmek istediğinize emin misiniz?`)) return

  try {
    const res = await axios.delete(`/api/sfx/${item.filename}`)
    if (res.data.success) {
      sfxList.value = sfxList.value.filter(x => x.filename !== item.filename)
      if (activeTrack.value?.filename === item.filename) {
        if (audioElement) audioElement.pause()
        activeTrack.value = null
        isPlaying.value = false
      }
      alertType.value = 'success'
      alertMessage.value = 'Ses efekti silindi.'
    }
  } catch (err) {
    alertType.value = 'error'
    alertMessage.value = 'Silme işlemi başarısız.'
  }
}

onBeforeUnmount(() => {
  if (audioElement) {
    audioElement.pause()
    audioElement = null
  }
})
</script>
