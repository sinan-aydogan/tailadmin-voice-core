<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
    <!-- Backdrop -->
    <div
      class="fixed inset-0 bg-black/75 backdrop-blur-sm transition-opacity"
      @click="close"
    ></div>

    <!-- Modal Content -->
    <div class="relative w-full max-w-4xl rounded-2xl bg-surface border border-neutral-800 shadow-2xl flex flex-col max-h-[90vh] z-10 overflow-hidden">
      <!-- Header -->
      <div class="p-5 border-b border-neutral-800 flex items-center justify-between gap-3 shrink-0">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 shrink-0">
            <span class="text-lg select-none">📜</span>
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="text-base font-semibold text-neutral-100 truncate">
                {{ apiKey?.name || 'API Anahtarı' }} — İşlem Geçmişi
              </h3>
              <span
                v-if="apiKey?.is_active"
                class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 shrink-0"
              >
                Aktif
              </span>
              <span
                v-else
                class="px-2 py-0.5 rounded text-[10px] font-semibold bg-red-500/15 border border-red-500/30 text-red-400 shrink-0"
              >
                Pasif
              </span>
            </div>
            <p class="text-xs text-neutral-500 truncate mt-0.5 font-mono">
              {{ apiKey?.key ? maskKey(apiKey.key) : '' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="fetchLogs"
            :disabled="isLoading"
            class="px-3 py-1.5 rounded-lg bg-neutral-900 border border-neutral-700 hover:border-neutral-600 text-neutral-300 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            title="Yenile"
          >
            <span :class="{ 'animate-spin': isLoading }">🔄</span>
            <span class="hidden sm:inline">Yenile</span>
          </button>
          <button
            type="button"
            @click="close"
            class="w-8 h-8 rounded-lg bg-neutral-900 border border-neutral-800 hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 text-sm flex items-center justify-center transition-colors cursor-pointer"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- Stats Summary Strip -->
      <div class="px-5 py-3 bg-neutral-900/60 border-b border-neutral-800/80 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs shrink-0">
        <div>
          <div class="text-[10px] text-neutral-500 font-medium uppercase tracking-wider">Toplam İstek</div>
          <div class="text-sm font-semibold text-neutral-200 font-mono mt-0.5">{{ stats.total || 0 }}</div>
        </div>
        <div>
          <div class="text-[10px] text-emerald-400/80 font-medium uppercase tracking-wider">Başarılı (2xx)</div>
          <div class="text-sm font-semibold text-emerald-400 font-mono mt-0.5">{{ stats.success || 0 }}</div>
        </div>
        <div>
          <div class="text-[10px] text-red-400/80 font-medium uppercase tracking-wider">Hatalı / Reddedilen</div>
          <div class="text-sm font-semibold text-red-400 font-mono mt-0.5">{{ stats.error || 0 }}</div>
        </div>
        <div>
          <div class="text-[10px] text-neutral-500 font-medium uppercase tracking-wider">Son Kullanım</div>
          <div class="text-xs font-medium text-neutral-300 truncate mt-0.5">
            {{ formatRelativeTime(apiKey?.last_used_at) || 'Hiç kullanılmadı' }}
          </div>
        </div>
      </div>

      <!-- Logs Table Body -->
      <div class="flex-1 overflow-y-auto min-h-0 p-5 space-y-3">
        <!-- Loading State -->
        <div v-if="isLoading && logs.length === 0" class="py-16 text-center text-neutral-500 text-xs flex flex-col items-center gap-2">
          <div class="w-6 h-6 border-2 border-accent border-t-transparent rounded-full animate-spin"></div>
          <span>Kayıtlar yükleniyor...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="logs.length === 0" class="py-16 text-center text-neutral-500 text-xs flex flex-col items-center gap-2">
          <div class="w-12 h-12 rounded-2xl bg-neutral-900 border border-neutral-800 flex items-center justify-center text-xl text-neutral-400">
            📭
          </div>
          <div class="font-medium text-neutral-300 text-sm">Henüz Bir İşlem Kaydı Yok</div>
          <div class="text-neutral-500 max-w-sm">
            Bu API anahtarı kullanılarak REST API uç noktalarına (`/api/v1/*`) istek atıldığında işlemler burada kronolojik olarak listelenecektir.
          </div>
        </div>

        <!-- Logs Table -->
        <div v-else class="rounded-xl border border-neutral-800 overflow-hidden bg-neutral-900/40">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-neutral-900 border-b border-neutral-800 text-[11px] text-neutral-400 uppercase font-semibold tracking-wider">
                <tr>
                  <th class="py-3 px-4">Tarih / Saat</th>
                  <th class="py-3 px-3">Eylem</th>
                  <th class="py-3 px-3">Uç Nokta (Endpoint)</th>
                  <th class="py-3 px-3 text-center">Durum</th>
                  <th class="py-3 px-4">Detay & Parametreler</th>
                  <th class="py-3 px-3 text-right">Süre</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-800/60 font-sans">
                <tr
                  v-for="log in logs"
                  :key="log.id"
                  class="hover:bg-neutral-800/40 transition-colors"
                >
                  <!-- Time -->
                  <td class="py-3 px-4 whitespace-nowrap">
                    <div class="font-medium text-neutral-200 text-xs">{{ formatDate(log.created_at) }}</div>
                    <div class="text-[10px] text-neutral-500">{{ formatRelativeTime(log.created_at) }} · {{ log.ip_address || 'Yerel' }}</div>
                  </td>

                  <!-- Action Badge -->
                  <td class="py-3 px-3 whitespace-nowrap">
                    <span :class="['px-2 py-0.5 rounded-md text-[10px] font-semibold border inline-flex items-center gap-1', getActionBadgeClass(log.action_type)]">
                      <span>{{ getActionIcon(log.action_type) }}</span>
                      <span>{{ getActionLabel(log.action_type) }}</span>
                    </span>
                  </td>

                  <!-- Endpoint & Method -->
                  <td class="py-3 px-3 font-mono text-[11px] whitespace-nowrap">
                    <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold mr-1.5', getMethodClass(log.method)]">
                      {{ log.method }}
                    </span>
                    <span class="text-neutral-300">{{ log.endpoint }}</span>
                  </td>

                  <!-- Status Code -->
                  <td class="py-3 px-3 text-center whitespace-nowrap">
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-mono font-bold', getStatusClass(log.status_code)]">
                      {{ log.status_code }}
                    </span>
                  </td>

                  <!-- Request Summary -->
                  <td class="py-3 px-4 text-[11px] text-neutral-300 max-w-xs truncate">
                    <div v-if="log.request_summary?.text_preview" class="truncate font-sans" :title="log.request_summary.text_preview">
                      <span class="text-accent font-mono text-[10px] mr-1">[{{ log.request_summary.engine || 'TTS' }}]</span>
                      "{{ log.request_summary.text_preview }}"
                    </div>
                    <div v-else-if="log.request_summary?.file_name" class="truncate font-mono text-[10.5px] text-neutral-300">
                      🎙️ {{ log.request_summary.file_name }} ({{ log.request_summary.file_size_kb }} KB)
                    </div>
                    <div v-else-if="log.task_id" class="text-neutral-400 font-mono text-[11px]">
                      Görev #{{ log.task_id }}
                    </div>
                    <div v-else-if="log.request_summary?.model_id" class="font-mono text-[10.5px] text-neutral-400 truncate">
                      📦 {{ log.request_summary.model_id }}
                    </div>
                    <div v-else class="text-neutral-500 font-mono text-[10px] truncate">
                      {{ formatSummaryJson(log.request_summary) }}
                    </div>
                  </td>

                  <!-- Response Time -->
                  <td class="py-3 px-3 text-right whitespace-nowrap font-mono text-[11px] text-neutral-400">
                    <span v-if="log.response_time_ms">{{ log.response_time_ms }} ms</span>
                    <span v-else class="text-neutral-600">-</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="p-4 bg-neutral-900 border-t border-neutral-800 flex items-center justify-between gap-3 shrink-0">
        <div>
          <button
            v-if="logs.length > 0"
            type="button"
            @click="clearLogs"
            :disabled="isClearing"
            class="px-3.5 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-semibold transition-colors disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
          >
            <span>🗑️</span>
            <span>{{ isClearing ? 'Temizleniyor...' : 'Geçmişi Temizle' }}</span>
          </button>
        </div>

        <button
          type="button"
          @click="close"
          class="px-5 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-semibold transition-colors cursor-pointer"
        >
          Kapat
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  show: Boolean,
  apiKey: Object,
})

const emit = defineEmits(['close', 'logs-cleared'])

const logs = ref([])
const stats = ref({ total: 0, success: 0, error: 0 })
const isLoading = ref(false)
const isClearing = ref(false)

const close = () => {
  emit('close')
}

const maskKey = (key) => {
  if (!key) return ''
  if (key.length <= 12) return key
  return key.substring(0, 8) + '••••••••••••' + key.substring(key.length - 4)
}

const fetchLogs = async () => {
  if (!props.apiKey?.id) return
  isLoading.value = true
  try {
    const res = await fetch(`/settings/api-keys/${props.apiKey.id}/logs`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      }
    })
    if (res.ok) {
      const data = await res.json()
      if (data.success) {
        logs.value = data.logs || []
        stats.value = data.stats || { total: 0, success: 0, error: 0 }
      }
    }
  } catch (err) {
    console.error('İşlem geçmişi alınamadı:', err)
  } finally {
    isLoading.value = false
  }
}

const clearLogs = async () => {
  if (!props.apiKey?.id) return
  if (!confirm(`"${props.apiKey.name}" anahtarına ait tüm işlem geçmişini silmek istediğinize emin misiniz?`)) {
    return
  }

  isClearing.value = true
  try {
    const res = await fetch(`/settings/api-keys/${props.apiKey.id}/logs`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    })
    if (res.ok) {
      logs.value = []
      stats.value = { total: 0, success: 0, error: 0 }
      emit('logs-cleared', props.apiKey.id)
    }
  } catch (err) {
    console.error('Geçmiş temizleme hatası:', err)
  } finally {
    isClearing.value = false
  }
}

watch(() => props.show, (isShown) => {
  if (isShown && props.apiKey?.id) {
    fetchLogs()
  } else {
    logs.value = []
    stats.value = { total: 0, success: 0, error: 0 }
  }
})

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('tr-TR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

const formatRelativeTime = (dateStr) => {
  if (!dateStr) return null
  const d = new Date(dateStr)
  const diffSec = Math.round((new Date() - d) / 1000)
  if (diffSec < 60) return `${diffSec} saniye önce`
  const diffMin = Math.round(diffSec / 60)
  if (diffMin < 60) return `${diffMin} dk önce`
  const diffHour = Math.round(diffMin / 60)
  if (diffHour < 24) return `${diffHour} saat önce`
  const diffDay = Math.round(diffHour / 24)
  return `${diffDay} gün önce`
}

const getActionLabel = (type) => {
  const map = {
    'tts_generate': 'TTS Üretimi',
    'stt_transcribe': 'STT Deşifre',
    'task_show': 'Görev Detayı',
    'tasks_index': 'Görev Listesi',
    'task_delete': 'Görev Silindi',
    'models_list': 'Model Listesi',
    'model_download': 'Model İndirme',
    'models_download_status': 'İndirme Durumu',
    'profiles_index': 'Profil Listesi',
    'profile_create': 'Profil Ekleme',
    'profile_delete': 'Profil Silme',
    'system_stats': 'Sistem İstatistiği',
  }
  return map[type] || type || 'İstek'
}

const getActionIcon = (type) => {
  if (type === 'tts_generate') return '🔊'
  if (type === 'stt_transcribe') return '🎙️'
  if (type === 'model_download' || type === 'models_list') return '📦'
  if (type === 'profile_create' || type === 'profiles_index') return '👤'
  if (type?.startsWith('task')) return '📋'
  return '⚡'
}

const getActionBadgeClass = (type) => {
  if (type === 'tts_generate') return 'bg-purple-500/15 border-purple-500/30 text-purple-300'
  if (type === 'stt_transcribe') return 'bg-cyan-500/15 border-cyan-500/30 text-cyan-300'
  if (type?.startsWith('model')) return 'bg-amber-500/15 border-amber-500/30 text-amber-300'
  if (type?.startsWith('profile')) return 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300'
  if (type?.startsWith('task')) return 'bg-blue-500/15 border-blue-500/30 text-blue-300'
  return 'bg-neutral-800 border-neutral-700 text-neutral-300'
}

const getMethodClass = (method) => {
  if (method === 'POST') return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
  if (method === 'GET') return 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
  if (method === 'DELETE') return 'bg-red-500/20 text-red-300 border border-red-500/30'
  return 'bg-neutral-800 text-neutral-300'
}

const getStatusClass = (code) => {
  if (code >= 200 && code < 300) return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
  if (code >= 400 && code < 500) return 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
  if (code >= 500) return 'bg-red-500/20 text-red-300 border border-red-500/40'
  return 'bg-neutral-800 text-neutral-400'
}

const formatSummaryJson = (summary) => {
  if (!summary || Object.keys(summary).length === 0) return '-'
  return JSON.stringify(summary)
}
</script>
