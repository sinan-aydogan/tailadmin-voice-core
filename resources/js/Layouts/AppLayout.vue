<template>
  <div class="h-screen w-screen overflow-hidden bg-bg text-text flex flex-col antialiased">
    <div class="flex-1 flex min-h-0 overflow-hidden">
      <!-- Sidebar -->
      <aside class="w-80 flex-shrink-0 h-full bg-surface border-r border-neutral-800 flex flex-col z-20 select-none">
        <!-- Logo -->
        <div class="h-16 flex items-center px-6 border-b border-neutral-800 shrink-0">
          <a
            href="https://github.com/sinan-aydogan/tailadmin-voice-core"
            target="_blank"
            rel="noopener noreferrer"
            @click.prevent="openExternal('https://github.com/sinan-aydogan/tailadmin-voice-core')"
            class="flex items-center gap-3 min-w-0 group cursor-pointer select-none"
            title="GitHub: sinan-aydogan/tailadmin-voice-core"
          >
            <div class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center text-bg shadow-sm shrink-0 group-hover:scale-105 group-hover:bg-accent-400 transition-all">
              <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
                <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                <line x1="12" y1="19" x2="12" y2="22" />
                <line x1="8" y1="22" x2="16" y2="22" />
              </svg>
            </div>
            <div class="min-w-0">
              <div class="font-bold tracking-tight text-base text-neutral-100 group-hover:text-accent-300 transition-colors truncate">Voice Core</div>
              <div class="text-[10px] text-accent-400/80 group-hover:text-accent-300 font-medium tracking-wider uppercase truncate">@tailadmin/ui · Native</div>
            </div>
          </a>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto py-4 px-3.5 space-y-1.5 min-h-0">
          <Link
            v-for="item in navItems"
            :key="item.href"
            :href="item.href"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors"
            :class="$page.url.startsWith(item.href) ? 'bg-accent/15 text-accent-300 font-semibold shadow-sm shadow-accent/5' : 'text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800/60'"
          >
            <span class="w-5 h-5 flex items-center justify-center shrink-0">
              <component :is="item.icon" class="w-5 h-5" />
            </span>
            <span class="truncate">{{ item.label }}</span>
            <span v-if="item.badge && item.badge > 0" class="ml-auto text-xs px-2 py-0.5 rounded-full bg-accent text-bg font-bold shrink-0">
              {{ item.badge }}
            </span>
          </Link>

          <!-- Divider -->
          <div class="pt-2 my-1 border-t border-neutral-800"></div>

          <!-- API Dökümantasyonu Link -->
          <Link
            href="/documentation"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors"
            :class="$page.url.startsWith('/documentation') ? 'bg-accent/15 text-accent-300 font-semibold shadow-sm shadow-accent/5' : 'text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800/60'"
          >
            <span class="w-5 h-5 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </span>
            <span class="truncate">{{ t('nav.api_docs', 'API Dokümantasyonu') }}</span>
          </Link>

          <!-- Destek Ol Link -->
          <Link
            href="/support"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors group"
            :class="$page.url.startsWith('/support') ? 'bg-pink-500/15 text-pink-300 font-semibold shadow-sm shadow-pink-500/5' : 'text-neutral-400 hover:text-pink-300 hover:bg-neutral-800/60'"
          >
            <span class="w-5 h-5 flex items-center justify-center shrink-0 text-pink-400 group-hover:scale-110 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
              </svg>
            </span>
            <span class="truncate">{{ t('nav.support') }}</span>
            <span class="ml-auto flex h-2 w-2 relative">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-pink-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-pink-500"></span>
            </span>
          </Link>
        </nav>

        <!-- Environment Info -->
        <div class="p-4 border-t border-neutral-800 text-xs text-neutral-500 space-y-1.5 shrink-0 bg-surface">
          <div class="flex items-center justify-between">
            <span>{{ t('header.runtime') }}</span>
            <span class="text-neutral-300 font-mono">NativePHP</span>
          </div>
          <div class="flex items-center justify-between">
            <span>UI Kit</span>
            <a
              href="https://www.npmjs.com/package/@tailadmin/ui"
              target="_blank"
              rel="noopener noreferrer"
              @click.prevent="openExternal('https://www.npmjs.com/package/@tailadmin/ui')"
              class="text-accent-400 hover:text-accent-300 hover:underline font-mono font-medium inline-flex items-center gap-1 transition-colors group cursor-pointer"
              title="@tailadmin/ui npm"
            >
              <span>@tailadmin/ui</span>
              <svg class="w-3 h-3 opacity-60 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>
          <div class="flex items-center justify-between">
            <span>{{ t('header.framework') }}</span>
            <span class="text-neutral-300 font-mono">Laravel 13</span>
          </div>
          <div class="flex items-center justify-between">
            <span>{{ t('header.creator') }}</span>
            <a
              href="https://tailadmin.dev"
              target="_blank"
              rel="noopener noreferrer"
              @click.prevent="openExternal('https://tailadmin.dev')"
              class="text-accent-400 hover:text-accent-300 hover:underline font-mono font-medium inline-flex items-center gap-1 transition-colors group cursor-pointer"
              title="Tailadmin (https://tailadmin.dev)"
            >
              <span>Tailadmin</span>
              <svg class="w-3 h-3 opacity-60 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>
        </div>
      </aside>

      <!-- Main Content Area -->
      <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
        <!-- Header -->
        <header class="h-16 border-b border-neutral-800 bg-surface/50 backdrop-blur px-8 flex items-center justify-between shrink-0 z-10">
          <h1 class="text-lg font-semibold text-neutral-100">{{ title }}</h1>

          <div class="flex items-center gap-3">
            <!-- Custom Page Header Actions Slot -->
            <slot name="header-actions" />

            <!-- Microphone Source Dropdown (Dil seçiminin sol tarafı) -->
            <div class="relative flex items-center">
              <div
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700/80 hover:border-neutral-600 transition-colors"
                :title="t('header.mic_source', 'Mikrofon Kaynağı')"
              >
                <svg class="w-3.5 h-3.5 text-accent shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                </svg>
                <select
                  :value="selectedAudioDeviceId"
                  @focus="ensureMicrophonePermission"
                  @click="ensureMicrophonePermission"
                  @change="selectAudioDevice($event.target.value)"
                  class="bg-transparent text-xs text-neutral-200 font-medium focus:outline-none cursor-pointer max-w-[170px] truncate pr-1"
                  :title="t('header.mic_source', 'Mikrofon Kaynağı')"
                >
                  <option value="" class="bg-neutral-900 text-neutral-200">
                    {{ t('header.default_mic', 'Varsayılan Mikrofon') }}
                  </option>
                  <option
                    v-for="(dev, idx) in audioInputDevices"
                    :key="dev.deviceId || idx"
                    :value="dev.deviceId"
                    class="bg-neutral-900 text-neutral-200"
                  >
                    {{ dev.label || `${t('header.microphone', 'Mikrofon')} ${idx + 1}` }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Language Switcher Dropdown -->
            <div class="relative flex items-center">
              <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700/80 hover:border-neutral-600 transition-colors">
                <span class="text-sm select-none">{{ currentLanguage.flag }}</span>
                <select
                  :value="locale"
                  @change="handleLanguageChange($event.target.value)"
                  class="bg-transparent text-xs text-neutral-200 font-medium focus:outline-none cursor-pointer pr-1"
                  :title="t('header.change_language')"
                >
                  <option
                    v-for="lang in languages"
                    :key="lang.code"
                    :value="lang.code"
                    class="bg-neutral-900 text-neutral-200 py-1"
                  >
                    {{ lang.flag }} {{ lang.nativeName }} ({{ lang.code }})
                  </option>
                </select>
              </div>
            </div>

            <!-- Queue Worker Status Badge -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-neutral-800/80 text-neutral-300 border border-neutral-700">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
              {{ t('header.worker_active') }}
            </span>
          </div>
        </header>

        <!-- Page Body -->
        <main class="flex-1 overflow-y-auto p-8 min-h-0">
          <slot />
        </main>
      </div>
    </div>

    <!-- Live System Footer -->
    <SystemFooter class="shrink-0" />
  </div>
</template>

<script setup>
import { computed, h, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import SystemFooter from '../Components/SystemFooter.vue'
import { useI18n } from '../i18n'
import { useAudioDevices } from '../Composables/useAudioDevices'

const props = defineProps({
  title: {
    type: String,
    default: 'Dashboard'
  }
})

const { locale, setLocale, t, languages, currentLanguage } = useI18n()
const {
  audioInputDevices,
  selectedAudioDeviceId,
  loadAudioDevices,
  ensureMicrophonePermission,
  selectAudioDevice,
} = useAudioDevices()

onMounted(() => {
  loadAudioDevices()
})

const handleLanguageChange = (code) => {
  setLocale(code)
}

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

// Simple inline icons for sidebar
const createSvg = (path) => ({
  render: () => h('svg', { class: 'w-5 h-5', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: path })
  ])
})

const navItems = computed(() => [
  { label: t('nav.dashboard'), href: '/dashboard', icon: createSvg('M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6') },
  { label: t('nav.tts'), href: '/tts', icon: createSvg('M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z') },
  { label: t('nav.stt'), href: '/stt', icon: createSvg('M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z') },
  { label: t('nav.sfx'), href: '/sfx', icon: createSvg('M13 10V3L4 14h7v7l9-11h-7z') },
  { label: t('nav.music'), href: '/music', icon: createSvg('M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3') },
  { label: t('nav.profiles'), href: '/profiles', icon: createSvg('M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z') },
  { label: t('nav.models'), href: '/models', icon: createSvg('M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4') },
  { label: t('nav.queue'), href: '/queue', icon: createSvg('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01') },
  { label: t('nav.playlists'), href: '/playlists', icon: createSvg('M4 6h16M4 10h16M4 14h16M4 18h16') },
  { label: t('nav.prompts'), href: '/prompts', icon: createSvg('M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z') },
  { label: t('nav.flows'), href: '/flows', icon: createSvg('M9 3v4a1 1 0 01-1 1H4m11-5v4a1 1 0 001 1h4M4 14v3a3 3 0 003 3h3m10-6v3a3 3 0 01-3 3h-3m-4-6h8') },
  { label: t('nav.agents'), href: '/agents', icon: createSvg('M9.663 17h4.673M12 3a3 3 0 00-3 3v1H7a2 2 0 00-2 2v3a5 5 0 005 5h4a5 5 0 005-5V9a2 2 0 00-2-2h-2V6a3 3 0 00-3-3zM9 12h.01M15 12h.01') },
  { label: t('nav.knowledge'), href: '/knowledge', icon: createSvg('M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s4.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253') },
  { label: t('nav.settings'), href: '/settings', icon: createSvg('M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z') },
])
</script>
