<template>
  <footer class="h-9 border-t border-neutral-800 bg-surface/80 backdrop-blur flex items-center justify-between px-4 text-xs text-neutral-400 select-none sticky bottom-0 z-30">
    <!-- Left: Hardware Resource Stats -->
    <div class="flex items-center gap-4">
      <!-- CPU -->
      <div class="flex items-center gap-1.5" title="CPU Kullanımı">
        <span class="font-medium text-neutral-300">CPU</span>
        <span :class="getPctClass(stats.cpu_pct)">{{ stats.cpu_pct }}%</span>
      </div>

      <span class="text-neutral-700">|</span>

      <!-- RAM -->
      <div class="flex items-center gap-1.5" title="RAM Kullanımı">
        <span class="font-medium text-neutral-300">RAM</span>
        <span :class="getPctClass(stats.ram?.pct)">
          {{ stats.ram?.used_gb || 0 }} / {{ stats.ram?.total_gb || 0 }} GB ({{ stats.ram?.pct || 0 }}%)
        </span>
      </div>

      <span class="text-neutral-700">|</span>

      <!-- Disk -->
      <div class="flex items-center gap-1.5" title="Modeller Diski">
        <span class="font-medium text-neutral-300">Disk</span>
        <span :class="getPctClass(stats.disk?.pct)">
          {{ stats.disk?.used_gb || 0 }} / {{ stats.disk?.total_gb || 0 }} GB ({{ stats.disk?.pct || 0 }}%)
        </span>
      </div>

      <span class="text-neutral-700">|</span>

      <!-- GPU / Accelerator -->
      <div class="flex items-center gap-1.5" title="Hızlandırıcı">
        <span class="font-medium text-neutral-300">Hızlandırıcı</span>
        <span class="text-accent-400 uppercase font-mono">{{ stats.gpu?.backend || 'CPU' }}</span>
        <span v-if="stats.gpu?.allocated_gb > 0" class="text-neutral-400">
          ({{ stats.gpu.allocated_gb }} GB)
        </span>
      </div>
    </div>

    <!-- Right: Operations Indicator & Upward Dropdown -->
    <div class="relative" ref="dropdownRef">
      <!-- Trigger Pill Button -->
      <button
        @click="toggleDropdown"
        type="button"
        class="flex items-center gap-2 px-2.5 py-1 rounded-xl transition-all duration-200 border text-xs select-none focus:outline-none focus:ring-1 focus:ring-accent/50 cursor-pointer shadow-sm"
        :class="{
          'bg-sky-500/15 border-sky-500/40 text-sky-200 hover:bg-sky-500/25 shadow-sky-500/10': isDownloadingModel,
          'bg-accent/15 border-accent/40 text-accent-200 hover:bg-accent/25 shadow-accent/10': hasActiveVoiceTask && !isDownloadingModel,
          'bg-amber-500/15 border-amber-500/40 text-amber-200 hover:bg-amber-500/25': !hasActiveOperation && queuedCount > 0,
          'bg-neutral-800/60 border-neutral-700/60 hover:border-neutral-600 hover:bg-neutral-800 text-neutral-300': !hasActiveOperation && queuedCount === 0,
          'ring-1 ring-accent/60': isDropdownOpen
        }"
        :title="triggerTooltip"
      >
        <!-- 1. Active Operation State -->
        <template v-if="hasActiveOperation">
          <!-- Animated Icon -->
          <div class="relative flex items-center justify-center flex-shrink-0">
            <!-- Model Download Icon -->
            <svg
              v-if="primaryOp?.type === 'model_download'"
              class="w-3.5 h-3.5 animate-bounce text-sky-400"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <!-- TTS Voice Generation Icon (Soundwaves) -->
            <svg
              v-else-if="primaryOp?.type === 'tts'"
              class="w-3.5 h-3.5 animate-pulse text-accent-400"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
            </svg>
            <!-- STT Transcribe Icon (Mic) -->
            <svg
              v-else-if="primaryOp?.type === 'stt'"
              class="w-3.5 h-3.5 animate-pulse text-cyan-400"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
            </svg>
            <!-- Fallback Spinner -->
            <svg v-else class="w-3.5 h-3.5 animate-spin text-accent-400" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
          </div>

          <!-- Operation Title / Details -->
          <div class="flex items-center gap-1.5 min-w-0">
            <span class="font-semibold truncate max-w-[140px] sm:max-w-[180px]">
              {{ primaryOp?.title }}
            </span>
            <span v-if="primaryOp?.progress > 0" class="font-mono text-[11px] font-bold" :class="isDownloadingModel ? 'text-sky-300' : 'text-accent-300'">
              %{{ Number(primaryOp.progress).toFixed(primaryOp.type === 'model_download' ? 1 : 0) }}
            </span>
          </div>

          <!-- Multi-task count indicator -->
          <span v-if="activeOperations.length > 1" class="text-[10px] text-neutral-400 font-medium">
            (+{{ activeOperations.length - 1 }})
          </span>
        </template>

        <!-- 2. No Active Operation, but Queue has waiting tasks -->
        <template v-else-if="queuedCount > 0">
          <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
          <span class="font-semibold text-amber-200">Kuyrukta Bekliyor</span>
        </template>

        <!-- 3. Idle State (No active, no queued) -->
        <template v-else>
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span class="text-neutral-300 font-medium">Voice Core Native</span>
        </template>

        <!-- Queue Count Badge (Always visible if queuedCount > 0) -->
        <span
          v-if="queuedCount > 0"
          class="ml-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/25 text-amber-300 border border-amber-500/40 flex items-center gap-1 shadow-sm"
          title="Kuyrukta bekleyen işlem sayısı"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
          <span>{{ queuedCount }} Kuyrukta</span>
        </span>

        <!-- Chevron Up/Down Icon -->
        <svg
          :class="['w-3 h-3 text-neutral-400 transition-transform duration-200', isDropdownOpen ? 'rotate-180 text-accent-400' : '']"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
        </svg>
      </button>

      <!-- Upward Opening Dropdown (Dropup Popover) -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-3 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-3 scale-95"
      >
        <div
          v-if="isDropdownOpen"
          class="absolute bottom-full right-0 mb-2.5 w-96 sm:w-[440px] max-h-[520px] rounded-2xl bg-neutral-900/95 backdrop-blur-xl border border-neutral-700/80 shadow-2xl shadow-black/90 flex flex-col z-50 overflow-hidden text-neutral-200 text-xs"
        >
          <!-- Dropdown Header -->
          <div class="px-4 py-3 border-b border-neutral-800 bg-neutral-950/60 flex items-center justify-between gap-2 flex-shrink-0">
            <div class="flex items-center gap-2">
              <span class="font-bold text-neutral-100 text-sm">İşlemler & Kuyruk</span>
              <span
                v-if="activeCount > 0"
                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-accent/20 text-accent-300 border border-accent/30"
              >
                {{ activeCount }} Aktif
              </span>
              <span
                v-if="queuedCount > 0"
                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30"
              >
                {{ queuedCount }} Sırada
              </span>
            </div>

            <div class="flex items-center gap-2">
              <!-- Worker status indicator -->
              <span
                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium border"
                :class="isWorkerRunning ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30'"
                :title="isWorkerRunning ? 'Queue worker arka planda aktif' : 'Queue worker çalışmıyor'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="isWorkerRunning ? 'bg-emerald-400 animate-pulse' : 'bg-red-400'"></span>
                <span>{{ isWorkerRunning ? 'Worker Aktif' : 'Worker Kapalı' }}</span>
              </span>

              <!-- Refresh Button -->
              <button
                @click="fetchOperations(true)"
                :disabled="isFetchingOps"
                class="p-1 rounded-lg text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 transition-colors disabled:opacity-50 cursor-pointer"
                title="Şimdi Yenile"
              >
                <svg :class="['w-3.5 h-3.5', { 'animate-spin': isFetchingOps }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </button>

              <!-- Close Button -->
              <button
                @click="isDropdownOpen = false"
                class="p-1 rounded-lg text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 transition-colors cursor-pointer"
                title="Kapat"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Filter / Tab Switcher -->
          <div class="px-4 pt-2 border-b border-neutral-800/80 bg-neutral-900/40 flex items-center gap-2 flex-shrink-0">
            <button
              @click="activeTab = 'active_queued'"
              :class="[
                'pb-2 px-1 text-xs font-semibold border-b-2 transition-colors cursor-pointer',
                activeTab === 'active_queued'
                  ? 'border-accent text-accent-300'
                  : 'border-transparent text-neutral-400 hover:text-neutral-200'
              ]"
            >
              Aktif & Kuyruk ({{ activeCount + queuedCount }})
            </button>
            <button
              @click="activeTab = 'recent'"
              :class="[
                'pb-2 px-1 text-xs font-semibold border-b-2 transition-colors cursor-pointer',
                activeTab === 'recent'
                  ? 'border-accent text-accent-300'
                  : 'border-transparent text-neutral-400 hover:text-neutral-200'
              ]"
            >
              Son İşlemler ({{ recentOperations.length }})
            </button>
          </div>

          <!-- Dropdown Body: Scrollable Task List -->
          <div class="flex-1 overflow-y-auto p-4 space-y-4 max-h-[360px] custom-scrollbar">

            <!-- TAB 1: Aktif & Kuyruktaki İşlemler -->
            <div v-if="activeTab === 'active_queued'" class="space-y-4">
              <!-- Empty State -->
              <div
                v-if="activeOperations.length === 0 && queuedOperations.length === 0"
                class="py-8 text-center text-neutral-500 space-y-2"
              >
                <div class="w-10 h-10 rounded-2xl bg-neutral-800/60 border border-neutral-700/50 flex items-center justify-center mx-auto text-neutral-400">
                  <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <div class="text-xs font-medium text-neutral-300">Şu anda çalışan veya kuyrukta bekleyen işlem yok</div>
                <div class="text-[11px] text-neutral-500 max-w-xs mx-auto">
                  Metin Okuma (TTS), Sesten Metne (STT) veya Model İndirme başlattığınızda burada canlı olarak görünecektir.
                </div>
              </div>

              <!-- 1. Aktif İşlemler Section -->
              <div v-if="activeOperations.length > 0" class="space-y-2">
                <div class="text-[11px] font-bold uppercase tracking-wider text-neutral-400 flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-accent-400 animate-ping"></span>
                  <span>Şu Anda Yürütülen İşlemler ({{ activeOperations.length }})</span>
                </div>

                <div class="space-y-2">
                  <div
                    v-for="op in activeOperations"
                    :key="op.id"
                    class="p-3 rounded-xl border transition-all"
                    :class="op.type === 'model_download'
                      ? 'bg-sky-500/10 border-sky-500/30'
                      : 'bg-accent/10 border-accent/30'"
                  >
                    <!-- Header of Active Card -->
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex items-center gap-2 min-w-0">
                        <!-- Type Badge -->
                        <span
                          class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider flex-shrink-0"
                          :class="op.type === 'model_download'
                            ? 'bg-sky-500/20 text-sky-300 border border-sky-500/30'
                            : (op.type === 'tts' ? 'bg-accent/20 text-accent-300 border border-accent/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30')"
                        >
                          {{ op.type === 'model_download' ? 'Model İndirme' : (op.type === 'tts' ? 'Ses Üretimi' : 'Metin Deşifre') }}
                        </span>

                        <div class="font-semibold text-neutral-100 text-xs truncate">
                          {{ op.title }}
                        </div>
                      </div>

                      <div class="flex items-center gap-1.5 flex-shrink-0">
                        <span class="font-mono text-xs font-bold" :class="op.type === 'model_download' ? 'text-sky-300' : 'text-accent-300'">
                          %{{ Number(op.progress || 0).toFixed(op.type === 'model_download' ? 1 : 0) }}
                        </span>
                        <svg class="w-3.5 h-3.5 animate-spin text-neutral-400" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                      </div>
                    </div>

                    <!-- Detail text -->
                    <div class="text-[11px] text-neutral-300/90 mt-1 line-clamp-2 break-words">
                      {{ op.detail }}
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-2 w-full h-1.5 bg-neutral-800 rounded-full overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="op.type === 'model_download' ? 'bg-gradient-to-r from-sky-400 to-emerald-400' : 'bg-gradient-to-r from-accent via-indigo-400 to-emerald-400'"
                        :style="{ width: Math.min(100, Math.max(5, op.progress || 0)) + '%' }"
                      ></div>
                    </div>

                    <!-- Extra Info Footer -->
                    <div class="mt-2 flex items-center justify-between text-[10px] text-neutral-400">
                      <span v-if="op.type === 'model_download' && op.total_bytes > 0">
                        {{ formatBytes(op.downloaded_bytes) }} / {{ formatBytes(op.total_bytes) }}
                      </span>
                      <span v-else-if="op.engine">
                        Motor: {{ op.engine }} {{ op.language ? '· Dil: ' + op.language : '' }}
                      </span>
                      <span v-else>İşleniyor...</span>

                      <button
                        @click="cancelItem(op)"
                        class="text-neutral-500 hover:text-red-400 transition-colors flex items-center gap-1 cursor-pointer"
                        title="İşlemi iptal et"
                      >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span>İptal Et</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 2. Kuyruktaki İşlemler Section -->
              <div v-if="queuedOperations.length > 0" class="space-y-2">
                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                  <span>Kuyruk Sırası ({{ queuedOperations.length }} İşlem Bekliyor)</span>
                </div>

                <div class="space-y-1.5">
                  <div
                    v-for="(item, index) in queuedOperations"
                    :key="item.id"
                    class="p-2.5 rounded-xl bg-neutral-950/60 border border-neutral-800 hover:border-neutral-700 transition-colors flex items-center justify-between gap-3"
                  >
                    <div class="flex items-center gap-2 min-w-0">
                      <!-- Queue Order Number -->
                      <span class="w-5 h-5 rounded-full bg-neutral-800 text-neutral-300 font-mono font-bold text-[10px] flex items-center justify-center flex-shrink-0">
                        #{{ index + 1 }}
                      </span>

                      <!-- Type Badge -->
                      <span
                        class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider flex-shrink-0"
                        :class="item.type === 'model_download'
                          ? 'bg-sky-500/20 text-sky-400'
                          : (item.type === 'tts' ? 'bg-accent/20 text-accent-300' : 'bg-cyan-500/20 text-cyan-300')"
                      >
                        {{ item.type === 'model_download' ? 'MODEL' : (item.type === 'tts' ? 'TTS' : 'STT') }}
                      </span>

                      <!-- Title & Details -->
                      <div class="min-w-0">
                        <div class="font-medium text-neutral-200 text-xs truncate">
                          {{ item.title }}
                        </div>
                        <div class="text-[10px] text-neutral-500 truncate max-w-[200px]">
                          {{ item.detail }}
                        </div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <span class="w-1 h-1 rounded-full bg-amber-400 animate-pulse"></span>
                        <span>Sırada</span>
                      </span>

                      <!-- Cancel Button -->
                      <button
                        @click="cancelItem(item)"
                        class="p-1 rounded text-neutral-500 hover:text-red-400 hover:bg-neutral-800 transition-colors cursor-pointer"
                        title="Kuyruktan Çıkar"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 2: Son Tamamlanan / Başarısız İşlemler -->
            <div v-else class="space-y-2">
              <div v-if="recentOperations.length === 0" class="py-6 text-center text-neutral-500 text-xs">
                Kayıtlı son işlem bulunmuyor.
              </div>

              <div
                v-for="rec in recentOperations"
                :key="rec.id"
                class="p-2.5 rounded-xl bg-neutral-950/50 border border-neutral-800/80 flex items-center justify-between gap-3"
              >
                <div class="flex items-center gap-2.5 min-w-0">
                  <!-- Status Icon -->
                  <div class="w-6 h-6 rounded-lg flex items-center justify-center flex-shrink-0"
                       :class="rec.status === 'completed' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400'">
                    <svg v-if="rec.status === 'completed'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </div>

                  <div class="min-w-0">
                    <div class="font-medium text-neutral-200 text-xs truncate">
                      {{ rec.detail || rec.title }}
                    </div>
                    <div class="text-[10px] text-neutral-500 flex items-center gap-2">
                      <span>{{ rec.title }}</span>
                      <span v-if="rec.completed_at">· {{ formatTimeAgo(rec.completed_at) }}</span>
                    </div>
                    <div v-if="rec.status === 'failed' && rec.error_message" class="text-[10px] text-red-400 truncate mt-0.5">
                      {{ rec.error_message }}
                    </div>
                  </div>
                </div>

                <!-- Actions: Audio Play & Download -->
                <div class="flex items-center gap-1.5 flex-shrink-0">
                  <button
                    v-if="rec.audio_url"
                    @click="playAudio(rec.audio_url, rec.id)"
                    class="p-1.5 rounded-lg transition-colors cursor-pointer"
                    :class="playingTaskId === rec.id ? 'bg-accent text-bg font-bold' : 'text-neutral-400 hover:text-accent hover:bg-neutral-800'"
                    :title="playingTaskId === rec.id ? 'Durdur' : 'Sesi Dinle'"
                  >
                    <!-- Pause icon -->
                    <svg v-if="playingTaskId === rec.id" class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                      <path d="M6 4h4v16H6zm8 0h4v16h-4z" />
                    </svg>
                    <!-- Play icon -->
                    <svg v-else class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                      <path d="M8 5v14l11-7z" />
                    </svg>
                  </button>

                  <a
                    v-if="rec.audio_url"
                    :href="rec.audio_url"
                    download
                    class="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800 transition-colors"
                    title="İndir"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </a>
                </div>
              </div>
            </div>

          </div>

          <!-- Dropdown Footer with Quick Navigation Links -->
          <div class="px-4 py-2.5 border-t border-neutral-800 bg-neutral-950/70 flex items-center justify-between text-[11px] text-neutral-400 flex-shrink-0">
            <Link
              href="/queue"
              @click="isDropdownOpen = false"
              class="hover:text-accent-300 flex items-center gap-1 font-medium transition-colors"
            >
              <span>Tüm İşlem Kuyruğu</span>
              <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </Link>

            <Link
              href="/models"
              @click="isDropdownOpen = false"
              class="hover:text-sky-300 flex items-center gap-1 font-medium transition-colors"
            >
              <span>Model Yöneticisi</span>
              <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>
        </div>
      </Transition>
    </div>
  </footer>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'

// Hardware stats
const stats = ref({
  cpu_pct: 0,
  ram: { used_gb: 0, total_gb: 0, pct: 0 },
  disk: { used_gb: 0, total_gb: 0, pct: 0 },
  gpu: { backend: 'cpu', allocated_gb: 0 }
})

// Operations state
const operationsData = ref({
  is_worker_running: true,
  active_count: 0,
  queued_count: 0,
  primary_operation: null,
  active_operations: [],
  queued_operations: [],
  recent_operations: []
})

// Dropdown & UI state
const isDropdownOpen = ref(false)
const dropdownRef = ref(null)
const activeTab = ref('active_queued')
const isFetchingOps = ref(false)

// Audio playback in dropdown
const playingTaskId = ref(null)
let currentAudioInstance = null

const toggleDropdown = () => {
  isDropdownOpen.value = !isDropdownOpen.value
  if (isDropdownOpen.value) {
    fetchOperations(true)
  }
}

// Computed helpers
const isWorkerRunning = computed(() => operationsData.value.is_worker_running ?? true)
const activeCount = computed(() => operationsData.value.active_count || 0)
const queuedCount = computed(() => operationsData.value.queued_count || 0)
const hasActiveOperation = computed(() => activeCount.value > 0)
const primaryOp = computed(() => operationsData.value.primary_operation || operationsData.value.active_operations?.[0] || null)
const activeOperations = computed(() => operationsData.value.active_operations || [])
const queuedOperations = computed(() => operationsData.value.queued_operations || [])
const recentOperations = computed(() => operationsData.value.recent_operations || [])

const isDownloadingModel = computed(() => primaryOp.value?.type === 'model_download')
const hasActiveVoiceTask = computed(() => primaryOp.value?.type === 'tts' || primaryOp.value?.type === 'stt')

const triggerTooltip = computed(() => {
  if (hasActiveOperation.value) {
    return `${primaryOp.value?.title || 'İşlem'} devam ediyor. ${queuedCount.value} işlem kuyrukta. Tıkla ve detayları gör.`
  }
  if (queuedCount.value > 0) {
    return `${queuedCount.value} işlem sırada bekliyor. Tıkla ve detayları gör.`
  }
  return 'Voice Core Native · Sistem Hazır (İşlem listesi için tıkla)'
})

// Fetch operations from consolidated API
const fetchOperations = async (isManual = false) => {
  if (isFetchingOps.value && !isManual) return
  if (typeof document !== 'undefined' && document.hidden && !isManual) return

  if (isManual) isFetchingOps.value = true
  try {
    const res = await fetch('/api/system/operations', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    if (res.ok) {
      const data = await res.json()
      operationsData.value = data
    }
  } catch (e) {
    // transient network ignore
  } finally {
    if (isManual) isFetchingOps.value = false
  }
}

// Cancel or delete operation
const cancelItem = async (item) => {
  const name = item.title || 'bu işlemi'
  if (!confirm(`"${name}" işlemini iptal etmek veya silmek istediğinize emin misiniz?`)) {
    return
  }
  try {
    const type = item.type === 'model_download' ? 'model_download' : 'task'
    const res = await fetch(`/api/system/operations/${type}/${item.raw_id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    if (res.ok) {
      await fetchOperations(true)
    }
  } catch (err) {
    console.error('İşlem iptal edilirken hata:', err)
  }
}

// Mini Audio Player for recent tasks
const playAudio = (url, taskId) => {
  if (playingTaskId.value === taskId && currentAudioInstance) {
    currentAudioInstance.pause()
    playingTaskId.value = null
    return
  }

  if (currentAudioInstance) {
    currentAudioInstance.pause()
  }

  currentAudioInstance = new Audio(url)
  playingTaskId.value = taskId
  currentAudioInstance.play().catch(e => {
    console.warn('Ses oynatılamadı:', e)
    playingTaskId.value = null
  })

  currentAudioInstance.onended = () => {
    playingTaskId.value = null
  }
  currentAudioInstance.onerror = () => {
    playingTaskId.value = null
  }
}

// Format utilities
const formatBytes = (bytes, decimals = 1) => {
  if (!bytes || bytes <= 0) return '0 B'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

const formatTimeAgo = (dateStr) => {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    const diffSec = Math.floor((Date.now() - d.getTime()) / 1000)
    if (diffSec < 60) return 'az önce'
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)} dk önce`
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)} sa önce`
    return d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })
  } catch (e) {
    return ''
  }
}

// System Hardware Stats Polling
let statsTimer = null
let isFetchingStats = false

const fetchStats = async () => {
  if (isFetchingStats || (typeof document !== 'undefined' && document.hidden)) return
  isFetchingStats = true
  try {
    const res = await fetch('/api/system/stats')
    if (res.ok) {
      stats.value = await res.json()
    }
  } catch (e) {
    // transient
  } finally {
    isFetchingStats = false
  }
}

const getPctClass = (pct) => {
  if (!pct) return 'text-neutral-300'
  if (pct >= 92) return 'text-danger-500 font-semibold'
  if (pct >= 80) return 'text-warning-500'
  return 'text-neutral-300'
}

// Click outside handler for dropdown
const handleClickOutside = (e) => {
  if (isDropdownOpen.value && dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isDropdownOpen.value = false
  }
}

// Adaptive live polling loop
let opsPollTimer = null

const runAdaptivePoll = async () => {
  await fetchOperations()
  // If active or queued tasks, poll every 1.8 seconds; otherwise 6 seconds
  const hasWork = (activeCount.value > 0 || queuedCount.value > 0 || isDropdownOpen.value)
  const nextInterval = hasWork ? 1800 : 6000
  opsPollTimer = setTimeout(runAdaptivePoll, nextInterval)
}

// Global window event triggers for instant feedback
const onTaskDispatched = () => fetchOperations(true)

onMounted(() => {
  fetchStats()
  statsTimer = setInterval(fetchStats, 10000)

  runAdaptivePoll()

  document.addEventListener('click', handleClickOutside)
  window.addEventListener('voice-task-created', onTaskDispatched)
  window.addEventListener('model-download-started', onTaskDispatched)
})

onUnmounted(() => {
  if (statsTimer) clearInterval(statsTimer)
  if (opsPollTimer) clearTimeout(opsPollTimer)
  document.removeEventListener('click', handleClickOutside)
  window.removeEventListener('voice-task-created', onTaskDispatched)
  window.removeEventListener('model-download-started', onTaskDispatched)

  if (currentAudioInstance) {
    currentAudioInstance.pause()
    currentAudioInstance = null
  }
})
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.2);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.25);
}
</style>
