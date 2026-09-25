<template>
  <AppLayout title="Müzik Üretimi (BGM Studio)">
    <div class="space-y-6">
      <!-- Top Alert Notification -->
      <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="alertMessage"
             :class="['p-4 rounded-2xl border text-xs flex items-center justify-between shadow-lg',
                      alertType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' :
                      alertType === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-300' :
                      'bg-cyan-500/10 border-cyan-500/30 text-cyan-300']">
          <div class="flex items-center gap-2.5">
            <span class="text-base">{{ alertType === 'success' ? '🎶' : alertType === 'error' ? '⚠️' : 'ℹ️' }}</span>
            <span class="font-medium">{{ alertMessage }}</span>
          </div>
          <button @click="alertMessage = null" class="opacity-70 hover:opacity-100 p-1 text-xs">✕</button>
        </div>
      </transition>

      <!-- Header & Stats Banner -->
      <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-purple-500/15 via-indigo-500/10 to-blue-500/15 border border-neutral-800 p-6">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-purple-500/20 border border-purple-500/30 text-[11px] font-semibold text-purple-300 mb-2">
              <span>🎵 Polifonik Akor & AI Fon Müziği Bestecisi</span>
            </div>
            <h1 class="text-xl font-bold text-neutral-100 flex items-center gap-2.5">
              <span>Müzik & Ambiyans Stüdyosu (BGM)</span>
            </h1>
            <p class="text-xs text-neutral-400 mt-1 max-w-2xl">
              Hikayeler, podcastler ve videolar için kesintisiz döngüye uygun (seamless loop) atmosferik arka plan müzikleri ve akor melodileri üretin.
            </p>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <div class="bg-neutral-900/80 backdrop-blur border border-neutral-800 rounded-xl px-4 py-2.5 text-center">
              <div class="text-[10px] text-neutral-400 uppercase font-medium">Kayıtlı Parça</div>
              <div class="text-base font-bold text-purple-400">{{ musicList.length }}</div>
            </div>
            <div class="bg-neutral-900/80 backdrop-blur border border-neutral-800 rounded-xl px-4 py-2.5 text-center">
              <div class="text-[10px] text-neutral-400 uppercase font-medium">Dikişsiz Loop</div>
              <div class="text-base font-bold text-emerald-400">Aktif (%100)</div>
            </div>
            <Link
              href="/prompts"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-semibold border border-neutral-700 transition-colors"
              title="Hikaye Makinesine Git"
            >
              <span>🎭 Hikaye Makinesi</span>
            </Link>
          </div>
        </div>
      </div>

      <!-- Main Layout: 2 Columns (Composer Form + Music Library) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Column: Composer Form (5 cols) -->
        <div class="lg:col-span-5 space-y-5">
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-5 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-neutral-800">
              <h2 class="text-sm font-bold text-neutral-100 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-purple-400 animate-pulse"></span>
                <span>Yeni Müzik / Fon Bestele</span>
              </h2>
              <span class="text-[11px] text-neutral-500 font-mono">24kHz Stereo Ready</span>
            </div>

            <!-- Genre / Mood Selection Cards -->
            <div>
              <label class="block text-[11px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Müzik Türü & Atmosfer</label>
              <div class="grid grid-cols-1 gap-2">
                <button
                  v-for="g in presets.genres"
                  :key="g.id"
                  type="button"
                  @click="selectGenre(g)"
                  :class="['p-2.5 rounded-xl text-left border transition-all cursor-pointer flex items-start gap-2.5',
                           form.genre === g.id
                             ? 'bg-purple-500/15 border-purple-500/40 shadow-sm shadow-purple-500/10'
                             : 'bg-neutral-900/80 hover:bg-neutral-800/80 border-neutral-800']"
                >
                  <div :class="['w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-xs font-bold mt-0.5',
                                form.genre === g.id ? 'bg-purple-500 text-neutral-950' : 'bg-neutral-800 text-neutral-400']">
                    {{ g.id.slice(0, 2).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <div class="text-xs font-bold text-neutral-200 flex items-center gap-2">
                      <span>{{ g.name }}</span>
                      <span class="text-[10px] text-purple-400 font-mono font-normal">{{ g.scale }} • {{ g.default_bpm }} BPM</span>
                    </div>
                    <div class="text-[10px] text-neutral-400 line-clamp-1 mt-0.5">{{ g.description }}</div>
                  </div>
                </button>
              </div>
            </div>

            <!-- Custom Concept / Prompt with LLM Enhancer -->
            <div class="space-y-2 p-3.5 rounded-2xl bg-neutral-900/60 border border-purple-500/20">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs">✨</span>
                  <label class="text-[11px] font-bold text-neutral-200">Müzik Konsepti / Prompt</label>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    v-if="previousPrompt !== null"
                    type="button"
                    @click="undoEnhance"
                    class="text-[10px] text-purple-400 hover:text-purple-300 underline cursor-pointer"
                    title="Önceki prompt metnine geri dön"
                  >
                    ↩️ Geri Al
                  </button>
                  <span class="text-[10px] font-mono" :class="form.prompt.length > 1800 ? 'text-rose-400 font-bold' : 'text-neutral-500'">{{ form.prompt.length }}/2000</span>
                </div>
              </div>

              <!-- LLM Model Selector and Enhance Action Bar -->
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-neutral-950/90 p-2 rounded-xl border border-neutral-800">
                <div class="flex-1 min-w-0 flex items-center gap-2">
                  <span class="text-xs text-neutral-400 shrink-0 pl-1">🤖</span>
                  <select
                    v-model="selectedLlmKey"
                    class="w-full bg-neutral-900 border border-neutral-800 text-[11px] text-neutral-200 py-1.5 px-2 rounded-lg focus:outline-none focus:border-purple-500/50 cursor-pointer truncate"
                    title="Promptu iyileştirmek için kullanılacak LLM modelini seçin"
                  >
                    <optgroup
                      v-for="prov in (props.llm_options?.providers || [])"
                      :key="prov.provider"
                      :label="prov.icon + ' ' + prov.name + (prov.has_key ? '' : ' (API Key Eksik)')"
                      class="bg-neutral-900 text-neutral-200 font-semibold"
                    >
                      <option
                        v-for="m in prov.models"
                        :key="prov.provider + ':::' + m"
                        :value="prov.provider + ':::' + m"
                        class="bg-neutral-900 text-neutral-300 font-normal"
                      >
                        {{ prov.icon }} {{ prov.name.split(' ')[0] }}: {{ m }}
                      </option>
                    </optgroup>
                  </select>
                </div>

                <button
                  type="button"
                  @click="handleEnhancePrompt"
                  :disabled="isEnhancingPrompt"
                  class="px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-[11px] flex items-center justify-center gap-1.5 shrink-0 shadow-md shadow-purple-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer"
                  title="Promptu seçili LLM ile prodüksiyon kalitesinde detaylandır ve tempo/gam ayarlarını optimize et"
                >
                  <svg v-if="isEnhancingPrompt" class="w-3.5 h-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                  <span v-else>✨</span>
                  <span>{{ isEnhancingPrompt ? 'İyileştiriliyor...' : 'Promptu İyileştir' }}</span>
                </button>
              </div>

              <textarea
                v-model="form.prompt"
                rows="3"
                placeholder="Örn: Bahar ormanında uyanış, tatlı akustik gitar ve neşeli flüt tınılarıyla sakin çocuk masalı fon müziği..."
                :class="['w-full rounded-xl bg-neutral-950 p-3 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none transition-colors resize-none border',
                         formErrors.prompt ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-800 focus:border-purple-400']"
              ></textarea>
              <p v-if="formErrors.prompt" class="text-[10px] text-rose-400 flex items-center gap-1">
                <span>⚠</span> {{ formErrors.prompt }}
              </p>

              <!-- LLM Suggestion Feedback Tag -->
              <div v-if="lastEnhanceStats" class="flex flex-wrap items-center gap-2 pt-1 text-[10px] text-purple-300">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30">
                  <span>✨ {{ lastEnhanceStats.model }}</span>
                </span>
                <span class="text-neutral-400">Tempo: <b class="text-purple-300">{{ lastEnhanceStats.bpm }} BPM</b></span>
                <span class="text-neutral-500">•</span>
                <span class="text-neutral-400">Gam: <b class="text-purple-300">{{ lastEnhanceStats.scale }}</b></span>
                <span class="text-neutral-500">•</span>
                <span class="text-neutral-400">Doku: <b class="text-purple-300">{{ lastEnhanceStats.texture }}</b></span>
              </div>
            </div>

            <!-- Title Input -->
            <div class="space-y-1.5">
              <label class="text-[11px] font-semibold text-neutral-300">Parça Başlığı</label>
              <input
                v-model="form.title"
                type="text"
                placeholder="Örn: Bahar Ormanı Masalı"
                :class="['w-full rounded-xl bg-neutral-900 p-2.5 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none border',
                         formErrors.title ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-700/80 focus:border-purple-400']"
              />
              <p v-if="formErrors.title" class="text-[10px] text-rose-400 flex items-center gap-1 mt-1">
                <span>⚠</span> {{ formErrors.title }}
              </p>
            </div>

            <!-- Tempo (BPM) Slider -->
            <div class="space-y-1.5 p-3 rounded-xl bg-neutral-900/60 border border-neutral-800">
              <div class="flex items-center justify-between">
                <label class="text-[11px] font-medium text-neutral-300">Tempo (BPM): <span class="text-purple-400 font-bold font-mono">{{ form.bpm }} BPM</span></label>
                <span class="text-[10px] text-neutral-500">
                  {{ form.bpm < 75 ? 'Çok Ağır / Sakin' : form.bpm < 100 ? 'Orta / Masalsı' : form.bpm < 125 ? 'Hareketli' : 'Hızlı / Dinamik' }}
                </span>
              </div>
              <input
                type="range"
                min="50"
                max="150"
                step="5"
                v-model.number="form.bpm"
                class="w-full accent-purple-400 h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
              />
              <div class="flex justify-between text-[9px] text-neutral-500 font-mono">
                <span>50 BPM</span>
                <span>90 BPM (Masal)</span>
                <span>150 BPM</span>
              </div>
            </div>

            <!-- Scale & Texture Selection -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Müzik Gamı / Ton</label>
                <select
                  v-model="form.scale"
                  :class="['w-full rounded-xl bg-neutral-900 p-2 text-xs text-neutral-200 focus:outline-none border',
                           formErrors.scale ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-700 focus:border-purple-400']"
                >
                  <option v-for="sc in presets.scales" :key="sc" :value="sc">{{ sc }}</option>
                </select>
                <p v-if="formErrors.scale" class="text-[10px] text-rose-400 mt-1">⚠ {{ formErrors.scale }}</p>
              </div>

              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Enstrüman Dokusu</label>
                <select
                  v-model="form.texture"
                  :class="['w-full rounded-xl bg-neutral-900 p-2 text-xs text-neutral-200 focus:outline-none border',
                           formErrors.texture ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-700 focus:border-purple-400']"
                >
                  <option v-for="tx in presets.textures" :key="tx.id" :value="tx.id">{{ tx.name }}</option>
                </select>
                <p v-if="formErrors.texture" class="text-[10px] text-rose-400 mt-1">⚠ {{ formErrors.texture }}</p>
              </div>
            </div>

            <!-- Duration & Loop Controls -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Süre: {{ form.duration }} sn</label>
                <div class="flex items-center gap-1">
                  <button
                    v-for="d in [10.0, 15.0, 30.0]"
                    :key="d"
                    type="button"
                    @click="form.duration = d"
                    :class="['flex-1 py-1 rounded-lg text-[11px] font-medium transition-colors',
                             form.duration === d ? 'bg-purple-500 text-neutral-950 font-bold' : 'bg-neutral-900 text-neutral-400 hover:bg-neutral-800']"
                  >
                    {{ d }}s
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-medium text-neutral-400 mb-1">Döngü Modu (Loop)</label>
                <label class="flex items-center gap-2 p-2 rounded-xl bg-neutral-900 border border-neutral-800 cursor-pointer">
                  <input type="checkbox" v-model="form.loop" class="rounded accent-purple-400 text-purple-600 focus:ring-0" />
                  <span class="text-[11px] text-neutral-200 font-medium">Dikişsiz Loop</span>
                </label>
              </div>
            </div>

            <!-- Engine Selection -->
            <div>
              <label class="block text-[11px] font-medium text-neutral-400 mb-1">Üretim Motoru</label>
              <select
                v-model="form.engine"
                :class="['w-full rounded-xl bg-neutral-900 p-2 text-xs text-neutral-200 focus:outline-none border',
                         formErrors.engine ? 'border-rose-500 focus:border-rose-400' : 'border-neutral-700 focus:border-purple-400']"
              >
                <option value="smart_synth">⚡ Hızlı Akıllı DSP Sentez (Polifonik Pad Motoru)</option>
                <option value="musicgen">🤖 Meta MusicGen (GPU / Varsa)</option>
                <option value="cloud">☁️ Cloud Music API</option>
              </select>
              <p v-if="formErrors.engine" class="text-[10px] text-rose-400 mt-1">⚠ {{ formErrors.engine }}</p>
            </div>

            <!-- Submit Button -->
            <button
              @click="handleGenerate"
              :disabled="isGenerating"
              class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-400 hover:to-indigo-400 text-neutral-950 font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer"
            >
              <svg v-if="isGenerating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              <span v-else>🎶</span>
              <span>{{ isGenerating ? 'Müzik Besteleniyor & Miksleniyor...' : 'Müziği Bestele & Üret' }}</span>
            </button>
          </div>
        </div>

        <!-- Right Column: Music Library & Studio Audio Player (7 cols) -->
        <div class="lg:col-span-7 space-y-4">
          <!-- Active Player Card -->
          <div v-if="activeTrack" class="p-5 rounded-2xl bg-neutral-900/90 border border-purple-500/40 shadow-xl shadow-purple-500/5 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-purple-300 font-bold text-base">
                  🎵
                </div>
                <div>
                  <h3 class="text-sm font-bold text-neutral-100">{{ activeTrack.title }}</h3>
                  <div class="text-[11px] text-neutral-400 flex items-center gap-2">
                    <span class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 font-medium text-[10px]">{{ activeTrack.scale }}</span>
                    <span>•</span>
                    <span class="font-mono text-neutral-300">{{ activeTrack.bpm }} BPM</span>
                    <span>•</span>
                    <span>{{ activeTrack.duration_sec }} sn</span>
                    <span v-if="activeTrack.loopable" class="text-emerald-400 text-[10px]">🔁 Loop</span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <button
                  @click="loopPlayback = !loopPlayback"
                  :class="['px-2.5 py-1.5 rounded-xl text-xs font-medium transition-colors border',
                           loopPlayback ? 'bg-purple-500/20 border-purple-500/40 text-purple-300' : 'bg-neutral-800 border-neutral-700 text-neutral-400']"
                  title="Sonsuz Döngüde Çal"
                >
                  🔁 {{ loopPlayback ? 'Döngü Açık' : 'Döngü Kapalı' }}
                </button>
                <a
                  :href="activeTrack.audio_url"
                  download
                  class="p-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 hover:text-neutral-100 text-xs transition-colors"
                  title="WAV Dosyasını İndir"
                >
                  ⬇️ İndir
                </a>
              </div>
            </div>

            <!-- Custom Audio Scrubber -->
            <div class="space-y-1.5 pt-1">
              <div class="flex items-center gap-3">
                <button
                  @click="togglePlayActive"
                  class="w-10 h-10 rounded-full bg-purple-500 hover:bg-purple-400 text-neutral-950 flex items-center justify-center font-bold text-sm shrink-0 transition-transform active:scale-95 shadow-md shadow-purple-500/20"
                >
                  <span v-if="isPlaying">⏸</span>
                  <span v-else class="translate-x-0.5">▶</span>
                </button>

                <div class="flex-1 space-y-1">
                  <input
                    type="range"
                    min="0"
                    :max="audioDuration || 1"
                    step="0.01"
                    :value="currentTime"
                    @input="seekAudio($event.target.value)"
                    class="w-full accent-purple-400 h-1.5 bg-neutral-800 rounded-lg cursor-pointer"
                  />
                  <div class="flex justify-between text-[10px] text-neutral-500 font-mono">
                    <span>{{ formatTime(currentTime) }}</span>
                    <span>{{ formatTime(audioDuration) }}</span>
                  </div>
                </div>

                <div class="flex items-center gap-1.5 text-neutral-400 text-xs shrink-0 pl-2">
                  <span>🔊</span>
                  <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    v-model="volume"
                    @input="changeVolume"
                    class="w-16 accent-purple-400 h-1 bg-neutral-800 rounded cursor-pointer"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Music Catalog List -->
          <div class="p-6 rounded-2xl bg-surface border border-neutral-800 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-neutral-800">
              <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-neutral-100">Fon Müziği & Atmosfer Kütüphanesi</h2>
                <span class="px-2 py-0.5 rounded-full bg-neutral-800 text-[11px] text-neutral-300 font-medium">
                  {{ filteredList.length }}
                </span>
              </div>

              <!-- Search -->
              <div class="flex items-center gap-2">
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Müzik ara..."
                  class="rounded-xl bg-neutral-900 border border-neutral-700/80 px-3 py-1.5 text-xs text-neutral-200 placeholder-neutral-500 focus:outline-none focus:border-purple-400 w-36 sm:w-48"
                />
              </div>
            </div>

            <!-- List of Tracks -->
            <div v-if="filteredList.length === 0" class="py-12 text-center text-neutral-500 text-xs">
              <div class="text-2xl mb-2">🔍</div>
              <div>Aranan kriterlere uygun müzik parçası bulunamadı.</div>
            </div>

            <div v-else class="space-y-2 max-h-[580px] overflow-y-auto pr-1">
              <div
                v-for="item in filteredList"
                :key="item.filename"
                :class="['p-3 rounded-xl border flex items-center justify-between gap-3 transition-all',
                         activeTrack?.filename === item.filename
                           ? 'bg-purple-500/10 border-purple-500/30'
                           : 'bg-neutral-900/60 hover:bg-neutral-800/60 border-neutral-800']"
              >
                <div class="flex items-center gap-3 min-w-0">
                  <button
                    @click="playTrack(item)"
                    class="w-9 h-9 rounded-lg bg-neutral-800 hover:bg-purple-500 hover:text-neutral-950 text-neutral-200 flex items-center justify-center text-xs shrink-0 transition-colors"
                  >
                    <span v-if="activeTrack?.filename === item.filename && isPlaying">⏸</span>
                    <span v-else class="translate-x-0.5">▶</span>
                  </button>

                  <div class="min-w-0">
                    <div class="text-xs font-semibold text-neutral-200 truncate flex items-center gap-2">
                      <span class="truncate">{{ item.title }}</span>
                      <span v-if="item.is_default" class="text-[9px] px-1 rounded bg-neutral-800 text-neutral-400 shrink-0">Yerleşik</span>
                    </div>
                    <div class="text-[10px] text-neutral-500 flex items-center gap-2 mt-0.5">
                      <span class="text-purple-400 font-mono">{{ item.scale }}</span>
                      <span>•</span>
                      <span class="font-mono text-neutral-300">{{ item.bpm }} BPM</span>
                      <span>•</span>
                      <span>{{ item.duration_sec }}s</span>
                      <span>•</span>
                      <span class="font-mono">{{ (item.size_bytes / 1024).toFixed(0) }} KB</span>
                      <span v-if="item.loopable" class="text-emerald-400/80">🔁 Loop</span>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                  <Link
                    href="/prompts"
                    class="px-2 py-1 rounded-lg bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 text-[11px] font-medium transition-colors"
                    title="Hikaye Makinesinde Kullan"
                  >
                    🎭 Hikayede Kullan
                  </Link>
                  <a
                    :href="item.audio_url"
                    download
                    class="p-1.5 rounded-lg bg-neutral-800/80 hover:bg-neutral-700 text-neutral-400 hover:text-neutral-200 text-xs transition-colors"
                    title="İndir"
                  >
                    ⬇️
                  </a>
                  <button
                    v-if="!item.is_default"
                    @click="handleDelete(item)"
                    class="p-1.5 rounded-lg bg-neutral-800/80 hover:bg-rose-500/20 text-neutral-400 hover:text-rose-400 text-xs transition-colors"
                    title="Sil"
                  >
                    🗑️
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<!-- Music Studio Component (TailAdmin AI Voice Core) -->
<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '../../Layouts/AppLayout.vue'

const props = defineProps({
  library: {
    type: Array,
    default: () => []
  },
  presets: {
    type: Object,
    default: () => ({ genres: [], scales: [], textures: [] })
  },
  llm_options: {
    type: Object,
    default: () => ({ current: {}, providers: [] })
  }
})

const musicList = ref([...props.library])
const searchQuery = ref('')
const isGenerating = ref(false)
const alertMessage = ref(null)
const alertType = ref('success')
const formErrors = ref({})

// LLM Prompt Enhancement State
const selectedLlmKey = ref(
  props.llm_options?.current?.provider && props.llm_options?.current?.model
    ? `${props.llm_options.current.provider}:::${props.llm_options.current.model}`
    : 'ollama:::google/gemma-4-e4b'
)

const selectedLlm = computed(() => {
  const parts = (selectedLlmKey.value || '').split(':::')
  return {
    provider: parts[0] || 'ollama',
    model: parts[1] || 'google/gemma-4-e4b'
  }
})

const isEnhancingPrompt = ref(false)
const previousPrompt = ref(null)
const lastEnhanceStats = ref(null)

const handleEnhancePrompt = async () => {
  if (isEnhancingPrompt.value) return
  isEnhancingPrompt.value = true
  alertMessage.value = null

  try {
    const { provider, model } = selectedLlm.value
    const res = await axios.post('/api/music/enhance-prompt', {
      prompt: form.value.prompt,
      genre: form.value.genre,
      bpm: form.value.bpm,
      scale: form.value.scale,
      provider,
      model
    })

    if (res.data && res.data.success) {
      previousPrompt.value = form.value.prompt
      form.value.prompt = res.data.enhanced_prompt

      if (res.data.suggested_bpm) {
        form.value.bpm = res.data.suggested_bpm
      }
      if (res.data.suggested_scale) {
        form.value.scale = res.data.suggested_scale
      }
      if (res.data.suggested_texture) {
        form.value.texture = res.data.suggested_texture
      }

      lastEnhanceStats.value = {
        model: res.data.model_used || model,
        provider: res.data.provider_used || provider,
        bpm: res.data.suggested_bpm,
        scale: res.data.suggested_scale,
        texture: res.data.suggested_texture
      }

      alertType.value = 'success'
      alertMessage.value = `Prompt "${res.data.model_used || model}" ile başarıyla zenginleştirildi!`
    } else {
      throw new Error(res.data?.error || 'Prompt geliştirilemedi.')
    }
  } catch (err) {
    alertType.value = 'error'
    alertMessage.value = err.response?.data?.error || err.message || 'LLM bağlantı hatası oluştu.'
  } finally {
    isEnhancingPrompt.value = false
  }
}

const undoEnhance = () => {
  if (previousPrompt.value !== null) {
    form.value.prompt = previousPrompt.value
    previousPrompt.value = null
    lastEnhanceStats.value = null
  }
}


const defaultGenre = props.presets.genres?.[0] || { id: 'fairytale_children', default_bpm: 90, scale: 'C Major', texture: 'acoustic' }

const form = ref({
  prompt: '',
  genre: defaultGenre.id,
  title: '',
  bpm: defaultGenre.default_bpm,
  scale: defaultGenre.scale,
  texture: defaultGenre.texture,
  duration: 15.0,
  loop: true,
  engine: 'smart_synth'
})

// Audio Player State
const activeTrack = ref(null)
const isPlaying = ref(false)
const loopPlayback = ref(true)
const currentTime = ref(0)
const audioDuration = ref(0)
const volume = ref(0.85)
let audioElement = null

const initAudio = () => {
  if (!audioElement) {
    audioElement = new Audio()
    audioElement.volume = volume.value
    audioElement.ontimeupdate = () => {
      currentTime.value = audioElement.currentTime
    }
    audioElement.onloadedmetadata = () => {
      audioDuration.value = audioElement.duration
    }
    audioElement.onended = () => {
      if (loopPlayback.value) {
        audioElement.currentTime = 0
        audioElement.play()
      } else {
        isPlaying.value = false
        currentTime.value = 0
      }
    }
  }
}

const playTrack = (track) => {
  initAudio()
  if (activeTrack.value?.filename === track.filename && isPlaying.value) {
    audioElement.pause()
    isPlaying.value = false
    return
  }

  activeTrack.value = track
  audioElement.src = track.audio_url
  audioElement.loop = loopPlayback.value
  audioElement.play().then(() => {
    isPlaying.value = true
  }).catch(e => {
    console.warn('Audio play error:', e)
  })
}

const togglePlayActive = () => {
  if (!audioElement || !activeTrack.value) return
  if (isPlaying.value) {
    audioElement.pause()
    isPlaying.value = false
  } else {
    audioElement.play()
    isPlaying.value = true
  }
}

const seekAudio = (time) => {
  if (audioElement) {
    audioElement.currentTime = time
    currentTime.value = time
  }
}

const changeVolume = () => {
  if (audioElement) {
    audioElement.volume = volume.value
  }
}

const formatTime = (secs) => {
  if (!secs || isNaN(secs)) return '0:00'
  const m = Math.floor(secs / 60)
  const s = Math.floor(secs % 60)
  return `${m}:${s < 10 ? '0' : ''}${s}`
}

const selectGenre = (genre) => {
  form.value.genre = genre.id
  form.value.bpm = genre.default_bpm
  form.value.scale = genre.scale
  form.value.texture = genre.texture
  form.value.title = genre.name
}

const filteredList = computed(() => {
  if (!searchQuery.value) return musicList.value
  const q = searchQuery.value.toLowerCase()
  return musicList.value.filter(item =>
    item.title.toLowerCase().includes(q) ||
    item.genre.toLowerCase().includes(q) ||
    item.scale.toLowerCase().includes(q) ||
    (item.prompt && item.prompt.toLowerCase().includes(q))
  )
})

const handleGenerate = async () => {
  isGenerating.value = true
  alertMessage.value = null
  formErrors.value = {}

  try {
    const res = await axios.post('/api/music/generate', form.value)
    if (res.data.success) {
      alertType.value = 'success'
      alertMessage.value = `"${res.data.data.title}" fon müziği başarıyla bestelendi!`
      musicList.value.unshift(res.data.data)
      playTrack(res.data.data)
    } else {
      throw new Error(res.data.error || 'Üretim başarısız.')
    }
  } catch (err) {
    alertType.value = 'error'
    if (err.response?.status === 422 && err.response?.data?.errors) {
      // Laravel validation errors - show field-level messages
      const errors = err.response.data.errors
      formErrors.value = Object.fromEntries(
        Object.entries(errors).map(([field, msgs]) => [field, Array.isArray(msgs) ? msgs[0] : msgs])
      )
      const firstError = Object.values(formErrors.value)[0]
      alertMessage.value = `⚠ Doğrulama hatası: ${firstError}`
    } else {
      alertMessage.value = err.response?.data?.error || err.response?.data?.message || err.message || 'Hata oluştu.'
    }
  } finally {
    isGenerating.value = false
  }
}

const handleDelete = async (item) => {
  if (!confirm(`"${item.title}" fon müziğini silmek istediğinize emin misiniz?`)) return

  try {
    const res = await axios.delete(`/api/music/${item.filename}`)
    if (res.data.success) {
      musicList.value = musicList.value.filter(x => x.filename !== item.filename)
      if (activeTrack.value?.filename === item.filename) {
        if (audioElement) audioElement.pause()
        activeTrack.value = null
        isPlaying.value = false
      }
      alertType.value = 'success'
      alertMessage.value = 'Müzik parçası silindi.'
    }
  } catch (err) {
    alertType.value = 'error'
    alertMessage.value = 'Silme işlemi başarısız.'
  }
}

onBeforeUnmount(() => {
  if (audioElement) {
    audioElement.pause()
    audioElement = null
  }
})
</script>
