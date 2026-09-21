<template>
  <AppLayout :title="`Akış: ${form.name || 'İsimsiz'}`">
    <div class="flex flex-col h-full space-y-4">
      <!-- Top bar -->
      <div class="p-4 rounded-2xl bg-surface border border-neutral-800 flex flex-col lg:flex-row lg:items-center justify-between gap-3 shadow-sm shrink-0">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <Link href="/flows" class="p-2 rounded-xl hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 transition-colors shrink-0" title="Akışlara dön">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </Link>
          <input
            v-model="form.name"
            type="text"
            placeholder="Akış adı"
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

      <!-- Canvas area -->
      <div class="flex-1 min-h-[520px] rounded-2xl border border-neutral-800 bg-neutral-950 overflow-hidden flex">
        <!-- Palette -->
        <div class="w-52 shrink-0 border-r border-neutral-800 bg-surface p-3 space-y-2 overflow-y-auto">
          <div class="text-[11px] font-semibold text-neutral-500 uppercase tracking-wide px-1 mb-1">Düğümler</div>
          <div
            v-for="(def, type) in paletteDefs"
            :key="type"
            draggable="true"
            @dragstart="onDragStart($event, type)"
            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border cursor-grab active:cursor-grabbing transition-colors"
            :class="[colorClasses(def.color).bg, colorClasses(def.color).border, 'hover:brightness-125']"
          >
            <span class="text-sm">{{ def.icon }}</span>
            <span class="text-xs font-medium text-neutral-200">{{ def.label }}</span>
          </div>
          <p class="text-[10px] text-neutral-600 px-1 pt-2 leading-relaxed">Düğümleri canvas'a sürükleyip bırakın, ardından bağlamak için kenarlarından çekin.</p>
        </div>

        <!-- Flow canvas -->
        <div class="flex-1 relative" @drop="onDrop" @dragover.prevent>
          <VueFlow
            v-model:nodes="nodes"
            v-model:edges="edges"
            :default-viewport="{ zoom: 0.9 }"
            :min-zoom="0.2"
            :max-zoom="1.5"
            @connect="onConnect"
            @node-click="onNodeClick"
            @pane-click="selectedNodeId = null"
            class="bg-neutral-950"
          >
            <Background :gap="18" pattern-color="#27272a" />
            <Controls />
            <template #node-voiceNode="nodeProps">
              <FlowNode v-bind="nodeProps" />
            </template>
          </VueFlow>
        </div>

        <!-- Inspector -->
        <FlowInspector
          v-if="selectedNode"
          :node="selectedNode"
          :all-nodes="nodes"
          :prompt-templates="promptTemplates"
          :voice-profiles="voiceProfiles"
          :voice-models="voiceModels"
          :trigger-url="flow.trigger_url"
          :knowledge-documents="knowledgeDocuments"
          @apply="applyInspector"
          @close="selectedNodeId = null"
        />
      </div>
    </div>

    <!-- Test Run Modal -->
    <div v-if="showTestModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="w-full max-w-2xl rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
          <h2 class="text-base font-semibold text-neutral-100">Akışı Test Et</h2>
          <button @click="showTestModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
        </div>

        <div>
          <label class="block text-xs font-medium text-neutral-400 mb-1">Örnek JSON Gövde (isteğe bağlı)</label>
          <textarea
            v-model="testBodyJson"
            rows="3"
            placeholder='{"ornek_alan": "deger"}'
            class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 font-mono focus:outline-none focus:border-accent"
          ></textarea>
        </div>

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
            {{ testResult.success ? 'Akış başarıyla tamamlandı.' : ('Hata: ' + testResult.message) }}
          </div>
          <div v-for="log in testResult.run?.logs || []" :key="log.id" class="p-2.5 rounded-xl bg-neutral-900 border border-neutral-800 text-[11px]">
            <div class="flex items-center justify-between">
              <span class="font-mono text-neutral-300">{{ log.node_type }}</span>
              <span :class="log.status === 'completed' ? 'text-emerald-400' : 'text-red-400'">{{ log.status }} · {{ log.duration_ms }}ms</span>
            </div>
            <pre v-if="log.output" class="mt-1.5 text-neutral-500 whitespace-pre-wrap break-all">{{ JSON.stringify(log.output, null, 2) }}</pre>
            <div v-if="log.error_message" class="mt-1.5 text-red-400">{{ log.error_message }}</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import { VueFlow, useVueFlow } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import '@vue-flow/controls/dist/style.css'
import AppLayout from '../../Layouts/AppLayout.vue'
import FlowNode from '../../Components/Flow/FlowNode.vue'
import FlowInspector from '../../Components/Flow/FlowInspector.vue'
import { NODE_DEFS, colorClasses } from '../../Utils/flowNodeDefs'

const props = defineProps({
  flow: Object,
  promptTemplates: Array,
  voiceProfiles: Array,
  voiceModels: Array,
  knowledgeDocuments: { type: Array, default: () => [] },
})

const flow = props.flow
const form = ref({
  name: flow.name,
  is_active: flow.is_active,
})


const paletteDefs = Object.fromEntries(Object.entries(NODE_DEFS).filter(([, def]) => !def.paletteHidden))

const { screenToFlowCoordinate, addEdges } = useVueFlow()

let idCounter = 0
function nextId() {
  idCounter += 1
  return `node_${Date.now()}_${idCounter}`
}

function toCanvasNode(rawNode) {
  return {
    id: rawNode.id,
    type: 'voiceNode',
    position: rawNode.position || { x: 0, y: 0 },
    deletable: rawNode.type !== 'trigger.webhook',
    data: {
      nodeType: rawNode.type,
      label: rawNode.label || '',
      config: rawNode.data || {},
    },
  }
}

function fromCanvasNode(node) {
  return {
    id: node.id,
    type: node.data.nodeType,
    position: node.position,
    label: node.data.label || undefined,
    data: node.data.config || {},
  }
}

const nodes = ref([])
const edges = ref([])

onMounted(() => {
  const def = flow.definition || { nodes: [], edges: [] }

  if (!def.nodes || def.nodes.length === 0) {
    nodes.value = [toCanvasNode({ id: nextId(), type: 'trigger.webhook', position: { x: 40, y: 160 }, data: {} })]
  } else {
    nodes.value = def.nodes.map(toCanvasNode)
  }

  edges.value = (def.edges || []).map(e => ({
    id: e.id,
    source: e.source,
    target: e.target,
    sourceHandle: e.sourceHandle || undefined,
    animated: true,
  }))
})

function onDragStart(evt, nodeType) {
  evt.dataTransfer.setData('application/flow-node-type', nodeType)
  evt.dataTransfer.effectAllowed = 'move'
}

function onDrop(evt) {
  const nodeType = evt.dataTransfer.getData('application/flow-node-type')
  if (!nodeType || !NODE_DEFS[nodeType]) return

  const position = screenToFlowCoordinate({ x: evt.clientX, y: evt.clientY })
  const def = NODE_DEFS[nodeType]

  nodes.value = [...nodes.value, toCanvasNode({
    id: nextId(),
    type: nodeType,
    position,
    data: def.defaultConfig(),
  })]
}

function onConnect(connection) {
  addEdges([{ ...connection, id: `edge_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, animated: true }])
}

const selectedNodeId = ref(null)
const selectedNode = computed(() => nodes.value.find(n => n.id === selectedNodeId.value) || null)

function onNodeClick({ node }) {
  selectedNodeId.value = node.id
}

function applyInspector({ id, label, config }) {
  const node = nodes.value.find(n => n.id === id)
  if (!node) return
  node.data = { ...node.data, label, config }
}

const saving = ref(false)
function save() {
  saving.value = true
  router.put(`/flows/${flow.id}`, {
    name: form.value.name,
    is_active: form.value.is_active,
    definition: {
      nodes: nodes.value.map(fromCanvasNode),
      edges: edges.value.map(e => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle || null })),
    },
  }, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => { saving.value = false },
  })
}

const copied = ref(false)
function copyTriggerUrl() {
  navigator.clipboard?.writeText(flow.trigger_url).then(() => {
    copied.value = true
    setTimeout(() => { copied.value = false }, 1500)
  })
}

// --- Test run ---
const showTestModal = ref(false)
const testBodyJson = ref('')
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
  if (testBodyJson.value.trim()) {
    try {
      const parsed = JSON.parse(testBodyJson.value)
      Object.entries(parsed).forEach(([k, v]) => payload.append(`body[${k}]`, v))
    } catch (e) {
      testResult.value = { success: false, message: 'JSON gövde geçersiz: ' + e.message }
      testing.value = false
      return
    }
  }
  if (testFile.value) {
    payload.append('audio', testFile.value)
  }

  try {
    const { data } = await axios.post(`/flows/${flow.id}/test-run`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    testResult.value = data
  } catch (e) {
    testResult.value = e.response?.data || { success: false, message: e.message }
  } finally {
    testing.value = false
  }
}
</script>

<style>
.vue-flow__controls {
  background: transparent;
  box-shadow: none;
}
.vue-flow__controls-button {
  background: #18181b;
  border: 1px solid #27272a;
  color: #d4d4d8;
}
.vue-flow__controls-button:hover {
  background: #27272a;
}
.vue-flow__edge-path {
  stroke: #52525b;
}
</style>
