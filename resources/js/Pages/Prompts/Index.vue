<template>
  <AppLayout title="Prompt Şablonları">
    <div class="space-y-6 max-w-6xl pb-12">
      <!-- Header Banner & Actions -->
      <div class="p-6 rounded-2xl bg-surface border border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
        <div class="flex items-center gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 shrink-0">
            <span class="text-xl select-none">💡</span>
          </div>
          <div>
            <h1 class="text-lg font-semibold text-neutral-100 flex items-center gap-2">
              <span>Prompt Şablonları</span>
              <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/15 text-purple-300 border border-purple-500/30 font-mono">
                {{ filteredTemplates.length }} Şablon
              </span>
            </h1>
            <p class="text-xs text-neutral-400 mt-0.5">
              Metin Okuma (TTS) için dinamik değişkenli (<code class="text-accent font-mono">$1</code>, <code class="text-accent font-mono">$2</code>) prompt şablonları tanımlayın.
            </p>
          </div>
        </div>

        <button
          type="button"
          @click="openCreateModal"
          class="px-4 py-2.5 rounded-xl bg-accent text-bg font-semibold text-xs hover:opacity-90 transition-opacity flex items-center justify-center gap-2 shadow-sm cursor-pointer whitespace-nowrap"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          <span>Yeni Prompt Şablonu</span>
        </button>
      </div>

      <!-- Search & Category Filters -->
      <div class="p-4 rounded-2xl bg-surface border border-neutral-800 flex flex-col md:flex-row items-center justify-between gap-3 shadow-sm">
        <!-- Search Input -->
        <div class="relative w-full md:w-80">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Şablon başlığı veya içeriğinde ara..."
            class="w-full rounded-xl bg-neutral-900 border border-neutral-700 pl-9 pr-3.5 py-2 text-xs text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-accent"
          />
          <svg class="w-4 h-4 text-neutral-500 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Category Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0 scrollbar-none">
          <button
            type="button"
            @click="selectedCategory = 'all'"
            :class="[
              'px-3 py-1.5 rounded-xl text-xs font-medium transition-all whitespace-nowrap cursor-pointer border',
              selectedCategory === 'all'
                ? 'bg-accent/15 border-accent text-accent-300 font-semibold'
                : 'bg-neutral-900/60 border-neutral-800 text-neutral-400 hover:text-neutral-200'
            ]"
          >
            Tümü
          </button>
          <button
            v-for="cat in availableCategories"
            :key="cat"
            type="button"
            @click="selectedCategory = cat"
            :class="[
              'px-3 py-1.5 rounded-xl text-xs font-medium transition-all whitespace-nowrap cursor-pointer border',
              selectedCategory === cat
                ? 'bg-accent/15 border-accent text-accent-300 font-semibold'
                : 'bg-neutral-900/60 border-neutral-800 text-neutral-400 hover:text-neutral-200'
            ]"
          >
            {{ cat }}
          </button>
        </div>
      </div>

      <!-- Templates Grid -->
      <div v-if="filteredTemplates.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="template in filteredTemplates"
          :key="template.id"
          class="p-5 rounded-2xl bg-surface border border-neutral-800 hover:border-neutral-700 transition-all flex flex-col justify-between space-y-4 group shadow-sm"
        >
          <!-- Top Row: Category + Title + Actions -->
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <span class="px-2.5 py-0.5 rounded-lg bg-neutral-900 border border-neutral-800 text-[11px] font-medium text-neutral-400">
                {{ template.category || 'Genel' }}
              </span>

              <div class="flex items-center gap-1">
                <!-- Favorite Toggle -->
                <button
                  type="button"
                  @click="toggleFavorite(template.id)"
                  class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-400 hover:text-amber-400 transition-colors cursor-pointer"
                  :title="template.is_favorite ? 'Favorilerden çıkar' : 'Favorilere ekle'"
                >
                  <span v-if="template.is_favorite" class="text-amber-400 text-sm">★</span>
                  <span v-else class="text-neutral-600 hover:text-amber-400 text-sm">☆</span>
                </button>

                <!-- Edit Button -->
                <button
                  type="button"
                  @click="openEditModal(template)"
                  class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-400 hover:text-neutral-200 transition-colors cursor-pointer"
                  title="Düzenle"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>

                <!-- Delete Button -->
                <button
                  type="button"
                  @click="deleteTemplate(template.id, template.title)"
                  class="p-1.5 rounded-lg hover:bg-neutral-800 text-neutral-500 hover:text-red-400 transition-colors cursor-pointer"
                  title="Sil"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Title & Description -->
            <div>
              <h2 class="text-sm font-semibold text-neutral-100 group-hover:text-accent-300 transition-colors">
                {{ template.title }}
              </h2>
              <p v-if="template.description" class="text-xs text-neutral-400 line-clamp-2 mt-0.5">
                {{ template.description }}
              </p>
            </div>
          </div>

          <!-- Prompt Content Preview Box with Highlighted Variables -->
          <div class="p-3 rounded-xl bg-neutral-900/80 border border-neutral-800 text-xs text-neutral-300 font-sans leading-relaxed">
            <span v-html="highlightVariables(template.content)"></span>
          </div>

          <!-- Variables Badges & Action -->
          <div class="flex items-center justify-between gap-3 pt-1 border-t border-neutral-800/80">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span v-if="!template.extracted_variables || template.extracted_variables.length === 0" class="text-[11px] text-neutral-500">
                Sabit Prompt (Değişken Yok)
              </span>
              <span
                v-for="v in template.extracted_variables"
                :key="v"
                class="px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30 text-[10px] font-mono font-semibold text-purple-300"
              >
                {{ v }}
              </span>
            </div>

            <Link
              :href="`/tts?prompt_id=${template.id}`"
              class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-accent hover:text-bg text-neutral-200 text-xs font-semibold transition-all flex items-center gap-1.5 shrink-0 shadow-sm"
            >
              <span>Metin Okuma'da Kullan</span>
              <span class="text-xs">✨</span>
            </Link>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="p-12 rounded-2xl bg-surface border border-neutral-800 text-center space-y-3">
        <div class="text-3xl select-none">🔍</div>
        <div class="text-sm font-medium text-neutral-200">Eşleşen prompt şablonu bulunamadı</div>
        <p class="text-xs text-neutral-500 max-w-sm mx-auto">
          Arama kriterlerinizi değiştirebilir veya yeni bir prompt şablonu oluşturabilirsiniz.
        </p>
        <button
          type="button"
          @click="openCreateModal"
          class="mt-2 px-4 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity inline-flex items-center gap-2 cursor-pointer"
        >
          <span>Yeni Şablon Ekle</span>
        </button>
      </div>

      <!-- Create / Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="w-full max-w-xl rounded-2xl bg-surface border border-neutral-800 shadow-2xl p-6 space-y-5 max-h-[90vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
            <h2 class="text-base font-semibold text-neutral-100 flex items-center gap-2">
              <span>{{ isEditing ? 'Prompt Şablonunu Düzenle' : 'Yeni Prompt Şablonu' }}</span>
            </h2>
            <button @click="showModal = false" class="text-neutral-400 hover:text-neutral-200 cursor-pointer">✕</button>
          </div>

          <!-- Modal Form -->
          <form @submit.prevent="submitModal" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Title -->
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1">Şablon Başlığı *</label>
                <input
                  v-model="modalForm.title"
                  type="text"
                  required
                  placeholder="Örn: Çocuk Hikayesi"
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>

              <!-- Category -->
              <div>
                <label class="block text-xs font-medium text-neutral-400 mb-1">Kategori</label>
                <input
                  v-model="modalForm.category"
                  type="text"
                  placeholder="Hikaye, Haber, Reklam vb."
                  class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
                />
              </div>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1">Açıklama (İsteğe bağlı)</label>
              <input
                v-model="modalForm.description"
                type="text"
                placeholder="Bu şablonun ne amaçla kullanıldığını belirten kısa not..."
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              />
            </div>

            <!-- Prompt Content -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-medium text-neutral-400">Prompt Şablonu Metni *</label>
                <span class="text-[10px] text-accent font-medium">
                  $1, $2, $3 yazarak dinamik alan tanımlayın
                </span>
              </div>
              <textarea
                v-model="modalForm.content"
                rows="4"
                required
                placeholder="Örn: $1 yaş grubu çocuklar için mini bir hikaye yaz. Hikaye $2 hakkında olsun ve ana karakterler $3 olsun..."
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-xs text-neutral-100 focus:outline-none focus:border-accent font-sans"
              ></textarea>

              <!-- Live Detected Variables Preview -->
              <div class="mt-2 p-2.5 rounded-xl bg-neutral-900/80 border border-neutral-800 flex items-center gap-2 flex-wrap text-xs">
                <span class="text-neutral-500 text-[11px]">Tespit Edilen Değişkenler:</span>
                <span v-if="detectedModalVariables.length === 0" class="text-neutral-500 text-[11px] italic">
                  Henüz değişken yok (Sabit metin)
                </span>
                <span
                  v-for="v in detectedModalVariables"
                  :key="v"
                  class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono text-[10px] font-semibold border border-purple-500/30"
                >
                  {{ v }}
                </span>
              </div>
            </div>

            <!-- System Prompt (Optional) -->
            <div>
              <label class="block text-xs font-medium text-neutral-400 mb-1">Özel Sistem Talimatı (İsteğe bağlı)</label>
              <textarea
                v-model="modalForm.system_prompt"
                rows="2"
                placeholder="Bu şablon çalışırken LLM'e verilecek özel sistem talimatı..."
                class="w-full rounded-xl bg-neutral-900 border border-neutral-700 p-2.5 text-xs text-neutral-100 focus:outline-none focus:border-accent"
              ></textarea>
            </div>

            <!-- Favorite Checkbox -->
            <label class="flex items-center gap-2.5 text-xs text-neutral-300 cursor-pointer select-none">
              <input
                v-model="modalForm.is_favorite"
                type="checkbox"
                class="rounded bg-neutral-900 border-neutral-700 text-accent focus:ring-0 cursor-pointer"
              />
              <span>Bu şablonu favorilere ekle (en üstte gösterilsin)</span>
            </label>

            <!-- Actions -->
            <div class="pt-3 border-t border-neutral-800 flex justify-end gap-2.5">
              <button
                type="button"
                @click="showModal = false"
                class="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors cursor-pointer"
              >
                İptal
              </button>
              <button
                type="submit"
                :disabled="isSubmittingModal || !modalForm.title.trim() || !modalForm.content.trim()"
                class="px-5 py-2 rounded-xl bg-accent text-bg text-xs font-semibold hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
              >
                <span v-if="isSubmittingModal" class="w-3.5 h-3.5 border-2 border-bg border-t-transparent rounded-full animate-spin"></span>
                <span>{{ isEditing ? 'Güncelle' : 'Kaydet' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

const props = defineProps({
  templates: Array,
  categories: Array,
  filters: Object,
})

const searchQuery = ref('')
const selectedCategory = ref('all')

const localTemplates = ref([...(props.templates || [])])

const availableCategories = computed(() => {
  const cats = new Set()
  localTemplates.value.forEach(t => {
    if (t.category) cats.add(t.category)
  })
  return Array.from(cats)
})

const filteredTemplates = computed(() => {
  let list = localTemplates.value

  if (selectedCategory.value !== 'all') {
    list = list.filter(t => t.category === selectedCategory.value)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(t =>
      (t.title && t.title.toLowerCase().includes(q)) ||
      (t.content && t.content.toLowerCase().includes(q)) ||
      (t.description && t.description.toLowerCase().includes(q))
    )
  }

  return list
})

const highlightVariables = (text) => {
  if (!text) return ''
  return text.replace(/\$([a-zA-Z0-9_]+)/g, '<span class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 font-mono font-semibold border border-purple-500/30">$$$1</span>')
}

// Modal State
const showModal = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const isSubmittingModal = ref(false)

const modalForm = ref({
  title: '',
  category: 'Genel',
  description: '',
  content: '',
  system_prompt: '',
  is_favorite: false,
})

const detectedModalVariables = computed(() => {
  if (!modalForm.value.content) return []
  const matches = modalForm.value.content.match(/\$([a-zA-Z0-9_]+)/g)
  if (!matches) return []
  return Array.from(new Set(matches))
})

const openCreateModal = () => {
  isEditing.value = false
  editingId.value = null
  modalForm.value = {
    title: '',
    category: 'Genel',
    description: '',
    content: '',
    system_prompt: '',
    is_favorite: false,
  }
  showModal.value = true
}

const openEditModal = (template) => {
  isEditing.value = true
  editingId.value = template.id
  modalForm.value = {
    title: template.title,
    category: template.category || 'Genel',
    description: template.description || '',
    content: template.content,
    system_prompt: template.system_prompt || '',
    is_favorite: !!template.is_favorite,
  }
  showModal.value = true
}

const submitModal = () => {
  if (!modalForm.value.title.trim() || !modalForm.value.content.trim()) return

  isSubmittingModal.value = true

  const url = isEditing.value ? `/prompts/${editingId.value}` : '/prompts'
  const method = isEditing.value ? 'put' : 'post'

  router[method](url, modalForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      showModal.value = false
      isSubmittingModal.value = false
      router.reload({ only: ['templates', 'categories'] })
    },
    onError: () => {
      isSubmittingModal.value = false
    }
  })
}

const toggleFavorite = (id) => {
  router.post(`/prompts/${id}/favorite`, {}, {
    preserveScroll: true,
  })
  const item = localTemplates.value.find(t => t.id === id)
  if (item) item.is_favorite = !item.is_favorite
}

const deleteTemplate = (id, title) => {
  if (!confirm(`"${title}" adlı prompt şablonunu silmek istediğinize emin misiniz?`)) {
    return
  }
  router.delete(`/prompts/${id}`, {
    preserveScroll: true,
    onSuccess: () => {
      localTemplates.value = localTemplates.value.filter(t => t.id !== id)
    }
  })
}
</script>
