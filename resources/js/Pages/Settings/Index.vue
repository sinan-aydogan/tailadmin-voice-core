<template>
  <AppLayout title="Ayarlar">
    <div class="space-y-6 max-w-4xl">
      <!-- Success Banner -->
      <div v-if="saveSuccess"
           class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <svg class="w-4 h-4 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
          </svg>
          <span class="font-medium">Ayarlar başarıyla kaydedildi. Model dizini güncellendi.</span>
        </div>
        <button @click="saveSuccess = false" class="text-emerald-400 hover:text-emerald-200">✕</button>
      </div>

      <!-- Application Display Language Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
            <span class="text-base select-none">🌐</span>
          </div>
          <div>
            <h2 class="text-base font-semibold text-neutral-100">{{ t('settings.app_language') }}</h2>
            <p class="text-xs text-neutral-500 mt-0.5">{{ t('settings.app_language_desc') }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 pt-1">
          <button
            v-for="lang in languages"
            :key="lang.code"
            type="button"
            @click="setLocale(lang.code)"
            :class="['p-3 rounded-xl border text-left transition-all flex items-center gap-2.5 cursor-pointer',
                     locale === lang.code
                       ? 'bg-accent/15 border-accent text-neutral-100 font-semibold shadow-lg shadow-accent/10'
                       : 'bg-neutral-900/60 border-neutral-800 hover:border-neutral-700 text-neutral-300 hover:bg-neutral-900']"
          >
            <span class="text-lg">{{ lang.flag }}</span>
            <div class="min-w-0 flex-1">
              <div class="text-xs truncate font-medium">{{ lang.nativeName }}</div>
              <div class="text-[10px] text-neutral-500 truncate">{{ lang.name }}</div>
            </div>
            <span v-if="locale === lang.code" class="text-accent text-xs">✓</span>
          </button>
        </div>
      </div>

      <form @submit.prevent="saveSettings" class="space-y-6">
        <!-- Models Directory Configuration Card -->
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
              </svg>
            </div>
            <div>
              <h2 class="text-base font-semibold text-neutral-100">Model Depolama ve İndirme Klasörü</h2>
              <p class="text-xs text-neutral-500 mt-0.5">Yapay zeka ses modellerinin (XTTS, Whisper, Piper vb.) indirileceği ve saklanacağı yerel disk dizini.</p>
            </div>
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1.5">Klasör Yolu</label>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                <input
                  v-model="form.models_dir"
                  type="text"
                  required
                  placeholder="C:\...\data\models"
                  class="flex-1 rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs font-mono text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                />

                <button
                  type="button"
                  @click="browseFolder"
                  :disabled="isBrowsing"
                  class="px-4 py-3 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-semibold transition-colors disabled:opacity-50 flex items-center justify-center gap-2 whitespace-nowrap border border-neutral-700/60"
                >
                  <svg v-if="isBrowsing" class="w-4 h-4 animate-spin text-accent" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                  </svg>
                  <svg v-else class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                  </svg>
                  <span>{{ isBrowsing ? 'Açılıyor...' : 'Klasör Seç' }}</span>
                </button>

                <button
                  type="button"
                  @click="resetToDefault"
                  class="px-3.5 py-3 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 text-xs font-medium transition-colors border border-neutral-800 whitespace-nowrap"
                  title="Varsayılan proje dizinine dön"
                >
                  Sıfırla
                </button>
              </div>
            </div>

            <div class="p-3.5 rounded-xl bg-neutral-900/60 border border-neutral-800 text-xs text-neutral-400 flex items-start gap-2.5">
              <svg class="w-4 h-4 text-accent flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="space-y-1">
                <div>Farklı bir sürücü (örneğin <code class="text-accent font-mono text-[11px]">D:\AI_Models</code> veya harici SSD) seçerek işletim sistemi diskinizde yer tasarrufu sağlayabilirsiniz.</div>
                <div class="text-[11px] text-neutral-500">Klasörü değiştirdiğinizde, Model Yöneticisi ve Metin Okuma motorları yeni klasördeki modelleri tarayacaktır.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Hugging Face API Token Configuration Card -->
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                <span class="text-base select-none">🤗</span>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="text-base font-semibold text-neutral-100">Hugging Face API Anahtarı (Access Token)</h2>
                  <span v-if="form.hf_token" class="px-2 py-0.5 rounded-md bg-emerald-500/15 border border-emerald-500/30 text-[10px] font-semibold text-emerald-400 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Aktif
                  </span>
                  <span v-else class="px-2 py-0.5 rounded-md bg-neutral-800 text-[10px] text-neutral-400">
                    Opsiyonel
                  </span>
                </div>
                <p class="text-xs text-neutral-500 mt-0.5">Hugging Face üzerinden model (XTTS, Bark, Whisper, Tortoise vb.) indirirken API istek sınırı (rate limit) ve kota engellerine takılmamak için kullanılır.</p>
              </div>
            </div>
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1.5">User Access Token</label>
              <div class="relative flex items-center">
                <input
                  v-model="form.hf_token"
                  :type="showToken ? 'text' : 'password'"
                  placeholder="hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 pr-24 text-xs font-mono text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                />
                <div class="absolute right-2.5 flex items-center gap-1">
                  <button
                    type="button"
                    @click="showToken = !showToken"
                    class="px-2.5 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs transition-colors"
                    :title="showToken ? 'Gizle' : 'Göster'"
                  >
                    {{ showToken ? 'Gizle' : 'Göster' }}
                  </button>
                  <button
                    v-if="form.hf_token"
                    type="button"
                    @click="form.hf_token = ''"
                    class="px-2 py-1.5 rounded-lg text-neutral-400 hover:text-red-400 text-xs transition-colors"
                    title="Temizle"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>

            <div class="p-3.5 rounded-xl bg-neutral-900/60 border border-neutral-800 text-xs text-neutral-400 flex items-start gap-2.5">
              <svg class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="space-y-1">
                <div>
                  Hugging Face hesabınız yoksa veya token oluşturmak isterseniz
                  <a href="https://huggingface.co/settings/tokens" target="_blank" rel="noopener noreferrer" class="text-accent underline hover:text-accent-300 font-medium">huggingface.co/settings/tokens</a>
                  sayfasından <span class="text-neutral-200 font-semibold">"Read" (Okuma)</span> izinli ücretsiz bir token alabilirsiniz.
                </div>
                <div class="text-[11px] text-neutral-500">
                  Anahtar girildiğinde, tüm model indirme ve snapshot istekleri kimlik doğrulamalı (authenticated) yapılır; böylece anonim IP rate limitlerine takılmadan yüksek hızda indirme gerçekleşir.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Hardware Configuration Card -->
        <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5">
          <h2 class="text-base font-semibold text-neutral-100">Sistem ve Donanım Tercihleri</h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- GPU Setting -->
            <div class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-2">
              <label class="block text-xs font-medium text-neutral-300">Hızlandırıcı / GPU Modu</label>
              <select
                v-model="form.use_gpu"
                class="w-full rounded-lg bg-neutral-800 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              >
                <option value="auto">Otomatik Algıla (CUDA / MPS / CPU)</option>
                <option value="cuda">NVIDIA CUDA (Zorla GPU)</option>
                <option value="mps">Apple Metal (MPS)</option>
                <option value="cpu">Yalnızca CPU</option>
              </select>
              <div class="text-[11px] text-neutral-500">Mevcut GPU donanımınıza göre çıkarım hızını optimize eder.</div>
            </div>

            <!-- Max CPU Threads -->
            <div class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-2">
              <div class="flex items-center justify-between">
                <label class="block text-xs font-medium text-neutral-300">Maksimum CPU Thread</label>
                <span class="text-xs font-mono font-semibold text-accent">{{ form.max_cpu_threads }} Çekirdek</span>
              </div>
              <input
                v-model.number="form.max_cpu_threads"
                type="range"
                min="1"
                max="32"
                step="1"
                class="w-full accent-accent cursor-pointer"
              />
              <div class="text-[11px] text-neutral-500">PyTorch çıkarımlarında kullanılacak maksimum CPU çekirdek sayısı.</div>
            </div>

            <!-- Default Engine -->
            <div class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-2 sm:col-span-2">
              <label class="block text-xs font-medium text-neutral-300">Varsayılan TTS Motoru</label>
              <select
                v-model="form.default_tts_engine"
                class="w-full rounded-lg bg-neutral-800 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              >
                <option value="piper-tr">Piper TTS (Türkçe - Hafif & Hızlı)</option>
                <option value="xtts-v2">Coqui XTTS v2 (Yüksek Kalite / Klonlama)</option>
                <option value="bark">Suno Bark (Doğal / İfadeli)</option>
              </select>
              <div class="text-[11px] text-neutral-500">Yeni metin okuma işlemlerinde varsayılan olarak seçilen motor.</div>
            </div>
          </div>

          <div class="pt-2 flex justify-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="px-6 py-2.5 rounded-xl font-semibold text-sm bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2"
            >
              <span v-if="form.processing" class="w-4 h-4 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
              <span>{{ form.processing ? 'Kaydediliyor...' : 'Ayarları Kaydet' }}</span>
            </button>
          </div>
        </div>
      </form>

      <!-- Architecture Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-3">
        <h3 class="text-sm font-semibold text-neutral-200">Mimari ve Donma Çözümü</h3>
        <p class="text-xs text-neutral-400 leading-relaxed">
          Bu sürümde tüm ses modelleri ve PyTorch inference işlemleri ayrı işletim sistemi süreçlerinde (Process) çalıştırılır.
          Laravel Queue Worker arkaplanda görevleri işletirken Electron ve Inertia arayüzü ana thread'de 60 FPS akıcı çalışmaya devam eder.
        </p>
        <div class="pt-2 flex flex-wrap gap-4 text-xs text-neutral-500 font-mono">
          <span>Backend: Laravel 13</span>
          <span>·</span>
          <span>Desktop: NativePHP Electron</span>
          <span>·</span>
          <span class="text-accent-400 font-medium">UI: @tailadmin/ui + Inertia + Vue 3</span>
          <span>·</span>
          <span>Engine: Python 3.11</span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import { useI18n } from '../../i18n'

const props = defineProps({
  settings: Object,
  default_models_dir: String,
})

const { locale, setLocale, t, languages } = useI18n()

const isBrowsing = ref(false)
const saveSuccess = ref(false)
const showToken = ref(false)

const form = useForm({
  models_dir: props.settings?.models_dir || props.default_models_dir || '',
  use_gpu: props.settings?.use_gpu || 'auto',
  max_cpu_threads: props.settings?.max_cpu_threads || 4,
  default_tts_engine: props.settings?.default_tts_engine || 'piper-tr',
  hf_token: props.settings?.hf_token || '',
})

const browseFolder = async () => {
  isBrowsing.value = true
  try {
    const res = await fetch('/settings/browse-folder', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      }
    })
    if (res.ok) {
      const data = await res.json()
      if (data.success && data.path) {
        form.models_dir = data.path
      }
    }
  } catch (err) {
    console.error('Klasör seçilirken hata:', err)
  } finally {
    isBrowsing.value = false
  }
}

const resetToDefault = () => {
  if (props.default_models_dir) {
    form.models_dir = props.default_models_dir
  }
}

const saveSettings = () => {
  form.post('/settings', {
    onSuccess: () => {
      saveSuccess.value = true
      setTimeout(() => {
        saveSuccess.value = false
      }, 4000)
    }
  })
}
</script>
