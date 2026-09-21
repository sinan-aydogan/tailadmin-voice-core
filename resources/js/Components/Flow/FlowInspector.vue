<template>
  <div class="w-96 shrink-0 h-full border-l border-neutral-800 bg-surface flex flex-col">
    <div class="px-4 py-3 border-b border-neutral-800 flex items-center justify-between shrink-0">
      <div class="flex items-center gap-2 min-w-0">
        <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs shrink-0" :class="[colors.bg, colors.text]">{{ def.icon }}</span>
        <span class="text-xs font-semibold text-neutral-100 truncate">{{ def.label }}</span>
      </div>
      <button @click="$emit('close')" class="text-neutral-400 hover:text-neutral-200 cursor-pointer text-sm">✕</button>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
      <!-- Node label -->
      <div v-if="nodeType !== 'trigger.webhook'">
        <label class="block text-xs font-medium text-neutral-400 mb-1">Düğüm Adı</label>
        <input v-model="label" type="text" class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent" />
      </div>

      <!-- trigger.webhook -->
      <template v-if="nodeType === 'trigger.webhook'">
        <p class="text-xs text-neutral-400 leading-relaxed">Bu akış aşağıdaki adrese <code class="text-accent">POST</code> isteği geldiğinde çalışır. Gönderilen JSON alanları <code class="text-accent">{{ bodyTokenExample }}</code>, yüklenen ses dosyası ise <code class="text-accent">{{ audioTokenExample }}</code> ile sonraki düğümlerde kullanılabilir.</p>
        <div class="p-2.5 rounded-xl bg-neutral-900 border border-neutral-800 text-[11px] font-mono text-accent break-all">{{ triggerUrl }}</div>
      </template>

      <!-- action.stt -->
      <template v-else-if="nodeType === 'action.stt'">
        <FieldWithPicker label="Ses Kaynağı" v-model="config.audio_source" :options="variableOptions" placeholder="{{trigger.audio_path}}" />
        <SelectField label="Dil" v-model="config.language" :options="languageOptions" />
        <TextField label="Model Boyutu (isteğe bağlı)" v-model="config.model_size" placeholder="tiny, base, small, medium, large" />
      </template>

      <!-- action.llm -->
      <template v-else-if="nodeType === 'action.llm'">
        <div>
          <label class="block text-xs font-medium text-neutral-400 mb-1">Prompt Şablonu (isteğe bağlı)</label>
          <select v-model="templateId" class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent">
            <option :value="null">Şablon kullanma (elle yaz)</option>
            <option v-for="tpl in promptTemplates" :key="tpl.id" :value="tpl.id">{{ tpl.title }}</option>
          </select>
        </div>

        <template v-if="templateId">
          <div v-for="v in templateVariables" :key="v" class="space-y-1">
            <label class="block text-xs font-medium text-neutral-400">{{ v }}</label>
            <div class="flex gap-1.5">
              <input
                :value="config.template_values?.[v] || ''"
                @input="setTemplateValue(v, $event.target.value)"
                type="text"
                class="flex-1 min-w-0 rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              />
              <VariableMenu :options="variableOptions" @pick="(t) => setTemplateValue(v, (config.template_values?.[v] || '') + t)" />
            </div>
          </div>
        </template>
        <FieldWithPicker v-else label="Prompt" v-model="config.prompt" :options="variableOptions" textarea placeholder="Seslendirilecek metni oluşturacak talimatı yaz veya {{node.text}} gibi bir değişken kullan" />

        <TextAreaField label="Sistem Talimatı (isteğe bağlı)" v-model="config.system_prompt" :rows="2" />

        <div class="grid grid-cols-2 gap-3">
          <TextField label="Sağlayıcı (isteğe bağlı)" v-model="config.provider" placeholder="Varsayılan (Ayarlar)" />
          <TextField label="Model (isteğe bağlı)" v-model="config.model" placeholder="Varsayılan (Ayarlar)" />
        </div>

        <div class="pt-2 border-t border-neutral-800 space-y-3">
          <label class="flex items-center gap-2.5 text-xs text-neutral-300 cursor-pointer select-none">
            <input v-model="config.use_memory" type="checkbox" class="rounded bg-neutral-900 border-neutral-700 text-accent focus:ring-0 cursor-pointer" />
            <span>Hafızayı Kullan (çok turlu konuşma)</span>
          </label>
          <template v-if="config.use_memory">
            <FieldWithPicker label="Konuşma Kimliği (conversation_id)" v-model="config.conversation_id" :options="variableOptions" placeholder="{{trigger.body.conversation_id}}" />
            <p class="text-[11px] text-neutral-500 leading-relaxed -mt-1.5">Her konuşmayı ayıran benzersiz kimlik — Telegram için <code class="text-accent">{{ telegramChatIdExample }}</code>, Twilio için <code class="text-accent">{{ callSidTokenExample }}</code> kullanın.</p>
            <TextField label="Hatırlanacak Tur Sayısı" v-model.number="config.memory_turns" type="number" />
          </template>

          <label
            v-if="knowledgeDocuments.length"
            class="flex items-center gap-2.5 text-xs text-neutral-300 cursor-pointer select-none"
          >
            <input v-model="config.use_knowledge" type="checkbox" class="rounded bg-neutral-900 border-neutral-700 text-accent focus:ring-0 cursor-pointer" />
            <span>Bilgi kaynaklarını kullan (RAG)</span>
          </label>
          <template v-if="knowledgeDocuments.length && config.use_knowledge">
            <p class="text-[11px] text-neutral-500 leading-relaxed -mt-1.5">Bu düğüm yalnızca aşağıda işaretlediğiniz dökümanlara bakar — aynı akıştaki başka bir LLM düğümü farklı dökümanlar seçebilir.</p>
            <div class="space-y-1.5">
              <div
                v-for="doc in knowledgeDocuments"
                :key="doc.id"
                class="flex items-center gap-2 px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-800 text-xs text-neutral-200"
              >
                <label class="flex items-center gap-2.5 flex-1 min-w-0 cursor-pointer select-none">
                  <input
                    type="checkbox"
                    :checked="(config.knowledge_document_ids || []).includes(doc.id)"
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

          <DocumentDetailModal v-if="viewingDocId" :document-id="viewingDocId" @close="viewingDocId = null" />
          <p v-else-if="!knowledgeDocuments.length" class="text-[11px] text-neutral-600 leading-relaxed">RAG kullanmak için önce <span class="text-accent">Bilgi Kaynakları</span> sayfasından en az bir döküman ekleyin.</p>
        </div>
      </template>

      <!-- action.tts -->
      <template v-else-if="nodeType === 'action.tts'">
        <FieldWithPicker label="Metin" v-model="config.text" :options="variableOptions" textarea placeholder="Seslendirilecek metin veya {{node.text}}" />
        <div>
          <label class="block text-xs font-medium text-neutral-400 mb-1">TTS Motoru</label>
          <select v-model="config.engine" class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent">
            <option v-if="voiceModels.length === 0" value="piper-tr">piper-tr</option>
            <option v-for="m in voiceModels" :key="m.id" :value="m.id">{{ m.name || m.id }}</option>
          </select>
        </div>
        <SelectField label="Dil" v-model="config.language" :options="languageOptions" />
        <div>
          <label class="block text-xs font-medium text-neutral-400 mb-1">Ses Profili (klonlama, isteğe bağlı)</label>
          <select v-model="config.profile_id" class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent">
            <option :value="null">Varsayılan Ses</option>
            <option v-for="p in voiceProfiles" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
      </template>

      <!-- action.http -->
      <template v-else-if="nodeType === 'action.http'">
        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-1">
            <label class="block text-xs font-medium text-neutral-400 mb-1">Metod</label>
            <select v-model="config.method" class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent">
              <option v-for="m in ['GET','POST','PUT','PATCH','DELETE']" :key="m" :value="m">{{ m }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <FieldWithPicker label="URL" v-model="config.url" :options="variableOptions" placeholder="https://api.ornek.com/webhook" />
          </div>
        </div>

        <KeyValueList label="Başlıklar (Headers)" v-model="config.headers" :options="variableOptions" />
        <KeyValueList label="Gövde (Body)" v-model="config.body" :options="variableOptions" />

        <TextField label="Zaman Aşımı (sn)" v-model.number="config.timeout" type="number" />
      </template>

      <!-- logic.condition -->
      <template v-else-if="nodeType === 'logic.condition'">
        <FieldWithPicker label="Sol Değer" v-model="config.left" :options="variableOptions" placeholder="{{node.text}}" />
        <SelectField label="Operatör" v-model="config.operator" :options="operatorOptions" />
        <FieldWithPicker
          v-if="!['exists','not_exists'].includes(config.operator)"
          label="Sağ Değer"
          v-model="config.right"
          :options="variableOptions"
          placeholder="Karşılaştırılacak değer"
        />
        <p class="text-[11px] text-neutral-500 leading-relaxed">Koşul doğruysa <span class="text-emerald-400 font-medium">Evet</span> çıkışı, yanlışsa <span class="text-rose-400 font-medium">Hayır</span> çıkışı izlenir.</p>
      </template>

      <!-- output.response -->
      <template v-else-if="nodeType === 'output.response'">
        <SelectField label="Cevap Tipi" v-model="config.mode" :options="[
          { value: 'audio', label: 'Ses (audio_url)' },
          { value: 'text', label: 'Metin' },
          { value: 'json', label: 'JSON' },
          { value: 'twiml', label: 'TwiML (Twilio telefon)' },
        ]" />

        <template v-if="config.mode === 'twiml'">
          <SelectField label="TwiML Türü" v-model="config.twiml_type" :options="[
            { value: 'play', label: 'Ses Çal (Play)' },
            { value: 'say', label: 'Metni Seslendir (Say — Twilio\'nun kendi sesiyle)' },
            { value: 'play_and_gather', label: 'Çal + Karşı Tarafı Dinle (çok turlu telefon görüşmesi)' },
          ]" />
          <FieldWithPicker
            :label="config.twiml_type === 'say' ? 'Söylenecek Metin' : 'Ses URL\'i'"
            v-model="config.source"
            :options="variableOptions"
            :placeholder="config.twiml_type === 'say' ? '{{node.text}}' : '{{node.audio_url}}'"
          />
          <p v-if="config.twiml_type === 'play_and_gather'" class="text-[11px] text-neutral-500 leading-relaxed">Twilio, karşı tarafın konuşmasını kendi konuşma tanımayla yazıya çevirip bu akışı <code class="text-accent">SpeechResult</code> alanıyla yeniden tetikler — LLM düğümünde hafızayı <code class="text-accent">{{ callSidTokenExample }}</code> ile açarsanız gerçek çok turlu telefon görüşmesi kurulur.</p>
        </template>
        <FieldWithPicker v-else label="Kaynak Değer" v-model="config.source" :options="variableOptions" placeholder="{{node.audio_url}}" />
      </template>
    </div>

    <div class="p-3 border-t border-neutral-800 flex justify-end gap-2 shrink-0">
      <button @click="$emit('close')" class="px-3.5 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors cursor-pointer">İptal</button>
      <button @click="apply" class="px-4 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity cursor-pointer">Uygula</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, h } from 'vue'
import { NODE_DEFS, colorClasses, OPERATOR_OPTIONS, buildVariableOptions } from '../../Utils/flowNodeDefs'
import DocumentDetailModal from '../Knowledge/DocumentDetailModal.vue'

const props = defineProps({
  node: { type: Object, required: true },
  allNodes: { type: Array, default: () => [] },
  promptTemplates: { type: Array, default: () => [] },
  voiceProfiles: { type: Array, default: () => [] },
  voiceModels: { type: Array, default: () => [] },
  triggerUrl: { type: String, default: '' },
  knowledgeDocuments: { type: Array, default: () => [] },
})

const emit = defineEmits(['apply', 'close'])

const nodeType = computed(() => props.node.data.nodeType)
const def = computed(() => NODE_DEFS[nodeType.value])
const colors = computed(() => colorClasses(def.value.color))
const operatorOptions = OPERATOR_OPTIONS
const languageOptions = [
  { value: 'tr', label: 'Türkçe' },
  { value: 'en', label: 'İngilizce' },
  { value: 'de', label: 'Almanca' },
  { value: 'fr', label: 'Fransızca' },
  { value: 'es', label: 'İspanyolca' },
]

const bodyTokenExample = '{{trigger.body.alan}}'
const audioTokenExample = '{{trigger.audio_path}}'
const callSidTokenExample = '{{trigger.body.CallSid}}'
const telegramChatIdExample = '{{trigger.body.message.chat.id}}'

const label = ref('')
const config = ref({})
const templateId = ref(null)

function resetFromNode() {
  label.value = props.node.data.label || ''
  config.value = JSON.parse(JSON.stringify(props.node.data.config || {}))
  templateId.value = config.value.prompt_template_id || null
}
resetFromNode()
watch(() => props.node.id, resetFromNode)

watch(templateId, (val) => {
  config.value.prompt_template_id = val
  if (!config.value.template_values) config.value.template_values = {}
})

const templateVariables = computed(() => {
  const tpl = props.promptTemplates.find(t => t.id === templateId.value)
  if (!tpl || !tpl.content) return []
  const matches = tpl.content.match(/\$([a-zA-Z0-9_]+)/g)
  return matches ? Array.from(new Set(matches)) : []
})

function setTemplateValue(key, value) {
  if (!config.value.template_values) config.value.template_values = {}
  config.value.template_values[key] = value
}

const viewingDocId = ref(null)

function toggleKnowledgeDoc(docId) {
  if (!config.value.knowledge_document_ids) config.value.knowledge_document_ids = []
  const idx = config.value.knowledge_document_ids.indexOf(docId)
  if (idx === -1) config.value.knowledge_document_ids.push(docId)
  else config.value.knowledge_document_ids.splice(idx, 1)
}

const variableOptions = computed(() => buildVariableOptions(props.allNodes, props.node.id))

function apply() {
  emit('apply', {
    id: props.node.id,
    label: label.value,
    config: config.value,
  })
}

// --- tiny local field components (kept in this file to avoid a component-per-input) ---
// Deliberately undeclared props: everything arrives in `attrs` so plain
// object/string values and `onUpdate:modelValue`/`onPick` callbacks both work.
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

const VariableMenu = (_props, { attrs }) => h('select', {
  value: '',
  onChange: (e) => {
    if (e.target.value) attrs.onPick(e.target.value)
    e.target.value = ''
  },
  title: 'Değişken ekle',
  class: 'w-9 shrink-0 rounded-xl bg-neutral-900 border border-neutral-700 text-[10px] text-neutral-400 focus:outline-none focus:border-accent cursor-pointer text-center',
}, [
  h('option', { value: '' }, '+ ⤵'),
  ...attrs.options.map(o => h('option', { value: o.token }, o.label)),
])

const FieldWithPicker = (_props, { attrs }) => h('div', [
  h('div', { class: 'flex items-center justify-between mb-1' }, [
    h('label', { class: 'block text-xs font-medium text-neutral-400' }, attrs.label),
  ]),
  h('div', { class: 'flex gap-1.5' }, [
    attrs.textarea
      ? h('textarea', {
          value: attrs.modelValue,
          rows: 3,
          placeholder: attrs.placeholder,
          onInput: (e) => attrs['onUpdate:modelValue'](e.target.value),
          class: 'flex-1 min-w-0 rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent font-sans',
        })
      : h('input', {
          value: attrs.modelValue,
          type: 'text',
          placeholder: attrs.placeholder,
          onInput: (e) => attrs['onUpdate:modelValue'](e.target.value),
          class: 'flex-1 min-w-0 rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent',
        }),
    h(VariableMenu, { options: attrs.options, onPick: (t) => attrs['onUpdate:modelValue']((attrs.modelValue || '') + t) }),
  ]),
])

const KeyValueList = (_props, { attrs }) => {
  const rows = attrs.modelValue || []
  const update = (newRows) => attrs['onUpdate:modelValue'](newRows)
  return h('div', [
    h('div', { class: 'flex items-center justify-between mb-1' }, [
      h('label', { class: 'block text-xs font-medium text-neutral-400' }, attrs.label),
      h('button', {
        type: 'button',
        onClick: () => update([...rows, { key: '', value: '' }]),
        class: 'text-[10px] text-accent hover:opacity-80 cursor-pointer',
      }, '+ Ekle'),
    ]),
    h('div', { class: 'space-y-1.5' }, rows.map((row, idx) => h('div', { class: 'flex gap-1.5', key: idx }, [
      h('input', {
        value: row.key,
        placeholder: 'anahtar',
        onInput: (e) => update(rows.map((r, i) => i === idx ? { ...r, key: e.target.value } : r)),
        class: 'w-24 shrink-0 rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent',
      }),
      h('input', {
        value: row.value,
        placeholder: 'değer veya {{node.field}}',
        onInput: (e) => update(rows.map((r, i) => i === idx ? { ...r, value: e.target.value } : r)),
        class: 'flex-1 min-w-0 rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent',
      }),
      h(VariableMenu, { options: attrs.options, onPick: (t) => update(rows.map((r, i) => i === idx ? { ...r, value: (r.value || '') + t } : r)) }),
      h('button', {
        type: 'button',
        onClick: () => update(rows.filter((_, i) => i !== idx)),
        class: 'w-7 shrink-0 text-neutral-500 hover:text-red-400 cursor-pointer',
      }, '✕'),
    ]))),
  ])
}
</script>
