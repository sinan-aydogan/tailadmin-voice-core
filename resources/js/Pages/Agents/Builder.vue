<template>
  <AppLayout :title="`Ajan: ${form.name || 'İsimsiz'}`">
    <div class="max-w-4xl mx-auto space-y-4 pb-16">
      <!-- Top bar -->
      <div class="p-4 rounded-2xl bg-surface border border-neutral-800 flex flex-col lg:flex-row lg:items-center justify-between gap-3 shadow-sm">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <Link href="/agents" class="p-2 rounded-xl hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 transition-colors shrink-0" title="Ajanlara dön">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </Link>
          <input
            v-model="form.name"
            type="text"
            placeholder="Ajan adı"
            class="min-w-0 flex-1 max-w-xs rounded-xl bg-neutral-900 border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-100 focus:outline-none focus:border-accent"
          />
          <label class="flex items-center gap-2 text-xs text-neutral-400 shrink-0 cursor-pointer select-none">
            <input v-model="form.is_active" type="checkbox" class="rounded bg-neutral-900 border-neutral-700 text-accent focus:ring-0 cursor-pointer" />
            <span>Aktif</span>
          </label>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="copyTriggerUrl"
            class="px-3 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer"
            title="Tetikleyici URL'i kopyala"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
            <span>{{ copied ? 'Kopyalandı!' : 'Webhook URL' }}</span>
          </button>
          <button
            type="button"
            @click="showTestModal = true"
            class="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
          >
            <span>▶</span><span>Test Et</span>
          </button>
          <Link
            :href="`/agents/${agent.id}/voice`"
            class="px-4 py-2 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 text-xs font-semibold transition-colors flex items-center gap-1.5"
          >
            <span>🎙️</span><span>Sesli Görüşme</span>
          </Link>
          <button
            type="button"
            @click="save"
            :disabled="saving"
            class="px-4 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
          >
            <span v-if="saving" class="w-3.5 h-3.5 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
            <span>Kaydet</span>
          </button>
        </div>
      </div>

      <!-- Persona -->
      <section class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3.5">
        <h2 class="text-xs font-semibold text-neutral-200 uppercase tracking-wide">Kimlik ve Talimat</h2>
        <TextAreaField label="Açıklama (isteğe bağlı)" v-model="form.description" :rows="2" placeholder="Bu ajan ne için var?" />
        <TextAreaField label="Sistem Talimatı" v-model="form.system_prompt" :rows="4" placeholder="Sen ... yardımcı bir asistansın." />
        <div class="grid grid-cols-2 gap-3">
          <TextField label="Sağlayıcı (isteğe bağlı)" v-model="form.provider" placeholder="Varsayılan (Ayarlar)" />
          <TextField label="Model (isteğe bağlı)" v-model="form.model" placeholder="Varsayılan (Ayarlar)" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <TextField label="API Anahtarı (isteğe bağlı)" v-model="form.api_key" placeholder="Varsayılan (Ayarlar)" type="password" />
          <TextField label="Base URL (isteğe bağlı)" v-model="form.base_url" placeholder="Varsayılan (Ayarlar)" />
        </div>
        <TextField label="Azami Araç Çağrısı Turu" v-model.number="form.max_tool_iterations" type="number" />
      </section>

      <!-- Knowledge / RAG -->
      <section class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3">
        <h2 class="text-xs font-semibold text-neutral-200 uppercase tracking-wide">Bilgi Kaynakları (RAG)</h2>
        <label v-if="knowledgeDocuments.length" class="flex items-center gap-2.5 text-xs text-neutral-300 cursor-pointer select-none">
          <input v-model="form.use_knowledge" type="checkbox" class="rounded bg-neutral-900 border-neutral-700 text-accent focus:ring-0 cursor-pointer" />
          <span>Cevap vermeden önce bilgi kaynaklarını kullan</span>
        </label>
        <template v-if="knowledgeDocuments.length && form.use_knowledge">
          <div class="space-y-1.5">
            <div
              v-for="doc in knowledgeDocuments"
              :key="doc.id"
              class="flex items-center gap-2 px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-800 text-xs text-neutral-200"
            >
              <label class="flex items-center gap-2.5 flex-1 min-w-0 cursor-pointer select-none">
                <input
                  type="checkbox"
                  :checked="(form.knowledge_document_ids || []).includes(doc.id)"
                  @change="toggleKnowledgeDoc(doc.id)"
                  class="shrink-0 rounded bg-neutral-900 border-neutral-700 text-accent focus:ring-0 cursor-pointer"
                />
                <span class="truncate" :title="doc.title">{{ doc.title }}</span>
              </label>
              <button
                type="button"
                @click="viewingDocId = doc.id"
                class="shrink-0 p-1 rounded-lg hover:bg-neutral-800 text-neutral-500 hover:text-neutral-200 transition-colors cursor-pointer"
                title="Dökümanın detayını görüntüle"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
              </button>
            </div>
          </div>
        </template>
        <p v-if="!knowledgeDocuments.length" class="text-[11px] text-neutral-600 leading-relaxed">RAG kullanmak için önce <Link href="/knowledge" class="text-accent hover:underline">Bilgi Kaynakları</Link> sayfasından en az bir döküman ekleyin.</p>
        <DocumentDetailModal v-if="viewingDocId" :document-id="viewingDocId" @close="viewingDocId = null" />
      </section>

      <!-- Voice -->
      <section class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3.5">
        <h2 class="text-xs font-semibold text-neutral-200 uppercase tracking-wide">Ses Ayarları</h2>
        <label class="flex items-center gap-2.5 text-xs text-neutral-300 cursor-pointer select-none">
          <input v-model="form.stt_enabled" type="checkbox" class="rounded bg-neutral-900 border-neutral-700 text-accent focus:ring-0 cursor-pointer" />
          <span>Gelen sesi yazıya çevir (STT)</span>
        </label>
        <div v-if="form.stt_enabled" class="grid grid-cols-2 gap-3">
          <SelectField label="STT Dili" v-model="form.stt_language" :options="languageOptions" />
          <TextField label="Model Boyutu (isteğe bağlı)" v-model="form.stt_model_size" placeholder="tiny, base, small, medium, large" />
        </div>

        <div class="pt-2 border-t border-neutral-800 grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-neutral-400 mb-1">TTS Motoru</label>
            <select v-model="form.tts_engine" class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent">
              <option v-if="voiceModels.length === 0" value="piper-tr">piper-tr</option>
              <option v-for="m in voiceModels" :key="m.id" :value="m.id">{{ m.name || m.id }}</option>
            </select>
          </div>
          <SelectField label="TTS Dili" v-model="form.tts_language" :options="languageOptions" />
        </div>
        <div>
          <label class="block text-xs font-medium text-neutral-400 mb-1">Ses Profili (klonlama, isteğe bağlı)</label>
          <select v-model="form.tts_profile_id" class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent">
            <option :value="null">Varsayılan Ses</option>
            <option v-for="p in voiceProfiles" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

        <div class="pt-2 border-t border-neutral-800">
          <SelectField label="Cevap Tipi" v-model="form.response_mode" :options="[
            { value: 'audio', label: 'Ses (audio_url)' },
            { value: 'text', label: 'Metin' },
            { value: 'json', label: 'JSON' },
            { value: 'twiml', label: 'TwiML (Twilio telefon)' },
          ]" />
          <template v-if="form.response_mode === 'twiml'">
            <SelectField class="mt-3" label="TwiML Türü" v-model="form.twiml_type" :options="[
              { value: 'play', label: 'Ses Çal (Play)' },
              { value: 'say', label: 'Metni Seslendir (Say)' },
              { value: 'play_and_gather', label: 'Çal + Karşı Tarafı Dinle (çok turlu telefon görüşmesi)' },
            ]" />
          </template>
        </div>
      </section>

      <!-- Tools -->
      <section class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-semibold text-neutral-200 uppercase tracking-wide">Araçlar (Flow'lar)</h2>
          <button type="button" @click="addTool" class="text-[11px] text-accent hover:opacity-80 cursor-pointer font-medium">+ Araç Ekle</button>
        </div>
        <p class="text-[11px] text-neutral-500 leading-relaxed">LLM, konuşma ilerledikçe aşağıdaki araçlardan hangisini çağıracağına kendisi karar verir. Her araç, seçtiğiniz bir Akış'ı (Flow) çalıştırır — LLM'in ürettiği parametreler o akışın <code class="text-accent">trigger.body</code> alanına geçer.</p>

        <div v-if="!(form.tools || []).length" class="p-4 rounded-xl bg-neutral-900 border border-neutral-800 border-dashed text-center text-[11px] text-neutral-500">
          Henüz araç eklenmedi.
        </div>

        <div
          v-for="(tool, tIdx) in form.tools"
          :key="tIdx"
          class="p-4 rounded-xl bg-neutral-900 border border-neutral-800 space-y-3"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] font-mono text-neutral-500">Araç #{{ tIdx + 1 }}</span>
            <button type="button" @click="removeTool(tIdx)" class="text-neutral-500 hover:text-red-400 cursor-pointer text-xs">✕ Kaldır</button>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <TextField label="Araç Adı" v-model="tool.name" placeholder="check_weather" />
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1">Akış (Flow)</label>
              <select v-model="tool.flow_id" class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent">
                <option :value="null">Akış seçin</option>
                <option v-for="f in flows" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
          </div>
          <TextAreaField label="Açıklama (LLM'e ne zaman kullanacağını anlatır)" v-model="tool.description" :rows="2" placeholder="Bir şehrin güncel hava durumunu getirir." />

          <div class="pt-2 border-t border-neutral-800/80 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-medium text-neutral-400">Parametreler</span>
              <button type="button" @click="addParam(tool)" class="text-[10px] text-accent hover:opacity-80 cursor-pointer">+ Parametre Ekle</button>
            </div>
            <div v-for="(param, pIdx) in tool._params" :key="pIdx" class="flex items-center gap-1.5">
              <input v-model="param.name" placeholder="ad" class="w-28 shrink-0 rounded-lg bg-neutral-950 border border-neutral-700 p-2 text-[11px] text-neutral-100 focus:outline-none focus:border-accent" />
              <select v-model="param.type" class="w-24 shrink-0 rounded-lg bg-neutral-950 border border-neutral-700 p-2 text-[11px] text-neutral-100 focus:outline-none focus:border-accent">
                <option value="string">string</option>
                <option value="number">number</option>
                <option value="boolean">boolean</option>
              </select>
              <input v-model="param.description" placeholder="açıklama" class="flex-1 min-w-0 rounded-lg bg-neutral-950 border border-neutral-700 p-2 text-[11px] text-neutral-100 focus:outline-none focus:border-accent" />
              <label class="flex items-center gap-1 shrink-0 text-[10px] text-neutral-400 cursor-pointer select-none">
                <input v-model="param.required" type="checkbox" class="rounded bg-neutral-950 border-neutral-700 text-accent focus:ring-0 cursor-pointer" />
                <span>zorunlu</span>
              </label>
              <button type="button" @click="removeParam(tool, pIdx)" class="w-6 shrink-0 text-neutral-500 hover:text-red-400 cursor-pointer">✕</button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Test Run Modal -->
    <div v-if="showTestModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="w-full max-w-2xl rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
          <h2 class="text-base font-semibold text-neutral-100">Ajanı Test Et</h2>
          <button @click="showTestModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
        </div>

        <TextField label="Mesaj (text)" v-model="testMessage" placeholder="Ankara hava durumu nedir?" />
        <TextField label="Konuşma Kimliği (conversation_id, isteğe bağlı)" v-model="testConversationId" placeholder="test-1" />

        <div>
          <label class="block text-xs font-medium text-neutral-400 mb-1">Örnek Ses Dosyası (isteğe bağlı)</label>
          <input type="file" accept="audio/*" @change="onTestFileChange" class="text-xs text-neutral-300" />
        </div>

        <button
          type="button"
          @click="runTest"
          :disabled="testing"
          class="w-full px-4 py-2.5 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
        >
          <span v-if="testing" class="w-3.5 h-3.5 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
          <span>{{ testing ? 'Çalıştırılıyor...' : 'Çalıştır' }}</span>
        </button>

        <div v-if="testResult" class="space-y-2 pt-2 border-t border-neutral-800">
          <div class="text-xs font-semibold" :class="testResult.success ? 'text-emerald-400' : 'text-red-400'">
            {{ testResult.success ? 'Ajan başarıyla cevap verdi.' : ('Hata: ' + testResult.message) }}
          </div>
          <div v-if="testResult.final?.value" class="p-3 rounded-xl bg-neutral-900 border border-accent/30 text-xs text-neutral-100 whitespace-pre-wrap">
            {{ testResult.final.value }}
          </div>
          <div v-for="step in testResult.run?.steps || []" :key="step.id" class="p-2.5 rounded-xl bg-neutral-900 border border-neutral-800 text-[11px]">
            <div class="flex items-center justify-between">
              <span class="font-mono text-neutral-300">{{ step.step_type }}<template v-if="step.tool_name"> · {{ step.tool_name }}</template></span>
              <span class="text-neutral-500">{{ step.duration_ms }}ms</span>
            </div>
            <pre v-if="step.output" class="mt-1.5 text-neutral-500 whitespace-pre-wrap break-all">{{ JSON.stringify(step.output, null, 2) }}</pre>
            <div v-if="step.error_message" class="mt-1.5 text-red-400">{{ step.error_message }}</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, h } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '../../Layouts/AppLayout.vue'
import DocumentDetailModal from '../../Components/Knowledge/DocumentDetailModal.vue'

const props = defineProps({
  agent: Object,
  flows: { type: Array, default: () => [] },
  voiceProfiles: { type: Array, default: () => [] },
  voiceModels: { type: Array, default: () => [] },
  knowledgeDocuments: { type: Array, default: () => [] },
})

const agent = props.agent

const languageOptions = [
  { value: 'tr', label: 'Türkçe' },
  { value: 'en', label: 'İngilizce' },
  { value: 'de', label: 'Almanca' },
  { value: 'fr', label: 'Fransızca' },
  { value: 'es', label: 'İspanyolca' },
]

// JSON Schema `parameters` <-> flat editable row list conversion, so each
// tool's parameter list can be edited as simple {name,type,description,required} rows.
function schemaToParams(parameters) {
  const props = parameters?.properties || {}
  const required = parameters?.required || []
  return Object.entries(props).map(([name, def]) => ({
    name,
    type: def.type || 'string',
    description: def.description || '',
    required: required.includes(name),
  }))
}

function paramsToSchema(params) {
  const properties = {}
  const required = []
  for (const p of params) {
    if (!p.name) continue
    properties[p.name] = { type: p.type || 'string', description: p.description || '' }
    if (p.required) required.push(p.name)
  }
  return { type: 'object', properties, required }
}

const form = ref({
  name: agent.name,
  description: agent.description,
  system_prompt: agent.system_prompt,
  provider: agent.provider,
  model: agent.model,
  api_key: agent.api_key,
  base_url: agent.base_url,
  max_tool_iterations: agent.max_tool_iterations || 6,
  use_knowledge: !!agent.use_knowledge,
  knowledge_document_ids: agent.knowledge_document_ids || [],
  stt_enabled: agent.stt_enabled ?? true,
  stt_language: agent.stt_language || 'tr',
  stt_model_size: agent.stt_model_size,
  tts_engine: agent.tts_engine || 'piper-tr',
  tts_language: agent.tts_language || 'tr',
  tts_profile_id: agent.tts_profile_id,
  response_mode: agent.response_mode || 'text',
  twiml_type: agent.twiml_type || 'play',
  is_active: agent.is_active,
  tools: (agent.tools || []).map(t => ({
    name: t.name,
    description: t.description,
    flow_id: t.flow_id,
    _params: schemaToParams(t.parameters),
  })),
})

function toggleKnowledgeDoc(docId) {
  if (!form.value.knowledge_document_ids) form.value.knowledge_document_ids = []
  const idx = form.value.knowledge_document_ids.indexOf(docId)
  if (idx === -1) form.value.knowledge_document_ids.push(docId)
  else form.value.knowledge_document_ids.splice(idx, 1)
}
const viewingDocId = ref(null)

function addTool() {
  form.value.tools.push({ name: '', description: '', flow_id: null, _params: [] })
}
function removeTool(idx) {
  form.value.tools.splice(idx, 1)
}
function addParam(tool) {
  tool._params.push({ name: '', type: 'string', description: '', required: false })
}
function removeParam(tool, idx) {
  tool._params.splice(idx, 1)
}

const saving = ref(false)
function save() {
  saving.value = true
  const payload = {
    ...form.value,
    tools: form.value.tools.map(t => ({
      name: t.name,
      description: t.description,
      flow_id: t.flow_id,
      parameters: paramsToSchema(t._params),
    })),
  }
  delete payload._params

  router.put(`/agents/${agent.id}`, payload, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => { saving.value = false },
  })
}

const copied = ref(false)
function copyTriggerUrl() {
  navigator.clipboard?.writeText(agent.trigger_url).then(() => {
    copied.value = true
    setTimeout(() => { copied.value = false }, 1500)
  })
}

// --- Test run ---
const showTestModal = ref(false)
const testMessage = ref('')
const testConversationId = ref('')
const testFile = ref(null)
const testing = ref(false)
const testResult = ref(null)

function onTestFileChange(evt) {
  testFile.value = evt.target.files?.[0] || null
}

async function runTest() {
  testing.value = true
  testResult.value = null

  const payload = new FormData()
  if (testMessage.value.trim()) payload.append('body[text]', testMessage.value)
  if (testConversationId.value.trim()) payload.append('body[conversation_id]', testConversationId.value)
  if (testFile.value) payload.append('audio', testFile.value)

  try {
    const { data } = await axios.post(`/agents/${agent.id}/test-run`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    testResult.value = data
  } catch (e) {
    testResult.value = e.response?.data || { success: false, message: e.message }
  } finally {
    testing.value = false
  }
}

// --- tiny local field components (mirrors FlowInspector's pattern) ---
const TextField = (_props, { attrs }) => h('div', [
  h('label', { class: 'block text-xs font-medium text-neutral-400 mb-1' }, attrs.label),
  h('input', {
    value: attrs.modelValue,
    type: attrs.type || 'text',
    placeholder: attrs.placeholder,
    onInput: (e) => attrs['onUpdate:modelValue'](attrs.type === 'number' ? Number(e.target.value) : e.target.value),
    class: 'w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent',
  }),
])

const TextAreaField = (_props, { attrs }) => h('div', [
  h('label', { class: 'block text-xs font-medium text-neutral-400 mb-1' }, attrs.label),
  h('textarea', {
    value: attrs.modelValue,
    rows: attrs.rows || 3,
    placeholder: attrs.placeholder,
    onInput: (e) => attrs['onUpdate:modelValue'](e.target.value),
    class: 'w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent font-sans',
  }),
])

const SelectField = (_props, { attrs }) => h('div', [
  h('label', { class: 'block text-xs font-medium text-neutral-400 mb-1' }, attrs.label),
  h('select', {
    value: attrs.modelValue,
    onChange: (e) => attrs['onUpdate:modelValue'](e.target.value),
    class: 'w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent',
  }, attrs.options.map(o => h('option', { value: o.value }, o.label))),
])
</script>
