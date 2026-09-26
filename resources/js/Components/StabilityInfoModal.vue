<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <!-- Backdrop with blur -->
    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

    <div class="flex min-h-full items-center justify-center p-4">
      <div class="relative w-full max-w-2xl rounded-2xl bg-neutral-900 border border-neutral-700/80 shadow-2xl p-6 overflow-hidden text-neutral-200">
        <!-- Ambient decorative glow -->
        <div class="absolute -top-24 -right-24 w-60 h-60 rounded-full bg-accent/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-60 h-60 rounded-full bg-purple-500/10 blur-3xl pointer-events-none"></div>

        <!-- Header -->
        <div class="flex items-start justify-between pb-4 border-b border-neutral-800 relative z-10">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-accent/20 border border-accent/40 flex items-center justify-center text-accent">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-semibold text-white flex items-center gap-2">
                Duygu & Kararlılık (Stability) Rehberi
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-accent/20 text-accent border border-accent/30 font-medium">
                  Ses Ayarı
                </span>
              </h3>
              <p class="text-xs text-neutral-400 mt-0.5">
                Modelin konuşma monotonluğu ile dramatik duygu/gülüş serbestliği arasındaki dengeyi yönetin.
              </p>
            </div>
          </div>
          <button
            @click="$emit('close')"
            class="p-1.5 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="mt-5 space-y-4 text-xs relative z-10 max-h-[70vh] overflow-y-auto pr-1 custom-scrollbar">
          <!-- Quick concept summary -->
          <div class="p-3.5 rounded-xl bg-neutral-800/60 border border-neutral-700/60 leading-relaxed text-neutral-300">
            <span class="text-white font-medium">Temel Mantık:</span> Değer <strong class="text-accent">azaldıkça</strong> seslendiren yapay zeka bir tiyatro aktörü gibi duygusal, esnek ve metindeki ifadelere göre gülebilen/kıkırdayabilen bir tona girer. Değer <strong class="text-cyan-400">arttıkça</strong> model hatasız ve kurumsal bir haber spikeri gibi sabit ve monoton ritme kilitlenir.
          </div>

          <!-- Value Ranges -->
          <div class="space-y-3">
            <div class="font-medium text-white text-xs flex items-center gap-2">
              <span>Değer Aralıkları & Davranış Profili</span>
            </div>

            <!-- 10% - 35% -->
            <div class="p-3.5 rounded-xl bg-purple-500/10 border border-purple-500/30 space-y-1.5">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-purple-300 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                  %10 - %35 : Yüksek Duygu & Aktör Serbestliği (Önerilen: Gülüş / Drama)
                </span>
                <span class="text-[10px] font-mono text-purple-400 bg-purple-950/60 px-2 py-0.5 rounded-md border border-purple-800/40">Dramatik</span>
              </div>
              <p class="text-neutral-300 text-[11px] leading-relaxed">
                Model tonlama kurallarını gevşetir. Metinde <code class="text-purple-200 bg-purple-900/40 px-1 py-0.5 rounded">Hahaha...</code>, <code class="text-purple-200 bg-purple-900/40 px-1 py-0.5 rounded">Hehe</code> gibi kıkırdamalar veya ünlemli ifadeler varsa, bunları düz hecelemek yerine <strong>gerçekten kahkaha atarak ve ses perdesini yükseltip alçaltarak</strong> üretir.
              </p>
              <div class="text-[10px] text-neutral-400 italic">
                İdeal Kullanım: Hikayeler, masallar, oyun karakterleri, gülüş içeren samimi sohbetler.
              </div>
            </div>

            <!-- 40% - 65% -->
            <div class="p-3.5 rounded-xl bg-accent/10 border border-accent/30 space-y-1.5">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-accent flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-accent"></span>
                  %40 - %65 : Dengeli & Doğal Anlatı (Varsayılan: %50)
                </span>
                <span class="text-[10px] font-mono text-accent bg-accent/20 px-2 py-0.5 rounded-md border border-accent/40">Altın Denge</span>
              </div>
              <p class="text-neutral-300 text-[11px] leading-relaxed">
                İnsan konuşmasının en doğal halidir. Diksiyon net, ritim akıcıdır; aşırı dalgalanma yapmadan metnin anlam ve noktalamasına göre hafif iniş çıkışlar ve nefes payları sunar.
              </p>
              <div class="text-[10px] text-neutral-400 italic">
                İdeal Kullanım: Sesli kitaplar, asistan yanıtları, belgesel ve podcast seslendirmeleri.
              </div>
            </div>

            <!-- 70% - 100% -->
            <div class="p-3.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 space-y-1.5">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-cyan-300 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                  %70 - %100 : Resmi, Sabit & Spiker Tonu (Yüksek Kararlılık)
                </span>
                <span class="text-[10px] font-mono text-cyan-400 bg-cyan-950/60 px-2 py-0.5 rounded-md border border-cyan-800/40">Kurumsal</span>
              </div>
              <p class="text-neutral-300 text-[11px] leading-relaxed">
                Model tonlama sapmalarını tamamen baskılar. Ses tınısı kelimeden kelimeye değişmez, gülüş ve fısıltı gibi beklenmedik tonlamalar üretmez; son derece stabil bir tempo korunur.
              </p>
              <div class="text-[10px] text-neutral-400 italic">
                İdeal Kullanım: Haber bültenleri, resmi anonslar, e-öğrenme ders anlatımları, IVR/santral yanıtları.
              </div>
            </div>
          </div>

          <!-- Model Differences Table -->
          <div class="space-y-2 pt-2">
            <div class="font-medium text-white text-xs">Motorlara Göre Karşılığı</div>
            <div class="rounded-xl border border-neutral-800 overflow-hidden">
              <table class="w-full text-left text-[11px]">
                <thead class="bg-neutral-800/80 text-neutral-300 border-b border-neutral-700/60">
                  <tr>
                    <th class="p-2.5 font-medium">Model Grubu</th>
                    <th class="p-2.5 font-medium">Arka Plandaki Parametre</th>
                    <th class="p-2.5 font-medium">Etkisi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-neutral-800 bg-neutral-900/50 text-neutral-300">
                  <tr>
                    <td class="p-2.5 font-medium text-white">ElevenLabs</td>
                    <td class="p-2.5 font-mono text-accent">stability (0.0 - 1.0)</td>
                    <td class="p-2.5">Doğrudan aktör performansını ve kahkaha yatkınlığını yönetir.</td>
                  </tr>
                  <tr>
                    <td class="p-2.5 font-medium text-white">Coqui XTTS v2</td>
                    <td class="p-2.5 font-mono text-purple-400">temperature (0.2 - 1.1)</td>
                    <td class="p-2.5">Orijinal ses klonunun duygusal doğallığını ve nefeslerini canlandırır.</td>
                  </tr>
                  <tr>
                    <td class="p-2.5 font-medium text-white">Suno Bark</td>
                    <td class="p-2.5 font-mono text-amber-400">temperature / creativity</td>
                    <td class="p-2.5">[laughter], [sigh] gibi akustik seslerin gücünü artırır.</td>
                  </tr>
                  <tr>
                    <td class="p-2.5 font-medium text-white">Alania & Freya</td>
                    <td class="p-2.5 font-mono text-cyan-400">dinamik fonetik ritim</td>
                    <td class="p-2.5">Diksiyon akışının ne kadar dinamik veya sakin olacağını dengeler.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Other Parameters Guide -->
          <div class="space-y-2 pt-3 border-t border-neutral-800">
            <div class="font-medium text-white text-xs">Diğer Akustik Kontroller</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
              <div class="p-2.5 rounded-lg bg-neutral-800/40 border border-neutral-700/50 space-y-1">
                <div class="font-semibold text-emerald-400">🎵 Ses Perdesi (Pitch)</div>
                <div class="text-neutral-300">
                  -6 (kalın/tok/bariton anlatıcı) ile +6 (genç/çocuk/neşeli) semitone aralığında sesi inceltir veya kalınlaştırır.
                </div>
              </div>
              <div class="p-2.5 rounded-lg bg-neutral-800/40 border border-neutral-700/50 space-y-1">
                <div class="font-semibold text-blue-400">🎯 Benzerlik (Similarity Boost)</div>
                <div class="text-neutral-300">
                  Orijinal ses profiline sadakat. %75 idealdir. %90+ çok benzer ama pürüzlü, %50 pürüzsüz ama biraz farklı çıkar.
                </div>
              </div>
              <div class="p-2.5 rounded-lg bg-neutral-800/40 border border-neutral-700/50 space-y-1">
                <div class="font-semibold text-amber-400">🎭 Üslup Abartısı (Style)</div>
                <div class="text-neutral-300">
                  Konuşmadaki dramatik vurguları artırır. %0 doğaldır, %40+ tiyatral ve abartılı bir duygu vurgusu katar.
                </div>
              </div>
              <div class="p-2.5 rounded-lg bg-neutral-800/40 border border-neutral-700/50 space-y-1">
                <div class="font-semibold text-purple-400">⏸️ Varsayılan Es Süresi</div>
                <div class="text-neutral-300">
                  Metindeki [pause] etiketlerinin veya cümle aralarının kaç saniye (0.3s - 2.5s) sessiz soluklanacağını belirler.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="mt-5 pt-4 border-t border-neutral-800 flex justify-end relative z-10">
          <button
            @click="$emit('close')"
            class="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-medium transition-colors"
          >
            Anladım, Kapat
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  show: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['close'])
</script>
