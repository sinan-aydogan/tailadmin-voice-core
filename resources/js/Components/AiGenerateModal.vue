<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
    <div class="w-full max-w-2xl rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-5 max-h-[90vh] overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-neutral-800 pb-3.5">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-purple-500/15 border border-purple-500/30 flex items-center justify-center text-purple-400">
            <span class="text-base select-none">✨</span>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h2 class="text-base font-semibold text-neutral-100">AI ile Metin Üret</h2>
              <span v-if="llmInfo" class="px-2 py-0.5 rounded-md text-[10px] font-mono font-semibold uppercase bg-purple-500/15 text-purple-300 border border-purple-500/30 flex items-center gap-1" :title="llmInfo.base_url || 'Varsayılan Uç Nokta'">
                <span>{{ llmInfo.provider }}</span>
                <span v-if="llmInfo.model" class="text-neutral-400 font-normal">({{ llmInfo.model }})</span>
              </span>
            </div>
            <div class="flex items-center justify-between gap-4 text-xs text-neutral-400 mt-0.5">
              <p>Kayıtlı prompt şablonlarından seçin veya serbest talimat girerek LLM ile seslendirme metni üretin.</p>
              <a v-if="llmInfo?.base_url" href="/settings#models" target="_blank" class="hidden sm:inline-flex items-center gap-1 text-[11px] text-neutral-500 hover:text-accent font-mono shrink-0" title="LLM Ayarlarını Düzenle">
                <span>🔗 {{ llmInfo.base_url }}</span>
              </a>
            </div>
          </div>
        </div>
        <button @click="closeModal" class="text-neutral-400 hover:text-neutral-200 cursor-pointer p-1">✕</button>
      </div>

      <!-- Mode & Template Selection -->
      <div class="space-y-4">
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium text-neutral-300">Prompt Şablonu</label>
            <a href="/prompts" target="_blank" class="text-[11px] text-accent hover:underline flex items-center gap-1">
              <span>Şablonları Yönet</span>
              <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>

          <select
            v-model="selectedTemplateId"
            @change="handleTemplateChange"
            class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
          >
            <option value="custom">✍️ Serbest Prompt Yaz (Özel İstek)</option>
            <optgroup v-if="templates.length > 0" label="Kayıtlı Şablonlar">
              <option v-for="t in templates" :key="t.id" :value="t.id">
                {{ t.is_favorite ? '★ ' : '' }}{{ t.title }} ({{ t.category || 'Genel' }})
              </option>
            </optgroup>
          </select>
        </div>

        <!-- If a Template is Selected -->
        <div v-if="selectedTemplate" class="p-4 rounded-xl bg-neutral-900/70 border border-neutral-800 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <div class="text-xs font-semibold text-neutral-200">{{ selectedTemplate.title }}</div>
            <span class="px-2 py-0.5 rounded-md bg-neutral-800 text-[10px] text-neutral-400 font-medium">
              {{ selectedTemplate.category || 'Genel' }}
            </span>
          </div>

          <p v-if="selectedTemplate.description" class="text-xs text-neutral-400">
            {{ selectedTemplate.description }}
          </p>

          <!-- Raw Template View with Highlighted Placeholders -->
          <div class="text-[11px] text-neutral-500 bg-neutral-950 p-2.5 rounded-lg font-mono border border-neutral-800/80 leading-relaxed">
            <span class="text-neutral-400 select-none">Şablon: </span>
            <span v-html="highlightVariables(selectedTemplate.content)"></span>
          </div>

          <!-- Dynamic Variable Inputs ($1, $2, etc.) -->
          <div v-if="templateVariables.length > 0" class="pt-2 border-t border-neutral-800 space-y-3">
            <div class="text-xs font-semibold text-accent flex items-center gap-1.5">
              <span>⚙️ Şablon Değişkenleri</span>
              <span class="text-[10px] text-neutral-400 font-normal">
                (Aşağıdaki alanları doldurduğunuzda otomatik olarak prompt içine gömülecektir)
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div v-for="v in templateVariables" :key="v.key" class="space-y-1">
                <label class="block text-xs font-medium text-neutral-300">
                  <span class="px-1.5 py-0.2 rounded bg-purple-500/20 text-purple-300 font-mono text-[10px] font-semibold border border-purple-500/30 mr-1.5">
                    {{ v.key }}
                  </span>
                  <span>{{ v.label }}</span>
                </label>
                <input
                  v-model="variableValues[v.key]"
                  type="text"
                  :placeholder="v.default || `${v.key} için değer yazın...`"
                  class="w-full rounded-lg bg-neutral-950 border border-neutral-700 p-2 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Custom Prompt Free Textarea (if 'custom' mode) -->
        <div v-else class="space-y-2">
          <label class="block text-xs font-medium text-neutral-300">Prompt Talimatı</label>
          <textarea
            v-model="customPrompt"
            rows="4"
            placeholder="Örn: 5-12 yaş arası çocuklar için mini hikaye. paylaşımcı olmak ile ilgili olsun, tavşan ile karga arasında geçsin..."
            class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
          ></textarea>

          <!-- Free Prompt Dynamic Variables if user wrote $1, $2 in custom prompt -->
          <div v-if="customVariables.length > 0" class="pt-2 border-t border-neutral-800 space-y-2">
            <div class="text-xs font-semibold text-accent flex items-center gap-1.5">
              <span>⚙️ Algılanan Değişkenler</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div v-for="k in customVariables" :key="k" class="space-y-1">
                <label class="block text-xs font-medium text-neutral-300 font-mono">
                  <span class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 text-[10px] font-semibold border border-purple-500/30">
                    {{ k }}
                  </span>
                  Değeri
                </label>
                <input
                  v-model="variableValues[k]"
                  type="text"
                  :placeholder="`${k} değeri...`"
                  class="w-full rounded-lg bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Live Compiled Final Prompt Preview -->
        <div v-if="compiledPromptPreview" class="p-3 rounded-xl bg-neutral-900/50 border border-neutral-800 text-xs space-y-1">
          <div class="text-[11px] font-semibold text-neutral-400 flex items-center justify-between">
            <span>Gelişmiş Prompt Önizlemesi:</span>
            <span class="text-[10px] text-neutral-500 font-mono">{{ compiledPromptPreview.length }} karakter</span>
          </div>
          <div class="text-neutral-300 font-sans text-xs italic">
            "{{ compiledPromptPreview }}"
          </div>
        </div>

        <!-- Generate Button -->
        <div class="flex justify-end pt-1">
          <button
            type="button"
            @click="generateText"
            :disabled="isGenerating || !canGenerate"
            class="px-5 py-2.5 rounded-xl bg-accent text-bg font-semibold text-xs hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2 shadow-sm cursor-pointer"
          >
            <span v-if="isGenerating" class="w-3.5 h-3.5 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
            <span>{{ isGenerating ? 'Yapay Zeka Metni Üretiyor (Lokal modeller donanıma göre 1-2 dk sürebilir)...' : 'Metni Üret ✨' }}</span>
          </button>
        </div>

        <!-- Generation Result Preview Box -->
        <div v-if="generatedResult" class="pt-4 border-t border-neutral-800 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span class="text-xs font-semibold text-neutral-200">Üretilen Metin</span>
              <span v-if="resultModel" class="text-[10px] font-mono text-neutral-400 px-2 py-0.5 rounded bg-neutral-900 border border-neutral-800">
                {{ resultModel }}
              </span>
            </div>
            <span class="text-xs text-neutral-400 font-mono">
              {{ generatedResult.length }} karakter · ~{{ wordCount }} kelime
            </span>
          </div>

          <!-- Warning banner if simulation was used -->
          <div v-if="resultWarning" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300 flex items-start gap-2">
            <span class="text-base select-none">ℹ️</span>
            <div>{{ resultWarning }}</div>
          </div>

          <textarea
            v-model="generatedResult"
            rows="5"
            class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent font-sans leading-relaxed"
          ></textarea>

          <!-- Result Action Buttons -->
          <div class="flex items-center justify-between pt-1">
            <button
              type="button"
              @click="generateText"
              :disabled="isGenerating"
              class="px-3.5 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer"
            >
              <span>🔄 Yeniden Üret</span>
            </button>

            <button
              type="button"
              @click="applyText"
              class="px-5 py-2.5 rounded-xl bg-accent text-bg font-semibold text-xs hover:opacity-90 transition-opacity flex items-center gap-2 shadow-md shadow-accent/10 cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
              <span>Metin Alanına Aktar</span>
            </button>
          </div>
        </div>

        <!-- Error Alert -->
        <div v-if="errorMessage" class="p-3.5 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span>⚠</span>
            <span>{{ errorMessage }}</span>
          </div>
          <button @click="errorMessage = null" class="text-red-400 hover:text-red-200">✕</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
  show: Boolean,
  initialTemplateId: [Number, String],
})

const emit = defineEmits(['close', 'apply'])

const templates = ref([])
const activeProvider = ref('')
const llmInfo = ref(null)
const selectedTemplateId = ref('custom')
const customPrompt = ref('')
const variableValues = ref({})
const isGenerating = ref(false)
const generatedResult = ref('')
const resultModel = ref('')
const resultWarning = ref('')
const errorMessage = ref(null)

const escapeRegex = (string) => {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

const fetchTemplates = async () => {
  try {
    const res = await fetch('/api/prompts')
    if (res.ok) {
      const data = await res.json()
      templates.value = data.templates || []
      if (data.llm_info) {
        llmInfo.value = data.llm_info
        activeProvider.value = data.llm_info.provider || ''
      }
      if (selectedTemplateId.value && selectedTemplateId.value !== 'custom') {
        handleTemplateChange()
      }
    }
  } catch (e) {
    console.warn('Prompt şablonları yüklenemedi:', e)
  }
}

onMounted(() => {
  fetchTemplates()
})

watch(() => props.show, (val) => {
  if (val) {
    fetchTemplates()
    if (props.initialTemplateId && props.initialTemplateId !== 'custom') {
      selectedTemplateId.value = Number(props.initialTemplateId)
      handleTemplateChange()
    }
  } else {
    errorMessage.value = null
  }
})

watch(() => props.initialTemplateId, (val) => {
  if (val) {
    selectedTemplateId.value = val === 'custom' ? 'custom' : Number(val)
    handleTemplateChange()
  }
})

const selectedTemplate = computed(() => {
  if (selectedTemplateId.value === 'custom') return null
  return templates.value.find(t => t.id === Number(selectedTemplateId.value)) || null
})

// Variables derived from template
const templateVariables = computed(() => {
  if (!selectedTemplate.value) return []

  const t = selectedTemplate.value
  const vars = t.extracted_variables || []
  const schema = t.variables_schema || []

  return vars.map(v => {
    const s = schema.find(item => item.key === v)
    return {
      key: v,
      label: s?.label || `${v} Değeri`,
      default: s?.default || '',
    }
  })
})

// Dynamic variables in custom prompt
const customVariables = computed(() => {
  if (selectedTemplate.value || !customPrompt.value) return []
  const matches = customPrompt.value.match(/\$([a-zA-Z0-9_]+)/g)
  if (!matches) return []
  return Array.from(new Set(matches))
})

const handleTemplateChange = () => {
  variableValues.value = {}
  if (selectedTemplate.value) {
    const schema = selectedTemplate.value.variables_schema || []
    const vars = selectedTemplate.value.extracted_variables || []
    vars.forEach(v => {
      const s = schema.find(item => item.key === v)
      variableValues.value[v] = s?.default || ''
    })
  }
}

const compiledPromptPreview = computed(() => {
  let text = ''
  if (selectedTemplate.value) {
    text = selectedTemplate.value.content
  } else {
    text = customPrompt.value
  }

  if (!text) return ''

  if (selectedTemplate.value) {
    for (const v of templateVariables.value) {
      const val = (variableValues.value[v.key] !== undefined && variableValues.value[v.key] !== '')
        ? variableValues.value[v.key]
        : v.default
      if (val !== undefined && val !== null && val !== '') {
        text = text.replace(new RegExp(escapeRegex(v.key), 'g'), val)
      }
    }
  } else {
    for (const [k, val] of Object.entries(variableValues.value)) {
      if (val !== undefined && val !== null && val !== '') {
        text = text.replace(new RegExp(escapeRegex(k), 'g'), val)
      }
    }
  }

  return text
})

const canGenerate = computed(() => {
  if (selectedTemplate.value) return true
  return !!customPrompt.value.trim()
})

const wordCount = computed(() => {
  if (!generatedResult.value) return 0
  return generatedResult.value.trim().split(/\s+/).length
})

const highlightVariables = (text) => {
  if (!text) return ''
  return text.replace(/\$([a-zA-Z0-9_]+)/g, '<span class="px-1 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono font-semibold border border-purple-500/30">$$$1</span>')
}

const generateText = async () => {
  isGenerating.value = true
  errorMessage.value = null
  resultWarning.value = ''

  try {
    const payload = {}

    if (selectedTemplate.value) {
      payload.template_id = selectedTemplate.value.id
      payload.prompt = selectedTemplate.value.content
      
      const mergedVariables = {}
      for (const v of templateVariables.value) {
        mergedVariables[v.key] = (variableValues.value[v.key] !== undefined && variableValues.value[v.key] !== '')
          ? variableValues.value[v.key]
          : v.default
      }
      payload.variables = mergedVariables

      if (selectedTemplate.value.system_prompt) {
        payload.system_prompt = selectedTemplate.value.system_prompt
      }
    } else {
      payload.prompt = customPrompt.value
      payload.variables = variableValues.value
    }

    const res = await fetch('/api/llm/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify(payload)
    })

    const data = await res.json()

    if (res.ok && data.success) {
      generatedResult.value = data.text
      resultModel.value = data.model || ''
      activeProvider.value = data.provider || ''
      if (data.warning) {
        resultWarning.value = data.warning
      }
    } else {
      errorMessage.value = data.message || 'Metin üretimi sırasında bir hata oluştu.'
    }
  } catch (err) {
    errorMessage.value = 'Sunucu bağlantı hatası: ' + err.message
  } finally {
    isGenerating.value = false
  }
}

const applyText = () => {
  if (!generatedResult.value) return
  emit('apply', generatedResult.value)
  closeModal()
}

const closeModal = () => {
  emit('close')
}
</script>
