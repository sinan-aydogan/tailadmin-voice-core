<template>
  <AppLayout title="Sesten Metne (STT)">
    <div class="space-y-6 max-w-4xl">
      <!-- Upload Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
        <h2 class="text-base font-semibold text-neutral-100">Ses Dosyası Deşifre Et (Whisper)</h2>

        <form @submit.prevent="submit" class="space-y-4">
          <!-- File Input -->
          <div>
            <label class="block text-xs font-medium text-neutral-400 mb-1.5">Ses Dosyası (WAV, MP3, M4A, OGG)</label>
            <input
              type="file"
              accept="audio/*"
              required
              @change="handleFileChange"
              class="block w-full text-sm text-neutral-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-accent file:text-bg hover:file:opacity-90 cursor-pointer bg-neutral-900 rounded-xl border border-neutral-700 p-2"
            />
          </div>

          <!-- Language -->
          <div class="w-48">
            <label class="block text-xs font-medium text-neutral-400 mb-1.5">Konuşulan Dil</label>
            <select
              v-model="form.language"
              class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
            >
              <option value="tr">Türkçe</option>
              <option value="en">İngilizce</option>
              <option value="de">Almanca</option>
              <option value="fr">Fransızca</option>
              <option value="auto">Otomatik Algıla</option>
            </select>
          </div>

          <div class="flex justify-end pt-2">
            <button
              type="submit"
              :disabled="form.processing || !form.audio"
              class="px-6 py-2.5 rounded-xl font-semibold text-sm bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2"
            >
              <span v-if="form.processing" class="w-4 h-4 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
              <span>{{ form.processing ? 'Yükleniyor & Kuyruğa Alınıyor...' : 'Deşifre Et' }}</span>
            </button>
          </div>
        </form>
      </div>

      <!-- STT History & Transcripts -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-neutral-200">Deşifre Geçmişi</h3>
          <button @click="reloadTasks" class="text-xs text-accent-400 hover:underline">Yenile</button>
        </div>

        <div class="divide-y divide-neutral-800/60">
          <div v-if="tasks.length === 0" class="p-8 text-center text-sm text-neutral-500">
            Henüz deşifre edilmiş bir ses bulunmuyor.
          </div>
          <div v-for="t in tasks" :key="t.id" class="py-4 space-y-2">
            <div class="flex items-center justify-between">
              <div class="font-medium text-sm text-neutral-200">{{ t.payload?.original_name || 'Ses Dosyası' }}</div>
              <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium uppercase"
                      :class="{
                        'bg-success-500/10 text-success-500': t.status === 'completed',
                        'bg-warning-500/10 text-warning-500': t.status === 'running' || t.status === 'pending',
                        'bg-danger-500/10 text-danger-500': t.status === 'failed',
                      }">
                  {{ t.status }}
                </span>
                <button
                  v-if="t.result?.text"
                  @click="copyToClipboard(t.result.text)"
                  class="text-xs px-2 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-300"
                >
                  Metni Kopyala
                </button>
              </div>
            </div>

            <!-- Transcript Output Box -->
            <div v-if="t.status === 'completed' && t.result?.text" class="p-3.5 rounded-xl bg-neutral-900 border border-neutral-800 text-sm text-neutral-200 leading-relaxed">
              {{ t.result.text }}
            </div>
            <div v-else-if="t.status === 'failed'" class="text-xs text-danger-500">
              Hata: {{ t.error_message }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

defineProps({
  tasks: Array,
})

const form = useForm({
  audio: null,
  language: 'tr',
})

const handleFileChange = (e) => {
  form.audio = e.target.files[0]
}

const submit = () => {
  form.post('/stt/transcribe', {
    onSuccess: () => {
      form.reset('audio')
    }
  })
}

const reloadTasks = () => {
  router.reload({ only: ['tasks'] })
}

const copyToClipboard = (text) => {
  navigator.clipboard.writeText(text)
  alert('Metin panoya kopyalandı!')
}
</script>
