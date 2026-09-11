<template>
  <AppLayout :title="t('support.title', 'Destek Ol & Destekçiler')">
    <div class="space-y-8 max-w-6xl mx-auto pb-12">
      <!-- Hero Banner -->
      <div class="p-8 sm:p-10 rounded-3xl bg-surface border border-neutral-800 relative overflow-hidden shadow-xl shadow-black/20">
        <!-- Ambient Glow -->
        <div class="absolute -right-16 -top-16 w-80 h-80 bg-gradient-to-br from-pink-500/15 via-accent/10 to-transparent rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-60 h-60 bg-gradient-to-tr from-accent/10 to-transparent rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl">
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-pink-500/15 border border-pink-500/25 text-pink-400 text-xs font-semibold mb-4">
            <svg class="w-4 h-4 fill-current animate-pulse" viewBox="0 0 24 24">
              <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
            </svg>
            <span>{{ t('support.badge', 'Açık Kaynak & Bağımsız Geliştirme') }}</span>
          </div>

          <h2 class="text-2xl sm:text-3xl font-bold text-neutral-100 tracking-tight">
            {{ t('support.hero_title', 'Voice Core’u Birlikte Büyütelim') }}
          </h2>
          <p class="text-sm sm:text-base text-neutral-400 mt-3 leading-relaxed">
            {{ t('support.subtitle', 'Voice Core açık kaynaklı ve bağımsız bir yapay zeka ses işleme istasyonudur. Katkılarınızla projeyi büyütebilir, yeni modellerin (TTS/STT) entegrasyonuna ve GPU optimizasyonlarına destek olabilirsiniz.') }}
          </p>
        </div>
      </div>

      <!-- Destek ve Bağış Kanalları -->
      <section class="space-y-4">
        <div>
          <h3 class="text-lg font-bold text-neutral-100 flex items-center gap-2.5">
            <span class="w-2 h-2 rounded-full bg-accent"></span>
            {{ t('support.donations_title', 'Bağış & Destek Kanalları') }}
          </h3>
          <p class="text-xs sm:text-sm text-neutral-400 mt-1">
            {{ t('support.donations_desc', 'Projenin sürdürülebilirliği ve bağımsız geliştirilmesi için bize bir kahve ısmarlayabilirsiniz.') }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Ko-fi Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 hover:border-neutral-700 transition-all flex flex-col justify-between group">
            <div>
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-xl bg-sky-500/15 text-sky-400 flex items-center justify-center font-bold text-sm">
                    ☕
                  </div>
                  <div>
                    <h4 class="font-bold text-neutral-100">Ko-fi</h4>
                    <span class="text-xs text-neutral-500">ko-fi.com/sinanaydogan</span>
                  </div>
                </div>
                <span class="text-xs px-2.5 py-1 rounded-full bg-neutral-900 border border-neutral-700 text-neutral-300 font-medium">
                  Bağış & Kahve
                </span>
              </div>
              <p class="text-xs text-neutral-400 leading-relaxed mt-2">
                Ko-fi üzerinden komisyonsuz olarak tek seferlik veya aylık kahve ısmarlayarak projeyi destekleyebilirsiniz.
              </p>
            </div>

            <div class="pt-5 mt-4 border-t border-neutral-800/80 flex items-center justify-between">
              <a
                href="https://ko-fi.com/sinanaydogan"
                target="_blank"
                rel="noopener noreferrer"
                @click.prevent="openExternal('https://ko-fi.com/sinanaydogan')"
                class="inline-flex items-center hover:opacity-90 transition-opacity"
                title="Support on Ko-fi"
              >
                <img
                  src="https://ko-fi.com/img/githubbutton_sm.svg"
                  alt="Ko-fi'de Destekle"
                  class="h-9 w-auto rounded shadow-sm"
                />
              </a>
              <button
                type="button"
                @click="openExternal('https://ko-fi.com/sinanaydogan')"
                class="text-xs text-accent-400 hover:text-accent-300 flex items-center gap-1 font-medium group-hover:translate-x-0.5 transition-transform"
              >
                <span>Sayfaya Git</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Buy Me A Coffee Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 hover:border-neutral-700 transition-all flex flex-col justify-between group">
            <div>
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-400 flex items-center justify-center font-bold text-sm">
                    💛
                  </div>
                  <div>
                    <h4 class="font-bold text-neutral-100">Buy Me a Coffee</h4>
                    <span class="text-xs text-neutral-500">buymeacoffee.com/sinanaydogan</span>
                  </div>
                </div>
                <span class="text-xs px-2.5 py-1 rounded-full bg-neutral-900 border border-neutral-700 text-neutral-300 font-medium">
                  Bağış & Üyelik
                </span>
              </div>
              <p class="text-xs text-neutral-400 leading-relaxed mt-2">
                Buy Me a Coffee platformu üzerinden güvenli kredi kartı veya ödeme yöntemleriyle bağışta bulunabilirsiniz.
              </p>
            </div>

            <div class="pt-5 mt-4 border-t border-neutral-800/80 flex items-center justify-between">
              <a
                href="https://www.buymeacoffee.com/sinanaydogan"
                target="_blank"
                rel="noopener noreferrer"
                @click.prevent="openExternal('https://www.buymeacoffee.com/sinanaydogan')"
                class="inline-flex items-center hover:opacity-90 transition-opacity"
                title="Buy Me a Coffee"
              >
                <img
                  src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png"
                  alt="Buy Me A Coffee"
                  class="h-9 w-auto rounded shadow-sm object-contain"
                />
              </a>
              <button
                type="button"
                @click="openExternal('https://www.buymeacoffee.com/sinanaydogan')"
                class="text-xs text-accent-400 hover:text-accent-300 flex items-center gap-1 font-medium group-hover:translate-x-0.5 transition-transform"
              >
                <span>Sayfaya Git</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Destekçiler Bölümü (Sponsors) -->
      <section class="space-y-4 pt-2">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-bold text-neutral-100 flex items-center gap-2.5">
              <span class="w-2 h-2 rounded-full bg-pink-500"></span>
              {{ t('support.sponsors_title', 'Destekçilerimiz & Sponsorlar') }}
            </h3>
            <p class="text-xs sm:text-sm text-neutral-400 mt-1">
              {{ t('support.sponsors_desc', 'Voice Core gelişimine katkıda bulunan ve projemize inanan değerli destekçilerimiz.') }}
            </p>
          </div>
        </div>

        <!-- Featured Sponsor: Panelica -->
        <div class="p-6 sm:p-8 rounded-3xl bg-surface border border-neutral-800 hover:border-neutral-700 transition-all relative overflow-hidden group">
          <!-- Subtle Top Accent Glow -->
          <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-accent via-indigo-500 to-pink-500 opacity-80"></div>

          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
              <!-- Panelica Logo Card -->
              <a
                href="https://panelica.com"
                target="_blank"
                rel="noopener noreferrer"
                @click.prevent="openExternal('https://panelica.com')"
                class="h-20 w-48 rounded-2xl bg-neutral-900 border border-neutral-700/80 p-3.5 flex items-center justify-center shrink-0 hover:border-neutral-500 transition-colors group-hover:scale-102"
                title="Panelica"
              >
                <img
                  src="https://panelica.com/assets/images/logo-light.png"
                  alt="Panelica"
                  class="max-h-12 max-w-full object-contain filter brightness-105"
                  loading="lazy"
                />
              </a>

              <!-- Info -->
              <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2.5">
                  <h4 class="text-xl font-bold text-neutral-100 tracking-tight">Panelica</h4>
                  <span class="px-2.5 py-0.5 rounded-full bg-accent/15 border border-accent/30 text-accent-300 text-xs font-semibold">
                    Destekçi Sponsor
                  </span>
                  <span class="text-xs text-neutral-500 font-mono">panelica.com</span>
                </div>

                <p class="text-xs sm:text-sm text-neutral-300 max-w-2xl leading-relaxed">
                  Hosting sağlayıcıları, ajanslar ve geliştiriciler için modern, yapay zeka (AI) destekli sunucu yönetim paneli ve cPanel/Plesk alternatifi. Tek tıkla migration, native mobil uygulamalar ve sezgisel mimari ile sunucularınızı zahmetsizce yönetin.
                </p>

                <!-- Tags -->
                <div class="flex flex-wrap gap-2 pt-1">
                  <span class="text-[11px] px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-800 text-neutral-400">
                    🤖 AI Co-Pilot
                  </span>
                  <span class="text-[11px] px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-800 text-neutral-400">
                    ⚡ Modern cPanel Alternatifi
                  </span>
                  <span class="text-[11px] px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-800 text-neutral-400">
                    🚀 1-Click Migration
                  </span>
                  <span class="text-[11px] px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-800 text-neutral-400">
                    🛡️ Yüksek Güvenlik
                  </span>
                </div>
              </div>
            </div>

            <!-- Action Button -->
            <div class="shrink-0 flex items-center lg:self-center">
              <button
                type="button"
                @click="openExternal('https://panelica.com')"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold bg-accent text-bg hover:opacity-95 hover:shadow-lg hover:shadow-accent/15 transition-all"
              >
                <span>Panelica'yı Ziyaret Et</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Become a Sponsor Callout -->
        <div class="p-6 rounded-2xl bg-surface/60 border border-neutral-800/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mt-6">
          <div class="space-y-1">
            <h4 class="text-sm font-bold text-neutral-200">
              {{ t('support.become_sponsor', 'Sponsor Olmak İster misiniz?') }}
            </h4>
            <p class="text-xs text-neutral-400 max-w-xl">
              {{ t('support.become_sponsor_desc', 'Voice Core projesine sponsor olarak markanızı veya ürününüzü yerel ve bağımsız AI geliştiricilerine duyurabilirsiniz.') }}
            </p>
          </div>
          <a
            href="https://github.com/sinan-aydogan/tailadmin-voice-core"
            target="_blank"
            rel="noopener noreferrer"
            @click.prevent="openExternal('https://github.com/sinan-aydogan/tailadmin-voice-core')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-neutral-900 hover:bg-neutral-800 text-neutral-200 border border-neutral-700 transition-colors shrink-0"
          >
            <svg class="w-4 h-4 text-neutral-400" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
            </svg>
            <span>İletişime Geç / GitHub</span>
          </a>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '../../Layouts/AppLayout.vue'
import { useI18n } from '../../i18n'
import axios from 'axios'

const { t } = useI18n()

const openExternal = async (url) => {
  if (typeof window !== 'undefined' && window.Native) {
    try {
      await axios.post('/api/system/open-url', { url })
      return
    } catch (e) {
      console.warn('Native openExternal failed, falling back to window.open', e)
    }
  }
  window.open(url, '_blank', 'noopener,noreferrer')
}
</script>
