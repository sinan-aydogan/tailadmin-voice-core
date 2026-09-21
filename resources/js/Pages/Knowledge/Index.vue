<template>
  <AppLayout :title="t('knowledge.title')">
    <div class="space-y-6 max-w-5xl pb-12">
      <!-- Header Banner & Actions -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
        <div class="flex items-center gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400 shrink-0">
            <span class="text-xl select-none">📚</span>
          </div>
          <div>
            <h1 class="text-lg font-semibold text-neutral-100 flex items-center gap-2">
              <span>{{ t('knowledge.title') }}</span>
              <span class="text-xs px-2.5 py-0.5 rounded-full bg-sky-500/15 text-sky-300 border border-sky-500/30 font-mono">
                {{ documents.length }} {{ t('knowledge.count_unit') }}
              </span>
            </h1>
            <p class="text-xs text-neutral-400 mt-0.5">
              {{ t('knowledge.subtitle') }}
            </p>
          </div>
        </div>

        <button
          type="button"
          @click="openCreateModal"
          class="px-4 py-2.5 rounded-xl bg-accent text-bg font-semibold text-xs hover:opacity-90 transition-opacity flex items-center justify-center gap-2 shadow-sm cursor-pointer whitespace-nowrap"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          <span>{{ t('knowledge.new_document') }}</span>
        </button>
      </div>

      <!-- Documents List -->
      <div v-if="documents.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="p-5 rounded-2xl bg-surface border border-neutral-800 hover:border-neutral-700 transition-all flex flex-col justify-between space-y-3 shadow-sm"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <h2 class="text-sm font-semibold text-neutral-100 truncate">{{ doc.title }}</h2>
              <p class="text-[11px] text-neutral-500 mt-0.5">{{ doc.char_count.toLocaleString() }} {{ t('knowledge.char_unit') }} · {{ doc.chunks_count }} {{ t('knowledge.chunk_unit') }}</p>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button type="button" @click="viewingDocId = doc.id" class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-500 hover:text-neutral-200 transition-colors cursor-pointer" :title="t('knowledge.view_detail')">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
              </button>
              <button type="button" @click="deleteDocument(doc)" class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-500 hover:text-red-400 transition-colors cursor-pointer" :title="t('common.delete')">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
          <p v-if="doc.source_filename" class="text-[11px] text-neutral-500 font-mono truncate">{{ doc.source_filename }}</p>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="p-12 rounded-2xl bg-surface border border-neutral-800 text-center space-y-3">
        <div class="text-3xl select-none">📚</div>
        <div class="text-sm font-medium text-neutral-200">{{ t('knowledge.empty_title') }}</div>
        <p class="text-xs text-neutral-500 max-w-sm mx-auto">
          {{ t('knowledge.empty_desc') }}
        </p>
        <button type="button" @click="openCreateModal" class="mt-2 px-4 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity inline-flex items-center gap-2 cursor-pointer">
          <span>{{ t('knowledge.new_document') }}</span>
        </button>
      </div>

      <!-- Create Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="w-full max-w-xl rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-5 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <h2 class="text-base font-semibold text-neutral-100">{{ t('knowledge.modal_title') }}</h2>
            <button @click="showModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
          </div>

          <form @submit.prevent="submitModal" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1">{{ t('knowledge.field_title') }}</label>
              <input
                v-model="form.title"
                type="text"
                required
                :placeholder="t('knowledge.field_title_placeholder')"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              />
            </div>

            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-medium text-neutral-400">{{ t('knowledge.field_content') }}</label>
                <label class="text-[11px] text-accent hover:opacity-80 cursor-pointer">
                  {{ t('knowledge.upload_file') }}
                  <input type="file" accept=".txt,.md,text/plain" class="hidden" @change="onFileSelected" />
                </label>
              </div>
              <textarea
                v-model="form.content"
                rows="10"
                required
                :placeholder="t('knowledge.content_placeholder')"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 focus:outline-none focus:border-accent font-sans"
              ></textarea>
              <p class="text-[11px] text-neutral-500 mt-1">{{ form.content.length.toLocaleString() }} {{ t('knowledge.char_unit') }}</p>
            </div>

            <div class="pt-3 border-t border-neutral-800 flex justify-end gap-2.5">
              <button type="button" @click="showModal = false" class="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors cursor-pointer">{{ t('common.cancel') }}</button>
              <button
                type="submit"
                :disabled="isSubmitting || !form.title.trim() || !form.content.trim()"
                class="px-5 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
              >
                <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
                <span>{{ t('common.save') }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <DocumentDetailModal v-if="viewingDocId" :document-id="viewingDocId" @close="viewingDocId = null" />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import DocumentDetailModal from '../../Components/Knowledge/DocumentDetailModal.vue'
import { useI18n } from '../../i18n'

const { t } = useI18n()

const props = defineProps({
  documents: Array,
})

const viewingDocId = ref(null)
const showModal = ref(false)
const isSubmitting = ref(false)
const form = ref({ title: '', content: '', source_filename: '' })

function openCreateModal() {
  form.value = { title: '', content: '', source_filename: '' }
  showModal.value = true
}

function onFileSelected(evt) {
  const file = evt.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    form.value.content = String(reader.result || '')
    form.value.source_filename = file.name
    if (!form.value.title.trim()) {
      form.value.title = file.name.replace(/\.[^.]+$/, '')
    }
  }
  reader.readAsText(file, 'utf-8')
}

function submitModal() {
  if (!form.value.title.trim() || !form.value.content.trim()) return
  isSubmitting.value = true
  router.post('/knowledge', form.value, {
    preserveScroll: true,
    onSuccess: () => {
      showModal.value = false
      isSubmitting.value = false
    },
    onError: () => { isSubmitting.value = false },
  })
}

function deleteDocument(doc) {
  if (!confirm(t('knowledge.delete_confirm').replace('{name}', doc.title))) return
  router.delete(`/knowledge/${doc.id}`, { preserveScroll: true })
}
</script>
