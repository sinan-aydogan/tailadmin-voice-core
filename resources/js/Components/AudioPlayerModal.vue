<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
      @keydown.esc="close"
      tabindex="-1"
    >
      <!-- Backdrop with blur -->
      <div
        class="fixed inset-0 bg-black/75 backdrop-blur-md transition-opacity animate-fade-in"
        @click="close"
      ></div>

      <!-- Modal Card -->
      <div
        class="relative w-full max-w-2xl bg-surface border border-neutral-800 rounded-3xl shadow-2xl overflow-hidden z-10 my-auto animate-scale-up"
        @click.stop
      >
        <!-- Top Glow Accent -->
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-accent via-sky-400 to-emerald-400"></div>

        <!-- Header -->
        <div class="p-6 border-b border-neutral-800/80 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-accent/15 border border-accent/30 flex items-center justify-center text-accent">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-neutral-100 flex items-center gap-2">
                <span>Ses Oynatıcı</span>
                <span v-if="isPlaying" class="flex items-center gap-1 text-emerald-400 text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                  Çalıyor
                </span>
              </h3>
              <p class="text-xs text-neutral-400 mt-0.5">
                Yapay zeka tarafından üretilen ses çıktısı
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2.5">
            <!-- Playlist Task Navigator (Task Counter & Arrows) -->
            <div
              v-if="totalItems && totalItems > 1"
              class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-neutral-900/90 border border-neutral-800 text-xs text-neutral-300"
            >
              <button
                @click="goToPrev"
                :disabled="!hasPrev"
                class="p-1 rounded-lg hover:bg-neutral-800 disabled:opacity-30 disabled:cursor-not-allowed text-neutral-400 hover:text-neutral-100 transition-colors"
                title="Önceki Görev (Shift + Sol Ok)"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <span class="text-[11px] font-medium text-neutral-400 select-none">
                Görev <strong class="text-neutral-100 font-mono font-bold">{{ itemIndex }}</strong> / <span class="font-mono">{{ totalItems }}</span>
              </span>
              <button
                @click="goToNext"
                :disabled="!hasNext"
                class="p-1 rounded-lg hover:bg-neutral-800 disabled:opacity-30 disabled:cursor-not-allowed text-neutral-400 hover:text-neutral-100 transition-colors"
                title="Sonraki Görev (Shift + Sağ Ok)"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>

            <button
              @click="close"
              class="w-9 h-9 rounded-xl bg-neutral-800/80 hover:bg-neutral-700 text-neutral-400 hover:text-neutral-100 flex items-center justify-center transition-colors"
              title="Kapat (Esc)"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Hidden Audio Element -->
        <audio
          ref="audioRef"
          :src="resolvedAudioUrl"
          preload="metadata"
          @timeupdate="onTimeUpdate"
          @loadedmetadata="onLoadedMetadata"
          @ended="onEnded"
          @play="isPlaying = true"
          @pause="isPlaying = false"
        ></audio>

        <!-- Body -->
        <div class="p-6 space-y-6">

          <!-- Model & Processing Time Info Box -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3.5 rounded-2xl bg-neutral-900/80 border border-neutral-800/90 text-xs">
            <!-- Model -->
            <div class="space-y-1">
              <div class="text-neutral-500 text-[11px] font-medium">Model / Motor</div>
              <div class="flex items-center gap-1.5 text-neutral-200 font-semibold truncate">
                <span class="px-1.5 py-0.5 rounded bg-accent/15 text-accent border border-accent/20 font-mono text-[11px]">
                  {{ modelName }}
                </span>
              </div>
            </div>

            <!-- İşlenme Zamanı / Süre -->
            <div class="space-y-1">
              <div class="text-neutral-500 text-[11px] font-medium">İşlem / Oluşturma Zamanı</div>
              <div class="text-neutral-200 font-medium truncate flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ processingTimeLabel }}</span>
              </div>
            </div>

            <!-- Dil & Format -->
            <div class="space-y-1 col-span-2 sm:col-span-1">
              <div class="text-neutral-500 text-[11px] font-medium">Dil / Format</div>
              <div class="text-neutral-200 font-medium flex items-center gap-2">
                <span class="uppercase font-semibold px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-300 text-[10px]">
                  {{ languageCode }}
                </span>
                <span class="text-neutral-500 font-mono text-[11px]">WAV 24kHz</span>
              </div>
            </div>
          </div>

          <!-- Metin (Speech Text Card) -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-neutral-300 uppercase tracking-wider flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                </svg>
                Metin
              </span>
              <button
                v-if="speechText"
                @click="copyText"
                class="text-[11px] text-neutral-400 hover:text-neutral-200 flex items-center gap-1 transition-colors"
                title="Metni Kopyala"
              >
                <svg v-if="copied" class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span>{{ copied ? 'Kopyalandı!' : 'Metni Kopyala' }}</span>
              </button>
            </div>

            <div class="p-4 rounded-2xl bg-neutral-900/60 border border-neutral-800 text-sm text-neutral-200 leading-relaxed max-h-36 overflow-y-auto select-text font-normal">
              {{ speechText || 'Metin belirtilmemiş.' }}
            </div>
          </div>

          <!-- Equalizer Animation Bar -->
          <div class="flex items-center justify-center gap-1.5 h-8 py-1">
            <span
              v-for="n in 28"
              :key="n"
              class="w-1 rounded-full bg-accent/60 transition-all duration-150"
              :class="isPlaying ? 'animate-wave' : 'opacity-25'"
              :style="{
                height: isPlaying ? getWaveHeight(n) : '4px',
                animationDelay: (n * 0.05) + 's',
                backgroundColor: isPlaying ? '#818cf8' : '#52525b'
              }"
            ></span>
          </div>

          <!-- Timeline & Controls Card -->
          <div class="p-5 rounded-2xl bg-neutral-900/90 border border-neutral-800 space-y-4">

            <!-- Clickable & Draggable Timeline Bar -->
            <div class="space-y-1.5">
              <div
                ref="timelineRef"
                class="relative w-full h-3 bg-neutral-800 rounded-full cursor-pointer select-none group flex items-center"
                @click="seekClick"
                @mousedown="startSeeking"
              >
                <!-- Buffered / Background line -->
                <div class="absolute inset-0 rounded-full bg-neutral-800 group-hover:bg-neutral-700/80 transition-colors"></div>

                <!-- Progress Fill -->
                <div
                  class="absolute left-0 top-0 bottom-0 bg-gradient-to-r from-accent via-indigo-400 to-sky-400 rounded-full pointer-events-none transition-[width] duration-75"
                  :style="{ width: progressPercent + '%' }"
                ></div>

                <!-- Thumb Indicator -->
                <div
                  class="absolute -translate-x-1/2 w-4 h-4 rounded-full bg-white shadow-lg shadow-accent/50 border-2 border-accent transition-transform duration-75 group-hover:scale-125"
                  :style="{ left: progressPercent + '%' }"
                ></div>
              </div>

              <!-- Time Counters (Current / Total) -->
              <div class="flex items-center justify-between text-xs font-mono font-semibold text-neutral-400">
                <span class="text-neutral-200">{{ formatSeconds(currentTime) }}</span>
                <span class="text-neutral-500">/</span>
                <span>{{ formatSeconds(duration) }}</span>
              </div>
            </div>

            <!-- Controls Row -->
            <div class="flex flex-wrap items-center justify-between gap-4 pt-1">
              <!-- Left: Playback Rate Selector -->
              <div class="flex items-center gap-1">
                <button
                  v-for="rate in [0.75, 1, 1.25, 1.5, 2]"
                  :key="rate"
                  @click="setPlaybackRate(rate)"
                  :class="[
                    'px-2 py-1 rounded-lg text-[11px] font-mono font-medium transition-colors',
                    playbackRate === rate
                      ? 'bg-accent/20 text-accent font-bold border border-accent/30'
                      : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800'
                  ]"
                >
                  {{ rate }}x
                </button>
              </div>

              <!-- Center: Playback Buttons -->
              <div class="flex items-center gap-2">
                <!-- Previous Task (|◀) -->
                <button
                  v-if="totalItems && totalItems > 1"
                  @click="goToPrev"
                  :disabled="!hasPrev"
                  class="w-9 h-9 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 disabled:opacity-25 disabled:hover:bg-neutral-800 disabled:cursor-not-allowed flex items-center justify-center transition-colors cursor-pointer"
                  title="Önceki Görev (Shift + Sol Ok)"
                >
                  <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                    <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                  </svg>
                </button>

                <!-- Backward 5s -->
                <button
                  @click="skip(-5)"
                  class="w-9 h-9 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 flex items-center justify-center transition-colors cursor-pointer"
                  title="5 Saniye Geri (←)"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.334 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z" />
                  </svg>
                </button>

                <!-- Primary Play / Pause Button -->
                <button
                  @click="togglePlay"
                  class="w-13 h-13 sm:w-14 sm:h-14 rounded-2xl bg-gradient-to-tr from-accent to-sky-500 hover:from-accent-hover hover:to-sky-400 text-bg shadow-lg shadow-accent/25 flex items-center justify-center transition-all duration-200 active:scale-95 group cursor-pointer"
                  :title="isPlaying ? 'Durdur (Boşluk)' : 'Başlat (Boşluk)'"
                >
                  <!-- Pause Icon -->
                  <svg v-if="isPlaying" class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
                  </svg>
                  <!-- Play Icon -->
                  <svg v-else class="w-6 h-6 fill-current translate-x-0.5" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z" />
                  </svg>
                </button>

                <!-- Forward 5s -->
                <button
                  @click="skip(5)"
                  class="w-9 h-9 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 flex items-center justify-center transition-colors cursor-pointer"
                  title="5 Saniye İleri (→)"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z" />
                  </svg>
                </button>

                <!-- Next Task (▶|) -->
                <button
                  v-if="totalItems && totalItems > 1"
                  @click="goToNext"
                  :disabled="!hasNext"
                  class="w-9 h-9 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 disabled:opacity-25 disabled:hover:bg-neutral-800 disabled:cursor-not-allowed flex items-center justify-center transition-colors cursor-pointer"
                  title="Sonraki Görev (Shift + Sağ Ok)"
                >
                  <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                    <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                  </svg>
                </button>
              </div>

              <!-- Right: Volume / Mute & Download -->
              <div class="flex items-center gap-3">
                <!-- Volume Control -->
                <div class="flex items-center gap-1.5 group/vol">
                  <button
                    @click="toggleMute"
                    class="p-2 rounded-lg text-neutral-400 hover:text-neutral-200 transition-colors"
                    :title="isMuted ? 'Sesi Aç' : 'Sesi Kapat'"
                  >
                    <svg v-if="isMuted || volume === 0" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15zM17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    </svg>
                  </button>
                  <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    v-model.number="volume"
                    @input="onVolumeChange"
                    class="w-16 h-1 bg-neutral-700 rounded-lg appearance-none cursor-pointer accent-accent"
                  />
                </div>

                <!-- Download Button (İndirme Düğmesi) -->
                <a
                  :href="resolvedAudioUrl"
                  :download="resolvedFilename || 'tts_audio.wav'"
                  class="px-3.5 py-2 rounded-xl bg-accent text-bg hover:opacity-95 font-semibold text-xs flex items-center gap-1.5 shadow-sm transition-opacity"
                  title="Sesi Dosya Olarak İndir"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  <span>İndir</span>
                </a>
              </div>
            </div>

          </div>

        </div>

        <!-- Footer Help Note -->
        <div class="px-6 py-3 bg-neutral-900/40 border-t border-neutral-800/60 flex items-center justify-between text-[11px] text-neutral-500">
          <span>
            Klavye Kısayolları: <kbd class="px-1.5 py-0.5 rounded bg-neutral-800 border border-neutral-700 font-mono text-neutral-300">Boşluk</kbd> Başlat/Durdur · <kbd class="px-1.5 py-0.5 rounded bg-neutral-800 border border-neutral-700 font-mono text-neutral-300">Esc</kbd> Kapat
            <template v-if="totalItems && totalItems > 1">
              · <kbd class="px-1.5 py-0.5 rounded bg-neutral-800 border border-neutral-700 font-mono text-neutral-300">Shift + ← / →</kbd> Önceki/Sonraki Görev
            </template>
          </span>
          <button @click="close" class="hover:text-neutral-300 cursor-pointer">Pencereyi Kapat</button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  task: {
    type: Object,
    default: () => null,
  },
  audioUrl: {
    type: String,
    default: '',
  },
  text: {
    type: String,
    default: '',
  },
  model: {
    type: String,
    default: '',
  },
  hasPrev: {
    type: Boolean,
    default: false,
  },
  hasNext: {
    type: Boolean,
    default: false,
  },
  itemIndex: {
    type: Number,
    default: null,
  },
  totalItems: {
    type: Number,
    default: null,
  },
})

const emit = defineEmits(['close', 'prev', 'next'])

const goToPrev = () => {
  if (props.hasPrev) {
    emit('prev')
  }
}

const goToNext = () => {
  if (props.hasNext) {
    emit('next')
  }
}

const audioRef = ref(null)
const timelineRef = ref(null)
const isPlaying = ref(false)
const currentTime = ref(0)
const duration = ref(0)
const volume = ref(1)
const isMuted = ref(false)
const playbackRate = ref(1)
const copied = ref(false)
const isSeeking = ref(false)

// Resolve audio URL & filename
const resolvedFilename = computed(() => {
  if (props.task) {
    if (props.task.payload?.filename) return props.task.payload.filename
    if (props.task.result?.filename) return props.task.result.filename
    if (props.task.output_path) {
      const parts = props.task.output_path.split(/[\\/]/)
      return parts[parts.length - 1]
    }
  }
  if (props.audioUrl) {
    const parts = props.audioUrl.split('/')
    return parts[parts.length - 1]
  }
  return 'audio.wav'
})

const resolvedAudioUrl = computed(() => {
  if (props.audioUrl) return props.audioUrl
  if (resolvedFilename.value) return `/api/audio/${resolvedFilename.value}`
  return ''
})

// Metin
const speechText = computed(() => {
  if (props.text) return props.text
  if (props.task?.payload?.text) return props.task.payload.text
  return ''
})

// Model Name
const modelName = computed(() => {
  if (props.model) return props.model
  const engine = props.task?.payload?.engine
  if (!engine) return 'Varsayılan Model'
  const names = {
    'xtts-v2': 'Coqui XTTS v2',
    'bark': 'Suno Bark (Small)',
    'tortoise': 'Tortoise TTS',
    'piper-tr': 'Piper TTS (Turkish)',
    'piper-en': 'Piper TTS (English)',
    'musicgen-small': 'MusicGen (Small)',
    'musicgen-medium': 'MusicGen (Medium)',
  }
  return names[engine] || engine
})

// Language
const languageCode = computed(() => {
  return props.task?.payload?.language || 'TR'
})

// Processing Time & Timestamps
const processingTimeLabel = computed(() => {
  if (!props.task) return 'Hazır'

  // If explicit generation_time is in result
  const res = props.task.result
  if (res && (res.generation_time || res.duration || res.time)) {
    const secs = Number(res.generation_time || res.time || res.duration)
    if (!isNaN(secs) && secs > 0) {
      return `${secs.toFixed(2)} saniye`
    }
  }

  // Calculate difference between started_at and completed_at
  if (props.task.started_at && props.task.completed_at) {
    const start = new Date(props.task.started_at).getTime()
    const end = new Date(props.task.completed_at).getTime()
    const diffSecs = (end - start) / 1000
    if (diffSecs > 0 && diffSecs < 600) {
      return `${diffSecs.toFixed(2)} sn`
    }
  }

  // Fallback to formatted completed_at or created_at
  const dateStr = props.task.completed_at || props.task.created_at
  if (dateStr) {
    try {
      const d = new Date(dateStr)
      return d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    } catch (e) {
      return dateStr
    }
  }

  return 'Tamamlandı'
})

// Progress percentage
const progressPercent = computed(() => {
  if (!duration.value || duration.value === 0) return 0
  return Math.min(100, Math.max(0, (currentTime.value / duration.value) * 100))
})

// Time formatter
const formatSeconds = (sec) => {
  if (!sec || isNaN(sec) || sec < 0) return '00:00'
  const m = Math.floor(sec / 60)
  const s = Math.floor(sec % 60)
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

// Waveform visualizer height simulation
const getWaveHeight = (index) => {
  const heights = ['14px', '22px', '8px', '26px', '18px', '10px', '24px', '16px', '30px', '12px']
  return heights[index % heights.length]
}

// Audio events
const onLoadedMetadata = () => {
  if (audioRef.value) {
    duration.value = audioRef.value.duration || 0
  }
}

const onTimeUpdate = () => {
  if (!isSeeking.value && audioRef.value) {
    currentTime.value = audioRef.value.currentTime
  }
}

const onEnded = () => {
  isPlaying.value = false
  currentTime.value = 0
  if (props.hasNext) {
    emit('next')
  }
}

// Playback controls
const togglePlay = () => {
  if (!audioRef.value) return
  if (isPlaying.value) {
    audioRef.value.pause()
  } else {
    audioRef.value.play().catch(e => console.warn('Play error:', e))
  }
}

const skip = (seconds) => {
  if (!audioRef.value) return
  const newTime = Math.min(duration.value, Math.max(0, audioRef.value.currentTime + seconds))
  audioRef.value.currentTime = newTime
  currentTime.value = newTime
}

const setPlaybackRate = (rate) => {
  playbackRate.value = rate
  if (audioRef.value) {
    audioRef.value.playbackRate = rate
  }
}

const toggleMute = () => {
  if (!audioRef.value) return
  isMuted.value = !isMuted.value
  audioRef.value.muted = isMuted.value
}

const onVolumeChange = () => {
  if (!audioRef.value) return
  audioRef.value.volume = volume.value
  isMuted.value = volume.value === 0
}

// Seeking on timeline
const seekClick = (event) => {
  if (!timelineRef.value || !audioRef.value || !duration.value) return
  const rect = timelineRef.value.getBoundingClientRect()
  const clickX = event.clientX - rect.left
  const ratio = Math.max(0, Math.min(1, clickX / rect.width))
  const newTime = ratio * duration.value
  audioRef.value.currentTime = newTime
  currentTime.value = newTime
}

const startSeeking = (event) => {
  isSeeking.value = true
  seekClick(event)

  const onMouseMove = (e) => {
    if (!isSeeking.value || !timelineRef.value || !duration.value) return
    const rect = timelineRef.value.getBoundingClientRect()
    const clickX = e.clientX - rect.left
    const ratio = Math.max(0, Math.min(1, clickX / rect.width))
    currentTime.value = ratio * duration.value
  }

  const onMouseUp = (e) => {
    if (isSeeking.value) {
      isSeeking.value = false
      if (audioRef.value) {
        audioRef.value.currentTime = currentTime.value
      }
      window.removeEventListener('mousemove', onMouseMove)
      window.removeEventListener('mouseup', onMouseUp)
    }
  }

  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)
}

// Copy text to clipboard
const copyText = async () => {
  if (!speechText.value) return
  try {
    await navigator.clipboard.writeText(speechText.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch (err) {
    console.error('Kopyalama hatası:', err)
  }
}

// Keyboard shortcuts
const handleKeyDown = (e) => {
  if (!props.show) return
  if (e.code === 'Space' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
    e.preventDefault()
    togglePlay()
  } else if (e.code === 'Escape') {
    close()
  } else if (e.shiftKey && e.code === 'ArrowLeft') {
    e.preventDefault()
    goToPrev()
  } else if (e.shiftKey && e.code === 'ArrowRight') {
    e.preventDefault()
    goToNext()
  } else if (e.code === 'ArrowLeft') {
    skip(-5)
  } else if (e.code === 'ArrowRight') {
    skip(5)
  }
}

const close = () => {
  if (audioRef.value) {
    audioRef.value.pause()
    audioRef.value.currentTime = 0
  }
  isPlaying.value = false
  emit('close')
}

// When audio source changes while modal is open (e.g. task switched), reset and play new audio
watch(() => resolvedAudioUrl.value, (newUrl) => {
  if (newUrl && props.show) {
    currentTime.value = 0
    duration.value = 0
    setTimeout(() => {
      if (audioRef.value) {
        audioRef.value.load()
        audioRef.value.playbackRate = playbackRate.value
        audioRef.value.play().then(() => {
          isPlaying.value = true
        }).catch(err => {
          console.log('Audio playback switch error:', err)
        })
      }
    }, 100)
  }
})

// When modal opens, auto-play audio
watch(() => props.show, (newVal) => {
  if (newVal) {
    currentTime.value = 0
    // Small delay to ensure audio element source is ready
    setTimeout(() => {
      if (audioRef.value) {
        audioRef.value.playbackRate = playbackRate.value
        audioRef.value.play().then(() => {
          isPlaying.value = true
        }).catch(err => {
          console.log('Autoplay blocked or pending interaction:', err)
        })
      }
    }, 150)
  } else {
    if (audioRef.value) {
      audioRef.value.pause()
    }
    isPlaying.value = false
  }
})

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
  if (audioRef.value) {
    audioRef.value.pause()
  }
})
</script>

<style scoped>
@keyframes wave {
  0%, 100% {
    transform: scaleY(0.4);
  }
  50% {
    transform: scaleY(1.3);
  }
}

.animate-wave {
  animation: wave 0.8s ease-in-out infinite;
  transform-origin: bottom;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.animate-fade-in {
  animation: fadeIn 0.2s ease-out forwards;
}

@keyframes scaleUp {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.animate-scale-up {
  animation: scaleUp 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
