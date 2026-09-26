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
              <button
                v-if="llmInfo?.base_url"
                type="button"
                @click="goToSettings"
                class="hidden sm:inline-flex items-center gap-1 text-[11px] text-neutral-500 hover:text-accent font-mono shrink-0 cursor-pointer bg-transparent border-0 p-0"
                title="LLM Ayarlarını Düzenle"
              >
                <span>🔗 {{ llmInfo.base_url }}</span>
              </button>
            </div>
          </div>
        </div>
        <button @click="closeModal" class="text-neutral-400 hover:text-neutral-200 cursor-pointer p-1">✕</button>
      </div>

      <!-- Main Controls -->
      <div class="space-y-4">
        <!-- Optional Target TTS Model Selection -->
        <div class="p-3.5 rounded-xl bg-neutral-900/60 border border-neutral-800 space-y-2.5">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-1.5">
              <span class="text-xs">🎙️</span>
              <label class="block text-xs font-semibold text-neutral-200">Hedef TTS Modeli (Opsiyonel)</label>
              <span class="px-1.5 py-0.2 rounded bg-neutral-800 text-[10px] font-mono text-neutral-400">Akustik Kısa Kodlar</span>
            </div>
            <div v-if="selectedTtsModel" class="flex items-center gap-2">
              <label class="inline-flex items-center gap-1.5 text-[11px] text-purple-300 cursor-pointer select-none">
                <input
                  type="checkbox"
                  v-model="useModelShortcodes"
                  class="rounded bg-neutral-950 border-neutral-700 text-purple-500 focus:ring-0 w-3.5 h-3.5 cursor-pointer"
                />
                <span>Kısa Kodları Kullan ([sigh], [pause] vb.)</span>
              </label>
            </div>
          </div>

          <select
            v-model="selectedTtsEngine"
            class="w-full rounded-xl bg-neutral-950 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
          >
            <option value="">— Model Seçilmedi (Genel Doğal Metin) —</option>
            <optgroup v-for="grp in groupedTtsModels" :key="grp.name" :label="grp.name">
              <option v-for="m in grp.models" :key="m.id" :value="m.engine || m.id">
                {{ m.name }} {{ m.badge ? `[${m.badge}]` : '' }}
              </option>
            </optgroup>
          </select>

          <!-- Model Features & Discovered Shortcodes Box -->
          <div v-if="selectedTtsModel" class="p-3 rounded-lg bg-purple-950/20 border border-purple-500/30 space-y-2">
            <div class="flex items-center justify-between flex-wrap gap-2">
              <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-purple-200">{{ selectedTtsModel.name }}</span>
                <span v-if="selectedTtsModel.badge" class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                  {{ selectedTtsModel.badge }}
                </span>
              </div>
              <span class="text-[10px] text-purple-300/80 font-mono">
                {{ selectedTtsModel.shortcodes?.length || 0 }} Akustik Kod Tanımlı
              </span>
            </div>

            <p class="text-[11px] text-neutral-300 leading-relaxed">
              {{ selectedTtsModel.description }}
            </p>

            <!-- Feature Pills -->
            <div v-if="selectedTtsModel.features?.length > 0" class="flex items-center gap-1 flex-wrap">
              <span class="text-[10px] text-neutral-400 font-medium">Özellikler:</span>
              <span
                v-for="feat in selectedTtsModel.features"
                :key="feat"
                class="px-2 py-0.5 rounded bg-neutral-900 border border-neutral-800 text-[10px] text-neutral-300"
              >
                ✓ {{ feat }}
              </span>
            </div>

            <!-- Clickable Shortcodes Chips -->
            <div v-if="selectedTtsModel.shortcodes?.length > 0" class="pt-2 border-t border-purple-500/20 space-y-1.5">
              <div class="flex items-center justify-between text-[10px] text-purple-300">
                <span class="font-semibold">Desteklenen Akustik Kodlar (Prompt'a eklemek için tıklayabilirsiniz):</span>
                <span v-if="shortcodeCopiedToast" class="text-emerald-400 font-semibold animate-pulse">✓ {{ shortcodeCopiedToast }}</span>
              </div>
              <div class="flex items-center gap-1.5 flex-wrap">
                <button
                  v-for="sc in selectedTtsModel.shortcodes"
                  :key="sc.code"
                  type="button"
                  @click="insertShortcode(sc.code)"
                  class="px-2 py-1 rounded-lg bg-purple-500/15 hover:bg-purple-500/30 border border-purple-500/30 text-[11px] text-purple-200 transition-all cursor-pointer flex items-center gap-1 shadow-sm active:scale-95"
                  :title="`${sc.desc} — Tıkla ve ekle`"
                >
                  <span>{{ sc.icon || '🏷️' }}</span>
                  <span class="font-mono font-medium">{{ sc.code }}</span>
                  <span class="text-[10px] text-neutral-400">({{ sc.label }})</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Mode & Template Selection -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center gap-2">
              <label class="block text-xs font-medium text-neutral-300">Prompt Şablonu</label>
              <button
                type="button"
                @click="resetForm"
                class="text-[11px] text-neutral-400 hover:text-neutral-200 flex items-center gap-1 cursor-pointer bg-transparent border-0 p-0 transition-colors"
                title="Tüm form alanlarını ve sonucu temizle"
              >
                <span>(Sıfırla)</span>
              </button>
            </div>
            <button
              type="button"
              @click="goToPrompts"
              class="text-[11px] text-accent hover:underline flex items-center gap-1 cursor-pointer bg-transparent border-0 p-0"
            >
              <span>Şablonları Yönet</span>
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
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
            placeholder="Örn: 5-12 yaş arası çocuklar için mini hikaye. Paylaşımcı olmak ile ilgili olsun, tavşan ile karga arasında geçsin..."
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
          <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span class="text-xs font-semibold text-neutral-200">Üretilen Metin</span>
              <span v-if="resultModel" class="text-[10px] font-mono text-neutral-400 px-2 py-0.5 rounded bg-neutral-900 border border-neutral-800">
                {{ resultModel }}
              </span>
              <span v-if="resultTtsEngineLabel" class="text-[10px] font-mono text-purple-300 px-2 py-0.5 rounded bg-purple-950/40 border border-purple-500/30 flex items-center gap-1">
                <span>🎙️</span>
                <span>{{ resultTtsEngineLabel }} için optimize edildi</span>
              </span>
            </div>

            <!-- View Switcher: Clean vs Raw -->
            <div v-if="parsedResult.hasNotes || generatedResult.includes('*')" class="flex items-center gap-1 bg-neutral-900 p-0.5 rounded-lg border border-neutral-800 text-[11px]">
              <button
                type="button"
                @click="resultViewMode = 'clean'"
                class="px-2.5 py-1 rounded-md transition-all cursor-pointer"
                :class="resultViewMode === 'clean' ? 'bg-accent/20 text-accent font-semibold border border-accent/30' : 'text-neutral-400 hover:text-neutral-200'"
              >
                ✓ Temiz Seslendirme Metni
              </button>
              <button
                type="button"
                @click="resultViewMode = 'raw'"
                class="px-2.5 py-1 rounded-md transition-all cursor-pointer"
                :class="resultViewMode === 'raw' ? 'bg-neutral-800 text-neutral-200 font-semibold' : 'text-neutral-500 hover:text-neutral-300'"
              >
                Ham LLM Çıktısı
              </button>
            </div>
            <span v-else class="text-xs text-neutral-400 font-mono">
              {{ generatedResult.length }} karakter · ~{{ wordCount }} kelime
            </span>
          </div>

          <!-- Suno-style Voiceover Directive Card -->
          <div v-if="parsedResult.directive" class="p-3.5 rounded-xl bg-gradient-to-r from-purple-950/40 via-neutral-900 to-amber-950/30 border border-purple-500/30 text-xs space-y-1 shadow-sm">
            <div class="flex items-center justify-between text-[11px] font-semibold text-purple-300">
              <div class="flex items-center gap-1.5">
                <span>🎭 Seslendirme & Yönetmen Talimatı (Suno Tarzı)</span>
                <span class="px-1.5 py-0.2 rounded bg-purple-500/20 text-purple-300 text-[10px] font-mono">Ayrıştırıldı</span>
              </div>
            </div>
            <div class="text-neutral-200 text-xs italic font-sans leading-relaxed">
              "{{ parsedResult.directive }}"
            </div>
            <div class="text-[10px] text-neutral-400 flex items-center gap-1 pt-0.5">
              <span class="text-emerald-400 font-bold">✓ Arındırıldı:</span>
              <span>Bu talimat seslendirme metninden ayrıldı; ses motoru "yıldız" veya talimatı okumaz.</span>
            </div>
          </div>

          <!-- Quick Inserter for Selected Model Shortcodes (Editable) -->
          <div v-if="selectedTtsModel?.shortcodes?.length > 0" class="flex items-center justify-between gap-2 p-2 rounded-lg bg-neutral-900/80 border border-neutral-800 flex-wrap">
            <span class="text-[10px] text-neutral-400 font-medium flex items-center gap-1">
              <span>🏷️</span>
              <span>Metne Kısa Kod Ekle:</span>
            </span>
            <div class="flex items-center gap-1 flex-wrap">
              <button
                v-for="sc in selectedTtsModel.shortcodes"
                :key="sc.code"
                type="button"
                @click="appendTagToEditableText(sc.code)"
                class="px-1.5 py-0.5 rounded bg-purple-500/10 hover:bg-purple-500/20 text-purple-200 border border-purple-500/20 text-[10px] font-mono transition-colors cursor-pointer"
                :title="sc.desc"
              >
                {{ sc.code }}
              </button>
            </div>
          </div>

          <!-- Warning banner if simulation was used -->
          <div v-if="resultWarning" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300 flex items-start gap-2">
            <span class="text-base select-none">ℹ️</span>
            <div>{{ resultWarning }}</div>
          </div>

          <!-- Textarea: Clean Spoken Text vs Raw -->
          <textarea
            v-if="resultViewMode === 'clean'"
            :value="cleanEditableText"
            @input="cleanEditableText = $event.target.value"
            rows="5"
            class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent font-sans leading-relaxed"
            placeholder="Temiz seslendirme metni..."
          ></textarea>
          <textarea
            v-else
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
import { router } from '@inertiajs/vue3'
import { parseVoiceoverText } from '../Utils/textSanitizer'

const props = defineProps({
  show: Boolean,
  initialTemplateId: [Number, String],
  initialEngine: String,
  availableModels: Array,
})

const emit = defineEmits(['close', 'apply'])

const templates = ref([])
const ttsModelsList = ref([])
const activeProvider = ref('')
const llmInfo = ref(null)

const selectedTemplateId = ref('custom')
const selectedTtsEngine = ref('')
const useModelShortcodes = ref(true)
const shortcodeCopiedToast = ref('')

const customPrompt = ref('')
const variableValues = ref({})
const isGenerating = ref(false)
const generatedResult = ref('')
const cleanEditableText = ref('')
const resultViewMode = ref('clean')
const resultModel = ref('')
const resultTtsEngine = ref('')
const resultWarning = ref('')
const errorMessage = ref(null)

const parsedResult = computed(() => {
  return parseVoiceoverText(generatedResult.value)
})

watch(generatedResult, (newVal) => {
  if (newVal) {
    const parsed = parseVoiceoverText(newVal)
    cleanEditableText.value = parsed.cleanText
    resultViewMode.value = 'clean'
  }
})

const escapeRegex = (string) => {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

// Compute selected TTS model with all its discovered features
const selectedTtsModel = computed(() => {
  if (!selectedTtsEngine.value) return null
  const needle = selectedTtsEngine.value.toLowerCase()
  return ttsModelsList.value.find(m => 
    (m.id && m.id.toLowerCase() === needle) || 
    (m.engine && m.engine.toLowerCase() === needle)
  ) || null
})

// Group models by category for clean optgroups
const groupedTtsModels = computed(() => {
  const groups = {}
  for (const m of ttsModelsList.value) {
    const cat = m.category || 'Diğer Modeller'
    if (!groups[cat]) {
      groups[cat] = []
    }
    groups[cat].push(m)
  }
  return Object.keys(groups).map(name => ({
    name,
    models: groups[name],
  }))
})

const resultTtsEngineLabel = computed(() => {
  if (!resultTtsEngine.value) return null
  const found = ttsModelsList.value.find(m => m.id === resultTtsEngine.value || m.engine === resultTtsEngine.value)
  return found ? found.name : resultTtsEngine.value
})

const fetchTemplates = async () => {
  try {
    const res = await fetch('/api/prompts')
    if (res.ok) {
      const data = await res.json()
      templates.value = data.templates || []
      if (data.tts_models && Array.isArray(data.tts_models)) {
        ttsModelsList.value = data.tts_models
      }
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

  // If tts models not populated from api/prompts, fetch from dedicated endpoint
  if (ttsModelsList.value.length === 0) {
    try {
      const resTts = await fetch('/api/tts/models-with-features')
      if (resTts.ok) {
        const dataTts = await resTts.json()
        if (dataTts.models) {
          ttsModelsList.value = dataTts.models
        }
      }
    } catch (e) {
      console.warn('TTS model özellikleri yüklenemedi:', e)
    }
  }
}

onMounted(() => {
  if (props.initialEngine) {
    selectedTtsEngine.value = props.initialEngine
  }
  fetchTemplates()
})

watch(() => props.show, (val) => {
  if (val) {
    fetchTemplates()
    if (props.initialEngine) {
      selectedTtsEngine.value = props.initialEngine
    }
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

watch(() => props.initialEngine, (val) => {
  if (val) {
    selectedTtsEngine.value = val
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

// Insert shortcode into custom prompt or copy to clipboard
const insertShortcode = (code) => {
  if (selectedTemplateId.value === 'custom') {
    customPrompt.value = (customPrompt.value ? customPrompt.value.trim() + ' ' : '') + code + ' '
    shortcodeCopiedToast.value = `${code} prompta eklendi`
  } else {
    try {
      navigator.clipboard.writeText(code)
      shortcodeCopiedToast.value = `${code} panoya kopyalandı`
    } catch (e) {
      shortcodeCopiedToast.value = `${code} seçildi`
    }
  }

  setTimeout(() => {
    shortcodeCopiedToast.value = ''
  }, 2000)
}

const appendTagToEditableText = (code) => {
  cleanEditableText.value = (cleanEditableText.value ? cleanEditableText.value.trim() + ' ' : '') + code + ' '
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

    // Attach chosen TTS engine so model shortcodes are integrated into LLM prompt
    if (selectedTtsEngine.value && useModelShortcodes.value) {
      payload.tts_engine = selectedTtsEngine.value
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
      resultTtsEngine.value = data.tts_engine || (selectedTtsEngine.value && useModelShortcodes.value ? selectedTtsEngine.value : '')
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

const resetForm = () => {
  customPrompt.value = ''
  variableValues.value = {}
  generatedResult.value = ''
  cleanEditableText.value = ''
  resultModel.value = ''
  resultTtsEngine.value = ''
  resultWarning.value = ''
  errorMessage.value = null
  selectedTemplateId.value = props.initialTemplateId || 'custom'
  if (props.initialEngine) {
    selectedTtsEngine.value = props.initialEngine
  }
}

// Reset form whenever modal closes so it opens completely fresh next time
watch(() => props.show, (isShown) => {
  if (!isShown) {
    resetForm()
  } else {
    // When opened fresh
    selectedTemplateId.value = props.initialTemplateId || 'custom'
    if (props.initialEngine) {
      selectedTtsEngine.value = props.initialEngine
    }
  }
})

const applyText = () => {
  if (!generatedResult.value) return
  const textToApply = resultViewMode.value === 'clean' ? (cleanEditableText.value || parsedResult.value.cleanText) : generatedResult.value
  emit('apply', {
    text: textToApply,
    directive: parsedResult.value.directive || '',
    raw: generatedResult.value,
    engine: selectedTtsEngine.value || null,
  })
  resetForm()
  emit('close')
}

const closeModal = () => {
  resetForm()
  emit('close')
}

const goToPrompts = () => {
  resetForm()
  emit('close')
  router.visit('/prompts')
}

const goToSettings = () => {
  resetForm()
  emit('close')
  router.visit('/settings')
}
</script>
