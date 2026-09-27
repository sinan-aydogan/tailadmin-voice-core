<template>
  <div v-if="showUpdateModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <!-- Backdrop with blur -->
    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" @click="showUpdateModal = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
      <div class="relative w-full max-w-xl rounded-2xl bg-neutral-900 border border-neutral-700/80 shadow-2xl p-6 overflow-hidden text-neutral-200">
        <!-- Ambient decorative glow -->
        <div
          class="absolute -top-24 -right-24 w-60 h-60 rounded-full blur-3xl pointer-events-none"
          :class="updateInfo.has_update ? 'bg-emerald-500/20' : 'bg-accent/15'"
        ></div>

        <!-- Header -->
        <div class="flex items-start justify-between pb-4 border-b border-neutral-800 relative z-10">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-xl flex items-center justify-center border shrink-0"
              :class="updateInfo.has_update
                ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-400'
                : 'bg-accent/20 border-accent/40 text-accent'"
            >
              <svg v-if="updateInfo.has_update" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-semibold text-white flex items-center gap-2">
                {{ updateInfo.has_update ? 'Yeni Sürüm Mevcut!' : 'Sisteminiz Güncel' }}
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-mono border font-medium"
                  :class="updateInfo.has_update
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                    : 'bg-neutral-800 text-neutral-300 border-neutral-700'"
                >
                  {{ updateInfo.has_update ? `v${updateInfo.latest_version}` : `v${updateInfo.current_version}` }}
                </span>
              </h3>
              <p class="text-xs text-neutral-400 mt-0.5">
                {{ updateInfo.has_update
                  ? 'Uygulamanın yeni bir sürümü GitHub üzerinde yayınlandı.'
                  : 'TailAdmin Voice Core en son sürümünde çalışıyor.' }}
              </p>
            </div>
          </div>
          <button
            @click="showUpdateModal = false"
            class="p-1.5 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors cursor-pointer"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="mt-5 space-y-4 text-xs relative z-10">
          <!-- Versions Comparison Grid -->
          <div class="grid grid-cols-2 gap-3">
            <div class="p-3 rounded-xl bg-neutral-950/60 border border-neutral-800">
              <div class="text-[10px] text-neutral-500 font-medium">Yüklü Sürüm</div>
              <div class="font-mono text-sm font-semibold text-neutral-200 mt-0.5">v{{ updateInfo.current_version }}</div>
            </div>
            <div class="p-3 rounded-xl bg-neutral-950/60 border border-neutral-800">
              <div class="text-[10px] text-neutral-500 font-medium">GitHub Son Sürüm</div>
              <div
                class="font-mono text-sm font-semibold mt-0.5 flex items-center gap-1.5"
                :class="updateInfo.has_update ? 'text-emerald-400' : 'text-neutral-200'"
              >
                <span>v{{ updateInfo.latest_version || updateInfo.current_version }}</span>
                <span v-if="updateInfo.has_update" class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                  Yeni
                </span>
              </div>
            </div>
          </div>

          <!-- Release Information -->
          <div v-if="updateInfo.release_name" class="p-3.5 rounded-xl bg-neutral-800/60 border border-neutral-700/60 space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-neutral-200">{{ updateInfo.release_name }}</span>
              <span v-if="formattedDate" class="text-[10px] text-neutral-400 font-mono">{{ formattedDate }}</span>
            </div>
            <div
              v-if="renderedNotes"
              class="prose prose-invert prose-xs text-neutral-300 max-h-48 overflow-y-auto pr-1 mt-2 text-[11px] leading-relaxed"
              v-html="renderedNotes"
            ></div>
          </div>

          <div v-if="checkError" class="p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-xs">
            {{ checkError }}
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="mt-6 pt-4 border-t border-neutral-800 flex items-center justify-between gap-3 relative z-10">
          <button
            type="button"
            @click="checkForUpdates(true)"
            :disabled="isChecking"
            class="px-3 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 border border-neutral-700 text-neutral-300 hover:text-white transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50 text-xs font-medium"
          >
            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isChecking }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ isChecking ? 'Kontrol Ediliyor...' : 'Yeniden Kontrol Et' }}</span>
          </button>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="showUpdateModal = false"
              class="px-4 py-2 rounded-xl bg-neutral-800/80 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors cursor-pointer"
            >
              Kapat
            </button>
            <button
              v-if="updateInfo.has_update"
              type="button"
              @click="openReleaseUrl(updateInfo.release_url)"
              class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-neutral-950 text-xs font-semibold transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-1.5 cursor-pointer"
            >
              <span>GitHub'da İncele & İndir</span>
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import { useUpdateChecker } from '../Composables/useUpdateChecker'

const {
  updateInfo,
  isChecking,
  checkError,
  showUpdateModal,
  checkForUpdates,
  openReleaseUrl,
} = useUpdateChecker()

const formattedDate = computed(() => {
  if (!updateInfo.value.published_at) return ''
  try {
    return new Date(updateInfo.value.published_at).toLocaleDateString('tr-TR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return updateInfo.value.published_at
  }
})

const renderedNotes = computed(() => {
  if (!updateInfo.value.release_notes) return ''
  try {
    const rawHtml = marked.parse(updateInfo.value.release_notes)
    return DOMPurify.sanitize(rawHtml)
  } catch {
    return updateInfo.value.release_notes
  }
})
</script>
