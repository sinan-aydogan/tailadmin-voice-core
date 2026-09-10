<template>
  <AppLayout title="İş Listeleri (Playlists)">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Create Playlist Form -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4 h-fit">
        <h2 class="text-base font-semibold text-neutral-100">Yeni İş Listesi</h2>
        <p class="text-xs text-neutral-400">
          Toplu metin seslendirme işleri oluşturup sırayla kuyruğa gönderebilirsiniz.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-neutral-400 mb-1.5">Liste Başlığı</label>
            <input
              v-model="form.title"
              type="text"
              required
              placeholder="Örn: Bölüm 1 Diyalogları"
              class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-neutral-400 mb-1.5">Açıklama</label>
            <textarea
              v-model="form.description"
              rows="3"
              placeholder="Bu listenin içeriği hakkında kısa bilgi..."
              class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-sm text-neutral-100 focus:outline-none focus:border-accent"
            ></textarea>
          </div>

          <button
            type="submit"
            :disabled="form.processing || !form.title"
            class="w-full py-2.5 rounded-xl font-semibold text-sm bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50"
          >
            Listeyi Oluştur
          </button>
        </form>
      </div>

      <!-- Playlists List -->
      <div class="lg:col-span-2 space-y-4">
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
          <h3 class="text-base font-semibold text-neutral-100">Mevcut İş Listeleri</h3>

          <div v-if="playlists.length === 0" class="p-12 text-center text-sm text-neutral-500">
            Henüz oluşturulmuş bir iş listesi bulunmuyor.
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
              v-for="pl in playlists"
              :key="pl.id"
              class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-3"
            >
              <div class="font-semibold text-neutral-100 text-sm">{{ pl.title }}</div>
              <div class="text-xs text-neutral-400">{{ pl.description || 'Açıklama yok' }}</div>
              <div class="text-xs text-neutral-500 pt-2 border-t border-neutral-800">
                Oluşturulma: {{ new Date(pl.created_at).toLocaleDateString('tr-TR') }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

defineProps({
  playlists: Array,
})

const form = useForm({
  title: '',
  description: '',
})

const submit = () => {
  form.post('/playlists', {
    onSuccess: () => {
      form.reset()
    }
  })
}
</script>
