<template>
  <AppLayout :title="t('documentation.title', 'API Dökümantasyonu')">
    <div class="h-[80vh] rounded-2xl border border-neutral-800 bg-surface overflow-hidden shadow-xl shadow-black/20">
      <iframe
        :src="docsUrl"
        title="Swagger API Dökümantasyonu"
        class="w-full h-full border-0 bg-white"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '../../Layouts/AppLayout.vue'
import { useI18n } from '../../i18n'

const { t } = useI18n()

const docsUrl = '/api/documentation'
</script>
