<template>
  <AppLayout title="Model Yöneticisi">
    <div class="space-y-6">

      <!-- Active Downloading Notification Banner -->
      <div
        v-if="activeDownloads.length > 0"
        class="p-4 rounded-2xl bg-gradient-to-r from-sky-500/10 via-accent/10 to-emerald-500/10 border border-sky-500/30 text-neutral-200 shadow-lg relative overflow-hidden"
      >
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-start md:items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-sky-500/20 border border-sky-500/40 flex items-center justify-center flex-shrink-0 text-sky-400">
              <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-sky-400">Canlı Model İndirme</span>
                <span class="w-2 h-2 rounded-full bg-sky-400 animate-ping"></span>
              </div>
              <div class="text-sm font-semibold text-neutral-100 mt-0.5">
                <span v-for="(ad, idx) in activeDownloads" :key="ad.id">
                  {{ getModelName(ad.model_id) }} (%{{ Number(ad.progress || 0).toFixed(1) }}){{ idx < activeDownloads.length - 1 ? ', ' : '' }}
                </span>
              </div>
              <p class="text-[11px] text-neutral-400 mt-0.5">
                İndirme arka planda çalışıyor. Arayüz donmaz, diğer sayfaları kullanabilir veya bu sayfada ilerlemeyi canlı izleyebilirsiniz.
              </p>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
              <div class="text-xs font-mono font-bold text-sky-300">
                {{ formatBytes(primaryActiveDownload?.downloaded_bytes) }} / {{ formatBytes(primaryActiveDownload?.total_bytes) }}
              </div>
              <div class="text-[10px] text-neutral-400">Aktarılan Veri</div>
            </div>
            <button
              @click="fetchDownloads(true)"
              class="text-xs px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 flex items-center gap-1.5 transition-colors"
            >
              <svg :class="['w-3.5 h-3.5', { 'animate-spin': isRefreshing }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>Güncelle</span>
            </button>
          </div>
        </div>

        <!-- Global Progress Bar for Primary Download -->
        <div v-if="primaryActiveDownload" class="mt-3">
          <div class="w-full h-1.5 bg-neutral-800/80 rounded-full overflow-hidden">
            <div
              class="h-full bg-gradient-to-r from-sky-400 via-accent to-emerald-400 rounded-full transition-all duration-300"
              :style="{ width: Math.min(100, Math.max(5, primaryActiveDownload.progress || 0)) + '%' }"
            ></div>
          </div>
        </div>
      </div>

      <!-- Header Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-base font-semibold text-neutral-100">Yapay Zeka Ses & Dil Modelleri</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-neutral-800 text-neutral-300 border border-neutral-700">
              {{ filteredModels.length }} Model Listelendi
            </span>
          </div>
          <p class="text-xs text-neutral-400 mt-1">
            HuggingFace ve yerel yapay zeka modellerini tek tıkla diskinize indirin. İndirme arka planda çalışır, arayüz donmaz.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <!-- Search Input -->
          <div class="relative min-w-[200px]">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Model ara..."
              class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl bg-neutral-900 border border-neutral-700 text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
            />
            <svg class="w-3.5 h-3.5 text-neutral-500 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Refresh Button -->
          <button
            @click="fetchDownloads(true)"
            :disabled="isRefreshing"
            class="text-xs px-3.5 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 flex items-center gap-1.5 transition-colors disabled:opacity-50"
          >
            <svg :class="['w-3.5 h-3.5', { 'animate-spin': isRefreshing }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ isRefreshing ? 'Güncelleniyor...' : 'Durumu Güncelle' }}</span>
          </button>
        </div>
      </div>

      <!-- Filter Controls -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
        <!-- Category Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto text-xs pb-1 sm:pb-0">
          <button
            v-for="cat in categories"
            :key="cat.id"
            @click="activeCategory = cat.id"
            :class="[
              'px-3 py-1.5 rounded-lg font-medium transition-colors whitespace-nowrap',
              activeCategory === cat.id
                ? 'bg-accent text-bg font-semibold'
                : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/60'
            ]"
          >
            {{ cat.name }}
          </button>
        </div>

        <!-- Status Filter -->
        <div class="flex items-center gap-1 text-xs">
          <button
            v-for="st in statusFilters"
            :key="st.id"
            @click="activeStatusFilter = st.id"
            :class="[
              'px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors',
              activeStatusFilter === st.id
                ? 'bg-neutral-700 text-neutral-100 font-semibold'
                : 'text-neutral-500 hover:text-neutral-300'
            ]"
          >
            {{ st.name }}
          </button>
        </div>
      </div>

      <!-- Models Grid -->
      <div v-if="filteredModels.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="m in filteredModels"
          :key="m.id"
          :class="[
            'p-5 rounded-2xl bg-surface border flex flex-col justify-between space-y-4 transition-all duration-200',
            isDownloading(m)
              ? 'border-sky-500/50 shadow-md shadow-sky-500/5 ring-1 ring-sky-500/30'
              : isPending(m)
                ? 'border-amber-500/40 ring-1 ring-amber-500/20'
                : 'border-neutral-800 hover:border-neutral-700'
          ]"
        >
          <div>
            <!-- Top Header & Status Badge -->
            <div class="flex items-start justify-between gap-2">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <h3 class="font-bold text-sm text-neutral-100">{{ m.name }}</h3>
                  <span
                    :class="[
                      'px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider',
                      getTypeBadgeClass(m.type)
                    ]"
                  >
                    {{ m.type }}
                  </span>
                </div>
                <span class="text-xs font-mono text-neutral-500 block">{{ m.id }}</span>
              </div>

              <!-- Status Badge -->
              <span
                v-if="isInstalled(m)"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                <span>Yüklü</span>
              </span>

              <span
                v-else-if="isDownloading(m)"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-sky-500/15 text-sky-300 border border-sky-500/30 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 animate-spin text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>İndiriliyor: %{{ Math.round(getDownload(m.id)?.progress || 0) }}</span>
              </span>

              <span
                v-else-if="isPending(m)"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Kuyrukta</span>
              </span>

              <span
                v-else-if="isFailed(m)"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-500/15 text-rose-400 border border-rose-500/30 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Hatalı</span>
              </span>

              <span
                v-else
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-neutral-800 text-neutral-400 border border-neutral-700/60 flex-shrink-0"
              >
                İndirilmedi
              </span>
            </div>

            <!-- Description -->
            <p class="text-xs text-neutral-400 mt-2.5 line-clamp-2 leading-relaxed">
              {{ m.description }}
            </p>

            <!-- Metadata Info -->
            <div class="mt-3.5 space-y-1.5 text-xs text-neutral-400 bg-neutral-900/60 p-3 rounded-xl border border-neutral-800/80">
              <div class="flex items-center justify-between">
                <span class="text-neutral-500">Tahmini Boyut:</span>
                <span class="font-mono text-neutral-200">~{{ m.size_estimate_mb >= 1000 ? (m.size_estimate_mb / 1024).toFixed(1) + ' GB' : m.size_estimate_mb + ' MB' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-neutral-500">Desteklenen Diller:</span>
                <span class="text-neutral-300 truncate max-w-[170px]" :title="m.languages?.join(', ')">
                  {{ m.languages?.join(', ') || '-' }}
                </span>
              </div>
              <div v-if="m.ram_required_gb" class="flex items-center justify-between">
                <span class="text-neutral-500">Önerilen RAM:</span>
                <span class="font-mono text-neutral-200">{{ m.ram_required_gb }} GB+</span>
              </div>
            </div>

            <!-- Live Progress Bar Section (When Downloading) -->
            <div v-if="isDownloading(m)" class="mt-4 p-3 rounded-xl bg-sky-950/20 border border-sky-500/20 space-y-2">
              <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-sky-300 flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-ping"></span>
                  İndiriliyor...
                </span>
                <span class="font-mono font-bold text-sky-300">
                  %{{ Number(getDownload(m.id)?.progress || 0).toFixed(1) }}
                </span>
              </div>

              <!-- Animated Bar -->
              <div class="w-full h-3 bg-neutral-900 rounded-full overflow-hidden border border-sky-500/40 p-0.5 shadow-inner">
                <div
                  class="h-full bg-gradient-to-r from-accent via-sky-400 to-emerald-400 rounded-full transition-all duration-300 ease-out shadow-sm shadow-accent/50"
                  :style="{ width: Math.min(100, Math.max(6, Number(getDownload(m.id)?.progress || 0))) + '%' }"
                ></div>
              </div>

              <!-- Byte Counters -->
              <div class="flex items-center justify-between text-[11px] text-neutral-400 font-mono pt-0.5">
                <span>{{ formatBytes(getDownload(m.id)?.downloaded_bytes) }}</span>
                <span>/ {{ formatBytes(getDownload(m.id)?.total_bytes || (m.size_estimate_mb * 1024 * 1024)) }}</span>
              </div>
            </div>

            <!-- Pending In Queue Alert -->
            <div v-else-if="isPending(m)" class="mt-4 p-3 rounded-xl bg-amber-950/20 border border-amber-500/20 flex items-center gap-2.5 text-xs text-amber-300">
              <svg class="w-4 h-4 text-amber-400 flex-shrink-0 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>İndirme kuyruğa alındı. Worker sırayla indirecek...</span>
            </div>

            <!-- Error Message Alert -->
            <div v-else-if="isFailed(m)" class="mt-4 p-3 rounded-xl bg-rose-950/20 border border-rose-500/20 text-xs text-rose-300">
              <div class="flex items-center gap-2 font-semibold text-rose-200">
                <svg class="w-4 h-4 text-rose-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>İndirme Tamamlanamadı</span>
              </div>
              <p class="text-[11px] text-rose-300/80 mt-1 line-clamp-2">
                {{ getDownload(m.id)?.error_message || 'Ağ bağlantısı veya dosya yazma hatası oluştu.' }}
              </p>
            </div>
          </div>

          <!-- Bottom Action Buttons -->
          <div class="pt-3 border-t border-neutral-800 flex items-center justify-between">
            <!-- Installed / Ready State -->
            <div
              v-if="isInstalled(m)"
              class="w-full py-2 text-center text-xs text-emerald-400 font-semibold bg-emerald-500/10 rounded-xl border border-emerald-500/20 flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
              <span>Kullanıma Hazır</span>
            </div>

            <!-- Downloading State (Disabled Button) -->
            <button
              v-else-if="isDownloading(m)"
              disabled
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-sky-500/20 border border-sky-500/30 text-sky-200 cursor-not-allowed flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 animate-spin text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>İndiriliyor... (%{{ Math.round(getDownload(m.id)?.progress || 0) }})</span>
            </button>

            <!-- Pending State (Disabled Button) -->
            <button
              v-else-if="isPending(m)"
              disabled
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-amber-500/20 border border-amber-500/30 text-amber-200 cursor-not-allowed flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Kuyrukta Bekliyor...</span>
            </button>

            <!-- Failed State (Retry Button) -->
            <button
              v-else-if="isFailed(m)"
              @click="download(m.id)"
              :disabled="submittingModelId === m.id"
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 border border-rose-500/40 transition-colors flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 text-rose-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>{{ submittingModelId === m.id ? 'Başlatılıyor...' : 'Tekrar İndirmeyi Dene' }}</span>
            </button>

            <!-- Not Downloaded State (Active Download Button) -->
            <button
              v-else
              @click="download(m.id)"
              :disabled="submittingModelId === m.id"
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-accent text-bg hover:opacity-90 transition-opacity flex items-center justify-center gap-2 shadow-sm disabled:opacity-50"
            >
              <svg v-if="submittingModelId === m.id" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              <span>{{ submittingModelId === m.id ? 'İndirme Başlatılıyor...' : 'Modeli İndir' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="p-12 text-center rounded-2xl bg-surface border border-neutral-800">
        <svg class="w-10 h-10 text-neutral-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="text-sm font-semibold text-neutral-300">Model Bulunamadı</div>
        <p class="text-xs text-neutral-500 mt-1">Arama veya filtre kriterlerinize uygun model bulunamadı.</p>
        <button
          @click="activeCategory = 'all'; activeStatusFilter = 'all'; searchQuery = ''"
          class="mt-4 text-xs px-3 py-1.5 rounded-lg bg-neutral-800 text-neutral-200 hover:bg-neutral-700"
        >
          Filtreleri Temizle
        </button>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

const props = defineProps({
  availableModels: {
    type: Array,
    default: () => []
  },
  downloads: {
    type: Array,
    default: () => []
  },
})

// Reactive local state
const modelsList = ref([...props.availableModels])
const downloadsList = ref([...(props.downloads || [])])
const isRefreshing = ref(false)
const submittingModelId = ref(null)

// Filtering & Search
const activeCategory = ref('all')
const activeStatusFilter = ref('all')
const searchQuery = ref('')

const categories = [
  { id: 'all', name: 'Tümü' },
  { id: 'tts', name: 'TTS (Metin Okuma)' },
  { id: 'stt', name: 'STT (Sesten Metne)' },
  { id: 'music', name: 'Müzik Üretimi' },
  { id: 'llm', name: 'Dil Modelleri (LLM)' },
]

const statusFilters = [
  { id: 'all', name: 'Tüm Durumlar' },
  { id: 'installed', name: 'Yüklü' },
  { id: 'in_progress', name: 'İndirilenler' },
  { id: 'not_installed', name: 'İndirilmemiş' },
]

// Download record lookup
const getDownload = (modelId) => {
  return downloadsList.value.find(d => d.model_id === modelId)
}

const isInstalled = (m) => {
  if (m.is_downloaded) return true
  const dl = getDownload(m.id)
  return dl && dl.status === 'completed'
}

const isDownloading = (m) => {
  if (isInstalled(m)) return false
  const dl = getDownload(m.id)
  return dl && dl.status === 'downloading'
}

const isPending = (m) => {
  if (isInstalled(m)) return false
  const dl = getDownload(m.id)
  return dl && dl.status === 'pending'
}

const isFailed = (m) => {
  if (isInstalled(m)) return false
  const dl = getDownload(m.id)
  return dl && dl.status === 'failed'
}

const activeDownloads = computed(() => {
  return downloadsList.value.filter(d => {
    const targetModel = modelsList.value.find(m => m.id === d.model_id)
    if (targetModel && targetModel.is_downloaded) return false
    return d.status === 'downloading' || d.status === 'pending'
  })
})

const primaryActiveDownload = computed(() => {
  return activeDownloads.value.find(d => d.status === 'downloading') || activeDownloads.value[0] || null
})

const hasActiveDownloads = computed(() => {
  return activeDownloads.value.length > 0
})

const getModelName = (modelId) => {
  const m = modelsList.value.find(item => item.id === modelId)
  return m ? m.name : modelId
}

const getTypeBadgeClass = (type) => {
  switch (type) {
    case 'tts':
      return 'bg-indigo-500/15 text-indigo-400 border border-indigo-500/30'
    case 'stt':
      return 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
    case 'music':
      return 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
    case 'llm':
      return 'bg-purple-500/15 text-purple-400 border border-purple-500/30'
    default:
      return 'bg-neutral-800 text-neutral-400'
  }
}

const formatBytes = (bytes, decimals = 1) => {
  if (!bytes || bytes <= 0) return '0 B'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

// Filtered models list
const filteredModels = computed(() => {
  return modelsList.value.filter(m => {
    // Category match
    if (activeCategory.value !== 'all' && m.type !== activeCategory.value) {
      return false
    }

    // Status filter match
    if (activeStatusFilter.value === 'installed' && !isInstalled(m)) {
      return false
    }
    if (activeStatusFilter.value === 'in_progress' && !isDownloading(m) && !isPending(m)) {
      return false
    }
    if (activeStatusFilter.value === 'not_installed' && (isInstalled(m) || isDownloading(m) || isPending(m))) {
      return false
    }

    // Search query match
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      const matchName = m.name?.toLowerCase().includes(q)
      const matchId = m.id?.toLowerCase().includes(q)
      const matchDesc = m.description?.toLowerCase().includes(q)
      const matchLang = m.languages?.some(l => l.toLowerCase().includes(q))
      if (!matchName && !matchId && !matchDesc && !matchLang) return false
    }

    return true
  })
})

// Fetch live downloads & models from API
const fetchDownloads = async (isManual = false) => {
  if (isManual) isRefreshing.value = true
  try {
    const res = await fetch('/api/models/downloads', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      }
    })
    if (res.ok) {
      const data = await res.json()
      if (Array.isArray(data.availableModels)) {
        modelsList.value = data.availableModels
      }
      if (Array.isArray(data.downloads)) {
        downloadsList.value = data.downloads
      }
    }
  } catch (err) {
    console.error('Model indirme durumu alınırken hata:', err)
  } finally {
    if (isManual) isRefreshing.value = false
  }
}

// Download action with instant optimistic feedback
const download = (modelId) => {
  const model = modelsList.value.find(m => m.id === modelId)
  const modelName = model ? model.name : modelId

  if (!confirm(`${modelName} modelini yerel diskinize indirmek istiyor musunuz?`)) {
    return
  }

  submittingModelId.value = modelId

  // Optimistic update
  const existingIndex = downloadsList.value.findIndex(d => d.model_id === modelId)
  const initialRecord = {
    id: Date.now(),
    model_id: modelId,
    status: 'pending',
    progress: 1,
    downloaded_bytes: 0,
    total_bytes: (model?.size_estimate_mb || 1000) * 1024 * 1024,
    error_message: null
  }

  if (existingIndex >= 0) {
    downloadsList.value[existingIndex] = {
      ...downloadsList.value[existingIndex],
      ...initialRecord
    }
  } else {
    downloadsList.value.unshift(initialRecord)
  }

  window.dispatchEvent(new CustomEvent('model-download-started'))

  router.post(`/models/download/${modelId}`, {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      submittingModelId.value = null
      window.dispatchEvent(new CustomEvent('model-download-started'))
      fetchDownloads()
    },
    onError: () => {
      submittingModelId.value = null
      fetchDownloads()
    },
    onFinish: () => {
      submittingModelId.value = null
    }
  })
}

// Live Adaptive Polling
let pollTimer = null

const runAdaptivePoll = async () => {
  await fetchDownloads()
  // If downloads are active, poll every 1.5s; otherwise 5s
  const nextInterval = hasActiveDownloads.value ? 1500 : 5000
  pollTimer = setTimeout(runAdaptivePoll, nextInterval)
}

onMounted(() => {
  pollTimer = setTimeout(runAdaptivePoll, 1000)
})

onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
})
</script>
