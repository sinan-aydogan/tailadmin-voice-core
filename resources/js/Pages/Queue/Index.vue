<template>
  <AppLayout title="İşlem Kuyruğu">
    <div class="space-y-6">
      <!-- Repair Success Alert Banner -->
      <div v-if="repairAlert"
           class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
          </svg>
          <div>
            <div class="font-semibold text-emerald-200">Kuyruk Onarıldı!</div>
            <div class="text-[11px] text-emerald-300/90 mt-0.5">{{ repairAlert }}</div>
          </div>
        </div>
        <button @click="repairAlert = null" class="text-emerald-400 hover:text-emerald-200 p-1">✕</button>
      </div>

      <!-- Header Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-base font-semibold text-neutral-100">Kuyruk Durumu</h2>
            <!-- Worker Status Badge -->
            <span v-if="isWorkerActive"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>Queue Worker Aktif</span>
            </span>
            <span v-else
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/20">
              <span class="w-2 h-2 rounded-full bg-red-400"></span>
              <span>Worker Durdu</span>
            </span>
          </div>
          <p class="text-xs text-neutral-400 mt-1">
            Tüm asenkron TTS ve STT işlemleri burada listelenir. Arka plan worker'ı işleri sırayla tüketir.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <!-- Kuyruğu Onar Butonu -->
          <button
            @click="repairQueue"
            :disabled="isRepairing"
            class="text-xs px-4 py-2.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 flex items-center gap-2 font-medium transition-colors disabled:opacity-50"
            title="Askıda kalan işlemleri temizler, worker'ı sıfırlar ve bekleyen görevleri yeniden başlatır"
          >
            <svg :class="['w-4 h-4 text-amber-400', { 'animate-spin': isRepairing }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ isRepairing ? 'Kuyruk Onarılıyor...' : 'Kuyruğu Onar & Yeniden Başlat' }}</span>
          </button>

          <!-- Manuel Yenile Butonu -->
          <button
            @click="fetchTasks(true)"
            :disabled="isRefreshing"
            class="text-xs px-3.5 py-2.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 flex items-center gap-1.5 transition-colors disabled:opacity-50"
          >
            <svg :class="['w-3.5 h-3.5', { 'animate-spin': isRefreshing }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ isRefreshing ? 'Yenileniyor...' : 'Yenile' }}</span>
          </button>
        </div>
      </div>

      <!-- Filter Tabs -->
      <div class="flex items-center gap-2 text-xs border-b border-neutral-800 pb-2">
        <button
          @click="currentFilter = 'all'"
          :class="['px-3 py-1.5 rounded-lg font-medium transition-colors', currentFilter === 'all' ? 'bg-neutral-800 text-neutral-100' : 'text-neutral-400 hover:text-neutral-200']"
        >
          Tümü ({{ tasksList.length }})
        </button>
        <button
          @click="currentFilter = 'pending'"
          :class="['px-3 py-1.5 rounded-lg font-medium transition-colors', currentFilter === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'text-neutral-400 hover:text-neutral-200']"
        >
          Bekleyenler ({{ pendingCount }})
        </button>
        <button
          @click="currentFilter = 'completed'"
          :class="['px-3 py-1.5 rounded-lg font-medium transition-colors', currentFilter === 'completed' ? 'bg-emerald-500/20 text-emerald-300' : 'text-neutral-400 hover:text-neutral-200']"
        >
          Tamamlananlar ({{ completedCount }})
        </button>
        <button
          @click="currentFilter = 'failed'"
          :class="['px-3 py-1.5 rounded-lg font-medium transition-colors', currentFilter === 'failed' ? 'bg-red-500/20 text-red-300' : 'text-neutral-400 hover:text-neutral-200']"
        >
          Hatalılar ({{ failedCount }})
        </button>
      </div>

      <!-- Tasks Table -->
      <div class="rounded-2xl bg-surface border border-neutral-800 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-neutral-300">
            <thead class="bg-neutral-900/60 text-xs uppercase text-neutral-400 border-b border-neutral-800">
              <tr>
                <th class="px-6 py-3.5">ID</th>
                <th class="px-6 py-3.5">Tür</th>
                <th class="px-6 py-3.5">Detay / Metin</th>
                <th class="px-6 py-3.5">Durum</th>
                <th class="px-6 py-3.5">Tarih</th>
                <th class="px-6 py-3.5 text-right">İşlem</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800/60">
              <tr v-if="filteredTasks.length === 0">
                <td colspan="6" class="px-6 py-12 text-center text-sm text-neutral-500">
                  Bu filtreye uygun işlem bulunmuyor.
                </td>
              </tr>
              <tr v-for="t in filteredTasks" :key="t.id" class="hover:bg-neutral-800/30 transition-colors">
                <td class="px-6 py-4 font-mono text-xs text-neutral-500">#{{ t.id }}</td>
                <td class="px-6 py-4">
                  <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase"
                        :class="t.type === 'tts' ? 'bg-accent/20 text-accent-300' : 'bg-cyan-500/20 text-cyan-400'">
                    {{ t.type }}
                  </span>
                </td>
                <td class="px-6 py-4 max-w-sm">
                  <div class="truncate text-xs text-neutral-200 font-medium">
                    {{ t.payload?.text || t.payload?.original_name || 'İsimsiz Görev' }}
                  </div>
                  <div class="text-[11px] text-neutral-500 mt-0.5 flex items-center gap-2">
                    <span>Motor: {{ t.payload?.engine || 'whisper' }}</span>
                    <span v-if="t.payload?.language">· Dil: {{ t.payload.language }}</span>
                  </div>
                  <!-- Inline Error Details -->
                  <div v-if="t.status === 'failed' && t.error_message" class="mt-1.5 p-2 rounded bg-red-950/40 border border-red-900/40 text-[11px] text-red-300">
                    <span class="font-semibold text-red-200">Hata: </span>
                    <span class="font-mono text-[10px] break-words">{{ t.error_message }}</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span v-if="t.status === 'pending'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    <span>Bekliyor</span>
                  </span>
                  <span v-else-if="t.status === 'running'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>İşleniyor</span>
                  </span>
                  <span v-else-if="t.status === 'completed'"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Tamamlandı</span>
                  </span>
                  <span v-else-if="t.status === 'failed'"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/20">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>Başarısız</span>
                  </span>
                </td>
                <td class="px-6 py-4 text-xs text-neutral-400 whitespace-nowrap">
                  {{ formatDateTime(t.created_at) }}
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <!-- Custom Play Button (Opens Modal) -->
                    <button
                      v-if="t.status === 'completed' && getAudioFilename(t)"
                      @click="openPlayerModal(t)"
                      class="p-1.5 rounded-lg text-neutral-400 hover:text-accent hover:bg-neutral-800 transition-colors"
                      title="Sesi Dinle (Oynatıcıyı Aç)"
                    >
                      <svg class="w-4 h-4 fill-current text-accent" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z" />
                      </svg>
                    </button>

                    <!-- Download Button -->
                    <a
                      v-if="t.status === 'completed' && getAudioFilename(t)"
                      :href="'/api/audio/' + getAudioFilename(t)"
                      download
                      class="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800 transition-colors"
                      title="İndir"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                    </a>

                    <!-- Retry Button (Farklı Model ile Yeniden Üret / Yeniden Dene) -->
                    <button
                      v-if="t.status === 'completed' || t.status === 'failed'"
                      @click="openRetryModal(t)"
                      class="p-1.5 rounded-lg transition-colors"
                      :class="t.status === 'completed' 
                        ? 'text-neutral-400 hover:text-accent hover:bg-neutral-800' 
                        : 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10'"
                      :title="t.status === 'completed' ? 'Farklı Model ile Yeniden Üret' : 'Farklı Model ile Yeniden Dene'"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                    </button>

                    <!-- Delete Button -->
                    <button
                      @click="deleteTask(t.id)"
                      class="p-1.5 rounded-lg text-neutral-500 hover:text-red-400 hover:bg-neutral-800 transition-colors"
                      title="Sil"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
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
      :available-models="availableModelsList"
      :profiles="profilesList"
      @close="showRetryModal = false"
      @retried="onTaskRetried"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import AudioPlayerModal from '../../Components/AudioPlayerModal.vue'
import RetryTaskModal from '../../Components/RetryTaskModal.vue'

const props = defineProps({
  tasks: [Array, Object],
  is_worker_running: Boolean,
  available_models: {
    type: Array,
    default: () => [],
  },
  profiles: {
    type: Array,
    default: () => [],
  },
})

const parseInitialTasks = () => {
  if (Array.isArray(props.tasks)) return props.tasks
  if (props.tasks && Array.isArray(props.tasks.data)) return props.tasks.data
  return []
}

const tasksList = ref(parseInitialTasks())
const isWorkerActive = ref(props.is_worker_running ?? true)
const availableModelsList = ref(props.available_models || [])
const profilesList = ref(props.profiles || [])
const isRepairing = ref(false)
const isRefreshing = ref(false)
const repairAlert = ref(null)
const currentFilter = ref('all')

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

const pendingCount = computed(() => tasksList.value.filter(t => t.status === 'pending' || t.status === 'running').length)
const completedCount = computed(() => tasksList.value.filter(t => t.status === 'completed').length)
const failedCount = computed(() => tasksList.value.filter(t => t.status === 'failed').length)

const filteredTasks = computed(() => {
  if (currentFilter.value === 'pending') {
    return tasksList.value.filter(t => t.status === 'pending' || t.status === 'running')
  }
  if (currentFilter.value === 'completed') {
    return tasksList.value.filter(t => t.status === 'completed')
  }
  if (currentFilter.value === 'failed') {
    return tasksList.value.filter(t => t.status === 'failed')
  }
  return tasksList.value
})

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
    return d.toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'medium' })
  } catch (e) {
    return dateStr
  }
}

const fetchTasks = async (showLoading = false) => {
  if (showLoading) isRefreshing.value = true
  try {
    const res = await fetch('/api/queue/tasks')
    if (res.ok) {
      const data = await res.json()
      if (Array.isArray(data.tasks)) {
        tasksList.value = data.tasks
      }
      if (typeof data.is_worker_running === 'boolean') {
        isWorkerActive.value = data.is_worker_running
      }
      if (Array.isArray(data.available_models)) {
        availableModelsList.value = data.available_models
      }
      if (Array.isArray(data.profiles)) {
        profilesList.value = data.profiles
      }
    }
  } catch (err) {
    console.warn('Kuyruk görevleri güncellenirken hata:', err)
  } finally {
    if (showLoading) isRefreshing.value = false
  }
}

const repairQueue = async () => {
  isRepairing.value = true
  repairAlert.value = null
  try {
    const res = await fetch('/queue/repair', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      }
    })
    if (res.ok) {
      const data = await res.json()
      repairAlert.value = data.message || 'Kuyruk başarıyla onarıldı ve yeniden başlatıldı.'
      isWorkerActive.value = data.is_running
      await fetchTasks()
      // Auto-dismiss alert after 6 seconds
      setTimeout(() => {
        repairAlert.value = null
      }, 6000)
    }
  } catch (err) {
    console.error('Kuyruk onarılırken hata:', err)
    repairAlert.value = 'Kuyruk onarımı sırasında bir hata oluştu.'
  } finally {
    isRepairing.value = false
  }
}

const deleteTask = async (id) => {
  if (!confirm('Bu işlemi silmek istediğinize emin misiniz?')) {
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

/* ── Live Adaptive Polling ── */
let pollTimer = null

const runAdaptivePoll = async () => {
  await fetchTasks()
  const nextInterval = hasActiveTasks.value ? 2000 : 5000
  pollTimer = setTimeout(runAdaptivePoll, nextInterval)
}

onMounted(() => {
  pollTimer = setTimeout(runAdaptivePoll, 2500)
})

onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
})
</script>
