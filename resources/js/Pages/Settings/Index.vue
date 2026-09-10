<template>
  <AppLayout title="Ayarlar">
    <div class="space-y-6 max-w-3xl">
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-6">
        <h2 class="text-base font-semibold text-neutral-100">Sistem ve Donanım Yapılandırması</h2>

        <div class="space-y-4">
          <!-- GPU Setting -->
          <div class="flex items-center justify-between p-4 rounded-xl bg-neutral-900/80 border border-neutral-800">
            <div>
              <div class="text-sm font-medium text-neutral-200">Hızlandırıcı / GPU Modu</div>
              <div class="text-xs text-neutral-500 mt-0.5">Otomatik algılama ile CUDA veya Apple MPS kullanılır.</div>
            </div>
            <span class="px-3 py-1 rounded-lg bg-neutral-800 font-mono text-xs text-accent-300 uppercase">
              {{ settings.use_gpu }}
            </span>
          </div>

          <!-- Max Threads -->
          <div class="flex items-center justify-between p-4 rounded-xl bg-neutral-900/80 border border-neutral-800">
            <div>
              <div class="text-sm font-medium text-neutral-200">Maksimum CPU Thread Sayısı</div>
              <div class="text-xs text-neutral-500 mt-0.5">Torch çıkarımlarında kullanılacak çekirdek kotası.</div>
            </div>
            <span class="px-3 py-1 rounded-lg bg-neutral-800 font-mono text-xs text-neutral-200">
              {{ settings.max_cpu_threads }} Çekirdek
            </span>
          </div>

          <!-- Default Engine -->
          <div class="flex items-center justify-between p-4 rounded-xl bg-neutral-900/80 border border-neutral-800">
            <div>
              <div class="text-sm font-medium text-neutral-200">Varsayılan TTS Motoru</div>
              <div class="text-xs text-neutral-500 mt-0.5">Yeni metin okuma işlemlerinde otomatik seçilen motor.</div>
            </div>
            <span class="px-3 py-1 rounded-lg bg-neutral-800 font-mono text-xs text-neutral-200">
              {{ settings.default_tts_engine }}
            </span>
          </div>
        </div>
      </div>

      <!-- Architecture Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-3">
        <h3 class="text-sm font-semibold text-neutral-200">Mimari ve Donma Çözümü</h3>
        <p class="text-xs text-neutral-400 leading-relaxed">
          Bu sürümde tüm ses modelleri ve PyTorch inference işlemleri ayrı işletim sistemi süreçlerinde (Process) çalıştırılır.
          Laravel Queue Worker arkaplanda görevleri işletirken Electron ve Inertia arayüzü ana thread'de 60 FPS akıcı çalışmaya devam eder.
        </p>
        <div class="pt-2 flex gap-4 text-xs text-neutral-500 font-mono">
          <span>Backend: Laravel 13</span>
          <span>·</span>
          <span>Desktop: NativePHP Electron</span>
          <span>·</span>
          <span>UI: Inertia + Vue 3</span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '../../Layouts/AppLayout.vue'

defineProps({
  settings: Object,
})
</script>
