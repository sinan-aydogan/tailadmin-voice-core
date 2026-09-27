import { ref } from 'vue'
import axios from 'axios'

const updateInfo = ref({
  current_version: '1.5.0',
  latest_version: null,
  latest_tag: null,
  has_update: false,
  release_name: null,
  release_url: null,
  release_notes: null,
  published_at: null,
  checked_at: null,
})

const isChecking = ref(false)
const checkError = ref(null)
const hasChecked = ref(false)
const showUpdateModal = ref(false)

export function useUpdateChecker() {
  const checkForUpdates = async (force = false) => {
    isChecking.value = true
    checkError.value = null
    try {
      const response = await axios.get('/api/system/check-update', {
        params: { force: force ? 1 : 0 }
      })
      if (response.data) {
        updateInfo.value = {
          current_version: response.data.current_version || '1.5.0',
          latest_version: response.data.latest_version,
          latest_tag: response.data.latest_tag,
          has_update: Boolean(response.data.has_update),
          release_name: response.data.release_name,
          release_url: response.data.release_url,
          release_notes: response.data.body,
          published_at: response.data.published_at,
          checked_at: response.data.checked_at,
        }
        hasChecked.value = true
      }
    } catch (err) {
      console.warn('Update check failed:', err)
      checkError.value = err.response?.data?.message || 'Güncelleme sunucusuna ulaşılamadı.'
    } finally {
      isChecking.value = false
    }
  }

  const openReleaseUrl = async (url) => {
    const targetUrl = url || updateInfo.value.release_url || 'https://github.com/sinan-aydogan/tailadmin-voice-core/releases'
    if (typeof window !== 'undefined' && window.Native) {
      try {
        await axios.post('/api/system/open-url', { url: targetUrl })
        return
      } catch (e) {
        console.warn('Native openExternal failed', e)
      }
    }
    window.open(targetUrl, '_blank', 'noopener,noreferrer')
  }

  return {
    updateInfo,
    isChecking,
    checkError,
    hasChecked,
    showUpdateModal,
    checkForUpdates,
    openReleaseUrl,
  }
}
