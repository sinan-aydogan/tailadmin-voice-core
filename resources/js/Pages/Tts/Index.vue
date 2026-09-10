<template>
  <AppLayout title="Metin Okuma (TTS)">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Input Form -->
      <div class="lg:col-span-2 space-y-6">
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
          <h2 class="text-base font-semibold text-neutral-100">Yeni Ses Üretimi</h2>

          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1.5">Metin</label>
              <textarea
                v-model="form.text"
                rows="5"
                required
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3.5 text-sm text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                placeholder="Seslendirilmesini istediğiniz metni buraya yazın..."
              ></textarea>
              <div class="flex justify-between text-[11px] text-neutral-500 mt-1">
                <span>Karakter: {{ form.text.length }} / 5000</span>
                <span>Worker izole process'te çalışır, UI donmaz.</span>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <!-- Engine -->
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1.5">TTS Motoru</label>
                <select
                  v-model="form.engine"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
                >
                  <option value="piper-tr">Piper (Türkçe - Hızlı)</option>
                  <option value="piper-en">Piper (İngilizce - Hızlı)</option>
                  <option value="piper-de">Piper (Almanca)</option>
                  <option value="piper-fr">Piper (Fransızca)</option>
                  <option value="xtts">XTTS v2 (Ses Klonlama)</option>
                  <option value="bark">Bark (Doğal / İfadeli)</option>
                  <option value="musicgen-small">MusicGen Small</option>
                </select>
              </div>

              <!-- Language -->
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1.5">Dil</label>
                <select
                  v-model="form.language"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
                >
                  <option value="tr">Türkçe (tr)</option>
                  <option value="en">İngilizce (en)</option>
                  <option value="de">Almanca (de)</option>
                  <option value="fr">Fransızca (fr)</option>
                  <option value="es">İspanyolca (es)</option>
                </select>
              </div>

              <!-- Profile (for voice cloning) -->
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1.5">Ses Profili (Klonlama)</label>
                <select
                  v-model="form.profile_id"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
                >
                  <option :value="null">Varsayılan Ses</option>
                  <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
            </div>

            <div class="pt-2 flex justify-end">
              <button
                type="submit"
                :disabled="form.processing || !form.text.trim()"
                class="px-6 py-2.5 rounded-xl font-semibold text-sm bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2"
              >
                <span v-if="form.processing" class="w-4 h-4 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
                <span>{{ form.processing ? 'Kuyruğa Gönderiliyor...' : 'Sesi Üret' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Quick Info / Status -->
      <div class="space-y-6">
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
          <h3 class="text-sm font-semibold text-neutral-200">Motor Bilgileri</h3>
          <div class="text-xs text-neutral-400 space-y-3">
            <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800">
              <div class="font-medium text-neutral-200">Piper TTS</div>
              <p class="mt-1 text-neutral-400">Çok hızlı, yerel ONNX tabanlı çıkarım. CPU'da bile 1-2 saniyede üretim tamamlanır.</p>
            </div>
            <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800">
              <div class="font-medium text-neutral-200">XTTS v2</div>
              <p class="mt-1 text-neutral-400">Yüksek kaliteli ses klonlama. 6 saniyelik referans ses ile istenilen sesi taklit edebilir.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- History & Audio Player -->
      <div class="lg:col-span-3">
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-neutral-200">Son TTS Üretimleri</h3>
            <button @click="reloadTasks" class="text-xs text-accent-400 hover:underline">Yenile</button>
          </div>

          <div class="divide-y divide-neutral-800/60">
            <div v-if="tasks.length === 0" class="p-8 text-center text-sm text-neutral-500">
              Henüz üretilmiş bir ses bulunmuyor.
            </div>
            <div v-for="t in tasks" :key="t.id" class="py-3.5 flex items-center justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="text-sm text-neutral-200 truncate font-medium">{{ t.payload?.text }}</div>
                <div class="text-xs text-neutral-500 mt-0.5">
                  {{ new Date(t.created_at).toLocaleTimeString('tr-TR') }} · Motor: {{ t.payload?.engine }} · Dil: {{ t.payload?.language }}
                </div>
              </div>

              <div class="flex items-center gap-3">
                <span class="px-2 py-0.5 rounded-full text-[11px] uppercase font-semibold"
                      :class="{
                        'bg-success-500/10 text-success-500': t.status === 'completed',
                        'bg-warning-500/10 text-warning-500': t.status === 'running' || t.status === 'pending',
                        'bg-danger-500/10 text-danger-500': t.status === 'failed',
                      }">
                  {{ t.status }}
                </span>

                <audio v-if="t.status === 'completed' && t.payload?.filename"
                       controls
                       class="h-8 w-56"
                       :src="'/api/audio/' + t.payload.filename"></audio>
                <a v-if="t.status === 'completed' && t.payload?.filename"
                   :href="'/api/audio/' + t.payload.filename"
                   download
                   class="p-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300"
                   title="İndir">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                </a>
              </div>
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

const props = defineProps({
  profiles: Array,
  tasks: Array,
  models: Array,
})

const form = useForm({
  text: '',
  engine: 'piper-tr',
  language: 'tr',
  profile_id: null,
})

const submit = () => {
  form.post('/tts/generate', {
    onSuccess: () => {
      form.text = ''
    }
  })
}

const reloadTasks = () => {
  router.reload({ only: ['tasks'] })
}
</script>
