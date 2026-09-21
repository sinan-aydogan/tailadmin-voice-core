<template>
  <AppLayout :title="t('documentation.title', 'API Dokümantasyonu')">
    <div class="space-y-4">
      <!-- Top Action & Info Bar -->
      <div class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-2xl bg-surface border border-neutral-800 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-accent-500/10 border border-accent-500/20 flex items-center justify-center text-accent-400">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-base font-semibold text-neutral-100">{{ t('documentation.heading', 'TailAdmin Voice Core API') }}</h2>
              <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-accent-500/15 text-accent-300 border border-accent-500/30">OAS 3.0</span>
            </div>
            <p class="text-xs text-neutral-400">
              {{ t('documentation.base_url', 'API Ana Adresi') }}:
              <code class="px-1.5 py-0.5 rounded bg-neutral-900 font-mono text-neutral-300">{{ apiUrl }}</code>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            @click="reloadIframe"
            class="px-3 py-1.5 text-xs font-medium rounded-xl bg-neutral-800 text-neutral-300 hover:text-white hover:bg-neutral-700 border border-neutral-700/80 transition-colors flex items-center gap-1.5"
            :title="t('documentation.reload', 'Dokümantasyonu Yenile')"
          >
            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isReloading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ t('documentation.reload_btn', 'Yenile') }}</span>
          </button>

          <a
            :href="docsJsonUrl"
            target="_blank"
            download="openapi.json"
            class="px-3 py-1.5 text-xs font-medium rounded-xl bg-neutral-800 text-neutral-300 hover:text-white hover:bg-neutral-700 border border-neutral-700/80 transition-colors flex items-center gap-1.5"
            :title="t('documentation.download_json', 'OpenAPI JSON İndir')"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>JSON</span>
          </a>

          <button
            type="button"
            @click="openInBrowser"
            class="px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-accent hover:bg-accent-400 text-neutral-950 transition-colors flex items-center gap-1.5 shadow-sm"
          >
            <span>{{ t('documentation.open_browser', 'Tarayıcıda Aç') }}</span>
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Swagger UI Embedded Frame -->
      <div class="h-[calc(100vh-14rem)] min-h-[600px] rounded-2xl border border-neutral-800 bg-[#0f0f11] overflow-hidden shadow-xl shadow-black/30 relative">
        <iframe
          ref="iframeRef"
          :src="docsUrl"
          title="Swagger API Dokümantasyonu"
          class="w-full h-full border-0 bg-[#0f0f11]"
          allow="clipboard-write"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import AppLayout from '../../Layouts/AppLayout.vue'
import { useI18n } from '../../i18n'

const props = defineProps({
  swaggerUrl: {
    type: String,
    default: '/api/documentation'
  },
  apiUrl: {
    type: String,
    default: '/api/v1'
  },
  docsJsonUrl: {
    type: String,
    default: '/docs'
  },
  hasApiKeyAuth: {
    type: Boolean,
    default: false
  },
  activeKeysCount: {
    type: Number,
    default: 0
  }
})

const { t } = useI18n()

const iframeRef = ref(null)
const isReloading = ref(false)

const docsUrl = computed(() => props.swaggerUrl || '/api/documentation')

const reloadIframe = () => {
  if (iframeRef.value) {
    isReloading.value = true
    const currentSrc = iframeRef.value.src
    iframeRef.value.src = ''
    setTimeout(() => {
      if (iframeRef.value) {
        iframeRef.value.src = currentSrc
        isReloading.value = false
      }
    }, 150)
  }
}

const openInBrowser = async () => {
  const url = props.swaggerUrl || '/api/documentation'
  if (typeof window !== 'undefined' && window.Native) {
    try {
      await axios.post('/api/system/open-url', { url })
      return
    } catch (e) {
      console.warn('Native openExternal failed, falling back to window.open', e)
    }
  }
  window.open(url, '_blank', 'noopener,noreferrer')
}
</script>
