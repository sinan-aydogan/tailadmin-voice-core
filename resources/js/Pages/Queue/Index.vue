<template>
  <AppLayout title="İşlem Kuyruğu">
    <div class="space-y-6">
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex items-center justify-between">
        <div>
          <h2 class="text-base font-semibold text-neutral-100">Kuyruk Durumu</h2>
          <p class="text-xs text-neutral-400 mt-1">
            Tüm asenkron TTS ve STT işlemleri burada listelenir. Arka plan worker'ı işleri sırayla tüketir.
          </p>
        </div>
        <div class="flex items-center gap-3">
          <button @click="refresh" class="text-xs px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200">
            Yenile
          </button>
        </div>
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
              <tr v-if="tasks.data?.length === 0">
                <td colspan="6" class="px-6 py-12 text-center text-sm text-neutral-500">
                  Kuyrukta işlem bulunmuyor.
                </td>
              </tr>
              <tr v-for="t in tasks.data" :key="t.id" class="hover:bg-neutral-800/30 transition-colors">
                <td class="px-6 py-4 font-mono text-xs text-neutral-500">#{{ t.id }}</td>
                <td class="px-6 py-4">
                  <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase"
                        :class="t.type === 'tts' ? 'bg-accent/20 text-accent-300' : 'bg-info-500/20 text-info-400'">
                    {{ t.type }}
                  </span>
                </td>
                <td class="px-6 py-4 max-w-xs">
                  <div class="truncate text-xs text-neutral-200 font-medium">
                    {{ t.payload?.text || t.payload?.original_name }}
                  </div>
                  <div class="text-[11px] text-neutral-500 mt-0.5">
                    Motor: {{ t.payload?.engine || 'whisper' }}
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span class="px-2.5 py-1 rounded-full text-xs font-semibold uppercase"
                        :class="{
                          'bg-success-500/10 text-success-500': t.status === 'completed',
                          'bg-warning-500/10 text-warning-500 animate-pulse': t.status === 'running' || t.status === 'pending',
                          'bg-danger-500/10 text-danger-500': t.status === 'failed',
                        }">
                    {{ t.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-xs text-neutral-400">
                  {{ new Date(t.created_at).toLocaleString('tr-TR') }}
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <audio v-if="t.status === 'completed' && t.payload?.filename"
                           controls
                           class="h-7 w-44"
                           :src="'/api/audio/' + t.payload.filename"></audio>
                    <button
                      @click="deleteTask(t.id)"
                      class="p-1.5 rounded-lg text-neutral-500 hover:text-danger-500 hover:bg-neutral-800 transition-colors"
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
  </AppLayout>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

const props = defineProps({
  tasks: Object,
})

let timer = null

const refresh = () => {
  router.reload({ only: ['tasks'] })
}

onMounted(() => {
  timer = setInterval(() => {
    // Only auto-reload if there are pending or running tasks
    const hasActive = props.tasks?.data?.some(t => t.status === 'pending' || t.status === 'running')
    if (hasActive) {
      refresh()
    }
  }, 3000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

const deleteTask = (id) => {
  if (confirm('Bu işlemi silmek istediğinize emin misiniz?')) {
    router.delete(`/queue/${id}`)
  }
}
</script>
