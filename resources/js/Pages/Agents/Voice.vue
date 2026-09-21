<template>
  <AppLayout :title="`Sesli Görüşme: ${agent.name}`">
    <div class="max-w-2xl mx-auto space-y-4 pb-12">
      <div class="flex items-center gap-3">
        <Link :href="`/agents/${agent.id}/edit`" class="p-2 rounded-xl hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 transition-colors shrink-0" title="Ajan düzenleyiciye dön">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </Link>
        <h1 class="text-sm font-semibold text-neutral-100">{{ agent.name }}</h1>
      </div>

      <div v-if="agent.response_mode !== 'audio'" class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300 leading-relaxed">
        Bu ajanın Cevap Tipi "Ses" değil, bu yüzden ajanın sesli cevabı oynatılamayacak — yalnızca yazılı cevap gösterilecek.
        <Link :href="`/agents/${agent.id}/edit`" class="underline hover:opacity-80">Düzenleyicide Cevap Tipi'ni "Ses" yapın.</Link>
      </div>
      <div v-if="!agent.is_active" class="p-3.5 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300 leading-relaxed">
        Bu ajan şu anda pasif. Görüşme yine de çalışır (test-run pasiflik kontrolü yapmaz) ama unutmayın.
      </div>

      <!-- Orb -->
      <div class="p-8 rounded-2xl bg-surface border border-neutral-800 flex flex-col items-center gap-5">
        <div class="relative w-40 h-40 flex items-center justify-center">
          <div
            class="absolute inset-0 rounded-full transition-all duration-150"
            :class="orbRingClass"
            :style="orbRingStyle"
          ></div>
          <div class="relative w-24 h-24 rounded-full flex items-center justify-center text-3xl select-none transition-colors duration-300" :class="orbCoreClass">
            {{ orbIcon }}
          </div>
        </div>

        <div class="text-xs font-medium" :class="stateColorClass">{{ stateLabel }}</div>

        <button
          type="button"
          @click="toggleSession"
          class="px-6 py-2.5 rounded-xl text-xs font-semibold transition-opacity hover:opacity-90 cursor-pointer"
          :class="running ? 'bg-red-500/90 text-white' : 'bg-accent text-bg'"
        >
          {{ running ? 'Görüşmeyi Bitir' : 'Görüşmeyi Başlat' }}
        </button>

        <p v-if="micError" class="text-[11px] text-red-400 text-center leading-relaxed">{{ micError }}</p>
        <p v-else class="text-[11px] text-neutral-500 text-center leading-relaxed max-w-sm">
          Konuşmaya başlayın, sessizliği algılayınca otomatik gönderilir. Ajan konuşurken tekrar konuşursanız oynatma kesilir (barge-in) ve söyledikleriniz yeni bir tur olarak alınır.
        </p>
      </div>

      <!-- Transcript -->
      <div v-if="turns.length" class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3">
        <h2 class="text-xs font-semibold text-neutral-200 uppercase tracking-wide">Konuşma</h2>
        <div class="space-y-2.5 max-h-96 overflow-y-auto">
          <div v-for="(turn, idx) in turns" :key="idx" class="flex" :class="turn.role === 'user' ? 'justify-end' : 'justify-start'">
            <div
              class="max-w-[80%] px-3.5 py-2 rounded-2xl text-xs leading-relaxed"
              :class="turn.role === 'user' ? 'bg-accent/15 text-accent-100 border border-accent/25' : 'bg-neutral-900 text-neutral-200 border border-neutral-800'"
            >
              {{ turn.text }}
              <span v-if="turn.error" class="block mt-1 text-[10px] text-red-400">{{ turn.error }}</span>
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
import AppLayout from '../../Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  agent: Object,
})
const agent = props.agent

// --- VAD tuning ---
const SPEECH_RMS_THRESHOLD = 0.035
const SPEECH_ONSET_MS = 200 // sustained energy above threshold before we call it "speech"
const SILENCE_HANGOVER_MS = 900 // sustained silence before we consider the utterance done
const MIN_UTTERANCE_MS = 350
const PRE_ROLL_CHUNKS = 3 // ~timeslice*3 of audio kept before speech onset so we don't clip the first syllable
const TIMESLICE_MS = 250

// state: idle | listening | recording | processing | speaking
const state = ref('idle')
const running = ref(false)
const micError = ref('')
const turns = ref([])
const micLevel = ref(0)

let audioCtx = null
let analyser = null
let dataArray = null
let mediaStream = null
let recorder = null
let rafId = null

let rollingChunks = [] // pre-roll buffer, always the last PRE_ROLL_CHUNKS blobs
let utteranceChunks = null // set while capturing an utterance
let aboveSince = null
let belowSince = null
let utteranceStartedAt = 0

let conversationId = null
let abortController = null
let ttsAudioEl = null

function resetVadTimers() {
  aboveSince = null
  belowSince = null
}

async function toggleSession() {
  if (running.value) {
    stopSession()
  } else {
    await startSession()
  }
}

async function startSession() {
  micError.value = ''
  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true })
  } catch (e) {
    micError.value = 'Mikrofon erişimi reddedildi veya kullanılamıyor: ' + e.message
    return
  }

  audioCtx = new (window.AudioContext || window.webkitAudioContext)()
  analyser = audioCtx.createAnalyser()
  analyser.fftSize = 2048
  dataArray = new Uint8Array(analyser.fftSize)
  audioCtx.createMediaStreamSource(mediaStream).connect(analyser)

  const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
    ? 'audio/webm;codecs=opus'
    : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '')

  recorder = new MediaRecorder(mediaStream, mimeType ? { mimeType } : {})
  recorder.ondataavailable = (e) => {
    if (!e.data || e.data.size === 0) return
    if (utteranceChunks) {
      utteranceChunks.push(e.data)
    } else {
      rollingChunks.push(e.data)
      if (rollingChunks.length > PRE_ROLL_CHUNKS) rollingChunks.shift()
    }
  }
  recorder.start(TIMESLICE_MS)

  conversationId = 'voice-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)
  turns.value = []
  running.value = true
  state.value = 'listening'
  resetVadTimers()
  vadLoop()
}

function stopSession() {
  running.value = false
  state.value = 'idle'
  if (rafId) cancelAnimationFrame(rafId)
  rafId = null
  if (abortController) abortController.abort()
  stopTtsPlayback()
  if (recorder && recorder.state !== 'inactive') recorder.stop()
  recorder = null
  if (mediaStream) mediaStream.getTracks().forEach(t => t.stop())
  mediaStream = null
  if (audioCtx) audioCtx.close()
  audioCtx = null
  analyser = null
  rollingChunks = []
  utteranceChunks = null
  micLevel.value = 0
}

function getRms() {
  analyser.getByteTimeDomainData(dataArray)
  let sum = 0
  for (let i = 0; i < dataArray.length; i++) {
    const v = (dataArray[i] - 128) / 128
    sum += v * v
  }
  return Math.sqrt(sum / dataArray.length)
}

function vadLoop() {
  if (!running.value || !analyser) return

  const rms = getRms()
  micLevel.value = Math.min(1, rms / 0.15)
  const now = performance.now()
  const isSpeech = rms > SPEECH_RMS_THRESHOLD

  if (isSpeech) {
    belowSince = null
    if (aboveSince === null) aboveSince = now

    const sustained = (now - aboveSince) >= SPEECH_ONSET_MS

    if (sustained && (state.value === 'listening')) {
      beginUtterance()
    } else if (sustained && (state.value === 'processing' || state.value === 'speaking')) {
      // Barge-in: user started talking while the agent was thinking/speaking.
      if (abortController) abortController.abort()
      stopTtsPlayback()
      beginUtterance()
    }
  } else {
    aboveSince = null
    if (state.value === 'recording') {
      if (belowSince === null) belowSince = now
      const silenceLong = (now - belowSince) >= SILENCE_HANGOVER_MS
      const longEnough = (now - utteranceStartedAt) >= MIN_UTTERANCE_MS
      if (silenceLong && longEnough) {
        endUtterance()
      }
    }
  }

  rafId = requestAnimationFrame(vadLoop)
}

function beginUtterance() {
  utteranceChunks = [...rollingChunks]
  utteranceStartedAt = performance.now()
  belowSince = null
  state.value = 'recording'
}

async function endUtterance() {
  const chunks = utteranceChunks
  utteranceChunks = null
  rollingChunks = []
  resetVadTimers()
  state.value = 'processing'

  const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' })

  const formData = new FormData()
  formData.append('body[conversation_id]', conversationId)
  formData.append('audio', blob, 'utterance.webm')

  abortController = new AbortController()
  const myConversationTurnAbort = abortController

  try {
    const { data } = await axios.post(`/agents/${agent.id}/test-run`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      signal: abortController.signal,
    })

    if (myConversationTurnAbort.signal.aborted) return

    if (!data.success) {
      turns.value.push({ role: 'assistant', text: 'Hata', error: data.message || 'Ajan çalıştırılamadı.' })
      state.value = 'listening'
      return
    }

    const steps = data.run?.steps || []
    const sttStep = steps.find(s => s.step_type === 'stt')
    const userText = sttStep?.output?.text?.trim()
    if (userText) turns.value.push({ role: 'user', text: userText })

    const lastLlmStep = [...steps].reverse().find(s => s.step_type === 'llm_call' && s.output?.content)
    const replyText = lastLlmStep?.output?.content?.trim() || (data.final?.mode === 'text' ? data.final.value : '')
    if (replyText) turns.value.push({ role: 'assistant', text: replyText })

    if (data.final?.mode === 'audio' && data.final.value) {
      playTtsAndListen(data.final.value)
    } else {
      state.value = 'listening'
    }
  } catch (e) {
    if (e.name === 'CanceledError' || e.name === 'AbortError') return
    turns.value.push({ role: 'assistant', text: 'Hata', error: e.response?.data?.message || e.message })
    state.value = 'listening'
  }
}

function playTtsAndListen(url) {
  stopTtsPlayback()
  ttsAudioEl = new Audio(url)
  state.value = 'speaking'
  ttsAudioEl.onended = () => {
    if (state.value === 'speaking') state.value = 'listening'
  }
  ttsAudioEl.onerror = () => {
    if (state.value === 'speaking') state.value = 'listening'
  }
  ttsAudioEl.play().catch(() => {
    state.value = 'listening'
  })
}

function stopTtsPlayback() {
  if (ttsAudioEl) {
    ttsAudioEl.pause()
    ttsAudioEl.onended = null
    ttsAudioEl.onerror = null
    ttsAudioEl = null
  }
}

onBeforeUnmount(() => {
  if (running.value) stopSession()
})

const stateLabel = computed(() => ({
  idle: 'Bekleniyor — görüşmeyi başlatın',
  listening: 'Dinleniyor...',
  recording: 'Konuşmanız kaydediliyor...',
  processing: 'Düşünüyor...',
  speaking: 'Ajan konuşuyor (araya girebilirsiniz)',
}[state.value]))

const stateColorClass = computed(() => ({
  idle: 'text-neutral-500',
  listening: 'text-accent-300',
  recording: 'text-amber-400',
  processing: 'text-neutral-400',
  speaking: 'text-emerald-400',
}[state.value]))

const orbCoreClass = computed(() => ({
  idle: 'bg-neutral-800 text-neutral-500',
  listening: 'bg-accent/20 text-accent-300 border border-accent/40',
  recording: 'bg-amber-500/20 text-amber-300 border border-amber-500/50',
  processing: 'bg-neutral-800 text-neutral-300 border border-neutral-700',
  speaking: 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/50',
}[state.value]))

const orbIcon = computed(() => ({
  idle: '🎙️',
  listening: '👂',
  recording: '🎤',
  processing: '💭',
  speaking: '🔊',
}[state.value]))

const orbRingClass = computed(() => {
  if (state.value === 'processing') return 'border-4 border-neutral-700 border-t-accent animate-spin'
  return 'border-4 border-transparent'
})

const orbRingStyle = computed(() => {
  if (state.value === 'recording') {
    const scale = 1 + micLevel.value * 0.3
    return { transform: `scale(${scale})`, boxShadow: `0 0 ${20 + micLevel.value * 40}px rgba(245,158,11,0.35)`, borderRadius: '9999px' }
  }
  if (state.value === 'speaking') {
    return { boxShadow: '0 0 30px rgba(16,185,129,0.35)', borderRadius: '9999px' }
  }
  if (state.value === 'listening') {
    return { boxShadow: '0 0 16px rgba(99,102,241,0.2)', borderRadius: '9999px' }
  }
  return {}
})
</script>
