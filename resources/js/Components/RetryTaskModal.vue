<template>
  <Teleport to="body">
    <div
      v-if="show && task"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
      @keydown.esc="close"
      tabindex="-1"
    >
      <!-- Backdrop with blur -->
      <div
        class="fixed inset-0 bg-black/80 backdrop-blur-md transition-opacity animate-fade-in"
        @click="close"
      ></div>

      <!-- Modal Card -->
      <div
        class="relative w-full max-w-2xl bg-surface border border-neutral-800 rounded-3xl shadow-2xl overflow-hidden z-10 my-auto animate-scale-up"
        @click.stop
      >
        <!-- Top Glow Accent -->
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r"
             :class="task.status === 'completed' ? 'from-accent via-cyan-400 to-emerald-400' : 'from-amber-500 via-orange-400 to-accent'"></div>

        <!-- Header -->
        <div class="p-6 border-b border-neutral-800/80 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl flex items-center justify-center"
                 :class="task.status === 'completed' ? 'bg-accent/15 border border-accent/30 text-accent' : 'bg-amber-500/15 border border-amber-500/30 text-amber-400'">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-neutral-100 flex items-center gap-2">
                <span>{{ task.status === 'completed' ? 'Farklı Model ile Yeniden Üret' : 'İşlemi Yeniden Dene' }}</span>
                <span class="text-xs font-mono font-normal text-neutral-500">#{{ task.id }}</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                      :class="task.type === 'tts' ? 'bg-accent/20 text-accent-300' : 'bg-cyan-500/20 text-cyan-400'">
                  {{ task.type }}
                </span>
              </h3>
              <p class="text-xs text-neutral-400 mt-0.5">
                {{ task.status === 'completed' ? 'Mevcut içeriği farklı bir model veya ses profili seçerek yeniden üretin.' : 'Model veya ayarları değiştirerek görevi tekrar kuyruğa alabilirsiniz.' }}
              </p>
            </div>
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

        <!-- Body -->
        <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">
          <!-- Current Task Info Card -->
          <div class="p-4 rounded-2xl bg-neutral-900/90 border border-neutral-800/90 space-y-2.5">
            <div class="text-xs font-medium text-neutral-400">İşlem Özeti</div>
            <div class="text-sm font-medium text-neutral-100 line-clamp-2">
              {{ task.payload?.text || task.payload?.original_name || task.payload?.filename || 'İsimsiz Görev' }}
            </div>
            <div class="flex flex-wrap items-center gap-2 text-[11px] text-neutral-400">
              <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-300">
                Önceki Motor: <strong class="text-neutral-100">{{ task.payload?.engine || 'Belirtilmemiş' }}</strong>
              </span>
              <span v-if="task.payload?.language" class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-300">
                Dil: <strong class="text-neutral-100">{{ task.payload.language }}</strong>
              </span>
              <span v-if="task.payload?.model_size" class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-300">
                Boyut: <strong class="text-neutral-100">{{ task.payload.model_size }}</strong>
              </span>
            </div>

            <!-- Error Banner -->
            <div v-if="task.error_message" class="mt-2 p-2.5 rounded-xl bg-red-950/40 border border-red-900/40 text-xs text-red-300 flex items-start gap-2">
              <svg class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div class="overflow-hidden">
                <div class="font-semibold text-red-200 text-[11px]">Son Hata Nedeni:</div>
                <div class="font-mono text-[10px] text-red-300/90 break-words mt-0.5 line-clamp-3 hover:line-clamp-none transition-all cursor-pointer"
                     title="Detayı görüntülemek için tıklayın">
                  {{ task.error_message }}
                </div>
              </div>
            </div>
          </div>

          <!-- Configuration Form -->
          <form @submit.prevent="submitRetry" class="space-y-4">
            <!-- ─── TTS Models Selection ─── -->
            <div v-if="task.type === 'tts'" class="space-y-2">
              <label class="block text-xs font-semibold text-neutral-300">
                Yeni TTS Motoru Seçin
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div
                  v-for="m in ttsModels"
                  :key="m.id"
                  @click="selectedEngine = m.id"
                  :class="[
                    'p-3.5 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between gap-2',
                    selectedEngine === m.id
                      ? 'bg-accent/10 border-accent text-neutral-100 shadow-sm ring-1 ring-accent/40'
                      : 'bg-neutral-900/60 border-neutral-800 hover:border-neutral-700 text-neutral-300 hover:bg-neutral-900'
                  ]"
                >
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold text-xs text-neutral-100">{{ m.name }}</span>
                    <span
                      v-if="m.is_downloaded"
                      class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                      Hazır
                    </span>
                    <span
                      v-else
                      class="text-[10px] text-neutral-500 bg-neutral-800/80 px-2 py-0.5 rounded-full"
                    >
                      İndirilmedi
                    </span>
                  </div>
                  <p class="text-[11px] text-neutral-400 line-clamp-2 leading-tight">
                    {{ m.description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- ─── STT Models Selection ─── -->
            <div v-if="task.type === 'stt'" class="space-y-2">
              <label class="block text-xs font-semibold text-neutral-300">
                Whisper Model Boyutu Seçin
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div
                  v-for="s in whisperSizes"
                  :key="s.id"
                  @click="selectedModelSize = s.id"
                  :class="[
                    'p-3.5 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between gap-2',
                    selectedModelSize === s.id
                      ? 'bg-cyan-500/10 border-cyan-500 text-neutral-100 ring-1 ring-cyan-500/40'
                      : 'bg-neutral-900/60 border-neutral-800 hover:border-neutral-700 text-neutral-300 hover:bg-neutral-900'
                  ]"
                >
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold text-xs text-neutral-100">{{ s.name }}</span>
                    <span
                      v-if="s.is_downloaded"
                      class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                      İndirildi
                    </span>
                    <span
                      v-else
                      class="text-[10px] text-neutral-500 bg-neutral-800/80 px-2 py-0.5 rounded-full"
                    >
                      ~{{ s.size }}
                    </span>
                  </div>
                  <p class="text-[11px] text-neutral-400 leading-tight">
                    {{ s.desc }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Voice Profile Selection (Only for XTTS) -->
            <div v-if="task.type === 'tts' && selectedEngine === 'xtts-v2'" class="space-y-1.5">
              <label class="block text-xs font-medium text-neutral-400">Ses Profili (Opsiyonel)</label>
              <select
                v-model="selectedProfilePath"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              >
                <option :value="null">Varsayılan Ses (Profilsiz)</option>
                <option v-for="p in profiles" :key="p.id" :value="p.file_path">
                  {{ p.name }} ({{ p.language || 'tr' }})
                </option>
              </select>
            </div>

            <!-- Language Selection -->
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-neutral-400">İşlem Dili</label>
              <select
                v-model="selectedLanguage"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              >
                <option v-if="task.type === 'stt'" value="auto">🌐 Otomatik Algıla</option>
                <option v-for="lang in VOICE_LANGUAGES" :key="lang.code" :value="lang.code">
                  {{ lang.flag }} {{ lang.name }} ({{ lang.code }})
                </option>
              </select>
            </div>
          </form>
        </div>

        <!-- Footer -->
        <div class="p-6 border-t border-neutral-800/80 flex items-center justify-end gap-3 bg-surface">
          <button
            type="button"
            @click="close"
            :disabled="isSubmitting"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors disabled:opacity-50"
          >
            İptal
          </button>
          <button
            type="button"
            @click="submitRetry"
            :disabled="isSubmitting"
            class="px-5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all disabled:opacity-50 font-bold shadow-lg"
            :class="task.status === 'completed' 
              ? 'bg-accent text-bg hover:opacity-90 shadow-accent/10' 
              : 'bg-amber-500 hover:bg-amber-400 text-neutral-950 shadow-amber-500/10'"
          >
            <svg v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" :class="task.status === 'completed' ? 'text-bg' : 'text-neutral-950'">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ isSubmitting ? (task.status === 'completed' ? 'Üretim Başlatılıyor...' : 'Kuyruğa Ekleniyor...') : (task.status === 'completed' ? 'Farklı Model ile Yeniden Üret' : 'Farklı Model ile Yeniden Başlat') }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { VOICE_LANGUAGES } from '../i18n'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  task: {
    type: Object,
    default: null,
  },
  availableModels: {
    type: Array,
    default: () => [],
  },
  profiles: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['close', 'retried'])

const isSubmitting = ref(false)
const selectedEngine = ref('piper-tr')
const selectedModelSize = ref('whisper-medium')
const selectedLanguage = ref('tr')
const selectedProfilePath = ref(null)

// Standard TTS models fallback if availableModels not passed
const defaultTtsModels = [
  {
    id: 'piper-tr',
    name: 'Piper TTS (Turkish)',
    description: 'Ultra hızlı, hafif ve Türkçe için optimize TTS motoru.',
    is_downloaded: true,
  },
  {
    id: 'xtts-v2',
    name: 'Coqui XTTS v2',
    description: 'Yüksek kaliteli, çok dilli ve ses klonlama özellikli TTS.',
    is_downloaded: true,
  },
  {
    id: 'bark',
    name: 'Suno Bark (Small)',
    description: 'Gerçekçi, çok dilli konuşma ve müzik üretebilen model.',
    is_downloaded: true,
  },
  {
    id: 'tortoise',
    name: 'Tortoise TTS',
    description: 'Zengin ses tonlama motoru (İngilizce için en iyi).',
    is_downloaded: true,
  },
  {
    id: 'piper-en',
    name: 'Piper TTS (English)',
    description: 'İngilizce için optimize edilmiş hafif model.',
    is_downloaded: false,
  },
]

const ttsModels = computed(() => {
  if (props.availableModels && props.availableModels.length > 0) {
    const list = props.availableModels.filter(m => m.type === 'tts')
    if (list.length > 0) return list
  }
  return defaultTtsModels
})

const whisperSizes = computed(() => {
  const getIsDownloaded = (id, fallback) => {
    if (props.availableModels) {
      const found = props.availableModels.find(m => m.id === id)
      if (found) return !!found.is_downloaded
    }
    return fallback
  }

  return [
    {
      id: 'groq-whisper',
      name: '⚡ Groq Whisper Large v3 (Bulut)',
      desc: 'Groq LPU çipinde çalışan ultra hızlı (<1s) bulut deşifresi.',
      size: '0 MB',
      is_downloaded: getIsDownloaded('groq-whisper', false),
    },
    {
      id: 'openai-whisper',
      name: '☁️ OpenAI Whisper Cloud (Bulut)',
      desc: 'OpenAI whisper-1 resmi bulut servisi.',
      size: '0 MB',
      is_downloaded: getIsDownloaded('openai-whisper', false),
    },
    {
      id: 'google-cloud-stt',
      name: '☁️ Google Cloud STT Chirp v2 (Bulut)',
      desc: 'Google Cloud Speech-to-Text kurumsal deşifre.',
      size: '0 MB',
      is_downloaded: getIsDownloaded('google-cloud-stt', false),
    },
    {
      id: 'whisper-medium',
      name: 'Faster Whisper Medium (Lokal - Önerilen)',
      desc: 'Yüksek doğruluk ve mükemmel Türkçe/İngilizce deşifre performansı.',
      size: '1.5 GB',
      is_downloaded: getIsDownloaded('whisper-medium', true),
    },
    {
      id: 'whisper-small',
      name: 'Faster Whisper Small (Lokal)',
      desc: 'Hızlı ve dengeli doğruluk, düşük bellek kullanımı.',
      size: '1.0 GB',
      is_downloaded: getIsDownloaded('whisper-small', false),
    },
    {
      id: 'whisper-base',
      name: 'Faster Whisper Base (Lokal)',
      desc: 'Hızlı temel deşifre modeli.',
      size: '250 MB',
      is_downloaded: getIsDownloaded('whisper-base', false),
    },
    {
      id: 'whisper-tiny',
      name: 'Faster Whisper Tiny (Lokal)',
      desc: 'En düşük bellek tüketimi, ultra hızlı.',
      size: '150 MB',
      is_downloaded: getIsDownloaded('whisper-tiny', false),
    },
    {
      id: 'whisper-large-v3',
      name: 'Faster Whisper Large V3 (Lokal)',
      desc: 'En yüksek seviyede deşifre kalitesi, GPU önerilir.',
      size: '6.0 GB',
      is_downloaded: getIsDownloaded('whisper-large-v3', false),
    },
  ]
})

// Sync initial form values when modal opens
watch(
  () => props.show,
  (newVal) => {
    if (newVal && props.task) {
      const payload = props.task.payload || {}
      selectedLanguage.value = payload.language || (props.task.type === 'stt' ? 'auto' : 'tr')
      selectedProfilePath.value = payload.profile_path || null
      
      if (props.task.type === 'tts') {
        // Default to a different engine or the downloaded one
        const currentEngine = payload.engine
        const alternatives = ttsModels.value.filter(m => m.is_downloaded && m.id !== currentEngine)
        selectedEngine.value = alternatives.length > 0 ? alternatives[0].id : (currentEngine || 'piper-tr')
      } else if (props.task.type === 'stt') {
        if (payload.engine && payload.engine !== 'whisper') {
          selectedModelSize.value = payload.engine
        } else {
          selectedModelSize.value = payload.model_size || 'whisper-medium'
        }
      }
    }
  },
  { immediate: true }
)

const close = () => {
  if (isSubmitting.value) return
  emit('close')
}

const submitRetry = async () => {
  if (!props.task || isSubmitting.value) return

  isSubmitting.value = true

  const isCloudStt = ['groq-whisper', 'openai-whisper', 'google-cloud-stt'].includes(selectedModelSize.value)
  const data = {
    engine: props.task.type === 'tts' ? selectedEngine.value : (isCloudStt ? selectedModelSize.value : 'whisper'),
    model_size: props.task.type === 'stt' ? selectedModelSize.value : null,
    language: selectedLanguage.value,
    profile_path: selectedProfilePath.value,
  }

  try {
    const response = await axios.post(`/queue/${props.task.id}/retry`, data)
    emit('retried', response.data?.task || props.task)
    emit('close')
  } catch (err) {
    console.warn('Axios retry failed, falling back to Inertia router:', err)
    router.post(
      `/queue/${props.task.id}/retry`,
      data,
      {
        preserveScroll: true,
        onSuccess: () => {
          emit('retried', props.task)
          emit('close')
        },
        onFinish: () => {
          isSubmitting.value = false
        }
      }
    )
    return
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
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

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.animate-scale-up {
  animation: scaleUp 0.15s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-fade-in {
  animation: fadeIn 0.15s ease-out forwards;
}
</style>
