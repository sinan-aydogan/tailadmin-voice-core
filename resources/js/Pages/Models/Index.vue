<template>
  <AppLayout title="Model Yöneticisi">
    <div class="space-y-6">
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex items-center justify-between">
        <div>
          <h2 class="text-base font-semibold text-neutral-100">Yapay Zeka Ses Modelleri</h2>
          <p class="text-xs text-neutral-400 mt-1">
            HuggingFace ve Piper modellerini yerel diskinize indirin. İndirme arka planda çalışır, arayüz donmaz.
          </p>
        </div>
        <button @click="reload" class="text-xs px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200">
          Durumu Güncelle
        </button>
      </div>

      <!-- Models Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="m in availableModels"
          :key="m.id"
          class="p-5 rounded-2xl bg-surface border border-neutral-800 flex flex-col justify-between space-y-4"
        >
          <div>
            <div class="flex items-start justify-between gap-2">
              <div>
                <h3 class="font-bold text-sm text-neutral-100">{{ m.name }}</h3>
                <span class="text-xs font-mono text-neutral-500">{{ m.id }}</span>
              </div>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase"
                :class="m.is_downloaded ? 'bg-success-500/10 text-success-500' : 'bg-neutral-800 text-neutral-400'"
              >
                {{ m.is_downloaded ? 'Yüklü' : 'İndirilmedi' }}
              </span>
            </div>

            <p class="text-xs text-neutral-400 mt-2 line-clamp-2">
              {{ m.description }}
            </p>

            <div class="flex items-center gap-2 mt-3 text-xs text-neutral-500">
              <span>Yaklaşık Boyut: ~{{ m.size_estimate_mb }} MB</span>
              <span>·</span>
              <span>Diller: {{ m.languages?.join(', ') }}</span>
            </div>
          </div>

          <!-- Download Action -->
          <div class="pt-3 border-t border-neutral-800 flex items-center justify-between">
            <button
              v-if="!m.is_downloaded"
              @click="download(m.id)"
              class="w-full py-2 rounded-xl text-xs font-semibold bg-accent text-bg hover:opacity-90 transition-opacity flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              <span>Modeli İndir</span>
            </button>
            <div v-else class="w-full py-2 text-center text-xs text-success-500 font-medium bg-success-500/5 rounded-xl border border-success-500/20">
              Kullanıma Hazır
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

defineProps({
  availableModels: Array,
  downloads: Array,
})

const download = (modelId) => {
  if (confirm(`${modelId} modelini indirmek istiyor musunuz?`)) {
    router.post(`/models/download/${modelId}`)
  }
}

const reload = () => {
  router.reload({ only: ['availableModels', 'downloads'] })
}
</script>
