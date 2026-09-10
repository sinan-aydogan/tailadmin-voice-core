<template>
  <AppLayout title="Ses Profilleri">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Create Profile Form -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4 h-fit">
        <h2 class="text-base font-semibold text-neutral-100">Yeni Profil Oluştur</h2>
        <p class="text-xs text-neutral-400">
          XTTS v2 motoru için en az 6-10 saniyelik temiz bir konuşma kaydı yükleyin.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-neutral-400 mb-1.5">Profil Adı</label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="Örn: Ahmet - Sakin Anlatım"
              class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-neutral-400 mb-1.5">Açıklama (Opsiyonel)</label>
            <input
              v-model="form.description"
              type="text"
              placeholder="Örn: Belgesel ve hikaye tonu"
              class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-neutral-400 mb-1.5">Örnek Ses Dosyası</label>
            <input
              type="file"
              accept="audio/*"
              @change="handleFileChange"
              class="block w-full text-sm text-neutral-400 file:mr-4 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-neutral-800 file:text-neutral-200 hover:file:bg-neutral-700 cursor-pointer bg-neutral-900 rounded-xl border border-neutral-700 p-2"
            />
          </div>

          <button
            type="submit"
            :disabled="form.processing || !form.name"
            class="w-full py-2.5 rounded-xl font-semibold text-sm bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50"
          >
            {{ form.processing ? 'Kaydediliyor...' : 'Profili Kaydet' }}
          </button>
        </form>
      </div>

      <!-- Profiles List -->
      <div class="lg:col-span-2 space-y-4">
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
          <h3 class="text-base font-semibold text-neutral-100">Kayıtlı Ses Profilleri</h3>

          <div v-if="profiles.length === 0" class="p-12 text-center text-sm text-neutral-500">
            Kayıtlı ses profili bulunamadı. Sol taraftan yeni bir profil ekleyebilirsiniz.
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
              v-for="p in profiles"
              :key="p.id"
              class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-3 flex flex-col justify-between"
            >
              <div>
                <div class="flex items-center justify-between">
                  <div class="font-semibold text-neutral-100 text-sm">{{ p.name }}</div>
                  <button
                    @click="deleteProfile(p.id)"
                    class="p-1.5 rounded-lg text-neutral-500 hover:text-danger-500 hover:bg-neutral-800 transition-colors"
                    title="Sil"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
                <div class="text-xs text-neutral-400 mt-1">{{ p.description || 'Açıklama yok' }}</div>
              </div>

              <div v-if="p.sample_path" class="pt-2 border-t border-neutral-800">
                <span class="text-[11px] text-neutral-500 block mb-1">Referans Ses Kaydı</span>
                <audio controls class="h-8 w-full" :src="'/api/audio/' + p.sample_path.split(/[\\/]/).pop()"></audio>
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

defineProps({
  profiles: Array,
})

const form = useForm({
  name: '',
  description: '',
  sample: null,
})

const handleFileChange = (e) => {
  form.sample = e.target.files[0]
}

const submit = () => {
  form.post('/profiles', {
    onSuccess: () => {
      form.reset()
    }
  })
}

const deleteProfile = (id) => {
  if (confirm('Bu ses profilini silmek istediğinize emin misiniz?')) {
    router.delete(`/profiles/${id}`)
  }
}
</script>
