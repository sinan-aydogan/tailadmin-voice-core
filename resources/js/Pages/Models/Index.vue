<template>
  <AppLayout title="Model Yöneticisi">
    <div class="space-y-6">

      <!-- Active Downloading Notification Banner -->
      <div
        v-if="activeDownloads.length > 0"
        class="p-4 rounded-2xl bg-gradient-to-r from-sky-500/10 via-accent/10 to-emerald-500/10 border border-sky-500/30 text-neutral-200 shadow-lg relative overflow-hidden"
      >
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-start md:items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-sky-500/20 border border-sky-500/40 flex items-center justify-center flex-shrink-0 text-sky-400">
              <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-sky-400">Canlı Model İndirme</span>
                <span class="w-2 h-2 rounded-full bg-sky-400 animate-ping"></span>
              </div>
              <div class="text-sm font-semibold text-neutral-100 mt-0.5">
                <span v-for="(ad, idx) in activeDownloads" :key="ad.id">
                  {{ getModelName(ad.model_id) }} (%{{ Number(ad.progress || 0).toFixed(1) }}){{ idx < activeDownloads.length - 1 ? ', ' : '' }}
                </span>
              </div>
              <p class="text-[11px] text-neutral-400 mt-0.5">
                İndirme arka planda çalışıyor. Arayüz donmaz, diğer sayfaları kullanabilir veya bu sayfada ilerlemeyi canlı izleyebilirsiniz.
              </p>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
              <div class="text-xs font-mono font-bold text-sky-300">
                {{ formatBytes(primaryActiveDownload?.downloaded_bytes) }} / {{ formatBytes(primaryActiveDownload?.total_bytes) }}
              </div>
              <div class="text-[10px] text-neutral-400">Aktarılan Veri</div>
            </div>
            <button
              @click="fetchDownloads(true)"
              class="text-xs px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 flex items-center gap-1.5 transition-colors"
            >
              <svg :class="['w-3.5 h-3.5', { 'animate-spin': isRefreshing }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>Güncelle</span>
            </button>
          </div>
        </div>

        <!-- Global Progress Bar for Primary Download -->
        <div v-if="primaryActiveDownload" class="mt-3">
          <div class="w-full h-1.5 bg-neutral-800/80 rounded-full overflow-hidden">
            <div
              class="h-full bg-gradient-to-r from-sky-400 via-accent to-emerald-400 rounded-full transition-all duration-300"
              :style="{ width: Math.min(100, Math.max(5, primaryActiveDownload.progress || 0)) + '%' }"
            ></div>
          </div>
        </div>
      </div>

      <!-- Header Card -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-base font-semibold text-neutral-100">Yapay Zeka Ses & Dil Modelleri</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-neutral-800 text-neutral-300 border border-neutral-700">
              {{ filteredModels.length }} Model Listelendi
            </span>
          </div>
          <p class="text-xs text-neutral-400 mt-1">
            HuggingFace ve yerel yapay zeka modellerini tek tıkla diskinize indirin. İndirme arka planda çalışır, arayüz donmaz.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <!-- Search Input -->
          <div class="relative min-w-[200px]">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Model ara..."
              class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl bg-neutral-900 border border-neutral-700 text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
            />
            <svg class="w-3.5 h-3.5 text-neutral-500 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Refresh Button -->
          <button
            @click="fetchDownloads(true)"
            :disabled="isRefreshing"
            class="text-xs px-3.5 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 flex items-center gap-1.5 transition-colors disabled:opacity-50"
          >
            <svg :class="['w-3.5 h-3.5', { 'animate-spin': isRefreshing }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ isRefreshing ? 'Güncelleniyor...' : 'Durumu Güncelle' }}</span>
          </button>
        </div>
      </div>

      <!-- Filter Controls -->
      <div class="flex flex-col gap-3 border-b border-neutral-800 pb-3">
        <!-- Top Filters Row: Hosting Mode (Local vs Cloud) & Status -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <!-- Hosting / Deployment Mode Filter (Local vs Cloud) -->
          <div class="flex items-center gap-1.5 p-1 rounded-xl bg-neutral-900 border border-neutral-800 text-xs overflow-x-auto">
            <button
              v-for="dm in deploymentFilters"
              :key="dm.id"
              @click="activeDeploymentFilter = dm.id"
              :class="[
                'px-3 py-1.5 rounded-lg font-medium transition-all flex items-center gap-1.5 text-xs whitespace-nowrap cursor-pointer',
                activeDeploymentFilter === dm.id
                  ? (dm.id === 'cloud' 
                      ? 'bg-purple-600 text-white font-semibold shadow-sm'
                      : dm.id === 'local'
                        ? 'bg-emerald-600 text-white font-semibold shadow-sm'
                        : 'bg-neutral-700 text-neutral-100 font-semibold shadow-sm')
                  : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/40'
              ]"
            >
              <span>{{ dm.icon }}</span>
              <span>{{ dm.name }}</span>
              <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-neutral-800 text-neutral-300 ml-0.5">
                {{ getDeploymentCount(dm.id) }}
              </span>
            </button>
            <div class="w-px h-4 bg-neutral-800 mx-0.5"></div>
            <button
              type="button"
              @click="openCloudModal()"
              class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/30 flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap"
              title="Bulut API anahtarlarını yapılandır (OpenAI, ElevenLabs, Google Cloud, Groq, Freya)"
            >
              <span>🔑</span>
              <span>Bulut Anahtarları</span>
            </button>
          </div>

          <!-- Status Filter -->
          <div class="flex items-center gap-1 text-xs overflow-x-auto">
            <button
              v-for="st in statusFilters"
              :key="st.id"
              @click="activeStatusFilter = st.id"
              :class="[
                'px-2.5 py-1.5 rounded-md text-[11px] font-medium transition-colors whitespace-nowrap',
                activeStatusFilter === st.id
                  ? 'bg-neutral-700 text-neutral-100 font-semibold'
                  : 'text-neutral-500 hover:text-neutral-300'
              ]"
            >
              {{ st.name }}
            </button>
          </div>
        </div>

        <!-- Bottom Category Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto text-xs pt-0.5">
          <span class="text-neutral-500 text-[11px] font-medium pr-1">Kategori:</span>
          <button
            v-for="cat in categories"
            :key="cat.id"
            @click="activeCategory = cat.id"
            :class="[
              'px-2.5 py-1 rounded-lg font-medium transition-colors whitespace-nowrap text-xs',
              activeCategory === cat.id
                ? 'bg-accent text-bg font-semibold'
                : 'text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/60'
            ]"
          >
            {{ cat.name }}
          </button>
        </div>
      </div>

      <!-- Models Grid -->
      <div v-if="filteredModels.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="m in filteredModels"
          :key="m.id"
          :class="[
            'p-5 rounded-2xl bg-surface border flex flex-col justify-between space-y-4 transition-all duration-200',
            isDownloading(m)
              ? 'border-sky-500/50 shadow-md shadow-sky-500/5 ring-1 ring-sky-500/30'
              : isPending(m)
                ? 'border-amber-500/40 ring-1 ring-amber-500/20'
                : 'border-neutral-800 hover:border-neutral-700'
          ]"
        >
          <div>
            <!-- Top Header & Status Badge -->
            <div class="flex items-start justify-between gap-2">
              <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <h3 class="font-bold text-sm text-neutral-100">{{ m.name }}</h3>
                  <span
                    :class="[
                      'px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider',
                      getTypeBadgeClass(m.type)
                    ]"
                  >
                    {{ m.type }}
                  </span>
                  <span
                    v-if="m.is_cloud"
                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-500/20 text-purple-300 border border-purple-500/40 flex items-center gap-1"
                  >
                    <span>☁️ {{ getProviderName(m) }}</span>
                  </span>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 flex items-center gap-1"
                  >
                    <span>💾 Lokal Model</span>
                  </span>
                </div>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                  <span class="text-xs font-mono text-neutral-500 block">{{ m.id }}</span>
                  <!-- License Badge -->
                  <span
                    v-if="m.license"
                    class="px-2 py-0.5 rounded text-[10px] font-medium bg-neutral-800/90 text-neutral-300 border border-neutral-700/60 inline-flex items-center gap-1"
                    :title="'Lisans: ' + m.license"
                  >
                    <svg class="w-3 h-3 text-neutral-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>{{ m.license }}</span>
                  </span>
                  <!-- Official Website / Documentation Link -->
                  <a
                    v-if="m.homepage_url"
                    :href="m.homepage_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="px-2 py-0.5 rounded text-[10px] font-medium bg-sky-500/10 hover:bg-sky-500/20 text-sky-300 hover:text-sky-200 border border-sky-500/30 inline-flex items-center gap-1 transition-colors"
                    title="Resmi web sayfası ve model dokümanı"
                  >
                    <span>Resmi Sayfa</span>
                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                  </a>
                </div>
              </div>

              <!-- Status Badge -->
              <span
                v-if="isInstalled(m)"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ m.is_cloud ? 'Bulut API Aktif' : 'Yüklü' }}</span>
              </span>

              <span
                v-else-if="isDownloading(m)"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-sky-500/15 text-sky-300 border border-sky-500/30 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 animate-spin text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>İndiriliyor: %{{ Math.round(getDownload(m.id)?.progress || 0) }}</span>
              </span>

              <span
                v-else-if="isPending(m)"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Kuyrukta</span>
              </span>

              <span
                v-else-if="m.is_cloud"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                <span>API Anahtarı Gerekli</span>
              </span>

              <span
                v-else-if="isFailed(m)"
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-500/15 text-rose-400 border border-rose-500/30 flex items-center gap-1.5 flex-shrink-0"
              >
                <svg class="w-3 h-3 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Hatalı</span>
              </span>

              <span
                v-else
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-neutral-800 text-neutral-400 border border-neutral-700/60 flex-shrink-0"
              >
                İndirilmedi
              </span>
            </div>

            <!-- Description -->
            <p class="text-xs text-neutral-400 mt-2.5 line-clamp-2 leading-relaxed">
              {{ m.description }}
            </p>

            <!-- Metadata Info -->
            <div class="mt-3.5 space-y-1.5 text-xs text-neutral-400 bg-neutral-900/60 p-3 rounded-xl border border-neutral-800/80">
              <div class="flex items-center justify-between">
                <span class="text-neutral-500">Tahmini Boyut:</span>
                <span v-if="m.is_cloud" class="font-mono text-purple-300 font-medium">Bulut API (0 MB Yerel Disk)</span>
                <span v-else class="font-mono text-neutral-200">~{{ m.size_estimate_mb >= 1000 ? (m.size_estimate_mb / 1024).toFixed(1) + ' GB' : m.size_estimate_mb + ' MB' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-neutral-500">Desteklenen Diller:</span>
                <span class="text-neutral-300 truncate max-w-[170px]" :title="m.languages?.join(', ')">
                  {{ m.languages?.join(', ') || '-' }}
                </span>
              </div>
              <div v-if="m.ram_required_gb" class="flex items-center justify-between">
                <span class="text-neutral-500">Önerilen RAM:</span>
                <span class="font-mono text-neutral-200">{{ m.ram_required_gb }} GB+</span>
              </div>
              <div v-if="m.license" class="flex items-center justify-between">
                <span class="text-neutral-500">Lisans:</span>
                <span class="text-neutral-300 font-mono text-[11px] truncate max-w-[210px]" :title="m.license">{{ m.license }}</span>
              </div>
              <div v-if="m.homepage_url" class="flex items-center justify-between pt-1 border-t border-neutral-800/60">
                <span class="text-neutral-500">Resmi Sayfa / Repo:</span>
                <a
                  :href="m.homepage_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-accent hover:text-accent/80 hover:underline flex items-center gap-1 font-medium text-[11px] transition-colors"
                  title="Resmi sayfayı yeni sekmede aç"
                >
                  <span>Ziyaret Et</span>
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </div>
            </div>

            <!-- Cloud Model Ready Notice -->
            <div v-if="m.is_cloud && isInstalled(m)" class="mt-4 p-3 rounded-xl bg-emerald-950/20 border border-emerald-500/20 text-xs text-emerald-200">
              <div class="flex items-center justify-between font-semibold text-emerald-300">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>Bulut API Bağlantısı Aktif ({{ getProviderName(m) }})</span>
                </div>
                <button
                  type="button"
                  @click="openCloudModal(m.cloud_provider)"
                  class="text-[11px] text-emerald-400 hover:text-emerald-200 underline font-normal cursor-pointer"
                >
                  Ayarlar
                </button>
              </div>
              <p class="text-[11px] text-emerald-300/80 mt-1 leading-relaxed">
                {{ getProviderName(m) }} API anahtarı yapılandırıldı. Doğrudan bulut servisi üzerinden çalışır (~0 MB yerel disk).
              </p>
            </div>

            <!-- Cloud Model Unconfigured Notice -->
            <div v-else-if="m.is_cloud" class="mt-4 p-3 rounded-xl bg-purple-950/20 border border-purple-500/30 text-xs text-purple-200">
              <div class="flex items-center justify-between font-semibold text-purple-200">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-purple-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>Bulut API Modeli</span>
                </div>
                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-purple-900/60 text-purple-300 font-semibold">{{ getProviderName(m) }}</span>
              </div>
              <p class="text-[11px] text-purple-300/80 mt-1 leading-relaxed">
                Yerel disk indirmesi gerekmez (~0 MB). {{ getProviderName(m) }} API anahtarınızı tanımlayarak hemen kullanmaya başlayabilirsiniz.
              </p>
            </div>

            <!-- Live Progress Bar Section (When Downloading) -->
            <div v-else-if="isDownloading(m)" class="mt-4 p-3 rounded-xl bg-sky-950/20 border border-sky-500/20 space-y-2">
              <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-sky-300 flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-ping"></span>
                  İndiriliyor...
                </span>
                <span class="font-mono font-bold text-sky-300">
                  %{{ Number(getDownload(m.id)?.progress || 0).toFixed(1) }}
                </span>
              </div>

              <!-- Animated Bar -->
              <div class="w-full h-3 bg-neutral-900 rounded-full overflow-hidden border border-sky-500/40 p-0.5 shadow-inner">
                <div
                  class="h-full bg-gradient-to-r from-accent via-sky-400 to-emerald-400 rounded-full transition-all duration-300 ease-out shadow-sm shadow-accent/50"
                  :style="{ width: Math.min(100, Math.max(6, Number(getDownload(m.id)?.progress || 0))) + '%' }"
                ></div>
              </div>

              <!-- Byte Counters -->
              <div class="flex items-center justify-between text-[11px] text-neutral-400 font-mono pt-0.5">
                <span>{{ formatBytes(getDownload(m.id)?.downloaded_bytes) }}</span>
                <span>/ {{ formatBytes(getDownload(m.id)?.total_bytes || (m.size_estimate_mb * 1024 * 1024)) }}</span>
              </div>
            </div>

            <!-- Pending In Queue Alert -->
            <div v-else-if="isPending(m)" class="mt-4 p-3 rounded-xl bg-amber-950/20 border border-amber-500/20 flex items-center gap-2.5 text-xs text-amber-300">
              <svg class="w-4 h-4 text-amber-400 flex-shrink-0 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>İndirme kuyruğa alındı. Worker sırayla indirecek...</span>
            </div>

            <!-- Error Message Alert -->
            <div v-else-if="isFailed(m)" class="mt-4 p-3 rounded-xl bg-rose-950/20 border border-rose-500/20 text-xs text-rose-300">
              <div class="flex items-center gap-2 font-semibold text-rose-200">
                <svg class="w-4 h-4 text-rose-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>İndirme Tamamlanamadı</span>
              </div>
              <p class="text-[11px] text-rose-300/80 mt-1 line-clamp-2">
                {{ getDownload(m.id)?.error_message || 'Ağ bağlantısı veya dosya yazma hatası oluştu.' }}
              </p>
            </div>
          </div>

          <!-- Bottom Action Buttons -->
          <div class="pt-3 border-t border-neutral-800 flex items-center justify-between">
            <!-- Cloud Model Ready State -->
            <button
              v-if="m.is_cloud && isInstalled(m)"
              type="button"
              @click="openCloudModal(m.cloud_provider)"
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center gap-2 transition-colors cursor-pointer"
            >
              <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
              <span>Bulut API Kullanıma Hazır (Yapılandır)</span>
            </button>

            <!-- Cloud Model Configure API Key Action -->
            <button
              v-else-if="m.is_cloud"
              type="button"
              @click="openCloudModal(m.cloud_provider)"
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-gradient-to-r from-purple-600 via-indigo-600 to-accent text-white hover:opacity-95 transition-all flex items-center justify-center gap-2 shadow-md shadow-purple-500/20 cursor-pointer"
            >
              <svg class="w-4 h-4 text-purple-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
              </svg>
              <span>{{ getProviderName(m) }} API Anahtarını Tanımla</span>
            </button>

            <!-- Regular Local Model Installed / Ready State -->
            <div
              v-else-if="isInstalled(m)"
              class="w-full py-2 text-center text-xs text-emerald-400 font-semibold bg-emerald-500/10 rounded-xl border border-emerald-500/20 flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
              <span>Kullanıma Hazır</span>
            </div>

            <!-- Downloading State (Disabled Button) -->
            <button
              v-else-if="isDownloading(m)"
              disabled
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-sky-500/20 border border-sky-500/30 text-sky-200 cursor-not-allowed flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 animate-spin text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>İndiriliyor... (%{{ Math.round(getDownload(m.id)?.progress || 0) }})</span>
            </button>

            <!-- Pending State (Disabled Button) -->
            <button
              v-else-if="isPending(m)"
              disabled
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-amber-500/20 border border-amber-500/30 text-amber-200 cursor-not-allowed flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Kuyrukta Bekliyor...</span>
            </button>

            <!-- Failed State (Retry Button) -->
            <button
              v-else-if="isFailed(m)"
              @click="download(m.id)"
              :disabled="submittingModelId === m.id"
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 border border-rose-500/40 transition-colors flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 text-rose-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>{{ submittingModelId === m.id ? 'Başlatılıyor...' : 'Tekrar İndirmeyi Dene' }}</span>
            </button>

            <!-- Not Downloaded State (Active Download Button) -->
            <button
              v-else
              @click="download(m.id)"
              :disabled="submittingModelId === m.id"
              class="w-full py-2.5 rounded-xl text-xs font-semibold bg-accent text-bg hover:opacity-90 transition-opacity flex items-center justify-center gap-2 shadow-sm disabled:opacity-50"
            >
              <svg v-if="submittingModelId === m.id" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              <span>{{ submittingModelId === m.id ? 'İndirme Başlatılıyor...' : 'Modeli İndir' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="p-12 text-center rounded-2xl bg-surface border border-neutral-800">
        <svg class="w-10 h-10 text-neutral-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="text-sm font-semibold text-neutral-300">Model Bulunamadı</div>
        <p class="text-xs text-neutral-500 mt-1">Arama veya filtre kriterlerinize uygun model bulunamadı.</p>
        <button
          @click="activeCategory = 'all'; activeStatusFilter = 'all'; activeDeploymentFilter = 'all'; searchQuery = ''"
          class="mt-4 text-xs px-3 py-1.5 rounded-lg bg-neutral-800 text-neutral-200 hover:bg-neutral-700"
        >
          Filtreleri Temizle
        </button>
      </div>

      <!-- Universal Cloud Provider API Modal -->
      <div
        v-if="showCloudModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm transition-opacity"
        @click.self="closeCloudModal"
      >
        <div class="w-full max-w-xl bg-surface border border-neutral-800 rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          <!-- Modal Header -->
          <div class="p-5 border-b border-neutral-800 flex items-center justify-between bg-neutral-900/60">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-purple-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z" />
                </svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-neutral-100 flex items-center gap-2">
                  Bulut Yapay Zeka Sağlayıcıları
                  <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">TTS, STT & LLM</span>
                </h3>
                <p class="text-xs text-neutral-400">Gemini, Claude, OpenAI, Groq, DeepSeek, OpenRouter, ElevenLabs ve Freya API anahtarları</p>
              </div>
            </div>
            <button
              @click="closeCloudModal"
              class="text-neutral-500 hover:text-neutral-300 p-1.5 rounded-lg hover:bg-neutral-800 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Provider Tabs Selection -->
          <div class="px-5 pt-3 border-b border-neutral-800 bg-neutral-900/30 flex items-center gap-1.5 overflow-x-auto">
            <button
              v-for="p in cloudProviderTabs"
              :key="p.id"
              @click="selectCloudProvider(p.id)"
              :class="[
                'px-3 py-2 rounded-t-xl text-xs font-semibold flex items-center gap-2 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                selectedProviderId === p.id
                  ? 'border-purple-500 text-purple-200 bg-purple-500/10'
                  : 'border-transparent text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800/40'
              ]"
            >
              <span>{{ p.icon }}</span>
              <span>{{ p.name }}</span>
              <span
                v-if="cloudProviders[p.id]?.is_configured"
                class="w-1.5 h-1.5 rounded-full bg-emerald-400"
                title="Yapılandırıldı"
              ></span>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 space-y-5">
            <!-- Active Provider Info Box -->
            <div class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-2 text-xs text-neutral-300">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 font-bold text-neutral-100">
                  <span class="text-base">{{ activeProviderConfig.icon }}</span>
                  <span>{{ activeProviderConfig.name }}</span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-neutral-800 text-neutral-300 font-mono">
                    {{ activeProviderConfig.badge }}
                  </span>
                </div>
                <span
                  v-if="cloudProviders[selectedProviderId]?.is_configured"
                  class="text-[10px] font-semibold px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 flex items-center gap-1"
                >
                  <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>Aktif</span>
                </span>
              </div>
              <p class="text-neutral-400 leading-relaxed text-[11px]">
                {{ cloudProviders[selectedProviderId]?.description || activeProviderConfig.description }}
              </p>
              <div class="text-[11px] text-neutral-400 pt-1 flex items-center gap-1">
                <span class="text-neutral-500">Kapsanan Modeller:</span>
                <span class="font-mono text-neutral-300">{{ cloudProviders[selectedProviderId]?.models?.join(', ') }}</span>
              </div>
            </div>

            <!-- API Key Input Field -->
            <div class="space-y-2">
              <div class="flex items-center justify-between text-xs">
                <label class="font-semibold text-neutral-200">
                  {{ activeProviderConfig.name }} API Anahtarı
                </label>
                <a
                  :href="cloudProviders[selectedProviderId]?.docs_url || activeProviderConfig.docs_url"
                  target="_blank"
                  class="text-[11px] text-purple-400 hover:text-purple-300 underline flex items-center gap-1"
                >
                  <span>Anahtar Oluştur</span>
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </div>
              <div class="relative">
                <input
                  v-model="cloudKeyInput"
                  :type="showKeyText ? 'text' : 'password'"
                  :placeholder="activeProviderConfig.placeholder"
                  class="w-full px-3.5 py-2.5 text-xs rounded-xl bg-neutral-900 border border-neutral-700 text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 font-mono pr-20"
                />
                <div class="absolute right-2 top-2 flex items-center gap-1">
                  <button
                    type="button"
                    @click="showKeyText = !showKeyText"
                    class="p-1 rounded text-neutral-400 hover:text-neutral-200 text-xs"
                    title="Göster / Gizle"
                  >
                    <svg v-if="showKeyText" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <button
                    v-if="cloudKeyInput"
                    type="button"
                    @click="cloudKeyInput = ''"
                    class="p-1 rounded text-neutral-500 hover:text-rose-400 text-xs"
                    title="Temizle"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Test Connection Button & Result -->
            <div class="space-y-2 pt-1">
              <button
                type="button"
                @click="testCloudConnection"
                :disabled="isTestingCloud || !cloudKeyInput"
                class="w-full py-2 px-3 text-xs rounded-xl border border-neutral-700 hover:border-neutral-600 bg-neutral-900/60 hover:bg-neutral-800 text-neutral-200 transition-colors flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
              >
                <svg v-if="isTestingCloud" class="w-3.5 h-3.5 animate-spin text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <svg v-else class="w-3.5 h-3.5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>{{ isTestingCloud ? 'Bağlantı Test Ediliyor...' : 'Canlı API Bağlantısını Test Et' }}</span>
              </button>

              <!-- Test Result Alert -->
              <div
                v-if="cloudTestResult"
                :class="[
                  'p-3 rounded-xl text-xs border flex items-start gap-2.5 transition-all',
                  cloudTestResult.success
                    ? 'bg-emerald-950/20 border-emerald-500/30 text-emerald-300'
                    : 'bg-rose-950/20 border-rose-500/30 text-rose-300'
                ]"
              >
                <svg v-if="cloudTestResult.success" class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="w-4 h-4 text-rose-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <div class="text-[11px] leading-relaxed">
                  {{ cloudTestResult.message }}
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="p-4 border-t border-neutral-800 bg-neutral-900/60 flex items-center justify-between gap-3">
            <button
              type="button"
              @click="closeCloudModal"
              class="px-4 py-2 text-xs rounded-xl border border-neutral-700 hover:bg-neutral-800 text-neutral-300 transition-colors cursor-pointer"
            >
              Kapat
            </button>

            <button
              type="button"
              @click="saveCloudKey"
              :disabled="isSavingCloud"
              class="px-5 py-2 text-xs font-semibold rounded-xl bg-gradient-to-r from-purple-600 via-indigo-600 to-accent text-white hover:opacity-95 transition-all flex items-center gap-2 shadow-md shadow-purple-500/20 disabled:opacity-50 cursor-pointer"
            >
              <svg v-if="isSavingCloud" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>{{ isSavingCloud ? 'Kaydediliyor...' : 'Kaydet ve Modelleri Aktif Et' }}</span>
            </button>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

const props = defineProps({
  availableModels: {
    type: Array,
    default: () => []
  },
  downloads: {
    type: Array,
    default: () => []
  },
})

// Reactive local state
const modelsList = ref([...props.availableModels])
const downloadsList = ref([...(props.downloads || [])])
const isRefreshing = ref(false)
const submittingModelId = ref(null)

// Filtering & Search
const activeCategory = ref('all')
const activeStatusFilter = ref('all')
const activeDeploymentFilter = ref('all')
const searchQuery = ref('')

const deploymentFilters = [
  { id: 'all', name: 'Tüm Modeller', icon: '🌐' },
  { id: 'local', name: 'Lokal (İndirilebilir)', icon: '💾' },
  { id: 'cloud', name: 'Bulut API', icon: '☁️' },
]

const getDeploymentCount = (filterId) => {
  if (filterId === 'local') return modelsList.value.filter(m => !m.is_cloud).length
  if (filterId === 'cloud') return modelsList.value.filter(m => !!m.is_cloud).length
  return modelsList.value.length
}

const categories = [
  { id: 'all', name: 'Tüm Kategoriler' },
  { id: 'tts', name: 'TTS (Metin Okuma)' },
  { id: 'stt', name: 'STT (Sesten Metne)' },
  { id: 'sfx', name: 'SFX (Ses Efektleri)' },
  { id: 'music', name: 'Müzik Üretimi' },
  { id: 'llm', name: 'Dil Modelleri (LLM)' },
]

const statusFilters = [
  { id: 'all', name: 'Tüm Durumlar' },
  { id: 'installed', name: 'Yüklü / Hazır' },
  { id: 'in_progress', name: 'İndirilenler' },
  { id: 'not_installed', name: 'İndirilmemiş' },
]

// Download record lookup
const getDownload = (modelId) => {
  return downloadsList.value.find(d => d.model_id === modelId)
}

const isInstalled = (m) => {
  if (m.is_downloaded) return true
  const dl = getDownload(m.id)
  return dl && dl.status === 'completed'
}

const isDownloading = (m) => {
  if (isInstalled(m)) return false
  const dl = getDownload(m.id)
  return dl && dl.status === 'downloading'
}

const isPending = (m) => {
  if (isInstalled(m)) return false
  const dl = getDownload(m.id)
  return dl && dl.status === 'pending'
}

const isFailed = (m) => {
  if (isInstalled(m)) return false
  const dl = getDownload(m.id)
  return dl && dl.status === 'failed'
}

const activeDownloads = computed(() => {
  return downloadsList.value.filter(d => {
    const targetModel = modelsList.value.find(m => m.id === d.model_id)
    if (targetModel && targetModel.is_downloaded) return false
    return d.status === 'downloading' || d.status === 'pending'
  })
})

const primaryActiveDownload = computed(() => {
  return activeDownloads.value.find(d => d.status === 'downloading') || activeDownloads.value[0] || null
})

const hasActiveDownloads = computed(() => {
  return activeDownloads.value.length > 0
})

const getModelName = (modelId) => {
  const m = modelsList.value.find(item => item.id === modelId)
  return m ? m.name : modelId
}

const getTypeBadgeClass = (type) => {
  switch (type) {
    case 'tts':
      return 'bg-indigo-500/15 text-indigo-400 border border-indigo-500/30'
    case 'stt':
      return 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
    case 'sfx':
      return 'bg-pink-500/15 text-pink-400 border border-pink-500/30'
    case 'music':
      return 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
    case 'llm':
      return 'bg-purple-500/15 text-purple-400 border border-purple-500/30'
    default:
      return 'bg-neutral-800 text-neutral-400'
  }
}

const formatBytes = (bytes, decimals = 1) => {
  if (!bytes || bytes <= 0) return '0 B'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

// Filtered models list
const filteredModels = computed(() => {
  return modelsList.value.filter(m => {
    // Deployment mode match (Local vs Cloud)
    if (activeDeploymentFilter.value === 'local' && m.is_cloud) {
      return false
    }
    if (activeDeploymentFilter.value === 'cloud' && !m.is_cloud) {
      return false
    }

    // Category match
    if (activeCategory.value !== 'all' && m.type !== activeCategory.value) {
      return false
    }

    // Status filter match
    if (activeStatusFilter.value === 'installed' && !isInstalled(m)) {
      return false
    }
    if (activeStatusFilter.value === 'in_progress' && !isDownloading(m) && !isPending(m)) {
      return false
    }
    if (activeStatusFilter.value === 'not_installed' && (isInstalled(m) || isDownloading(m) || isPending(m))) {
      return false
    }

    // Search query match
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      const matchName = m.name?.toLowerCase().includes(q)
      const matchId = m.id?.toLowerCase().includes(q)
      const matchDesc = m.description?.toLowerCase().includes(q)
      const matchLang = m.languages?.some(l => l.toLowerCase().includes(q))
      const matchLicense = m.license?.toLowerCase().includes(q)
      const matchProvider = m.cloud_provider?.toLowerCase().includes(q)
      if (!matchName && !matchId && !matchDesc && !matchLang && !matchLicense && !matchProvider) return false
    }

    return true
  })
})

// Fetch live downloads & models from API
const fetchDownloads = async (isManual = false) => {
  if (isManual) isRefreshing.value = true
  try {
    const res = await fetch('/api/models/downloads', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      }
    })
    if (res.ok) {
      const data = await res.json()
      if (Array.isArray(data.availableModels)) {
        modelsList.value = data.availableModels
      }
      if (Array.isArray(data.downloads)) {
        downloadsList.value = data.downloads
      }
    }
  } catch (err) {
    console.error('Model indirme durumu alınırken hata:', err)
  } finally {
    if (isManual) isRefreshing.value = false
  }
}

const getProviderName = (m) => {
  const p = m.cloud_provider || (m.id.startsWith('freya') ? 'freya' : '')
  switch (p.toLowerCase()) {
    case 'gemini': return 'Google Gemini'
    case 'anthropic':
    case 'claude': return 'Anthropic Claude'
    case 'openai': return 'OpenAI'
    case 'groq': return 'Groq (LPU)'
    case 'deepseek': return 'DeepSeek'
    case 'openrouter': return 'OpenRouter'
    case 'elevenlabs': return 'ElevenLabs'
    case 'google': return 'Google Cloud'
    case 'freya': return 'Freya Voice'
    case 'patientdesk': return 'Patientdesk.ai'
    default: return p ? p.toUpperCase() : 'Bulut'
  }
}

// Cloud Provider Tabs & Definitions
const cloudProviderTabs = [
  { id: 'gemini', name: 'Google Gemini', icon: '✨', badge: 'LLM & Flash', placeholder: 'AIzaSy...', docs_url: 'https://aistudio.google.com/app/apikey', description: 'Google Flash 2.0 & Pro yüksek hızlı, 1M+ token bağlam hafızalı yeni nesil dil modelleri.' },
  { id: 'anthropic', name: 'Claude', icon: '🎭', badge: 'Edebi LLM', placeholder: 'sk-ant-...', docs_url: 'https://console.anthropic.com/settings/keys', description: 'Claude 3.5 Sonnet ve Haiku üstün edebi diyalog, masal ve dramatik senaryo motorları.' },
  { id: 'openai', name: 'OpenAI', icon: '🧠', badge: 'LLM, TTS & STT', placeholder: 'sk-proj-... veya sk-...', docs_url: 'https://platform.openai.com/api-keys', description: 'GPT-4o dil modelleri, tts-1 / tts-1-hd ses motorları ve Whisper bulut transkripsiyonu.' },
  { id: 'groq', name: 'Groq (LPU)', icon: '⚡', badge: 'Ultra Hızlı LPU', placeholder: 'gsk_...', docs_url: 'https://console.groq.com/keys', description: 'Groq LPU çiplerinde 300+ token/sn hızında çalışan Llama 3.3 70B ve Whisper Large v3.' },
  { id: 'deepseek', name: 'DeepSeek', icon: '🐋', badge: 'MoE & Reasoner', placeholder: 'sk-...', docs_url: 'https://platform.deepseek.com/api_keys', description: 'DeepSeek Chat V3 ve derin düşünce zinciri (CoT) kuran Reasoner R1 modelleri.' },
  { id: 'openrouter', name: 'OpenRouter', icon: '🌐', badge: 'Çoklu Model', placeholder: 'sk-or-...', docs_url: 'https://openrouter.ai/keys', description: 'Tek API anahtarıyla yüzlerce yapay zeka modeline küresel yönlendirme ve havuz erişimi.' },
  { id: 'elevenlabs', name: 'ElevenLabs', icon: '🟠', badge: 'Duygusal TTS', placeholder: 'xi-api-key-...', docs_url: 'https://elevenlabs.io/app/settings/api-keys', description: '29+ dilde zengin duygu ve tonlama üreten endüstri lideri insansı ses modelleri.' },
  { id: 'freya', name: 'Freya Voice', icon: '🚀', badge: 'AudioRealism #1', placeholder: 'freya_live_...', docs_url: 'https://freyavoice.ai', description: 'AudioRealismBench #1 dereceli hiper-gerçekçi Adam & Eve insansı ses modelleri.' },
  { id: 'patientdesk', name: 'Patientdesk.ai', icon: '🇹🇷', badge: 'Alania & Duyu', placeholder: 'pd_...', docs_url: 'https://speech.patientdesk.ai', description: 'Patientdesk.ai Türkçe ses teknolojileri: Alania (Düşük gecikmeli akış TTS) ve Duyu (FLEURS %4.71 WER STT). Lansmana özel ilk ay ücretsizdir.' },
  { id: 'google', name: 'Google Cloud', icon: '🔵', badge: 'TTS & STT', placeholder: 'AIzaSy...', docs_url: 'https://console.cloud.google.com/apis/credentials', description: 'Google Cloud Text-to-Speech (Journey, Neural2) ve Speech-to-Text (Chirp v2) ses servisleri.' },
]

// Universal Cloud API Modal State & Methods
const showCloudModal = ref(false)
const cloudProviders = ref({})
const selectedProviderId = ref('openai')
const cloudKeyInput = ref('')
const showKeyText = ref(false)
const isTestingCloud = ref(false)
const isSavingCloud = ref(false)
const cloudTestResult = ref(null)

const activeProviderConfig = computed(() => {
  return cloudProviderTabs.find(p => p.id === selectedProviderId.value) || cloudProviderTabs[0]
})

const fetchCloudKeys = async () => {
  try {
    const res = await fetch('/api/models/cloud-keys', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    if (res.ok) {
      cloudProviders.value = await res.json()
    }
  } catch (err) {
    console.error('Bulut anahtarları alınırken hata:', err)
  }
}

const selectCloudProvider = (providerId) => {
  selectedProviderId.value = providerId
  cloudTestResult.value = null
  showKeyText.value = false
  const p = cloudProviders.value[providerId]
  cloudKeyInput.value = p?.key || ''
}

const openCloudModal = async (providerId = null) => {
  cloudTestResult.value = null
  showKeyText.value = false
  await fetchCloudKeys()

  if (providerId && cloudProviderTabs.some(p => p.id === providerId)) {
    selectedProviderId.value = providerId
  } else if (!selectedProviderId.value) {
    selectedProviderId.value = 'openai'
  }

  const p = cloudProviders.value[selectedProviderId.value]
  cloudKeyInput.value = p?.key || ''
  showCloudModal.value = true
}

const closeCloudModal = () => {
  showCloudModal.value = false
  cloudTestResult.value = null
}

const testCloudConnection = async () => {
  if (!cloudKeyInput.value) return
  isTestingCloud.value = true
  cloudTestResult.value = null

  try {
    const res = await fetch('/api/models/test-cloud-connection', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        provider: selectedProviderId.value,
        key: cloudKeyInput.value
      })
    })
    const data = await res.json()
    cloudTestResult.value = {
      success: !!data.success,
      message: data.message || (data.success ? 'Bağlantı başarılı!' : 'Bağlantı kurulamadı.')
    }
  } catch (err) {
    cloudTestResult.value = {
      success: false,
      message: 'Bağlantı testi hatası: ' + err.message
    }
  } finally {
    isTestingCloud.value = false
  }
}

const saveCloudKey = async () => {
  isSavingCloud.value = true
  try {
    const res = await fetch('/api/models/cloud-keys', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        provider: selectedProviderId.value,
        key: cloudKeyInput.value
      })
    })
    const data = await res.json()
    if (data.success) {
      await fetchCloudKeys()
      await fetchDownloads(true)
      cloudTestResult.value = {
        success: true,
        message: data.message || 'API anahtarı başarıyla kaydedildi!'
      }
      setTimeout(() => {
        closeCloudModal()
      }, 700)
    } else {
      cloudTestResult.value = {
        success: false,
        message: data.message || 'Kayıt sırasında bir hata oluştu.'
      }
    }
  } catch (err) {
    cloudTestResult.value = {
      success: false,
      message: 'Kayıt hatası: ' + err.message
    }
  } finally {
    isSavingCloud.value = false
  }
}

// Backwards compatibility alias for Freya
const showFreyaModal = showCloudModal
const openFreyaModal = () => openCloudModal('freya')
const closeFreyaModal = closeCloudModal
const testFreyaConnection = testCloudConnection
const saveFreyaKey = saveCloudKey

// Download action with instant optimistic feedback
const download = (modelId) => {
  const model = modelsList.value.find(m => m.id === modelId)

  // Cloud models (Freya Voice) do not have file weights to download
  if (model?.is_cloud) {
    openFreyaModal()
    return
  }

  const modelName = model ? model.name : modelId

  if (!confirm(`${modelName} modelini yerel diskinize indirmek istiyor musunuz?`)) {
    return
  }

  submittingModelId.value = modelId

  // Optimistic update
  const existingIndex = downloadsList.value.findIndex(d => d.model_id === modelId)
  const initialRecord = {
    id: Date.now(),
    model_id: modelId,
    status: 'pending',
    progress: 1,
    downloaded_bytes: 0,
    total_bytes: (model?.size_estimate_mb || 1000) * 1024 * 1024,
    error_message: null
  }

  if (existingIndex >= 0) {
    downloadsList.value[existingIndex] = {
      ...downloadsList.value[existingIndex],
      ...initialRecord
    }
  } else {
    downloadsList.value.unshift(initialRecord)
  }

  window.dispatchEvent(new CustomEvent('model-download-started'))

  router.post(`/models/download/${modelId}`, {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      submittingModelId.value = null
      window.dispatchEvent(new CustomEvent('model-download-started'))
      fetchDownloads()
    },
    onError: () => {
      submittingModelId.value = null
      fetchDownloads()
    },
    onFinish: () => {
      submittingModelId.value = null
    }
  })
}

// Live Adaptive Polling
let pollTimer = null

const runAdaptivePoll = async () => {
  await fetchDownloads()
  // If downloads are active, poll every 1.5s; otherwise 5s
  const nextInterval = hasActiveDownloads.value ? 1500 : 5000
  pollTimer = setTimeout(runAdaptivePoll, nextInterval)
}

onMounted(() => {
  pollTimer = setTimeout(runAdaptivePoll, 1000)
})

onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
})
</script>
