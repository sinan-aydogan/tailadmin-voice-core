import { ref } from 'vue'

const audioInputDevices = ref([])
const selectedAudioDeviceId = ref(
  (typeof window !== 'undefined' && localStorage.getItem('voice_core_mic_device_id')) || ''
)

let hasInitializedListener = false

export const loadAudioDevices = async () => {
  if (typeof navigator === 'undefined' || !navigator.mediaDevices?.enumerateDevices) {
    return
  }
  try {
    let devices = await navigator.mediaDevices.enumerateDevices()
    let audioInputs = devices.filter(d => d.kind === 'audioinput')

    audioInputDevices.value = audioInputs

    // If selected device is no longer plugged in / present, fallback to default
    if (selectedAudioDeviceId.value && !audioInputs.some(d => d.deviceId === selectedAudioDeviceId.value)) {
      selectedAudioDeviceId.value = ''
      if (typeof window !== 'undefined') {
        localStorage.removeItem('voice_core_mic_device_id')
      }
    }
  } catch (err) {
    console.warn('Mikrofon cihazları taranırken hata:', err)
  }
}

export const ensureMicrophonePermission = async () => {
  if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) return

  const hasLabels = audioInputDevices.value.some(d => d.label && d.label.trim().length > 0)
  if (!hasLabels) {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      stream.getTracks().forEach(t => t.stop())
      await loadAudioDevices()
    } catch (e) {
      // User dismissed or denied permission
    }
  }
}

export const selectAudioDevice = (deviceId) => {
  selectedAudioDeviceId.value = deviceId || ''
  if (typeof window !== 'undefined') {
    if (deviceId) {
      localStorage.setItem('voice_core_mic_device_id', deviceId)
    } else {
      localStorage.removeItem('voice_core_mic_device_id')
    }
  }
}

export function useAudioDevices() {
  if (typeof window !== 'undefined' && !hasInitializedListener && navigator.mediaDevices?.addEventListener) {
    hasInitializedListener = true
    navigator.mediaDevices.addEventListener('devicechange', loadAudioDevices)
  }

  return {
    audioInputDevices,
    selectedAudioDeviceId,
    loadAudioDevices,
    ensureMicrophonePermission,
    selectAudioDevice,
  }
}
