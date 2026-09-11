<template>
  <AppLayout title="Ses Profilleri">
    <div class="space-y-6">

      <!-- Alert Banner -->
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
          <span class="font-medium">{{ alertMessage }}</span>
        </div>
        <button @click="alertMessage = null" class="opacity-70 hover:opacity-100 p-1 text-sm">✕</button>
      </div>

      <!-- Main Grid Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Create Profile Card -->
        <div class="space-y-6 h-fit">

          <!-- Voice Recording Action Card (Highlight) -->
          <div class="p-6 rounded-2xl bg-gradient-to-b from-surface to-neutral-900 border border-neutral-800 space-y-4 shadow-xl">
            <div class="flex items-center gap-2.5 text-accent">
              <div class="p-2 rounded-xl bg-accent/15">
                <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                </svg>
              </div>
              <h2 class="text-base font-semibold text-neutral-100">Mikrofonla Ses Kaydı Al</h2>
            </div>

            <p class="text-xs text-neutral-400 leading-relaxed">
              Kendi sesinizi mikrofonla doğrudan kaydedip yapay zeka ses klonlama profili oluşturabilirsiniz. Okuma metni ve yönlendirici ipuçları stüdyo ekranında sunulur.
            </p>

            <button
              @click="openRecordModal"
              type="button"
              class="w-full py-3 px-4 rounded-xl font-semibold text-xs bg-accent text-bg hover:opacity-90 transition-all flex items-center justify-center gap-2 shadow-lg shadow-accent/20 cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
              </svg>
              <span>Ses Kaydı Alarak Profil Ekle</span>
            </button>
          </div>

          <!-- Alternative: Upload Existing Audio File Card -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
            <h3 class="text-sm font-semibold text-neutral-200 flex items-center gap-2">
              <svg class="w-4 h-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <span>Veya Ses Dosyası Yükleyin</span>
            </h3>

            <form @submit.prevent="submitFileUpload" class="space-y-3.5">
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1">Profil Adı</label>
                <input
                  v-model="uploadForm.name"
                  type="text"
                  required
                  placeholder="Örn: Mehmet - Hikaye Tonu"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>

              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1">Açıklama (Opsiyonel)</label>
                <input
                  v-model="uploadForm.description"
                  type="text"
                  placeholder="Örn: Sakin ve samimi ses"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>

              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1">Ses Dosyası (.wav, .mp3, .ogg)</label>
                <input
                  type="file"
                  accept="audio/*"
                  @change="handleFileChange"
                  class="block w-full text-xs text-neutral-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-neutral-800 file:text-neutral-200 hover:file:bg-neutral-700 cursor-pointer bg-neutral-900 rounded-xl border border-neutral-700 p-1.5"
                />
              </div>

              <button
                type="submit"
                :disabled="uploadForm.processing || !uploadForm.name || !uploadForm.sample"
                class="w-full py-2.5 rounded-xl font-semibold text-xs bg-neutral-800 hover:bg-neutral-700 text-neutral-200 transition-colors disabled:opacity-50"
              >
                {{ uploadForm.processing ? 'Yükleniyor...' : 'Dosyayı Yükle ve Kaydet' }}
              </button>
            </form>
          </div>

        </div>

        <!-- Right Column: Profiles List (2 cols) -->
        <div class="lg:col-span-2 space-y-4">
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-base font-semibold text-neutral-100">Kayıtlı Ses Profilleri</h3>
                <p class="text-xs text-neutral-400 mt-0.5">
                  XTTS v2 motorunda ses klonlama yaparken bu profilleri seçebilirsiniz.
                </p>
              </div>
              <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-neutral-800 text-neutral-300">
                Toplam: {{ profiles.length }}
              </span>
            </div>

            <div v-if="profiles.length === 0" class="py-16 text-center text-xs text-neutral-500 space-y-2">
              <svg class="w-10 h-10 text-neutral-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
              </svg>
              <div>Henüz kayıtlı bir ses profili bulunmuyor.</div>
              <div class="text-[11px] text-neutral-600">Sol taraftaki "Mikrofonla Ses Kaydı Al" butonuna basarak ilk profilinizi oluşturabilirsiniz.</div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div
                v-for="p in profiles"
                :key="p.id"
                class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-3 flex flex-col justify-between hover:border-neutral-700 transition-colors"
              >
                <div>
                  <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold text-neutral-100 text-sm flex items-center gap-1.5 truncate">
                      <span class="w-2 h-2 rounded-full bg-accent"></span>
                      <span class="truncate">{{ p.name }}</span>
                    </div>
                    <button
                      @click="deleteProfile(p.id)"
                      class="p-1.5 rounded-lg text-neutral-500 hover:text-red-400 hover:bg-neutral-800 transition-colors"
                      title="Profili Sil"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                  <div class="text-xs text-neutral-400 mt-1 line-clamp-2">{{ p.description || 'Açıklama belirtilmedi' }}</div>
                </div>

                <div v-if="p.sample_path" class="pt-3 border-t border-neutral-800 space-y-1.5">
                  <div class="flex items-center justify-between text-[11px] text-neutral-500">
                    <span>Referans Ses Örneği</span>
                    <a
                      :href="'/api/audio/' + getSampleFilename(p.sample_path)"
                      download
                      class="text-accent hover:underline flex items-center gap-1 text-[10px]"
                    >
                      <span>İndir</span>
                    </a>
                  </div>
                  <audio controls preload="none" class="h-8 w-full" :src="'/api/audio/' + getSampleFilename(p.sample_path)"></audio>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- MODAL: SES KAYIT STÜDYOSU (MICROPHONE RECORDING MODAL) -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div
        v-if="isRecordModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/80 backdrop-blur-md overflow-y-auto"
      >
        <div class="relative w-full max-w-2xl bg-surface border border-neutral-700/80 rounded-3xl p-6 sm:p-8 space-y-6 shadow-2xl my-8">

          <!-- Modal Header -->
          <div class="flex items-center justify-between pb-4 border-b border-neutral-800">
            <div class="flex items-center gap-3">
              <div class="p-2.5 rounded-2xl bg-accent/15 text-accent">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                </svg>
              </div>
              <div>
                <h3 class="text-base font-bold text-neutral-100">Mikrofon ile Ses Kaydı</h3>
                <p class="text-xs text-neutral-400">XTTS v2 klonlama için temiz ve anlaşılır bir ses kaydı alın</p>
              </div>
            </div>

            <button
              @click="closeRecordModal"
              class="p-2 rounded-xl text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 transition-colors"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- 1. Talimatlar / İpuçları (Instructions Grid) -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-[11px]">
            <div class="p-3 rounded-xl bg-neutral-900/90 border border-neutral-800 space-y-1">
              <div class="font-semibold text-neutral-200 flex items-center gap-1">
                <span>🔇</span> Sessiz Ortam
              </div>
              <div class="text-neutral-400 text-[10px]">Arka planda müzik, TV ve yankı olmamalıdır.</div>
            </div>

            <div class="p-3 rounded-xl bg-neutral-900/90 border border-neutral-800 space-y-1">
              <div class="font-semibold text-neutral-200 flex items-center gap-1">
                <span>🎙️</span> 15 cm Mesafe
              </div>
              <div class="text-neutral-400 text-[10px]">Mikrofona sabit mesafede ve doğrudan konuşun.</div>
            </div>

            <div class="p-3 rounded-xl bg-neutral-900/90 border border-neutral-800 space-y-1">
              <div class="font-semibold text-neutral-200 flex items-center gap-1">
                <span>🗣️</span> Doğal Ton
              </div>
              <div class="text-neutral-400 text-[10px]">Sakin, akıcı ve günlük konuşma hızınızda okuyun.</div>
            </div>

            <div class="p-3 rounded-xl bg-neutral-900/90 border border-neutral-800 space-y-1">
              <div class="font-semibold text-neutral-200 flex items-center gap-1">
                <span>⏱️</span> 10-20 Saniye
              </div>
              <div class="text-neutral-400 text-[10px]">Klonlama için en ideal kayıt süresi aralığıdır.</div>
            </div>
          </div>

          <!-- 2. Örnek Okuma Metinleri (Sample Texts Card) -->
          <div class="space-y-2.5">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold text-neutral-300 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <span>Okunacak Örnek Metin</span>
              </label>

              <!-- Sample Text Selector Tabs -->
              <div class="flex items-center gap-1">
                <button
                  v-for="(t, idx) in sampleTexts"
                  :key="idx"
                  @click="activeSampleIndex = idx"
                  :class="['px-2 py-0.5 rounded-lg text-[10px] font-medium transition-colors',
                           activeSampleIndex === idx
                             ? 'bg-accent text-bg font-semibold'
                             : 'bg-neutral-800 text-neutral-400 hover:text-neutral-200']"
                >
                  {{ t.label }}
                </button>
              </div>
            </div>

            <!-- Reading Display Box -->
            <div class="p-4 rounded-2xl bg-neutral-950/90 border border-neutral-700 text-neutral-100 text-sm leading-relaxed tracking-wide font-medium select-text relative">
              {{ currentReadingText }}
            </div>
          </div>

          <!-- 3. Mikrofon Cihazı Seçimi (Microphone Selector) -->
          <div class="p-3.5 rounded-2xl bg-neutral-900/90 border border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-accent/15 text-accent flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                </svg>
              </div>
              <div>
                <label class="block text-xs font-semibold text-neutral-200">Kullanılacak Mikrofon</label>
                <div class="text-[10px] text-neutral-400">Ses kaydı alınacak giriş cihazını seçin</div>
              </div>
            </div>

            <div class="flex items-center gap-2 min-w-[220px] sm:max-w-xs flex-1">
              <select
                v-model="selectedAudioDeviceId"
                :disabled="recordState === 'recording'"
                class="w-full text-xs py-2 px-3 rounded-xl bg-neutral-950 border border-neutral-700 text-neutral-200 focus:outline-none focus:border-accent disabled:opacity-50"
              >
                <option value="">Varsayılan Mikrofon</option>
                <option v-for="(dev, idx) in audioInputDevices" :key="dev.deviceId || idx" :value="dev.deviceId">
                  {{ dev.label || `Mikrofon ${idx + 1}` }}
                </option>
              </select>

              <button
                type="button"
                @click="loadAudioDevices"
                :disabled="recordState === 'recording'"
                class="p-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-400 hover:text-neutral-200 transition-colors flex-shrink-0 disabled:opacity-50"
                title="Mikrofon listesini yenile"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </button>
            </div>
          </div>

          <!-- 4. Kayıt Kontrol İstasyonu (Live Studio Box) -->
          <div class="p-5 rounded-2xl bg-neutral-900/90 border border-neutral-800 space-y-4 text-center">

            <!-- Timer and State Display -->
            <div class="flex items-center justify-center gap-4">
              <!-- Animated Recording Dot -->
              <div v-if="recordState === 'recording'" class="flex items-center gap-2 text-xs text-red-400 font-semibold animate-pulse">
                <span class="w-3 h-3 rounded-full bg-red-500"></span>
                <span>KAYIT ALINIYOR</span>
              </div>
              <div v-else-if="recordState === 'recorded'" class="flex items-center gap-2 text-xs text-emerald-400 font-semibold">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                <span>KAYIT TAMAMLANDI</span>
              </div>
              <div v-else class="text-xs text-neutral-400">
                Mikrofon hazır. Başlamak için butona tıklayın.
              </div>

              <!-- Time Counter -->
              <div class="text-2xl font-mono font-bold tracking-wider" :class="timerClass">
                {{ formattedTime }}
              </div>
            </div>

            <!-- Volume Level Meter Bar (while recording) -->
            <div v-if="recordState === 'recording'" class="w-full max-w-md mx-auto space-y-1">
              <div class="w-full bg-neutral-950 h-2 rounded-full overflow-hidden border border-neutral-800">
                <div
                  class="h-full transition-all duration-75 bg-gradient-to-r from-emerald-400 via-amber-400 to-red-500"
                  :style="{ width: `${volumeLevel}%` }"
                ></div>
              </div>
              <div class="text-[10px] text-neutral-400 flex justify-between">
                <span>Ses Seviyesi</span>
                <span>{{ recordingSeconds < 10 ? 'En az 10 saniye önerilir' : 'Süre uygun ✓' }}</span>
              </div>
            </div>

            <!-- Control Buttons -->
            <div class="flex items-center justify-center gap-3 pt-2">
              <!-- Start Recording Button -->
              <button
                v-if="recordState === 'idle'"
                @click="startRecording"
                type="button"
                class="px-6 py-2.5 rounded-xl font-semibold text-xs bg-red-500 hover:bg-red-600 text-white transition-all flex items-center gap-2 shadow-lg shadow-red-500/20 cursor-pointer"
              >
                <span class="w-3 h-3 rounded-full bg-white animate-ping"></span>
                <span>Kaydı Başlat</span>
              </button>

              <!-- Stop Recording Button -->
              <button
                v-if="recordState === 'recording'"
                @click="stopRecording"
                type="button"
                class="px-6 py-2.5 rounded-xl font-semibold text-xs bg-amber-500 hover:bg-amber-600 text-neutral-950 transition-all flex items-center gap-2 shadow-lg shadow-amber-500/20 cursor-pointer"
              >
                <span class="w-3 h-3 rounded-sm bg-neutral-950"></span>
                <span>Kaydı Durdur</span>
              </button>

              <!-- Re-record Button -->
              <button
                v-if="recordState === 'recorded'"
                @click="resetRecording"
                type="button"
                class="px-4 py-2 rounded-xl font-semibold text-xs bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors flex items-center gap-1.5 cursor-pointer"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Yeniden Kaydet</span>
              </button>
            </div>

            <!-- Audio Review Player (When recorded) -->
            <div v-if="recordState === 'recorded' && audioUrl" class="max-w-md mx-auto pt-2 space-y-1">
              <div class="text-[11px] text-neutral-400">Kaydı Dinleyin ve Kontrol Edin:</div>
              <audio controls class="w-full h-8" :src="audioUrl"></audio>
            </div>
          </div>

          <!-- 4. Profil Bilgileri (Name & Description) -->
          <div class="space-y-3 pt-2 border-t border-neutral-800">
            <div>
              <label class="block text-xs font-medium text-neutral-300 mb-1">
                Profil Adı <span class="text-red-400">*</span>
              </label>
              <input
                v-model="modalForm.name"
                type="text"
                required
                placeholder="Örn: Kendi Sesim - Stüdyo Kaydı"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              />
            </div>

            <div>
              <label class="block text-xs font-medium text-neutral-300 mb-1">Açıklama (İsteğe Bağlı)</label>
              <input
                v-model="modalForm.description"
                type="text"
                placeholder="Örn: Sakin okuma, mikrofondan doğrudan kayıt"
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              />
            </div>
          </div>

          <!-- Modal Actions Footer -->
          <div class="flex items-center justify-end gap-3 pt-4 border-t border-neutral-800">
            <button
              @click="closeRecordModal"
              type="button"
              class="px-4 py-2.5 rounded-xl font-medium text-xs bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors"
            >
              İptal
            </button>

            <button
              @click="saveRecordedProfile"
              :disabled="isSavingProfile || recordState !== 'recorded' || !modalForm.name.trim()"
              type="button"
              class="px-6 py-2.5 rounded-xl font-semibold text-xs bg-accent text-bg hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2 shadow-lg shadow-accent/20 cursor-pointer"
            >
              <svg v-if="isSavingProfile" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSavingProfile ? 'Kaydediliyor...' : 'Profili Kaydet ve Oluştur' }}</span>
            </button>
          </div>

        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

defineProps({
  profiles: {
    type: Array,
    default: () => []
  }
})

// Notification State
const alertMessage = ref(null)
const alertType = ref('success')

const showAlert = (msg, type = 'success') => {
  alertMessage.value = msg
  alertType.value = type
  setTimeout(() => {
    if (alertMessage.value === msg) {
      alertMessage.value = null
    }
  }, 5000)
}

// File Upload Form (Standard)
const uploadForm = useForm({
  name: '',
  description: '',
  sample: null,
})

const handleFileChange = (e) => {
  uploadForm.sample = e.target.files[0]
}

const submitFileUpload = () => {
  uploadForm.post('/profiles', {
    onSuccess: () => {
      uploadForm.reset()
      showAlert('Ses profili başarıyla yüklendi ve oluşturuldu.', 'success')
    },
    onError: () => {
      showAlert('Profil oluşturulurken bir hata oluştu. Lütfen dosya formatını kontrol edin.', 'error')
    }
  })
}

const deleteProfile = (id) => {
  if (confirm('Bu ses profilini silmek istediğinize emin misiniz?')) {
    router.delete(`/profiles/${id}`, {
      onSuccess: () => {
        showAlert('Ses profili silindi.', 'info')
      }
    })
  }
}

const getSampleFilename = (path) => {
  if (!path) return ''
  return path.split(/[\\/]/).pop()
}

// ═══════════════════════════════════════════════════════════════
// RECORDING STUDIO & MODAL STATE
// ═══════════════════════════════════════════════════════════════
const isRecordModalOpen = ref(false)
const recordState = ref('idle') // 'idle' | 'recording' | 'recorded'
const recordingSeconds = ref(0)
const volumeLevel = ref(0)
const isSavingProfile = ref(false)
const audioUrl = ref(null)
let recordedWavBlob = null

// Audio Stream & Web Audio API
let mediaStream = null
let mediaRecorder = null
let recordedChunks = []
let timerInterval = null
let audioContext = null
let analyserNode = null
let animFrameId = null

// Modal Profile Info Form
const modalForm = ref({
  name: '',
  description: '',
})

// Sample Texts for reading
const sampleTexts = [
  {
    label: 'Hikaye & Edebiyat',
    text: 'Güneş dağların ardından yavaşça batarken, vadideki köyün ışıkları birer birer yanmaya başlamıştı. Rüzgarın ağaç yaprakları arasında çıkardığı hafif fısıltı, akşamın dingin sessizliğine karışıyordu. Uzun bir günün ardından derin bir nefes alıp ufka doğru baktı; yarının yeni umutlar getireceğini biliyordu.'
  },
  {
    label: 'Haber & Bilim',
    text: 'Yapay zeka ve modern ses teknolojileri, iletişim dünyasında yepyeni bir dönemin kapılarını aralıyor. Doğal dil işleme modelleri, insan sesinin en ince tonlamalarını dahi başarıyla analiz ederek gerçeğe en yakın dinleme deneyimini sunmayı başarıyor.'
  },
  {
    label: 'Günlük & Samimi',
    text: 'Merhaba, bugün hava gerçekten çok güzel. Uzun zamandır ertelediğim projeleri tamamlamak için harika bir gün olduğunu düşünüyorum. Kahvemi alıp sakin bir müzik eşliğinde çalışmaya başladığımda zamanın nasıl geçtiğini hiç anlamıyorum bile.'
  }
]

const activeSampleIndex = ref(0)
const currentReadingText = computed(() => {
  return sampleTexts[activeSampleIndex.value]?.text || ''
})

const formattedTime = computed(() => {
  const m = Math.floor(recordingSeconds.value / 60)
  const s = recordingSeconds.value % 60
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
})

const timerClass = computed(() => {
  if (recordState.value !== 'recording') return 'text-neutral-100'
  if (recordingSeconds.value < 6) return 'text-amber-400'
  if (recordingSeconds.value <= 25) return 'text-emerald-400'
  return 'text-accent'
})

// Microphone Input Devices
const audioInputDevices = ref([])
const selectedAudioDeviceId = ref('')

const loadAudioDevices = async () => {
  if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
    return
  }
  try {
    let devices = await navigator.mediaDevices.enumerateDevices()
    let audioInputs = devices.filter(d => d.kind === 'audioinput')

    // If labels are empty (no permissions given yet in this session), request temporary stream to expose labels
    const hasLabels = audioInputs.some(d => d.label && d.label.trim().length > 0)
    if (!hasLabels && navigator.mediaDevices.getUserMedia) {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
        stream.getTracks().forEach(t => t.stop())
        devices = await navigator.mediaDevices.enumerateDevices()
        audioInputs = devices.filter(d => d.kind === 'audioinput')
      } catch (permErr) {
        console.warn('Mikrofon izni bekleniyor:', permErr)
      }
    }

    audioInputDevices.value = audioInputs

    if (selectedAudioDeviceId.value && !audioInputs.some(d => d.deviceId === selectedAudioDeviceId.value)) {
      selectedAudioDeviceId.value = ''
    }
  } catch (err) {
    console.error('Mikrofon cihazları taranırken hata:', err)
  }
}

const resetRecordModal = () => {
  resetRecording()
  modalForm.value = {
    name: '',
    description: '',
  }
  isRecordModalOpen.value = false
}

const openRecordModal = () => {
  resetRecordModal()
  isRecordModalOpen.value = true
  loadAudioDevices()
}

const closeRecordModal = () => {
  resetRecordModal()
}

// ═══════════════════════════════════════════════════════════════
// WEB AUDIO RECORDING & PCM WAV ENCODING
// ═══════════════════════════════════════════════════════════════

const startRecording = async () => {
  try {
    const audioConstraints = {
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true,
    }

    if (selectedAudioDeviceId.value) {
      audioConstraints.deviceId = { exact: selectedAudioDeviceId.value }
    }

    mediaStream = await navigator.mediaDevices.getUserMedia({
      audio: audioConstraints
    })

    // Setup AnalyserNode for volume meter
    try {
      audioContext = new (window.AudioContext || window.webkitAudioContext)()
      const source = audioContext.createMediaStreamSource(mediaStream)
      analyserNode = audioContext.createAnalyser()
      analyserNode.fftSize = 256
      source.connect(analyserNode)

      const pcmData = new Uint8Array(analyserNode.frequencyBinCount)
      const updateVolume = () => {
        if (recordState.value !== 'recording') return
        analyserNode.getByteFrequencyData(pcmData)
        let sum = 0
        for (let i = 0; i < pcmData.length; i++) {
          sum += pcmData[i]
        }
        volumeLevel.value = Math.min(100, Math.round((sum / pcmData.length) * 1.6))
        animFrameId = requestAnimationFrame(updateVolume)
      }
      animFrameId = requestAnimationFrame(updateVolume)
    } catch (e) {
      console.warn('AnalyserNode başlatılamadı:', e)
    }

    recordedChunks = []
    mediaRecorder = new MediaRecorder(mediaStream)

    mediaRecorder.ondataavailable = (event) => {
      if (event.data && event.data.size > 0) {
        recordedChunks.push(event.data)
      }
    }

    mediaRecorder.start(250) // slice every 250ms
    recordState.value = 'recording'
    recordingSeconds.value = 0

    timerInterval = setInterval(() => {
      recordingSeconds.value++
    }, 1000)

  } catch (err) {
    console.error('Mikrofon erişim hatası:', err)
    alert('Mikrofon erişimi sağlanamadı. Lütfen tarayıcı ayarlarından mikrofon izni verildiğinden emin olun.')
  }
}

const stopRecording = async () => {
  if (!mediaRecorder || recordState.value !== 'recording') return

  clearInterval(timerInterval)
  if (animFrameId) cancelAnimationFrame(animFrameId)
  volumeLevel.value = 0

  mediaRecorder.onstop = async () => {
    const rawBlob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'audio/webm' })
    try {
      // Convert to clean 16-bit PCM WAV
      recordedWavBlob = await convertBlobToWav(rawBlob)
    } catch (err) {
      console.warn('WAV dönüştürme başarısız oldu, ham kayıt kullanılacak:', err)
      recordedWavBlob = rawBlob
    }

    if (audioUrl.value) {
      URL.revokeObjectURL(audioUrl.value)
    }
    audioUrl.value = URL.createObjectURL(recordedWavBlob)
    recordState.value = 'recorded'
    stopRecordingCleanup()
  }

  mediaRecorder.stop()
}

const stopRecordingCleanup = () => {
  if (mediaStream) {
    mediaStream.getTracks().forEach(t => t.stop())
    mediaStream = null
  }
  if (audioContext && audioContext.state !== 'closed') {
    audioContext.close().catch(() => {})
    audioContext = null
  }
  if (timerInterval) clearInterval(timerInterval)
  if (animFrameId) cancelAnimationFrame(animFrameId)
}

const resetRecording = () => {
  stopRecordingCleanup()
  recordState.value = 'idle'
  recordingSeconds.value = 0
  volumeLevel.value = 0
  if (audioUrl.value) {
    URL.revokeObjectURL(audioUrl.value)
    audioUrl.value = null
  }
  recordedWavBlob = null
  recordedChunks = []
}

// Converts recorded audio blob to standard 16-bit PCM WAV
async function convertBlobToWav(blob) {
  const ctx = new (window.AudioContext || window.webkitAudioContext)()
  const arrayBuffer = await blob.arrayBuffer()
  const audioBuffer = await ctx.decodeAudioData(arrayBuffer)

  // Mix down channels to single mono channel
  const numChannels = audioBuffer.numberOfChannels
  const length = audioBuffer.length
  let mono = new Float32Array(length)

  if (numChannels === 1) {
    mono = audioBuffer.getChannelData(0)
  } else {
    const ch0 = audioBuffer.getChannelData(0)
    const ch1 = audioBuffer.getChannelData(1)
    for (let i = 0; i < length; i++) {
      mono[i] = (ch0[i] + ch1[i]) / 2
    }
  }

  const sampleRate = audioBuffer.sampleRate
  ctx.close().catch(() => {})

  return encodeWav(mono, sampleRate)
}

function encodeWav(samples, sampleRate) {
  const buffer = new ArrayBuffer(44 + samples.length * 2)
  const view = new DataView(buffer)

  const writeString = (v, offset, str) => {
    for (let i = 0; i < str.length; i++) {
      v.setUint8(offset + i, str.charCodeAt(i))
    }
  }

  // RIFF identifier
  writeString(view, 0, 'RIFF')
  view.setUint32(4, 36 + samples.length * 2, true)
  writeString(view, 8, 'WAVE')

  // fmt chunk
  writeString(view, 12, 'fmt ')
  view.setUint32(16, 16, true)
  view.setUint16(20, 1, true) // PCM format
  view.setUint16(22, 1, true) // Mono
  view.setUint32(24, sampleRate, true)
  view.setUint32(28, sampleRate * 2, true) // byte rate
  view.setUint16(32, 2, true) // block align
  view.setUint16(34, 16, true) // 16-bit

  // data chunk
  writeString(view, 36, 'data')
  view.setUint32(40, samples.length * 2, true)

  let offset = 44
  for (let i = 0; i < samples.length; i++, offset += 2) {
    const s = Math.max(-1, Math.min(1, samples[i]))
    view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true)
  }

  return new Blob([view], { type: 'audio/wav' })
}

// ═══════════════════════════════════════════════════════════════
// SAVE RECORDED PROFILE ACTION
// ═══════════════════════════════════════════════════════════════
const saveRecordedProfile = () => {
  if (!recordedWavBlob || !modalForm.value.name.trim()) return

  isSavingProfile.value = true
  const wavFile = new File([recordedWavBlob], 'recorded_voice.wav', { type: 'audio/wav' })

  const formData = new FormData()
  formData.append('name', modalForm.value.name.trim())
  if (modalForm.value.description) {
    formData.append('description', modalForm.value.description.trim())
  }
  formData.append('sample', wavFile)

  router.post('/profiles', formData, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      resetRecordModal()
      showAlert('Ses kaydı başarıyla oluşturuldu ve profile eklendi!', 'success')
    },
    onError: (errors) => {
      console.error('Profil kaydedilirken hata:', errors)
      const firstError = Object.values(errors)[0] || 'Kayıtlı profil yüklenirken bir hata oluştu.'
      showAlert(firstError, 'error')
    },
    onFinish: () => {
      isSavingProfile.value = false
    }
  })
}

onMounted(() => {
  loadAudioDevices()
  if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
    navigator.mediaDevices.addEventListener('devicechange', loadAudioDevices)
  }
})

onUnmounted(() => {
  stopRecordingCleanup()
  if (audioUrl.value) URL.revokeObjectURL(audioUrl.value)
  if (navigator.mediaDevices && navigator.mediaDevices.removeEventListener) {
    navigator.mediaDevices.removeEventListener('devicechange', loadAudioDevices)
  }
})
</script>
