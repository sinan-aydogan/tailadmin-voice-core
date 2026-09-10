<template>
  <footer class="h-9 border-t border-neutral-800 bg-surface/80 backdrop-blur flex items-center justify-between px-4 text-xs text-neutral-400 select-none sticky bottom-0 z-30">
    <div class="flex items-center gap-4">
      <!-- CPU -->
      <div class="flex items-center gap-1.5" title="CPU Kullanımı">
        <span class="font-medium text-neutral-300">CPU</span>
        <span :class="getPctClass(stats.cpu_pct)">{{ stats.cpu_pct }}%</span>
      </div>

      <span class="text-neutral-700">|</span>

      <!-- RAM -->
      <div class="flex items-center gap-1.5" title="RAM Kullanımı">
        <span class="font-medium text-neutral-300">RAM</span>
        <span :class="getPctClass(stats.ram?.pct)">
          {{ stats.ram?.used_gb || 0 }} / {{ stats.ram?.total_gb || 0 }} GB ({{ stats.ram?.pct || 0 }}%)
        </span>
      </div>

      <span class="text-neutral-700">|</span>

      <!-- Disk -->
      <div class="flex items-center gap-1.5" title="Modeller Diski">
        <span class="font-medium text-neutral-300">Disk</span>
        <span :class="getPctClass(stats.disk?.pct)">
          {{ stats.disk?.used_gb || 0 }} / {{ stats.disk?.total_gb || 0 }} GB ({{ stats.disk?.pct || 0 }}%)
        </span>
      </div>

      <span class="text-neutral-700">|</span>

      <!-- GPU / Accelerator -->
      <div class="flex items-center gap-1.5" title="Hızlandırıcı">
        <span class="font-medium text-neutral-300">Hızlandırıcı</span>
        <span class="text-accent-400 uppercase font-mono">{{ stats.gpu?.backend || 'CPU' }}</span>
        <span v-if="stats.gpu?.allocated_gb > 0" class="text-neutral-400">
          ({{ stats.gpu.allocated_gb }} GB)
        </span>
      </div>
    </div>

    <!-- Queue & Status indicator -->
    <div class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-success-500 animate-pulse"></span>
      <span class="text-neutral-400">Voice Core Native</span>
    </div>
  </footer>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const stats = ref({
  cpu_pct: 0,
  ram: { used_gb: 0, total_gb: 0, pct: 0 },
  disk: { used_gb: 0, total_gb: 0, pct: 0 },
  gpu: { backend: 'cpu', allocated_gb: 0 }
})

let timer = null

const fetchStats = async () => {
  try {
    const res = await fetch('/api/system/stats')
    if (res.ok) {
      stats.value = await res.json()
    }
  } catch (e) {
    // transient
  }
}

const getPctClass = (pct) => {
  if (!pct) return 'text-neutral-300'
  if (pct >= 92) return 'text-danger-500 font-semibold'
  if (pct >= 80) return 'text-warning-500'
  return 'text-neutral-300'
}

onMounted(() => {
  fetchStats()
  timer = setInterval(fetchStats, 3000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>
