<template>
  <AppLayout title="Sesten Metne (STT)">
    <div class="space-y-6 max-w-4xl">
      <!-- Upload Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
        <h2 class="text-base font-semibold text-neutral-100">Ses Dosyası Deşifre Et (Whisper)</h2>

        <form @submit.prevent="submit" class="space-y-4">
          <!-- File Input -->
          <div>
            <label class="block text-xs font-medium text-neutral-400 mb-1.5">Ses Dosyası (WAV, MP3, M4A, OGG)</label>
            <input
              type="file"
              accept="audio/*"
              @change="handleFileChange"
              ref="fileInputRef"
              class="block w-full text-sm text-neutral-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-accent file:text-bg hover:file:opacity-90 cursor-pointer bg-neutral-900 rounded-xl border border-neutral-700 p-2"
            />
          </div>

          <div class="flex flex-wrap items-center gap-4">
            <!-- Language -->
            <div class="w-56">
              <label class="block text-xs font-medium text-neutral-400 mb-1.5">Konuşulan Dil</label>
              <select
                v-model="form.language"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
              >
                <option value="auto">🌐 Otomatik Algıla</option>
                <option v-for="lang in VOICE_LANGUAGES" :key="lang.code" :value="lang.code">
                  {{ lang.flag }} {{ lang.name }} ({{ lang.code }})
                </option>
              </select>
            </div>

            <!-- Model & Engine Selector -->
            <div class="w-72">
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-medium text-neutral-400">STT Modeli & Motor</label>
                <span v-if="isSelectedModelReady" class="text-[10px] text-emerald-400 font-semibold flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Hazır
                </span>
                <span v-else class="text-[10px] text-amber-400 font-semibold flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Anahtar Gerekli
                </span>
              </div>
              <select
                v-model="selectedModelKey"
                @change="onModelChange"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
              >
                <optgroup label="── ☁️ Bulut API Modelleri (0 MB İndirme) ──">
                  <option value="groq-whisper">⚡ Groq Whisper Large v3 (Bulut - Ultra Hızlı &lt;1s)</option>
                  <option value="openai-whisper">☁️ OpenAI Whisper (whisper-1 Bulut)</option>
                  <option value="google-cloud-stt">☁️ Google Cloud STT (Chirp v2 Bulut)</option>
                </optgroup>
                <optgroup label="── 💾 Yerel Modeller (Faster Whisper) ──">
                  <option value="whisper-medium">Faster Whisper Medium (Lokal - Önerilen)</option>
                  <option value="whisper-small">Faster Whisper Small (Lokal - Dengeli)</option>
                  <option value="whisper-base">Faster Whisper Base (Lokal - Hızlı)</option>
                  <option value="whisper-tiny">Faster Whisper Tiny (Lokal - Ultra Hızlı)</option>
                  <option value="whisper-large-v3">Faster Whisper Large V3 (Lokal - En Yüksek)</option>
                </optgroup>
              </select>
            </div>
          </div>

          <!-- Cloud API Key Warning Banner if selected cloud model lacks API key -->
          <div v-if="!isSelectedModelReady" class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
              <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div>
                <div class="font-semibold text-amber-200">
                  {{ getProviderDisplayName(selectedModelInfo?.cloud_provider) }} API Anahtarı Gerekli
                </div>
                <div class="text-[11px] text-amber-300/90 mt-0.5">
                  <strong>{{ selectedModelInfo?.name || selectedModelKey }}</strong> modelini kullanabilmek için Model Yöneticisi'nden API anahtarınızı tanımlamalısınız.
                </div>
              </div>
            </div>
            <a href="/models" class="px-3.5 py-1.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 font-medium text-xs whitespace-nowrap transition-colors flex items-center gap-1.5 self-start sm:self-auto cursor-pointer">
              <span>API Anahtarı Tanımla</span>
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
              </svg>
            </a>
          </div>

          <div v-if="fileError || (form.errors.audio && !recordedBlob)" class="p-3 rounded-xl bg-danger-500/10 border border-danger-500/20 text-xs text-danger-400">
            {{ fileError || form.errors.audio }}
          </div>

          <div class="flex justify-end pt-2">
            <button
              type="submit"
              :disabled="form.processing || !form.audio || !isSelectedModelReady"
              class="px-6 py-2.5 rounded-xl font-semibold text-sm bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2 cursor-pointer"
            >
              <span v-if="form.processing && !recordedBlob" class="w-4 h-4 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
              <span v-if="!isSelectedModelReady">API Anahtarı Gerekli</span>
              <span v-else>{{ (form.processing && !recordedBlob) ? 'Yükleniyor & Kuyruğa Alınıyor...' : 'Deşifre Et' }}</span>
            </button>
          </div>
        </form>
      </div>

      <!-- ─── Microphone Recording Card ─── -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center"
               :class="isRecording ? 'bg-danger-500/20' : 'bg-accent/20'">
            <svg class="w-5 h-5" :class="isRecording ? 'text-danger-500' : 'text-accent'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-14 0m7 7v4m-4 0h8m-4-18a3 3 0 00-3 3v4a3 3 0 006 0V7a3 3 0 00-3-3z" />
            </svg>
          </div>
          <div>
            <h2 class="text-base font-semibold text-neutral-100">Mikrofon ile Kayıt</h2>
            <p class="text-xs text-neutral-500 mt-0.5">Tarayıcı mikrofonu kullanarak ses kaydı yapabilirsiniz.</p>
          </div>
        </div>

        <!-- Waveform & Timer -->
        <div v-if="isRecording" class="flex items-center gap-4 p-4 rounded-xl bg-neutral-900/80 border border-danger-500/30">
          <!-- Live waveform bars -->
          <div class="flex items-center gap-[3px] h-10">
            <div v-for="i in 16" :key="i"
                 class="w-1 bg-danger-500 rounded-full animate-waveform"
                 :style="{ animationDelay: (i * 0.07) + 's', height: '6px' }"></div>
          </div>
          <div class="flex-1 flex items-center gap-3">
            <div class="w-2.5 h-2.5 rounded-full bg-danger-500 animate-pulse"></div>
            <span class="text-sm font-mono text-danger-400">{{ formatTime(recordingDuration) }}</span>
          </div>
        </div>

        <!-- Recorded audio preview -->
        <div v-if="recordedBlob && !isRecording" class="flex items-center gap-4 p-4 rounded-xl bg-neutral-900/80 border border-neutral-700">
          <audio controls class="h-9 flex-1" :src="recordedUrl"></audio>
          <button @click="discardRecording" class="text-xs px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300"
                  title="Kaydı sil">
            İptal
          </button>
        </div>

        <!-- Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <button
              v-if="!isRecording"
              @click="startRecording"
              :disabled="!micSupported"
              class="px-5 py-2.5 rounded-xl font-semibold text-sm bg-danger-500/90 text-white hover:bg-danger-500 transition-colors disabled:opacity-40 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="6"/></svg>
              <span>Kaydı Başlat</span>
            </button>
            <button
              v-else
              @click="stopRecording"
              class="px-5 py-2.5 rounded-xl font-semibold text-sm bg-neutral-700 text-neutral-100 hover:bg-neutral-600 transition-colors flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
              <span>Kaydı Durdur</span>
            </button>

            <button
              v-if="recordedBlob && !isRecording"
              @click="submitRecording"
              :disabled="form.processing"
              class="px-5 py-2.5 rounded-xl font-semibold text-sm bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2"
            >
              <span v-if="form.processing && isSubmittingMic" class="w-4 h-4 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
              <span>{{ (form.processing && isSubmittingMic) ? 'Gönderiliyor...' : 'Deşifre Et' }}</span>
            </button>

            <span v-if="!micSupported" class="text-xs text-danger-400">Tarayıcınız mikrofon erişimini desteklemiyor.</span>
          </div>

          <div v-if="micError" class="p-3 rounded-xl bg-danger-500/10 border border-danger-500/20 text-xs text-danger-400">
            {{ micError }}
          </div>

          <!-- Microphone Selection Dropdown -->
          <div v-if="!isRecording" class="flex items-center gap-2">
            <label class="text-xs text-neutral-400 flex items-center gap-1">
              <svg class="w-3.5 h-3.5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
              </svg>
              <span>Mikrofon:</span>
            </label>
            <select
              v-model="selectedAudioDeviceId"
              class="text-xs py-1.5 px-3 rounded-xl bg-neutral-900 border border-neutral-700 text-neutral-200 focus:outline-none focus:border-accent max-w-xs"
            >
              <option value="">Varsayılan Mikrofon</option>
              <option v-for="(dev, idx) in audioInputDevices" :key="dev.deviceId || idx" :value="dev.deviceId">
                {{ dev.label || `Mikrofon ${idx + 1}` }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- STT History & Transcripts -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <h3 class="text-sm font-semibold text-neutral-200">Deşifre Geçmişi</h3>
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
            Henüz deşifre edilmiş bir ses bulunmuyor.
          </div>
          <div v-for="t in tasksList" :key="t.id" class="py-4 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="font-medium text-sm text-neutral-200 truncate">
                  {{ t.payload?.original_name || t.payload?.filename || 'Ses Dosyası' }}
                </div>
                <div class="flex items-center gap-2 text-xs text-neutral-500 mt-1">
                  <span>{{ formatDateTime(t.created_at) }}</span>
                  <span>·</span>
                  <span class="px-1.5 py-0.5 rounded bg-neutral-900 border border-neutral-800 text-neutral-300 uppercase text-[11px]">
                    {{ t.payload?.language || 'auto' }}
                  </span>
                  <span v-if="t.payload?.engine && t.payload.engine !== 'whisper'" class="px-1.5 py-0.5 rounded bg-purple-500/10 border border-purple-500/20 text-purple-300 text-[11px] font-mono">
                    ☁️ {{ t.payload.engine }}
                  </span>
                  <span v-else-if="t.payload?.model_size" class="px-1.5 py-0.5 rounded bg-neutral-900 border border-neutral-800 text-neutral-300 text-[11px] font-mono">
                    {{ t.payload.model_size }}
                  </span>
                </div>
              </div>

              <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
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
                  <span>Deşifre Ediliyor...</span>
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

                <!-- Copy Transcript Button -->
                <button
                  v-if="t.status === 'completed' && t.result?.text"
                  @click="copyToClipboard(t.id, t.result.text)"
                  class="text-xs px-2.5 py-1 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 transition-colors flex items-center gap-1.5"
                >
                  <span v-if="copySuccessId === t.id" class="text-emerald-400 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    Kopyalandı!
                  </span>
                  <span v-else class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                    </svg>
                    Metni Kopyala
                  </span>
                </button>

                <!-- Retry Button (Farklı Model ile Yeniden Deşifre Et / Yeniden Dene) -->
                <button
                  v-if="t.status === 'completed' || t.status === 'failed'"
                  @click="openRetryModal(t)"
                  class="p-1.5 rounded-lg transition-colors"
                  :class="t.status === 'completed' 
                    ? 'bg-neutral-800 hover:bg-cyan-500/20 text-neutral-400 hover:text-cyan-300' 
                    : 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 hover:text-amber-300'"
                  :title="t.status === 'completed' ? 'Farklı Whisper Modeli ile Yeniden Deşifre Et' : 'Farklı Model ile Yeniden Dene'"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                </button>

                <!-- Delete Button -->
                <button
                  @click="deleteTask(t.id)"
                  class="p-1.5 rounded-lg bg-neutral-800/60 hover:bg-red-500/20 text-neutral-400 hover:text-red-400 transition-colors"
                  title="İşlemi Sil"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Transcript Output Box -->
            <div v-if="t.status === 'completed' && t.result?.text"
                 class="p-4 rounded-xl bg-neutral-900 border border-neutral-800 text-sm text-neutral-200 leading-relaxed select-text">
              {{ t.result.text }}
            </div>

            <!-- Error Box -->
            <div v-else-if="t.status === 'failed' && t.error_message"
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

    <!-- Retry Task Modal -->
    <RetryTaskModal
      :show="showRetryModal"
      :task="selectedRetryTask"
      :available-models="models"
      @close="showRetryModal = false"
      @retried="onTaskRetried"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import RetryTaskModal from '../../Components/RetryTaskModal.vue'
import { VOICE_LANGUAGES } from '../../i18n'
import { useAudioDevices } from '../../Composables/useAudioDevices'

const { audioInputDevices, selectedAudioDeviceId, loadAudioDevices } = useAudioDevices()

const props = defineProps({
  tasks: Array,
  default_model: String,
  models: Array,
})

const tasksList = ref(props.tasks ? [...props.tasks] : [])
const isRefreshing = ref(false)
const copySuccessId = ref(null)

const showRetryModal = ref(false)
const selectedRetryTask = ref(null)

const selectedModelKey = ref(props.default_model || 'whisper-medium')

const isCloudStt = computed(() => {
  return ['groq-whisper', 'openai-whisper', 'google-cloud-stt'].includes(selectedModelKey.value)
})

const selectedModelInfo = computed(() => {
  return (props.models || []).find(m => m.id === selectedModelKey.value)
})

const isSelectedModelReady = computed(() => {
  if (!isCloudStt.value) return true
  return selectedModelInfo.value ? Boolean(selectedModelInfo.value.is_downloaded) : true
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

const onModelChange = () => {
  if (isCloudStt.value) {
    form.engine = selectedModelKey.value
    form.model_size = selectedModelKey.value
  } else {
    form.engine = 'whisper'
    form.model_size = selectedModelKey.value
  }
}

const openRetryModal = (task) => {
  selectedRetryTask.value = task
  showRetryModal.value = true
}

const onTaskRetried = async () => {
  await fetchTasks(true)
}

const fileInputRef = ref(null)

const form = useForm({
  audio: null,
  language: 'tr',
  model_size: props.default_model || 'whisper-medium',
  engine: ['groq-whisper', 'openai-whisper', 'google-cloud-stt'].includes(props.default_model) ? props.default_model : 'whisper',
})

const hasActiveTasks = computed(() => {
  return tasksList.value.some(t => t.status === 'pending' || t.status === 'running')
})

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
    const res = await fetch('/api/tasks/stt')
    if (res.ok) {
      const data = await res.json()
      tasksList.value = data
    }
  } catch (err) {
    console.warn('STT görevleri güncellenirken hata:', err)
  } finally {
    if (showLoading) isRefreshing.value = false
  }
}

const deleteTask = async (id) => {
  if (!confirm('Bu deşifre kaydını silmek istediğinize emin misiniz?')) {
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

const copyToClipboard = (taskId, text) => {
  navigator.clipboard.writeText(text)
  copySuccessId.value = taskId
  setTimeout(() => {
    if (copySuccessId.value === taskId) {
      copySuccessId.value = null
    }
  }, 2000)
}

const fileError = ref(null)

const handleFileChange = (e) => {
  form.audio = e.target.files[0]
  fileError.value = null
}

const submit = () => {
  fileError.value = null
  form.post('/stt/transcribe', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('audio')
      if (fileInputRef.value) fileInputRef.value.value = ''
      fileError.value = null
      window.dispatchEvent(new CustomEvent('voice-task-created'))
      fetchTasks()
      setTimeout(() => fetchTasks(), 600)
      setTimeout(() => fetchTasks(), 1600)
    },
    onError: (errors) => {
      console.error('STT dosya yükleme hatası:', errors)
      fileError.value = errors.audio || Object.values(errors)[0] || 'Dosya gönderilirken bir hata oluştu.'
    }
  })
}

/* ── Live Adaptive Polling ── */
let pollTimer = null

const runAdaptivePoll = async () => {
  await fetchTasks()
  const nextInterval = hasActiveTasks.value ? 2000 : 5000
  pollTimer = setTimeout(runAdaptivePoll, nextInterval)
}

onMounted(() => {
  pollTimer = setTimeout(runAdaptivePoll, 2500)
  loadAudioDevices()
  if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
    navigator.mediaDevices.addEventListener('devicechange', loadAudioDevices)
  }
})

onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
  if (navigator.mediaDevices && navigator.mediaDevices.removeEventListener) {
    navigator.mediaDevices.removeEventListener('devicechange', loadAudioDevices)
  }
})

/* ── Microphone Recording ── */
const micSupported = ref(!!navigator.mediaDevices?.getUserMedia)
const isRecording = ref(false)
const recordedBlob = ref(null)
const recordedUrl = ref(null)
const recordingDuration = ref(0)

let mediaRecorder = null
let audioChunks = []
let durationTimer = null
const micError = ref(null)
const isSubmittingMic = ref(false)

// Downsample PCM buffer to target rate (e.g. 48000/44100 -> 16000)
function downsampleBuffer(buffer, sampleRate, outSampleRate = 16000) {
  if (outSampleRate === sampleRate) return buffer
  if (outSampleRate > sampleRate) return buffer
  const sampleRateRatio = sampleRate / outSampleRate
  const newLength = Math.round(buffer.length / sampleRateRatio)
  const result = new Float32Array(newLength)
  let offsetResult = 0
  let offsetBuffer = 0
  while (offsetResult < result.length) {
    const nextOffsetBuffer = Math.round((offsetResult + 1) * sampleRateRatio)
    let accum = 0
    let count = 0
    for (let i = offsetBuffer; i < nextOffsetBuffer && i < buffer.length; i++) {
      accum += buffer[i]
      count++
    }
    result[offsetResult] = count > 0 ? accum / count : 0
    offsetResult++
    offsetBuffer = nextOffsetBuffer
  }
  return result
}

// Encode Float32Array to 16-bit PCM mono WAV ArrayBuffer
function encodeWav(samples, sampleRate = 16000) {
  const buffer = new ArrayBuffer(44 + samples.length * 2)
  const view = new DataView(buffer)

  const writeString = (v, offset, str) => {
    for (let i = 0; i < str.length; i++) {
      v.setUint8(offset + i, str.charCodeAt(i))
    }
  }

  // RIFF identifier
  writeString(view, 0, 'RIFF')
  view.setUint32(4, 36 + samples.length * 2, true)
  writeString(view, 8, 'WAVE')

  // fmt chunk
  writeString(view, 12, 'fmt ')
  view.setUint32(16, 16, true)
  view.setUint16(20, 1, true) // PCM format
  view.setUint16(22, 1, true) // Mono
  view.setUint32(24, sampleRate, true)
  view.setUint32(28, sampleRate * 2, true) // byte rate (sampleRate * 1 * 16 / 8)
  view.setUint16(32, 2, true) // block align (1 * 16 / 8)
  view.setUint16(34, 16, true) // 16-bit

  // data chunk
  writeString(view, 36, 'data')
  view.setUint32(40, samples.length * 2, true)

  // 16-bit PCM samples with clipping protection
  let offset = 44
  for (let i = 0; i < samples.length; i++) {
    const s = Math.max(-1, Math.min(1, samples[i]))
    const val = s < 0 ? s * 0x8000 : s * 0x7FFF
    view.setInt16(offset, val, true)
    offset += 2
  }

  return buffer
}

const formatTime = (seconds) => {
  const m = Math.floor(seconds / 60).toString().padStart(2, '0')
  const s = (seconds % 60).toString().padStart(2, '0')
  return `${m}:${s}`
}

const startRecording = async () => {
  try {
    const audioConstraints = selectedAudioDeviceId.value
      ? { deviceId: { exact: selectedAudioDeviceId.value } }
      : true
    const stream = await navigator.mediaDevices.getUserMedia({ audio: audioConstraints })
    audioChunks = []
    recordedBlob.value = null
    recordedUrl.value = null
    recordingDuration.value = 0
    micError.value = null

    // Prefer webm-opus but fall back to whatever is supported
    const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
      ? 'audio/webm;codecs=opus'
      : MediaRecorder.isTypeSupported('audio/webm')
        ? 'audio/webm'
        : ''

    mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : {})

    mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) audioChunks.push(e.data)
    }

    mediaRecorder.onstop = async () => {
      const rawBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType || 'audio/webm' })
      try {
        const arrayBuffer = await rawBlob.arrayBuffer()
        const AudioContextClass = window.AudioContext || window.webkitAudioContext
        const audioCtx = new AudioContextClass()
        const decodedBuffer = await audioCtx.decodeAudioData(arrayBuffer)

        // Mix down multi-channel to mono
        const numChannels = decodedBuffer.numberOfChannels
        const length = decodedBuffer.length
        const monoSamples = new Float32Array(length)
        for (let i = 0; i < numChannels; i++) {
          const channelData = decodedBuffer.getChannelData(i)
          for (let j = 0; j < length; j++) {
            monoSamples[j] += channelData[j] / numChannels
          }
        }

        // Downsample to 16kHz for Whisper
        const downsampled = downsampleBuffer(monoSamples, decodedBuffer.sampleRate, 16000)
        const wavBuffer = encodeWav(downsampled, 16000)
        const wavBlob = new Blob([wavBuffer], { type: 'audio/wav' })

        recordedBlob.value = wavBlob
        recordedUrl.value = URL.createObjectURL(wavBlob)
        audioCtx.close()
      } catch (convErr) {
        console.warn('WAV dönüştürme atlandı, orijinal blob kullanılıyor:', convErr)
        recordedBlob.value = rawBlob
        recordedUrl.value = URL.createObjectURL(rawBlob)
      }

      // Stop all tracks
      stream.getTracks().forEach(tr => tr.stop())
    }

    mediaRecorder.start(250) // collect chunks every 250ms
    isRecording.value = true

    durationTimer = setInterval(() => {
      recordingDuration.value++
    }, 1000)
  } catch (err) {
    console.error('Mikrofon erişimi hatası:', err)
    alert('Mikrofon erişimi sağlanamadı. Lütfen tarayıcı izinlerini kontrol edin.')
  }
}

const stopRecording = () => {
  if (mediaRecorder && mediaRecorder.state !== 'inactive') {
    mediaRecorder.stop()
  }
  isRecording.value = false
  if (durationTimer) {
    clearInterval(durationTimer)
    durationTimer = null
  }
}

const discardRecording = () => {
  if (recordedUrl.value) URL.revokeObjectURL(recordedUrl.value)
  recordedBlob.value = null
  recordedUrl.value = null
  recordingDuration.value = 0
  micError.value = null
}

const submitRecording = () => {
  if (!recordedBlob.value) return
  micError.value = null
  isSubmittingMic.value = true

  const isWav = recordedBlob.value.type.includes('wav')
  const ext = isWav ? 'wav' : (recordedBlob.value.type.includes('webm') ? 'webm' : 'wav')
  const file = new File([recordedBlob.value], `mic_recording_${Date.now()}.${ext}`, {
    type: recordedBlob.value.type || 'audio/wav',
  })

  form.audio = file
  form.post('/stt/transcribe', {
    preserveScroll: true,
    onSuccess: () => {
      discardRecording()
      form.reset('audio')
      micError.value = null
      window.dispatchEvent(new CustomEvent('voice-task-created'))
      fetchTasks()
      setTimeout(() => fetchTasks(), 600)
      setTimeout(() => fetchTasks(), 1600)
    },
    onError: (errors) => {
      console.error('STT kayıt gönderim hatası:', errors)
      micError.value = errors.audio || Object.values(errors)[0] || 'Kayıt gönderilirken bir hata oluştu.'
    },
    onFinish: () => {
      isSubmittingMic.value = false
    }
  })
}
</script>

<style scoped>
@keyframes waveform {
  0%, 100% { height: 6px; }
  50% { height: 28px; }
}
.animate-waveform {
  animation: waveform 0.6s ease-in-out infinite alternate;
}
</style>
