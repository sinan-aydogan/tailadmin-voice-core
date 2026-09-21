// Shared metadata for the Flow Builder canvas: node palette, custom node
// rendering, and the inspector panel all read from this single source so
// adding a new node type only requires one edit here.

export const NODE_DEFS = {
  'trigger.webhook': {
    label: 'Tetikleyici (Webhook)',
    icon: '⚡',
    color: 'emerald',
    hasTarget: false,
    hasSource: true,
    paletteHidden: true, // auto-created once per flow, not draggable from palette
    defaultConfig: () => ({}),
    summary: () => 'Gelen API isteği',
    outputFields: ['body', 'audio_path', 'query'],
  },
  'action.stt': {
    label: 'Sesten Metne (STT)',
    icon: '🎙️',
    color: 'sky',
    hasTarget: true,
    hasSource: true,
    defaultConfig: () => ({ audio_source: '{{trigger.audio_path}}', language: 'tr', model_size: '' }),
    summary: (c) => truncate(c.audio_source) || 'Ses kaynağı seçilmedi',
    outputFields: ['text', 'language', 'duration'],
  },
  'action.llm': {
    label: 'LLM (Metin Üretimi)',
    icon: '🤖',
    color: 'purple',
    hasTarget: true,
    hasSource: true,
    defaultConfig: () => ({
      prompt: '', system_prompt: '', provider: '', model: '', prompt_template_id: null, template_values: {},
      use_memory: false, memory_turns: 6, conversation_id: '{{trigger.body.conversation_id}}',
      use_knowledge: false, knowledge_document_ids: [],
    }),
    summary: (c) => truncate(c.prompt) || 'Prompt girilmedi',
    outputFields: ['text', 'provider', 'model', 'conversation_id'],
  },
  'action.tts': {
    label: 'Metin Okuma (TTS)',
    icon: '🔊',
    color: 'amber',
    hasTarget: true,
    hasSource: true,
    defaultConfig: () => ({ text: '', engine: 'piper-tr', language: 'tr', profile_id: null }),
    summary: (c) => truncate(c.text) || 'Metin girilmedi',
    outputFields: ['audio_url', 'output_path', 'filename'],
  },
  'action.http': {
    label: 'HTTP İsteği',
    icon: '🌐',
    color: 'blue',
    hasTarget: true,
    hasSource: true,
    defaultConfig: () => ({ method: 'POST', url: '', headers: [], body: [], timeout: 30 }),
    summary: (c) => c.url ? `${c.method || 'POST'} ${truncate(c.url)}` : 'URL girilmedi',
    outputFields: ['status', 'body', 'headers', 'successful'],
  },
  'logic.condition': {
    label: 'Koşul (If/Else)',
    icon: '🔀',
    color: 'rose',
    hasTarget: true,
    hasSource: 'condition',
    defaultConfig: () => ({ left: '', operator: 'exists', right: '' }),
    summary: (c) => c.left ? `${truncate(c.left, 20)} ${operatorLabel(c.operator)} ${c.right ?? ''}` : 'Koşul tanımlanmadı',
    outputFields: ['result', 'matched_handle'],
  },
  'output.response': {
    label: 'Cevap Dön',
    icon: '↩️',
    color: 'teal',
    hasTarget: true,
    hasSource: false,
    defaultConfig: () => ({ mode: 'json', source: '', twiml_type: 'play' }),
    summary: (c) => truncate(c.source) || 'Kaynak seçilmedi',
    outputFields: [],
  },
}

export const OPERATOR_OPTIONS = [
  { value: 'equals', label: 'Eşittir (=)' },
  { value: 'not_equals', label: 'Eşit Değildir (≠)' },
  { value: 'contains', label: 'İçerir' },
  { value: 'not_contains', label: 'İçermez' },
  { value: 'regex', label: 'Regex Eşleşir' },
  { value: 'exists', label: 'Doludur (Var)' },
  { value: 'not_exists', label: 'Boştur (Yok)' },
  { value: 'gt', label: 'Büyüktür (>)' },
  { value: 'gte', label: 'Büyük Eşittir (≥)' },
  { value: 'lt', label: 'Küçüktür (<)' },
  { value: 'lte', label: 'Küçük Eşittir (≤)' },
]

function operatorLabel(value) {
  return OPERATOR_OPTIONS.find(o => o.value === value)?.label || value
}

function truncate(str, len = 40) {
  if (!str) return ''
  return str.length > len ? str.slice(0, len) + '…' : str
}

export function colorClasses(color) {
  const map = {
    emerald: { bg: 'bg-emerald-500/10', border: 'border-emerald-500/30', text: 'text-emerald-400' },
    sky: { bg: 'bg-sky-500/10', border: 'border-sky-500/30', text: 'text-sky-400' },
    purple: { bg: 'bg-purple-500/10', border: 'border-purple-500/30', text: 'text-purple-400' },
    amber: { bg: 'bg-amber-500/10', border: 'border-amber-500/30', text: 'text-amber-400' },
    blue: { bg: 'bg-blue-500/10', border: 'border-blue-500/30', text: 'text-blue-400' },
    rose: { bg: 'bg-rose-500/10', border: 'border-rose-500/30', text: 'text-rose-400' },
    teal: { bg: 'bg-teal-500/10', border: 'border-teal-500/30', text: 'text-teal-400' },
  }
  return map[color] || map.sky
}

/**
 * Every {{token}} a node further down the chain can reference: every other
 * node in the flow, expanded with its known output fields.
 */
export function buildVariableOptions(nodes, currentNodeId) {
  const options = []
  for (const node of nodes) {
    if (node.id === currentNodeId) continue
    const def = NODE_DEFS[node.data.nodeType]
    if (!def) continue

    const label = node.data.label || def.label
    if (node.data.nodeType === 'trigger.webhook') {
      options.push({ token: '{{trigger.audio_path}}', label: `${label} → audio_path` })
      options.push({ token: '{{trigger.body}}', label: `${label} → body (tüm JSON)` })
      continue
    }
    for (const field of def.outputFields) {
      options.push({ token: `{{${node.id}.${field}}}`, label: `${label} → ${field}` })
    }
  }
  return options
}
