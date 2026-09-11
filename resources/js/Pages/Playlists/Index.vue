<template>
  <AppLayout title="İş Listeleri (Playlists)">
    <div class="space-y-6">
      <!-- Top Alert / Notification Banner if any -->
      <div v-if="alertMessage"
           :class="['p-4 rounded-2xl border text-xs flex items-center justify-between transition-all',
                    alertType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' :
                    alertType === 'error' ? 'bg-red-500/10 border-red-500/30 text-red-300' :
                    'bg-cyan-500/10 border-cyan-500/30 text-cyan-300']">
        <div class="flex items-center gap-2.5">
          <svg v-if="alertType === 'success'" class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
          </svg>
          <svg v-else-if="alertType === 'error'" class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <svg v-else class="w-5 h-5 text-cyan-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="font-medium">{{ alertMessage }}</span>
        </div>
        <button @click="alertMessage = null" class="opacity-70 hover:opacity-100 p-1 text-sm">✕</button>
      </div>

      <!-- Main Layout: 2 Columns (Sidebar + Content) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Column: Playlist List & Quick Create (4 cols) -->
        <div class="lg:col-span-4 space-y-4">
          <!-- Create Playlist Card -->
          <div class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold text-neutral-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Yeni İş Listesi</span>
              </h2>
              <button
                @click="showCreateForm = !showCreateForm"
                class="text-xs text-accent hover:underline font-medium"
              >
                {{ showCreateForm ? 'Kapat' : '+ Liste Ekle' }}
              </button>
            </div>

            <!-- Expandable Form -->
            <form v-if="showCreateForm" @submit.prevent="createPlaylist" class="space-y-3 pt-2 border-t border-neutral-800">
              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Liste Başlığı</label>
                <input
                  v-model="createForm.title"
                  type="text"
                  required
                  placeholder="Örn: Bölüm 1 Diyalogları"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>

              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Açıklama (İsteğe Bağlı)</label>
                <input
                  v-model="createForm.description"
                  type="text"
                  placeholder="Kısa not..."
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>

              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-[11px] font-medium text-neutral-400 mb-1">Model</label>
                  <select
                    v-model="createForm.engine"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                  >
                    <optgroup v-if="downloadedModels.length > 0" label="Kurulu / Kullanıma Hazır Modeller">
                      <option v-for="m in downloadedModels" :key="m.id" :value="m.id">
                        ✓ {{ m.name }} (Hazır)
                      </option>
                    </optgroup>
                    <optgroup v-if="notDownloadedModels.length > 0" label="Kurulum Bekleyen Modeller">
                      <option v-for="m in notDownloadedModels" :key="m.id" :value="m.id">
                        ⚠ {{ m.name }} (İndirilmedi)
                      </option>
                    </optgroup>
                  </select>
                </div>
                <div>
                  <label class="block text-[11px] font-medium text-neutral-400 mb-1">Dil</label>
                  <select
                    v-model="createForm.language"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                  >
                    <option v-for="lang in VOICE_LANGUAGES" :key="lang.code" :value="lang.code">
                      {{ lang.flag }} {{ lang.name }} ({{ lang.code }})
                    </option>
                  </select>
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Referans Ses Profili</label>
                <select
                  v-model="createForm.profile_id"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                >
                  <option :value="null">Varsayılan Ses</option>
                  <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>

              <button
                type="submit"
                :disabled="createForm.processing || !createForm.title"
                class="w-full py-2 rounded-xl font-semibold text-xs bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 mt-2"
              >
                Oluştur
              </button>
            </form>
          </div>

          <!-- Playlists List -->
          <div class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-neutral-800">
              <span class="text-xs font-semibold text-neutral-300">Kayıtlı Listeler ({{ localPlaylists.length }})</span>
              <span class="text-[11px] text-neutral-500">Seçmek için tıklayın</span>
            </div>

            <div v-if="localPlaylists.length === 0" class="py-10 text-center text-xs text-neutral-500">
              Henüz iş listesi bulunmuyor.
            </div>

            <div class="space-y-2.5 max-h-[600px] overflow-y-auto pr-1">
              <div
                v-for="pl in localPlaylists"
                :key="pl.id"
                @click="selectPlaylist(pl)"
                :class="['p-3.5 rounded-xl border transition-all cursor-pointer text-left',
                         selectedPlaylist?.id === pl.id
                           ? 'bg-neutral-800/90 border-accent shadow-lg shadow-accent/10 ring-1 ring-accent/30'
                           : 'bg-neutral-900/60 border-neutral-800 hover:border-neutral-700 hover:bg-neutral-900']"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="font-semibold text-neutral-100 text-xs truncate flex-1">{{ pl.title }}</div>
                  <!-- Status badge -->
                  <span :class="getStatusBadgeClass(pl.status)">
                    {{ getStatusLabel(pl.status) }}
                  </span>
                </div>

                <div v-if="pl.description" class="text-[11px] text-neutral-400 mt-1 line-clamp-1">
                  {{ pl.description }}
                </div>

                <!-- Info row: Engine badge + Items count -->
                <div class="flex items-center justify-between text-[11px] text-neutral-400 mt-2.5 pt-2 border-t border-neutral-800/80">
                  <span class="px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-300 font-mono text-[10px]">
                    {{ getModelName(pl.engine) }}
                  </span>
                  <span>{{ pl.completed_items || 0 }} / {{ pl.total_items || 0 }} iş</span>
                </div>

                <!-- Progress bar -->
                <div class="w-full bg-neutral-950 h-1.5 rounded-full overflow-hidden mt-2">
                  <div
                    class="bg-accent h-full transition-all duration-300"
                    :style="{ width: `${pl.progress_percentage || 0}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Selected Playlist Workspace (8 cols) -->
        <div class="lg:col-span-8 space-y-6">
          <div v-if="!selectedPlaylist" class="p-16 rounded-2xl bg-surface border border-neutral-800 text-center space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-neutral-800/60 flex items-center justify-center mx-auto text-neutral-500">
              <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <div class="space-y-1">
              <h3 class="text-sm font-semibold text-neutral-200">Hiçbir İş Listesi Seçilmedi</h3>
              <p class="text-xs text-neutral-400 max-w-sm mx-auto">
                Sol taraftan bir iş listesi seçin veya yeni bir liste oluşturarak seslendirilecek metinleri ekleyin.
              </p>
            </div>
          </div>

          <!-- Active Playlist View -->
          <div v-else class="space-y-6">
            <!-- 1. Header & Progress Card -->
            <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                  <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold text-neutral-100">{{ selectedPlaylist.title }}</h2>
                    <span :class="getStatusBadgeClass(selectedPlaylist.status)">
                      {{ getStatusLabel(selectedPlaylist.status) }}
                    </span>
                  </div>
                  <p v-if="selectedPlaylist.description" class="text-xs text-neutral-400 mt-1">
                    {{ selectedPlaylist.description }}
                  </p>
                </div>

                <!-- Action Buttons: Run, Re-run, Download ZIP, Delete -->
                <div class="flex flex-wrap items-center gap-2">
                  <!-- Process/Run Button -->
                  <button
                    @click="processPlaylist(false)"
                    :disabled="isProcessing || (selectedPlaylist.items?.length || 0) === 0"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-accent text-bg hover:opacity-90 transition-all flex items-center gap-1.5 disabled:opacity-50 shadow-lg shadow-accent/20 cursor-pointer"
                    title="Hazır, bekleyen ve hatalı işleri seslendirme kuyruğuna ekler"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ isProcessing ? 'Kuyruğa Ekleniyor...' : 'Listeyi İşle' }}</span>
                  </button>

                  <!-- Re-run All (Restart with different model) Button -->
                  <button
                    @click="processPlaylist(true)"
                    :disabled="isProcessing || (selectedPlaylist.items?.length || 0) === 0"
                    class="px-3 py-2 rounded-xl text-xs font-semibold bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 transition-all flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
                    title="Tüm işleri sıfırlar ve seçili güncel model/ses ile baştan seslendirir"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Yeniden İşle</span>
                  </button>

                  <!-- Bulk Download ZIP Button -->
                  <a
                    :href="completedCount > 0 ? `/playlists/${selectedPlaylist.id}/download-zip` : '#'"
                    :class="['px-3 py-2 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1.5',
                             completedCount > 0
                                ? 'bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 border-emerald-500/30'
                                : 'bg-neutral-800 text-neutral-500 border-neutral-700 cursor-not-allowed opacity-60']"
                    :title="completedCount > 0 ? `${completedCount} adet tamamlanan sesi ZIP olarak indir` : 'İndirilecek tamamlanmış ses yok'"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Tümünü İndir (ZIP) <span v-if="completedCount > 0">({{ completedCount }})</span></span>
                  </a>

                  <!-- Delete Playlist Button -->
                  <button
                    @click="deletePlaylist(selectedPlaylist.id)"
                    class="p-2 rounded-xl bg-neutral-800/60 hover:bg-red-500/20 text-neutral-400 hover:text-red-400 border border-neutral-700/60 transition-colors"
                    title="İş Listesini Tamamen Sil"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Overall Progress Bar and Counters -->
              <div class="space-y-2 pt-2 border-t border-neutral-800">
                <div class="flex items-center justify-between text-xs">
                  <span class="text-neutral-400">
                    İlerleme: <strong class="text-neutral-100">{{ selectedPlaylist.completed_items || 0 }} / {{ selectedPlaylist.total_items || 0 }}</strong> Tamamlandı
                  </span>
                  <span class="font-bold text-accent">%{{ selectedPlaylist.progress_percentage || 0 }}</span>
                </div>

                <div class="w-full bg-neutral-900 h-2.5 rounded-full overflow-hidden border border-neutral-800">
                  <div
                    :class="['h-full transition-all duration-500',
                             selectedPlaylist.status === 'processing' ? 'bg-gradient-to-r from-accent to-cyan-400 animate-pulse' : 'bg-accent']"
                    :style="{ width: `${selectedPlaylist.progress_percentage || 0}%` }"
                  ></div>
                </div>

                <!-- Sub-status counts -->
                <div class="flex flex-wrap gap-4 text-[11px] text-neutral-400 pt-1">
                  <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Tamamlanan: {{ completedCount }}</span>
                  </span>
                  <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <span>İşlenen: {{ processingCount }}</span>
                  </span>
                  <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>Kuyrukta: {{ queuedCount }}</span>
                  </span>
                  <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-neutral-500"></span>
                    <span>Hazır: {{ readyCount }}</span>
                  </span>
                  <span v-if="failedCount > 0" class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-red-400"></span>
                    <span class="text-red-400 font-medium">Hatalı: {{ failedCount }}</span>
                  </span>
                </div>
              </div>
            </div>

            <!-- 2. Model, Dil & Referans Ses Ayarları Card -->
            <div class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3">
              <div class="flex items-center justify-between">
                <h3 class="text-xs font-semibold text-neutral-200 flex items-center gap-1.5">
                  <svg class="w-4 h-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                  <span>Liste Seslendirme Yapılandırması</span>
                </h3>
                <span class="text-[11px] text-neutral-500">Modeli değiştirip "Yeniden Çalıştır" ile farklı ses elde edebilirsiniz</span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <!-- Engine -->
                <div>
                  <label class="block text-[11px] font-medium text-neutral-400 mb-1">TTS Motoru</label>
                  <select
                    v-model="configForm.engine"
                    @change="saveConfig"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                  >
                    <optgroup v-if="downloadedModels.length > 0" label="Kurulu / Kullanıma Hazır Modeller">
                      <option v-for="m in downloadedModels" :key="m.id" :value="m.id">
                        ✓ {{ m.name }} (Hazır)
                      </option>
                    </optgroup>
                    <optgroup v-if="notDownloadedModels.length > 0" label="Kurulum Bekleyen Modeller">
                      <option v-for="m in notDownloadedModels" :key="m.id" :value="m.id">
                        ⚠ {{ m.name }} (İndirilmedi)
                      </option>
                    </optgroup>
                  </select>
                </div>

                <!-- Language -->
                <div>
                  <label class="block text-[11px] font-medium text-neutral-400 mb-1">Dil</label>
                  <select
                    v-model="configForm.language"
                    @change="saveConfig"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                  >
                    <option v-for="lang in VOICE_LANGUAGES" :key="lang.code" :value="lang.code">
                      {{ lang.flag }} {{ lang.name }} ({{ lang.code }})
                    </option>
                  </select>
                </div>

                <!-- Reference Voice Profile -->
                <div>
                  <label class="block text-[11px] font-medium text-neutral-400 mb-1">Referans Ses (Klonlama)</label>
                  <select
                    v-model="configForm.profile_id"
                    @change="saveConfig"
                    class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                  >
                    <option :value="null">Varsayılan Ses</option>
                    <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
              </div>

              <!-- Info hint if XTTS without profile or uninstalled model -->
              <div v-if="selectedModelDetails && !selectedModelDetails.is_downloaded"
                   class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-300 flex items-center justify-between">
                <span>⚠ <strong>{{ selectedModelDetails.name }}</strong> bilgisayarınızda henüz kurulu değil. Model Yöneticisi'nden indirmeniz gerekmektedir.</span>
                <a href="/models" class="underline text-amber-200 font-semibold ml-2">İndir</a>
              </div>
            </div>

            <!-- 3. Yeni İşler / Metin Ekleme Alanı (Multi-line bulk add) -->
            <div class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-3">
              <div class="flex items-center justify-between">
                <h3 class="text-xs font-semibold text-neutral-200 flex items-center gap-1.5">
                  <svg class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  <span>Metin / İş Ekle (Tekli veya Çok Satırlı Diyalog)</span>
                </h3>
                <span class="text-[11px] text-neutral-500">
                  {{ detectedLineCount > 0 ? `${detectedLineCount} adet iş parçası tespit edildi` : 'Her satır ayrı bir iş olur' }}
                </span>
              </div>

              <form @submit.prevent="addItems" class="space-y-3">
                <textarea
                  v-model="newItemsText"
                  rows="3"
                  placeholder="Seslendirilecek metni girin veya çok satırlı diyalogları alt alta yapıştırın...&#10;Örnek Satır 1: Merhaba, Voice Core sistemine hoş geldiniz.&#10;Örnek Satır 2: Bu ikinci seslendirme dosyası olacak."
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
                ></textarea>

                <div class="flex items-center justify-between">
                  <div class="text-[11px] text-neutral-400">
                    Diyalogları veya kitap cümlelerini satır satır yapıştırıp tek tıkla listeye ekleyebilirsiniz.
                  </div>
                  <button
                    type="submit"
                    :disabled="isAddingItems || !newItemsText.trim()"
                    class="px-4 py-2 rounded-xl font-semibold text-xs bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-1.5"
                  >
                    <svg v-if="isAddingItems" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>{{ isAddingItems ? 'Ekleniyor...' : '+ İşleri Listeye Ekle' }}</span>
                  </button>
                </div>
              </form>
            </div>

            <!-- 4. Alt İşler Tablosu (Items Table) -->
            <div class="p-5 rounded-2xl bg-surface border border-neutral-800 space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <!-- Filter Tabs -->
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                  <button
                    @click="itemFilter = 'all'"
                    :class="['px-2.5 py-1 rounded-lg font-medium transition-colors',
                             itemFilter === 'all' ? 'bg-neutral-800 text-neutral-100' : 'text-neutral-400 hover:text-neutral-200']"
                  >
                    Tümü ({{ playlistItems.length }})
                  </button>
                  <button
                    @click="itemFilter = 'completed'"
                    :class="['px-2.5 py-1 rounded-lg font-medium transition-colors',
                             itemFilter === 'completed' ? 'bg-emerald-500/20 text-emerald-300' : 'text-neutral-400 hover:text-neutral-200']"
                  >
                    Tamamlanan ({{ completedCount }})
                  </button>
                  <button
                    @click="itemFilter = 'processing'"
                    :class="['px-2.5 py-1 rounded-lg font-medium transition-colors',
                             itemFilter === 'processing' ? 'bg-cyan-500/20 text-cyan-300' : 'text-neutral-400 hover:text-neutral-200']"
                  >
                    İşlenen ({{ processingCount }})
                  </button>
                  <button
                    @click="itemFilter = 'queued'"
                    :class="['px-2.5 py-1 rounded-lg font-medium transition-colors',
                             itemFilter === 'queued' ? 'bg-amber-500/20 text-amber-300' : 'text-neutral-400 hover:text-neutral-200']"
                  >
                    Kuyrukta ({{ queuedCount }})
                  </button>
                  <button
                    @click="itemFilter = 'ready'"
                    :class="['px-2.5 py-1 rounded-lg font-medium transition-colors',
                             itemFilter === 'ready' ? 'bg-neutral-700 text-neutral-200' : 'text-neutral-400 hover:text-neutral-200']"
                  >
                    Hazır ({{ readyCount }})
                  </button>
                  <button
                    @click="itemFilter = 'failed'"
                    :class="['px-2.5 py-1 rounded-lg font-medium transition-colors',
                             itemFilter === 'failed' ? 'bg-red-500/20 text-red-300' : 'text-neutral-400 hover:text-neutral-200']"
                  >
                    Hatalı ({{ failedCount }})
                  </button>
                </div>

                <!-- Refresh button -->
                <button
                  @click="refreshCurrentPlaylist"
                  :disabled="isRefreshing"
                  class="text-[11px] px-2.5 py-1 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 border border-neutral-700 flex items-center gap-1 transition-colors self-start sm:self-auto disabled:opacity-50"
                >
                  <svg :class="['w-3 h-3', { 'animate-spin': isRefreshing }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  <span>Yenile</span>
                </button>
              </div>

              <!-- Items List -->
              <div v-if="filteredItems.length === 0" class="p-10 text-center text-xs text-neutral-500">
                Bu filtreye uygun iş bulunamadı.
              </div>

              <div v-else class="space-y-2">
                <div
                  v-for="(item, idx) in filteredItems"
                  :key="item.id || idx"
                  class="p-3 rounded-xl bg-neutral-900/70 border border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-neutral-700 transition-colors"
                >
                  <!-- Item Index & Text -->
                  <div class="flex items-start gap-3 flex-1 min-w-0">
                    <span class="text-[11px] font-mono font-semibold text-neutral-500 pt-0.5 w-6 text-right">
                      #{{ getItemRealIndex(item) }}
                    </span>
                    <div class="min-w-0 flex-1">
                      <p class="text-xs text-neutral-200 leading-relaxed break-words font-medium">
                        {{ item.text }}
                      </p>
                      <!-- Error details if failed -->
                      <p v-if="item.status === 'failed' && item.error_message" class="text-[11px] text-red-400 mt-1 font-mono">
                        ⚠ Hata: {{ item.error_message }}
                      </p>
                    </div>
                  </div>

                  <!-- Status & Actions -->
                  <div class="flex items-center gap-2.5 flex-shrink-0 self-end sm:self-center">
                    <!-- Status Badge -->
                    <span :class="getStatusBadgeClass(item.status)">
                      {{ getStatusLabel(item.status) }}
                    </span>

                    <!-- Single Item "İşle" Button (for ready, failed, or null status) -->
                    <button
                      v-if="item.status === 'ready' || !item.status || item.status === 'failed'"
                      @click="processSingleItem(item)"
                      :disabled="processingItemIds.includes(item.id)"
                      class="px-2.5 py-1 rounded-lg bg-accent/15 hover:bg-accent text-accent hover:text-bg text-xs font-semibold border border-accent/30 transition-all flex items-center gap-1 cursor-pointer disabled:opacity-50"
                      title="Bu öğeyi seslendirme kuyruğuna gönder"
                    >
                      <svg v-if="processingItemIds.includes(item.id)" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                      <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                      </svg>
                      <span>{{ item.status === 'failed' ? 'Tekrar İşle' : 'İşle' }}</span>
                    </button>

                    <!-- Re-run button for completed item -->
                    <button
                      v-if="item.status === 'completed'"
                      @click="processSingleItem(item)"
                      :disabled="processingItemIds.includes(item.id)"
                      class="p-1.5 rounded-lg bg-neutral-800 hover:bg-amber-500/20 text-neutral-400 hover:text-amber-300 border border-neutral-700/60 transition-colors"
                      title="Bu öğeyi güncel model ile yeniden işle"
                    >
                      <svg :class="['w-3.5 h-3.5', { 'animate-spin': processingItemIds.includes(item.id) }]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                    </button>

                    <!-- Custom Play Button (Opens Modal) -->
                    <button
                      v-if="item.status === 'completed' && item.filename"
                      @click="openPlayerForItem(item)"
                      class="p-1.5 rounded-lg bg-neutral-800 hover:bg-accent/20 hover:text-accent text-neutral-300 transition-colors flex items-center justify-center group"
                      title="Sesi Dinle (Oynatıcıyı Aç)"
                    >
                      <svg class="w-3.5 h-3.5 fill-current text-accent" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z" />
                      </svg>
                    </button>

                    <!-- Single Download Button -->
                    <a
                      v-if="item.status === 'completed' && item.filename"
                      :href="'/api/audio/' + item.filename"
                      download
                      class="p-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors"
                      title="Sesi İndir"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                    </a>

                    <!-- Delete item button -->
                    <button
                      @click="removeItem(item.id)"
                      class="p-1.5 rounded-lg bg-neutral-800/60 hover:bg-red-500/20 text-neutral-500 hover:text-red-400 transition-colors"
                      title="Öğeyi Sil"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>

    <!-- Custom Audio Player Modal -->
    <AudioPlayerModal
      :show="showPlayerModal"
      :task="playerModalData.task"
      :audio-url="playerModalData.audioUrl"
      :text="playerModalData.text"
      :model="playerModalData.model"
      :has-prev="hasPrevPlayableItem"
      :has-next="hasNextPlayableItem"
      :item-index="currentPlayableIndex + 1"
      :total-items="playableItems.length"
      @prev="playPrevItem"
      @next="playNextItem"
      @close="showPlayerModal = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import AudioPlayerModal from '../../Components/AudioPlayerModal.vue'
import { VOICE_LANGUAGES } from '../../i18n'

const showPlayerModal = ref(false)
const activePlayerItemId = ref(null)

// Playable items in current playlist (items with generated audio)
const playableItems = computed(() => {
  return (selectedPlaylist.value?.items || []).filter(item => Boolean(item.filename))
})

const currentPlayableIndex = computed(() => {
  if (!activePlayerItemId.value) return -1
  return playableItems.value.findIndex(item => item.id === activePlayerItemId.value)
})

const hasPrevPlayableItem = computed(() => {
  return currentPlayableIndex.value > 0
})

const hasNextPlayableItem = computed(() => {
  return currentPlayableIndex.value >= 0 && currentPlayableIndex.value < playableItems.value.length - 1
})

const currentPlayableItem = computed(() => {
  if (currentPlayableIndex.value < 0) return null
  return playableItems.value[currentPlayableIndex.value] || null
})

const playerModalData = computed(() => {
  const item = currentPlayableItem.value
  if (!item) {
    return { task: null, audioUrl: '', text: '', model: '' }
  }
  return {
    audioUrl: `/api/audio/${item.filename}`,
    text: item.text,
    model: selectedPlaylist.value?.engine || 'TTS Model',
    task: {
      payload: {
        text: item.text,
        engine: selectedPlaylist.value?.engine,
        filename: item.filename,
      },
      completed_at: item.updated_at || item.created_at,
    }
  }
})

const openPlayerForItem = (item) => {
  activePlayerItemId.value = item.id
  showPlayerModal.value = true
}

const playPrevItem = () => {
  if (hasPrevPlayableItem.value) {
    const prevItem = playableItems.value[currentPlayableIndex.value - 1]
    if (prevItem) {
      activePlayerItemId.value = prevItem.id
    }
  }
}

const playNextItem = () => {
  if (hasNextPlayableItem.value) {
    const nextItem = playableItems.value[currentPlayableIndex.value + 1]
    if (nextItem) {
      activePlayerItemId.value = nextItem.id
    }
  }
}

const props = defineProps({
  playlists: {
    type: Array,
    default: () => []
  },
  models: {
    type: Array,
    default: () => []
  },
  profiles: {
    type: Array,
    default: () => []
  }
})

// Local state
const localPlaylists = ref([...(props.playlists || [])])
const selectedPlaylist = ref(localPlaylists.value[0] || null)
const showCreateForm = ref(false)
const isProcessing = ref(false)
const isAddingItems = ref(false)
const isRefreshing = ref(false)
const alertMessage = ref(null)
const alertType = ref('success')
const itemFilter = ref('all')
const newItemsText = ref('')
const processingItemIds = ref([])

// Config form for selected playlist
const configForm = ref({
  engine: 'piper-tr',
  language: 'tr',
  profile_id: null
})

// Create Playlist Form
const createForm = useForm({
  title: '',
  description: '',
  engine: 'piper-tr',
  language: 'tr',
  profile_id: null
})

// Models filtering for TTS
const ttsModels = computed(() => {
  return (props.models || []).filter(m => m.type === 'tts' || m.type === 'music')
})

const downloadedModels = computed(() => {
  return ttsModels.value.filter(m => m.is_downloaded)
})

const notDownloadedModels = computed(() => {
  return ttsModels.value.filter(m => !m.is_downloaded)
})

// Watch props updates from Inertia
watch(() => props.playlists, (newVal) => {
  if (newVal) {
    localPlaylists.value = newVal.map(p => ({
      ...p,
      items: Array.isArray(p.items) ? p.items : []
    }))
    if (selectedPlaylist.value) {
      const matched = localPlaylists.value.find(p => p.id === selectedPlaylist.value.id)
      if (matched) {
        selectedPlaylist.value = matched
        updateConfigForm()
      }
    } else if (localPlaylists.value.length > 0) {
      selectPlaylist(localPlaylists.value[0])
    }
  }
}, { deep: true })

// Helper to update config form when playlist changes
const updateConfigForm = () => {
  if (selectedPlaylist.value) {
    configForm.value = {
      engine: selectedPlaylist.value.engine || 'piper-tr',
      language: selectedPlaylist.value.language || 'tr',
      profile_id: selectedPlaylist.value.profile_id ?? null
    }
  }
}

const selectPlaylist = (pl) => {
  if (!pl) {
    selectedPlaylist.value = null
    return
  }
  selectedPlaylist.value = {
    ...pl,
    items: Array.isArray(pl.items) ? pl.items : []
  }
  updateConfigForm()
  itemFilter.value = 'all'
}

// Select first playlist initially if available
if (localPlaylists.value.length > 0 && !selectedPlaylist.value) {
  selectPlaylist(localPlaylists.value[0])
}

// Line count detected in new items textarea
const detectedLineCount = computed(() => {
  if (!newItemsText.value) return 0
  return newItemsText.value.split(/\r\n|\r|\n/).filter(line => line.trim().length > 0).length
})

// Selected Model Details
const selectedModelDetails = computed(() => {
  return (props.models || []).find(m => m.id === configForm.value.engine)
})

const isCurrentEngineReady = computed(() => {
  return selectedModelDetails.value?.is_downloaded || false
})

const getModelName = (engineId) => {
  const m = (props.models || []).find(item => item.id === engineId)
  return m ? m.name : (engineId || 'Bilinmeyen Model')
}

// Sub-items computed
const playlistItems = computed(() => {
  return selectedPlaylist.value?.items || []
})

const completedCount = computed(() => {
  return playlistItems.value.filter(i => i.status === 'completed').length
})

const processingCount = computed(() => {
  return playlistItems.value.filter(i => i.status === 'processing' || i.status === 'running').length
})

const queuedCount = computed(() => {
  return playlistItems.value.filter(i => i.status === 'pending' || i.status === 'queued').length
})

const readyCount = computed(() => {
  return playlistItems.value.filter(i => !i.status || i.status === 'ready').length
})

const failedCount = computed(() => {
  return playlistItems.value.filter(i => i.status === 'failed').length
})

const filteredItems = computed(() => {
  const items = playlistItems.value
  if (itemFilter.value === 'completed') return items.filter(i => i.status === 'completed')
  if (itemFilter.value === 'processing') return items.filter(i => i.status === 'processing' || i.status === 'running')
  if (itemFilter.value === 'queued') return items.filter(i => i.status === 'pending' || i.status === 'queued')
  if (itemFilter.value === 'ready') return items.filter(i => !i.status || i.status === 'ready')
  if (itemFilter.value === 'failed') return items.filter(i => i.status === 'failed')
  return items
})

const getItemRealIndex = (item) => {
  const idx = playlistItems.value.findIndex(i => i.id === item.id)
  return idx !== -1 ? idx + 1 : 1
}

// Status Badges & Labels
const getStatusLabel = (status) => {
  switch (status) {
    case 'completed': return 'Tamamlandı'
    case 'processing':
    case 'running': return 'İşleniyor...'
    case 'pending':
    case 'queued': return 'Kuyrukta'
    case 'failed': return 'Hatalı'
    case 'empty': return 'Boş'
    case 'ready':
    default: return 'Hazır'
  }
}

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'completed':
      return 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
    case 'processing':
    case 'running':
      return 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-cyan-500/15 text-cyan-400 border border-cyan-500/30 animate-pulse'
    case 'pending':
    case 'queued':
      return 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30 animate-pulse'
    case 'failed':
      return 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/15 text-red-400 border border-red-500/30'
    case 'ready':
    default:
      return 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-neutral-800 text-neutral-400 border border-neutral-700'
  }
}

// Actions
const createPlaylist = () => {
  createForm.post('/playlists', {
    onSuccess: () => {
      createForm.reset()
      showCreateForm.value = false
      showAlert('Yeni iş listesi başarıyla oluşturuldu.', 'success')
    },
    onError: () => {
      showAlert('İş listesi oluşturulamadı. Lütfen alanları kontrol edin.', 'error')
    }
  })
}

const deletePlaylist = (id) => {
  if (!confirm('Bu iş listesini ve tüm alt öğelerini silmek istediğinize emin misiniz?')) {
    return
  }

  router.delete(`/playlists/${id}`, {
    onSuccess: () => {
      showAlert('İş listesi silindi.', 'info')
      selectedPlaylist.value = localPlaylists.value[0] || null
      updateConfigForm()
    }
  })
}

const addItems = async () => {
  if (!selectedPlaylist.value || !newItemsText.value.trim()) return

  isAddingItems.value = true
  try {
    const res = await window.axios.post(`/playlists/${selectedPlaylist.value.id}/items`, {
      text: newItemsText.value
    })

    if (res.data?.success) {
      newItemsText.value = ''
      showAlert(`${res.data.added_count} adet iş parçası listeye eklendi.`, 'success')

      const pl = res.data.playlist || res.data
      if (pl && pl.id) {
        selectedPlaylist.value.status = pl.status
        selectedPlaylist.value.items = Array.isArray(pl.items) ? pl.items : []
        selectedPlaylist.value.total_items = pl.total_items ?? selectedPlaylist.value.items.length
        selectedPlaylist.value.completed_items = pl.completed_items ?? 0
        selectedPlaylist.value.progress_percentage = pl.progress_percentage ?? 0

        const matched = localPlaylists.value.find(p => p.id === pl.id)
        if (matched) {
          matched.status = pl.status
          matched.items = selectedPlaylist.value.items
          matched.total_items = selectedPlaylist.value.total_items
          matched.completed_items = selectedPlaylist.value.completed_items
          matched.progress_percentage = selectedPlaylist.value.progress_percentage
        }
      }

      await refreshCurrentPlaylist()
    }
  } catch (err) {
    console.error('İşler eklenirken hata:', err)
    showAlert('İş parçaları eklenirken bir hata oluştu.', 'error')
  } finally {
    isAddingItems.value = false
  }
}

const removeItem = async (itemId) => {
  if (!selectedPlaylist.value) return

  try {
    const res = await window.axios.delete(`/playlists/${selectedPlaylist.value.id}/items/${itemId}`)
    if (res.data?.success) {
      const pl = res.data.playlist || res.data
      if (pl && pl.id) {
        selectedPlaylist.value.status = pl.status
        selectedPlaylist.value.items = Array.isArray(pl.items) ? pl.items : []
        selectedPlaylist.value.total_items = pl.total_items ?? selectedPlaylist.value.items.length
        selectedPlaylist.value.completed_items = pl.completed_items ?? 0
        selectedPlaylist.value.progress_percentage = pl.progress_percentage ?? 0

        const matched = localPlaylists.value.find(p => p.id === pl.id)
        if (matched) {
          matched.status = pl.status
          matched.items = selectedPlaylist.value.items
          matched.total_items = selectedPlaylist.value.total_items
          matched.completed_items = selectedPlaylist.value.completed_items
          matched.progress_percentage = selectedPlaylist.value.progress_percentage
        }
      }
      await refreshCurrentPlaylist()
    }
  } catch (err) {
    console.error('Öğe silinirken hata:', err)
    showAlert('Öğe silinirken bir hata oluştu.', 'error')
  }
}

const saveConfig = async () => {
  if (!selectedPlaylist.value) return

  try {
    await window.axios.post(`/playlists/${selectedPlaylist.value.id}/config`, {
      engine: configForm.value.engine,
      language: configForm.value.language,
      profile_id: configForm.value.profile_id
    })

    selectedPlaylist.value.engine = configForm.value.engine
    selectedPlaylist.value.language = configForm.value.language
    selectedPlaylist.value.profile_id = configForm.value.profile_id

    // Also update in local list
    const matched = localPlaylists.value.find(p => p.id === selectedPlaylist.value.id)
    if (matched) {
      matched.engine = configForm.value.engine
      matched.language = configForm.value.language
      matched.profile_id = configForm.value.profile_id
    }

    showAlert('Model ve ses yapılandırması güncellendi.', 'success')
  } catch (err) {
    console.error('Ayarlar kaydedilirken hata:', err)
    showAlert('Yapılandırma kaydedilirken hata oluştu.', 'error')
  }
}

const processPlaylist = async (restartAll = false) => {
  if (!selectedPlaylist.value) return

  if (!isCurrentEngineReady.value) {
    showAlert(`Seçili model (${selectedModelDetails.value?.name || 'Model'}) henüz kurulmadı. Lütfen önce Model Yöneticisi'nden indirin.`, 'error')
    return
  }

  if (restartAll) {
    if (!confirm('Tüm işler sıfırlanacak ve güncel seçili model (' + getModelName(configForm.value.engine) + ') ile baştan seslendirilecek. Onaylıyor musunuz?')) {
      return
    }
  }

  isProcessing.value = true
  try {
    const res = await window.axios.post(`/playlists/${selectedPlaylist.value.id}/process`, {
      restart_all: restartAll
    })

    if (res.data?.success) {
      showAlert(res.data.message || 'İşler kuyruğa eklendi. Seslendirme işlemi arka planda başlatıldı.', 'success')
      window.dispatchEvent(new CustomEvent('voice-task-created'))
      await refreshCurrentPlaylist()
    }
  } catch (err) {
    console.error('Liste çalıştırılırken hata:', err)
    const msg = err.response?.data?.error || 'Liste işlenirken bir hata oluştu.'
    showAlert(msg, 'error')
  } finally {
    isProcessing.value = false
  }
}

const processSingleItem = async (item) => {
  if (!selectedPlaylist.value || !item || !item.id) return

  if (!isCurrentEngineReady.value) {
    showAlert(`Seçili model (${selectedModelDetails.value?.name || 'Model'}) henüz kurulmadı. Lütfen önce Model Yöneticisi'nden indirin.`, 'error')
    return
  }

  processingItemIds.value.push(item.id)
  try {
    const res = await window.axios.post(`/playlists/${selectedPlaylist.value.id}/items/${item.id}/process`)
    if (res.data?.success) {
      item.status = 'pending'
      showAlert(res.data.message || 'İş parçası seslendirme kuyruğuna eklendi.', 'success')
      window.dispatchEvent(new CustomEvent('voice-task-created'))
      await refreshCurrentPlaylist()
    }
  } catch (err) {
    console.error('Öğe işlenirken hata:', err)
    const msg = err.response?.data?.error || 'Öğe işlenirken bir hata oluştu.'
    showAlert(msg, 'error')
  } finally {
    processingItemIds.value = processingItemIds.value.filter(id => id !== item.id)
  }
}

const refreshCurrentPlaylist = async () => {
  if (!selectedPlaylist.value) return

  isRefreshing.value = true
  try {
    const res = await window.axios.get(`/api/playlists/${selectedPlaylist.value.id}`)
    const data = res.data?.playlist || res.data
    if (data && data.id) {
      selectedPlaylist.value.status = data.status
      selectedPlaylist.value.items = Array.isArray(data.items) ? data.items : []
      selectedPlaylist.value.total_items = data.total_items ?? selectedPlaylist.value.items.length
      selectedPlaylist.value.completed_items = data.completed_items ?? 0
      selectedPlaylist.value.progress_percentage = data.progress_percentage ?? 0
      selectedPlaylist.value.engine = data.engine
      selectedPlaylist.value.language = data.language
      selectedPlaylist.value.profile_id = data.profile_id

      // Update in local list too
      const matched = localPlaylists.value.find(p => p.id === data.id)
      if (matched) {
        matched.status = data.status
        matched.items = selectedPlaylist.value.items
        matched.total_items = selectedPlaylist.value.total_items
        matched.completed_items = selectedPlaylist.value.completed_items
        matched.progress_percentage = selectedPlaylist.value.progress_percentage
        matched.engine = data.engine
        matched.language = data.language
        matched.profile_id = data.profile_id
      }
    }
  } catch (err) {
    console.warn('Playlist güncellenirken hata:', err)
  } finally {
    isRefreshing.value = false
  }
}

const showAlert = (msg, type = 'success') => {
  alertMessage.value = msg
  alertType.value = type
  setTimeout(() => {
    if (alertMessage.value === msg) {
      alertMessage.value = null
    }
  }, 5000)
}

/* ── Live Adaptive Polling for Running Playlists ── */
let pollTimer = null

const runPolling = async () => {
  if (selectedPlaylist.value) {
    const hasActive = processingCount.value > 0 || queuedCount.value > 0 || selectedPlaylist.value.status === 'processing'
    if (hasActive) {
      await refreshCurrentPlaylist()
    }
  }
  const nextInterval = (processingCount.value > 0 || queuedCount.value > 0 || selectedPlaylist.value?.status === 'processing') ? 2000 : 6000
  pollTimer = setTimeout(runPolling, nextInterval)
}

onMounted(() => {
  pollTimer = setTimeout(runPolling, 2000)
})

onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
})
</script>
