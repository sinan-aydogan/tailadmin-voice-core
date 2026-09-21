<template>
  <div
    class="min-w-[220px] max-w-[260px] rounded-2xl border shadow-sm transition-all"
    :class="[
      colors.bg,
      selected ? 'border-accent ring-2 ring-accent/30' : colors.border,
      'bg-surface',
    ]"
  >
    <Handle
      v-if="def.hasTarget"
      type="target"
      :position="Position.Left"
      class="!w-2.5 !h-2.5 !bg-neutral-500 !border-2 !border-neutral-900"
    />

    <div class="px-3.5 py-2.5 flex items-center gap-2.5 border-b" :class="colors.border">
      <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-sm" :class="[colors.bg, colors.text]">
        {{ def.icon }}
      </span>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-neutral-100 truncate">{{ data.label || def.label }}</div>
        <div class="text-[10px] text-neutral-500 truncate">{{ def.label }}</div>
      </div>
    </div>

    <div class="px-3.5 py-2.5 text-[11px] text-neutral-400 leading-relaxed break-words">
      {{ def.summary(data.config || {}) }}
    </div>

    <template v-if="def.hasSource === 'condition'">
      <div class="relative h-8 border-t" :class="colors.border">
        <span class="absolute left-3 top-1.5 text-[10px] font-medium text-emerald-400">Evet</span>
        <span class="absolute right-3 top-1.5 text-[10px] font-medium text-rose-400">Hayır</span>
      </div>
      <Handle
        type="source"
        id="true"
        :position="Position.Right"
        :style="{ top: '40%' }"
        class="!w-2.5 !h-2.5 !bg-emerald-500 !border-2 !border-neutral-900"
      />
      <Handle
        type="source"
        id="false"
        :position="Position.Right"
        :style="{ top: '85%' }"
        class="!w-2.5 !h-2.5 !bg-rose-500 !border-2 !border-neutral-900"
      />
    </template>
    <Handle
      v-else-if="def.hasSource"
      type="source"
      :position="Position.Right"
      class="!w-2.5 !h-2.5 !bg-neutral-500 !border-2 !border-neutral-900"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import { NODE_DEFS, colorClasses } from '../../Utils/flowNodeDefs'

const props = defineProps({
  id: String,
  data: Object,
  selected: Boolean,
})

const def = computed(() => NODE_DEFS[props.data.nodeType])
const colors = computed(() => colorClasses(def.value.color))
</script>
