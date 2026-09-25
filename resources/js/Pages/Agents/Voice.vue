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

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="toggleSession"
            class="px-6 py-2.5 rounded-xl text-xs font-semibold transition-opacity hover:opacity-90 cursor-pointer"
            :class="running ? 'bg-red-500/90 text-white' : 'bg-accent text-bg'"
          >
            {{ running ? 'Görüşmeyi Bitir' : 'Görüşmeyi Başlat' }}
          </button>

          <button
            type="button"
            @click="resetConversation"
            class="px-4 py-2.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
            title="Konuşmayı ve oturumu sıfırla"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            <span>Yeni Sohbet</span>
          </button>
        </div>

        <p v-if="micError" class="text-[11px] text-red-400 text-center leading-relaxed">{{ micError }}</p>
        <p v-else class="text-[11px] text-neutral-500 text-center leading-relaxed max-w-sm">
          Konuşmaya başlayın, sessizliği algılayınca otomatik gönderilir. Ajan konuşurken tekrar konuşursanız oynatma kesilir (barge-in) ve söyledikleriniz yeni bir tur olarak alınır.
        </p>
      </div>

      <!-- Transcript -->
      <div v-if="turns.length" class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-semibold text-neutral-200 uppercase tracking-wide">Konuşma</h2>
          <button
            type="button"
            @click="resetConversation"
            class="text-[11px] text-neutral-500 hover:text-accent cursor-pointer flex items-center gap-1 transition-colors"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            <span>Yeni Sohbet Başlat</span>
          </button>
        </div>
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
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import axios from 'axios'
import { useAudioDevices } from '../../Composables/useAudioDevices'

const props = defineProps({
  agent: Object,
})
const agent = props.agent

const { selectedAudioDeviceId, loadAudioDevices } = useAudioDevices()

// Watch for device change during an active voice session to hot-swap audio stream
watch(selectedAudioDeviceId, async (newDeviceId) => {
  if (running.value && audioCtx) {
    try {
      const audioConstraints = {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
      }
      if (newDeviceId) {
        audioConstraints.deviceId = { exact: newDeviceId }
      }
      const newStream = await navigator.mediaDevices.getUserMedia({ audio: audioConstraints })

      if (sourceNode) {
        try { sourceNode.disconnect() } catch (e) {}
      }
      if (mediaStream) {
        mediaStream.getTracks().forEach(t => t.stop())
      }

      mediaStream = newStream
      sourceNode = audioCtx.createMediaStreamSource(mediaStream)
      sourceNode.connect(scriptProcessor)
    } catch (err) {
      micError.value = 'Mikrofon kaynağı değiştirilemedi: ' + err.message
    }
  }
})

// --- VAD tuning ---
const SPEECH_RMS_THRESHOLD = 0.035
const SPEECH_ONSET_MS = 200 // sustained energy above threshold before we call it "speech"
const SILENCE_HANGOVER_MS = 900 // sustained silence before we consider the utterance done
const MIN_UTTERANCE_MS = 350
const PRE_ROLL_BUFFERS = 5 // ~85ms * 5 = ~425ms of audio before speech onset

// state: idle | listening | recording | processing | speaking
const state = ref('idle')
const running = ref(false)
const micError = ref('')
const turns = ref([])
const micLevel = ref(0)

let audioCtx = null
let mediaStream = null
let sourceNode = null
let scriptProcessor = null
let muteGain = null

let rollingPcm = [] // pre-roll buffer of Float32Array chunks
let utterancePcm = [] // chunks recorded during speech
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
    const audioConstraints = {
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true,
    }
    if (selectedAudioDeviceId.value) {
      audioConstraints.deviceId = { exact: selectedAudioDeviceId.value }
    }
    mediaStream = await navigator.mediaDevices.getUserMedia({
      audio: audioConstraints,
    })
    loadAudioDevices()
  } catch (e) {
    micError.value = 'Mikrofon erişimi reddedildi veya kullanılamıyor: ' + e.message
    return
  }

  try {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)()
    sourceNode = audioCtx.createMediaStreamSource(mediaStream)

    // Use 4096 buffer size (~85ms @ 48kHz, ~93ms @ 44.1kHz, ~256ms @ 16kHz)
    scriptProcessor = audioCtx.createScriptProcessor(4096, 1, 1)

    // ScriptProcessor needs to connect to destination to receive events in some browsers.
    // Connect through a zero-gain node so microphone audio is not played back to user's speakers.
    muteGain = audioCtx.createGain()
    muteGain.gain.value = 0

    sourceNode.connect(scriptProcessor)
    scriptProcessor.connect(muteGain)
    muteGain.connect(audioCtx.destination)

    scriptProcessor.onaudioprocess = handleAudioProcess
  } catch (err) {
    micError.value = 'Ses işleme başlatılamadı: ' + err.message
    stopSession()
    return
  }

  conversationId = 'voice-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)
  turns.value = []
  rollingPcm = []
  utterancePcm = []
  running.value = true
  state.value = 'listening'
  resetVadTimers()
}

function handleAudioProcess(e) {
  if (!running.value) return

  const input = e.inputBuffer.getChannelData(0)
  let sum = 0
  for (let i = 0; i < input.length; i++) {
    sum += input[i] * input[i]
  }
  const rms = Math.sqrt(sum / input.length)
  micLevel.value = Math.min(1, rms / 0.15)

  const chunk = new Float32Array(input)
  const now = performance.now()
  const isSpeech = rms > SPEECH_RMS_THRESHOLD

  if (state.value === 'listening') {
    rollingPcm.push(chunk)
    if (rollingPcm.length > PRE_ROLL_BUFFERS) {
      rollingPcm.shift()
    }

    if (isSpeech) {
      if (aboveSince === null) aboveSince = now
      if ((now - aboveSince) >= SPEECH_ONSET_MS) {
        beginUtterance()
      }
    } else {
      aboveSince = null
    }
  } else if (state.value === 'recording') {
    utterancePcm.push(chunk)

    if (isSpeech) {
      belowSince = null
    } else {
      if (belowSince === null) belowSince = now
      const silenceLong = (now - belowSince) >= SILENCE_HANGOVER_MS
      const longEnough = (now - utteranceStartedAt) >= MIN_UTTERANCE_MS
      if (silenceLong && longEnough) {
        endUtterance()
      }
    }
  } else if (state.value === 'processing' || state.value === 'speaking') {
    // Barge-in: user spoke while agent was thinking or speaking
    if (isSpeech) {
      if (aboveSince === null) aboveSince = now
      if ((now - aboveSince) >= SPEECH_ONSET_MS) {
        if (abortController) abortController.abort()
        stopTtsPlayback()
        beginUtterance()
      }
    } else {
      aboveSince = null
    }
  }
}

function beginUtterance() {
  utterancePcm = [...rollingPcm]
  utteranceStartedAt = performance.now()
  belowSince = null
  aboveSince = null
  state.value = 'recording'
}

async function endUtterance() {
  state.value = 'processing'
  resetVadTimers()

  const chunks = utterancePcm
  utterancePcm = []
  rollingPcm = []

  let totalLen = 0
  for (let i = 0; i < chunks.length; i++) {
    totalLen += chunks[i].length
  }

  // If audio is practically empty (< 250ms), return to listening
  const minSamples = (audioCtx?.sampleRate || 16000) * 0.25
  if (totalLen < minSamples) {
    state.value = 'listening'
    return
  }

  const merged = new Float32Array(totalLen)
  let offset = 0
  for (let i = 0; i < chunks.length; i++) {
    merged.set(chunks[i], offset)
    offset += chunks[i].length
  }

  // Resample to 16kHz mono PCM for optimal Whisper performance
  const sampleRate = audioCtx?.sampleRate || 48000
  const downsampled = downsampleBuffer(merged, sampleRate, 16000)
  const wavBuffer = encodeWav(downsampled, 16000)
  const blob = new Blob([wavBuffer], { type: 'audio/wav' })

  const formData = new FormData()
  formData.append('body[conversation_id]', conversationId)
  formData.append('audio', blob, 'utterance.wav')

  abortController = new AbortController()
  const myConversationTurnAbort = abortController

  try {
    const { data } = await axios.post(`/agents/${agent.id}/test-run`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      signal: abortController.signal,
    })

    if (myConversationTurnAbort.signal.aborted) return

    if (!data.success) {
      if (data.message && data.message.includes('boş')) {
        // Empty / background noise utterance, resume listening smoothly
        state.value = 'listening'
        return
      }
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
    const msg = e.response?.data?.message || e.message
    if (msg && msg.includes('boş')) {
      state.value = 'listening'
      return
    }
    turns.value.push({ role: 'assistant', text: 'Hata', error: msg })
    state.value = 'listening'
  }
}

async function resetConversation() {
  if (conversationId) {
    try {
      await axios.post(`/agents/${agent.id}/clear-session`, { session_id: conversationId })
    } catch {}
  }
  conversationId = 'voice-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)
  turns.value = []
  stopTtsPlayback()
}

function stopSession() {
  running.value = false
  state.value = 'idle'
  if (abortController) abortController.abort()
  stopTtsPlayback()

  if (scriptProcessor) {
    scriptProcessor.disconnect()
    scriptProcessor.onaudioprocess = null
    scriptProcessor = null
  }
  if (sourceNode) {
    sourceNode.disconnect()
    sourceNode = null
  }
  if (muteGain) {
    muteGain.disconnect()
    muteGain = null
  }
  if (mediaStream) {
    mediaStream.getTracks().forEach(t => t.stop())
    mediaStream = null
  }
  if (audioCtx && audioCtx.state !== 'closed') {
    audioCtx.close().catch(() => {})
    audioCtx = null
  }

  rollingPcm = []
  utterancePcm = []
  micLevel.value = 0
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

// Downsample Float32Array to target sample rate (e.g. 16kHz)
function downsampleBuffer(buffer, sourceRate, targetRate = 16000) {
  if (sourceRate === targetRate || sourceRate < targetRate) return buffer
  const ratio = sourceRate / targetRate
  const newLength = Math.round(buffer.length / ratio)
  const result = new Float32Array(newLength)
  let offsetResult = 0
  let offsetBuffer = 0
  while (offsetResult < result.length) {
    const nextOffsetBuffer = Math.round((offsetResult + 1) * ratio)
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

// Encode Float32Array to 16-bit PCM mono WAV
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
