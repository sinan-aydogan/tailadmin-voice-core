<template>
  <AppLayout :title="t('flows.title')">
    <div class="space-y-6 max-w-6xl pb-12">
      <!-- Header Banner & Actions -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
        <div class="flex items-center gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent shrink-0">
            <span class="text-xl select-none">🔀</span>
          </div>
          <div>
            <h1 class="text-lg font-semibold text-neutral-100 flex items-center gap-2">
              <span>{{ t('flows.title') }}</span>
              <span class="text-xs px-2.5 py-0.5 rounded-full bg-accent/15 text-accent-300 border border-accent/30 font-mono">
                {{ flows.length }} {{ t('flows.count_unit') }}
              </span>
            </h1>
            <p class="text-xs text-neutral-400 mt-0.5">
              {{ t('flows.subtitle') }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="showTemplateModal = true"
            class="px-4 py-2.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 font-semibold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap"
          >
            <span>📋</span>
            <span>{{ t('flows.create_from_template') }}</span>
          </button>
          <button
            type="button"
            @click="createFlow"
            :disabled="creating"
            class="px-4 py-2.5 rounded-xl bg-accent text-bg font-semibold text-xs hover:opacity-90 transition-opacity flex items-center justify-center gap-2 shadow-sm cursor-pointer whitespace-nowrap disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span>{{ t('flows.new_flow') }}</span>
          </button>
        </div>
      </div>

      <!-- Template Picker Modal -->
      <div v-if="showTemplateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <h2 class="text-base font-semibold text-neutral-100">{{ t('flows.template_modal_title') }}</h2>
            <button @click="showTemplateModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
          </div>
          <p class="text-xs text-neutral-400">{{ t('flows.template_modal_desc') }}</p>

          <div class="space-y-2">
            <button
              v-for="tpl in templates"
              :key="tpl.key"
              type="button"
              @click="createFromTemplate(tpl)"
              :disabled="creating"
              class="w-full text-left p-3.5 rounded-xl bg-neutral-900 border border-neutral-800 hover:border-accent transition-colors cursor-pointer disabled:opacity-50 flex items-start gap-3"
            >
              <span class="text-lg shrink-0">{{ tpl.icon }}</span>
              <div class="min-w-0">
                <div class="text-xs font-semibold text-neutral-100">{{ tpl.name }}</div>
                <div class="text-[11px] text-neutral-500 mt-0.5 leading-relaxed">{{ tpl.description }}</div>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Flows Grid -->
      <div v-if="flows.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="flow in flows"
          :key="flow.id"
          class="p-5 rounded-2xl bg-surface border border-neutral-800 hover:border-neutral-700 transition-all flex flex-col justify-between space-y-4 shadow-sm"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <button
                type="button"
                @click="toggleActive(flow)"
                class="px-2.5 py-0.5 rounded-lg text-[11px] font-medium border cursor-pointer transition-colors"
                :class="flow.is_active ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' : 'bg-neutral-900 border-neutral-800 text-neutral-500'"
              >
                {{ flow.is_active ? t('common.active') : t('common.inactive') }}
              </button>

              <div class="flex items-center gap-1">
                <Link :href="`/flows/${flow.id}/edit`" class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 transition-colors cursor-pointer" :title="t('common.edit')">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </Link>
                <button type="button" @click="deleteFlow(flow)" class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-500 hover:text-red-400 transition-colors cursor-pointer" :title="t('common.delete')">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>

            <Link :href="`/flows/${flow.id}/edit`">
              <h2 class="text-sm font-semibold text-neutral-100 hover:text-accent-300 transition-colors">{{ flow.name }}</h2>
            </Link>
            <p v-if="flow.description" class="text-xs text-neutral-400 line-clamp-2">{{ flow.description }}</p>
          </div>

          <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 flex items-center justify-between gap-2">
            <span class="text-[11px] font-mono text-neutral-500 truncate">{{ flow.trigger_url }}</span>
            <button type="button" @click="copyUrl(flow)" class="text-[11px] text-accent hover:opacity-80 shrink-0 cursor-pointer">
              {{ copiedId === flow.id ? t('common.copied') : t('common.copy') }}
            </button>
          </div>

          <div class="flex items-center justify-between gap-3 pt-1 border-t border-neutral-800/80">
            <span class="text-[11px] text-neutral-500">
              {{ t('flows.last_run') }}
              <span v-if="flow.last_run_status" :class="statusColor(flow.last_run_status)" class="font-medium">{{ statusLabel(flow.last_run_status) }}</span>
              <span v-else class="text-neutral-600">{{ t('flows.never_run') }}</span>
            </span>
            <Link
              :href="`/flows/${flow.id}/edit`"
              class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-accent hover:text-bg text-neutral-200 text-xs font-semibold transition-all shrink-0 shadow-sm"
            >
              {{ t('common.edit') }}
            </Link>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="p-12 rounded-2xl bg-surface border border-neutral-800 text-center space-y-3">
        <div class="text-3xl select-none">🔀</div>
        <div class="text-sm font-medium text-neutral-200">{{ t('flows.empty_title') }}</div>
        <p class="text-xs text-neutral-500 max-w-sm mx-auto">
          {{ t('flows.empty_desc') }}
        </p>
        <button type="button" @click="createFlow" class="mt-2 px-4 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity inline-flex items-center gap-2 cursor-pointer">
          <span>{{ t('flows.empty_cta') }}</span>
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import { FLOW_TEMPLATES } from '../../Utils/flowTemplates'
import { useI18n } from '../../i18n'

const { t } = useI18n()

const props = defineProps({
  flows: Array,
})

const templates = FLOW_TEMPLATES
const showTemplateModal = ref(false)

const creating = ref(false)
function createFlow() {
  creating.value = true
  router.post('/flows', { name: t('flows.new_flow') }, {
    onFinish: () => { creating.value = false },
  })
}

function createFromTemplate(tpl) {
  creating.value = true
  showTemplateModal.value = false
  router.post('/flows', { name: tpl.name, definition: tpl.definition }, {
    onFinish: () => { creating.value = false },
  })
}

function toggleActive(flow) {
  flow.is_active = !flow.is_active
  router.post(`/flows/${flow.id}/toggle`, {}, { preserveScroll: true, preserveState: true })
}

function deleteFlow(flow) {
  if (!confirm(t('flows.delete_confirm').replace('{name}', flow.name))) return
  router.delete(`/flows/${flow.id}`, { preserveScroll: true })
}

const copiedId = ref(null)
function copyUrl(flow) {
  navigator.clipboard?.writeText(flow.trigger_url).then(() => {
    copiedId.value = flow.id
    setTimeout(() => { copiedId.value = null }, 1500)
  })
}

function statusLabel(status) {
  return { completed: t('common.success'), failed: t('common.failed'), running: t('common.running'), pending: t('common.pending') }[status] || status
}
function statusColor(status) {
  return { completed: 'text-emerald-400', failed: 'text-red-400', running: 'text-amber-400', pending: 'text-neutral-400' }[status] || 'text-neutral-400'
}
</script>
