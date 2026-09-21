<template>
  <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="$emit('close')">
    <div class="w-full max-w-xl rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-4 max-h-[85vh] flex flex-col">
      <div class="flex items-center justify-between border-b border-neutral-800 pb-3 shrink-0">
        <h2 class="text-base font-semibold text-neutral-100 truncate pr-4">{{ doc?.title || t('knowledge.default_title') }}</h2>
        <button @click="$emit('close')" class="text-neutral-400 hover:text-neutral-200 cursor-pointer shrink-0">✕</button>
      </div>

      <div v-if="loading" class="py-10 flex items-center justify-center">
        <span class="w-5 h-5 border-2 border-accent border-t-transparent rounded-full animate-spin"></span>
      </div>

      <template v-else-if="doc">
        <div class="flex items-center gap-2 flex-wrap text-[11px] text-neutral-500 shrink-0">
          <span>{{ doc.char_count?.toLocaleString() }} {{ t('knowledge.char_unit') }}</span>
          <span>·</span>
          <span>{{ doc.chunks_count }} {{ t('knowledge.chunk_unit') }}</span>
          <span v-if="doc.source_filename">·</span>
          <span v-if="doc.source_filename" class="font-mono">{{ doc.source_filename }}</span>
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 text-xs text-neutral-300 whitespace-pre-wrap leading-relaxed">{{ doc.content }}</div>
      </template>

      <p v-else class="text-xs text-red-400">{{ t('knowledge.load_error') }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { useI18n } from '../../i18n'

const { t } = useI18n()

const props = defineProps({
  documentId: { type: [Number, String], required: true },
})
defineEmits(['close'])

const doc = ref(null)
const loading = ref(true)

function load(id) {
  loading.value = true
  doc.value = null
  axios.get(`/knowledge/${id}`)
    .then(({ data }) => { doc.value = data.document })
    .catch(() => { doc.value = null })
    .finally(() => { loading.value = false })
}

watch(() => props.documentId, (id) => { if (id) load(id) }, { immediate: true })
</script>
