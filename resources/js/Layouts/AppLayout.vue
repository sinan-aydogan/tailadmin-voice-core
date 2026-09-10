<template>
  <div class="min-h-screen bg-bg text-text flex flex-col antialiased">
    <div class="flex-1 flex min-h-0">
      <!-- Sidebar -->
      <aside class="w-64 flex-shrink-0 bg-surface border-r border-neutral-800 flex flex-col">
        <!-- Logo -->
        <div class="h-16 flex items-center px-6 border-b border-neutral-800 gap-3">
          <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center text-bg shadow-sm">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
            </svg>
          </div>
          <div>
            <div class="font-bold tracking-tight text-base text-neutral-100">Voice Core</div>
            <div class="text-[10px] text-accent-400 font-medium tracking-wider uppercase">Native + Inertia</div>
          </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
          <Link
            v-for="item in navItems"
            :key="item.href"
            :href="item.href"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="$page.url.startsWith(item.href) ? 'bg-accent/15 text-accent-300 font-semibold' : 'text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800/60'"
          >
            <span class="w-5 h-5 flex items-center justify-center">
              <component :is="item.icon" class="w-5 h-5" />
            </span>
            <span>{{ item.label }}</span>
            <span v-if="item.badge && item.badge > 0" class="ml-auto text-xs px-2 py-0.5 rounded-full bg-accent text-bg font-bold">
              {{ item.badge }}
            </span>
          </Link>
        </nav>

        <!-- Environment Info -->
        <div class="p-4 border-t border-neutral-800 text-xs text-neutral-500">
          <div class="flex items-center justify-between mb-1">
            <span>Runtime</span>
            <span class="text-neutral-300 font-mono">NativePHP</span>
          </div>
          <div class="flex items-center justify-between">
            <span>Framework</span>
            <span class="text-neutral-300 font-mono">Laravel 13</span>
          </div>
        </div>
      </aside>

      <!-- Main Content Area -->
      <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <!-- Header -->
        <header class="h-16 border-b border-neutral-800 bg-surface/50 backdrop-blur px-8 flex items-center justify-between flex-shrink-0">
          <h1 class="text-lg font-semibold text-neutral-100">{{ title }}</h1>
          <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-neutral-800/80 text-neutral-300 border border-neutral-700">
              <span class="w-1.5 h-1.5 rounded-full bg-success-500"></span>
              Queue Worker Aktif
            </span>
          </div>
        </header>

        <!-- Page Body -->
        <main class="flex-1 overflow-y-auto p-8">
          <slot />
        </main>
      </div>
    </div>

    <!-- Live System Footer -->
    <SystemFooter />
  </div>
</template>

<script setup>
import { computed, h } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import SystemFooter from '../Components/SystemFooter.vue'

const props = defineProps({
  title: {
    type: String,
    default: 'Dashboard'
  }
})

// Simple inline icons for sidebar
const createSvg = (path) => ({
  render: () => h('svg', { class: 'w-5 h-5', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: path })
  ])
})

const navItems = [
  { label: 'Dashboard', href: '/dashboard', icon: createSvg('M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6') },
  { label: 'Metin Okuma (TTS)', href: '/tts', icon: createSvg('M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z') },
  { label: 'Sesten Metne (STT)', href: '/stt', icon: createSvg('M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z') },
  { label: 'Ses Profilleri', href: '/profiles', icon: createSvg('M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z') },
  { label: 'Model Yöneticisi', href: '/models', icon: createSvg('M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4') },
  { label: 'İşlem Kuyruğu', href: '/queue', icon: createSvg('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01') },
  { label: 'İş Listeleri', href: '/playlists', icon: createSvg('M4 6h16M4 10h16M4 14h16M4 18h16') },
  { label: 'Ayarlar', href: '/settings', icon: createSvg('M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z') },
]
</script>
