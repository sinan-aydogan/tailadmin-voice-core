<template>
  <AppLayout title="Dashboard">
    <div class="space-y-6">
      <!-- Welcome Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex items-center justify-between">
        <div>
          <h2 class="text-xl font-bold text-neutral-100">Hoş Geldiniz</h2>
          <p class="text-sm text-neutral-400 mt-1">
            NativePHP, Inertia ve Laravel Kuyruk Mimarisi ile donmasız, izole yapay zeka ses işleme istasyonu.
          </p>
        </div>
        <div class="flex gap-3">
          <Link href="/tts" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-accent text-bg hover:opacity-90 transition-opacity">
            <span>Metin Okut</span>
          </Link>
          <Link href="/models" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-neutral-800 text-neutral-200 hover:bg-neutral-700 transition-colors border border-neutral-700">
            <span>Model İndir</span>
          </Link>
        </div>
      </div>

      <!-- Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-surface border border-neutral-800">
          <div class="text-xs font-medium text-neutral-400 uppercase tracking-wider">Toplam Görev</div>
          <div class="text-2xl font-bold text-neutral-100 mt-2">{{ metrics.total_tasks }}</div>
          <div class="text-xs text-neutral-500 mt-1">TTS & STT kuyruk kayıtları</div>
        </div>

        <div class="p-5 rounded-2xl bg-surface border border-neutral-800">
          <div class="text-xs font-medium text-neutral-400 uppercase tracking-wider">Tamamlanan</div>
          <div class="text-2xl font-bold text-success-500 mt-2">{{ metrics.completed_tasks }}</div>
          <div class="text-xs text-neutral-500 mt-1">Başarılı üretimler</div>
        </div>

        <div class="p-5 rounded-2xl bg-surface border border-neutral-800">
          <div class="text-xs font-medium text-neutral-400 uppercase tracking-wider">Bekleyen / Çalışan</div>
          <div class="text-2xl font-bold text-warning-500 mt-2">{{ metrics.pending_tasks }}</div>
          <div class="text-xs text-neutral-500 mt-1">Kuyruktaki işler</div>
        </div>

        <div class="p-5 rounded-2xl bg-surface border border-neutral-800">
          <div class="text-xs font-medium text-neutral-400 uppercase tracking-wider">Ses Profilleri</div>
          <div class="text-2xl font-bold text-accent-400 mt-2">{{ metrics.total_profiles }}</div>
          <div class="text-xs text-neutral-500 mt-1">Klonlanabilir sesler</div>
        </div>
      </div>

      <!-- Recent Tasks -->
      <div class="rounded-2xl bg-surface border border-neutral-800 overflow-hidden">
        <div class="p-5 border-b border-neutral-800 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-100">Son İşlemler</h3>
          <Link href="/queue" class="text-xs text-accent-400 hover:underline">Tümünü Gör</Link>
        </div>
        <div class="divide-y divide-neutral-800/60">
          <div v-if="recentTasks.length === 0" class="p-8 text-center text-sm text-neutral-500">
            Henüz yapılmış bir işlem bulunmuyor.
          </div>
          <div v-for="task in recentTasks" :key="task.id" class="p-4 px-6 flex items-center justify-between hover:bg-neutral-800/30 transition-colors">
            <div class="flex items-center gap-4">
              <span class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs uppercase"
                    :class="task.type === 'tts' ? 'bg-accent/20 text-accent-300' : 'bg-info-500/20 text-info-500'">
                {{ task.type }}
              </span>
              <div>
                <div class="text-sm font-medium text-neutral-200 line-clamp-1">
                  {{ task.payload?.text || task.payload?.original_name || 'İşlem #' + task.id }}
                </div>
                <div class="text-xs text-neutral-500 mt-0.5">
                  {{ new Date(task.created_at).toLocaleString('tr-TR') }} · Motor: {{ task.payload?.engine || 'whisper' }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <span class="px-2.5 py-1 rounded-full text-xs font-medium uppercase"
                    :class="{
                      'bg-success-500/10 text-success-500': task.status === 'completed',
                      'bg-warning-500/10 text-warning-500': task.status === 'running' || task.status === 'pending',
                      'bg-danger-500/10 text-danger-500': task.status === 'failed',
                    }">
                {{ task.status }}
              </span>
              <!-- Play Button (Opens Modal) -->
              <button
                v-if="task.status === 'completed' && (task.payload?.filename || task.result?.filename || task.output_path)"
                @click="openPlayerModal(task)"
                class="p-2 rounded-xl bg-neutral-800 hover:bg-accent/20 hover:text-accent text-neutral-300 transition-colors flex items-center justify-center group"
                title="Sesi Dinle"
              >
                <svg class="w-4 h-4 fill-current text-accent" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Custom Audio Player Modal -->
    <AudioPlayerModal
      :show="showPlayerModal"
      :task="selectedTask"
      @close="showPlayerModal = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import AudioPlayerModal from '../Components/AudioPlayerModal.vue'

defineProps({
  metrics: Object,
  recentTasks: Array,
})

const showPlayerModal = ref(false)
const selectedTask = ref(null)

const openPlayerModal = (task) => {
  selectedTask.value = task
  showPlayerModal.value = true
}
</script>
