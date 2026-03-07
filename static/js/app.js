/**
 * Voice Core - Modern UI Application
 * Main application logic with shadcn/ui inspired design
 */

document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const loginView = document.getElementById('loginView');
    const appView = document.getElementById('appView');
    const loginForm = document.getElementById('loginForm');
    const loginError = document.getElementById('loginError');
    const logoutBtn = document.getElementById('logoutBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const themeToggle = document.getElementById('themeToggle');
    const mainContent = document.getElementById('mainContent');
    const pageTitle = document.getElementById('pageTitle');

    // State
    let currentUser = null;
    let currentDownloadModelId = null;

    // Theme Toggle
    themeToggle.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
        localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    });

    // Sidebar Toggle (Mobile)
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        sidebar.classList.toggle('absolute');
        sidebar.classList.toggle('z-40');
        sidebar.classList.toggle('h-screen');
    });

    // Initialize
    async function init() {
        if (api.token) {
            try {
                currentUser = await api.getMe();
                
                // Load user preferences (theme and language)
                await i18n.init();
                
                showApp();
                handleRoute();
                
                // Connect WebSocket
                if (typeof wsClient !== 'undefined') {
                    wsClient.connect();
                }
            } catch (e) {
                showLogin();
            }
        } else {
            showLogin();
        }
    }

    function showLogin() {
        loginView.classList.remove('hidden');
        appView.classList.add('hidden');
    }

    function showApp() {
        loginView.classList.add('hidden');
        appView.classList.remove('hidden');
        document.querySelectorAll('.username-display').forEach(el => {
            el.textContent = currentUser?.username || 'Admin';
        });
        lucide.createIcons();
    }

    // Login Form
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginError.classList.add('hidden');

        try {
            currentUser = await api.login(
                document.getElementById('username').value,
                document.getElementById('password').value
            );
            showApp();
            if (!window.location.hash) window.location.hash = '#dashboard';
            handleRoute();
            
            // Connect WebSocket after login
            if (typeof wsClient !== 'undefined') {
                wsClient.connect();
            }
        } catch (error) {
            loginError.classList.remove('hidden');
        }
    });

    // Logout
    logoutBtn.addEventListener('click', () => {
        api.clearToken();
        if (typeof wsClient !== 'undefined') {
            wsClient.disconnect();
        }
        showLogin();
    });

    // Routing
    window.addEventListener('hashchange', handleRoute);

    const routes = {
        'dashboard': { title: 'Dashboard', render: renderDashboard },
        'profiles': { title: 'Ses Profilleri', render: renderProfiles },
        'tts': { title: 'Metin Okuma', render: renderTts },
        'stt': { title: 'Sesten Metne', render: renderStt },
        'models': { title: 'Model Yöneticisi', render: renderModels },
        'queue': { title: 'İşlem Kuyruğu', render: renderQueue },
        'settings': { title: 'Ayarlar', render: renderSettings }
    };

    function handleRoute() {
        const hash = window.location.hash || '#dashboard';
        const page = hash.substring(1).split('?')[0];
        
        // Parse URL parameters
        const urlParams = new URLSearchParams(hash.split('?')[1] || '');
        const tabParam = urlParams.get('tab');
        
        // Update active sidebar item
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.route === page) {
                item.classList.add('active');
            }
        });

        // Set tab from URL parameter if available
        if (tabParam) {
            if (page === 'models' && ['all', 'tts', 'stt', 'music', 'llm'].includes(tabParam)) {
                currentModelTab = tabParam;
            } else if (page === 'settings' && ['general', 'api', 'services'].includes(tabParam)) {
                currentSettingsTab = tabParam;
            }
        }

        // Render page
        if (routes[page]) {
            pageTitle.textContent = routes[page].title;
            routes[page].render();
        } else {
            pageTitle.textContent = routes['dashboard'].title;
            routes['dashboard'].render();
        }

        // Close mobile sidebar
        if (window.innerWidth < 1024) {
            sidebar.classList.add('-translate-x-full');
        }
        
        // Refresh icons
        setTimeout(() => lucide.createIcons(), 100);
    }

    // ==================== Dashboard ====================
    async function renderDashboard() {
        mainContent.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                ${renderSkeletonCard()}
                ${renderSkeletonCard()}
                ${renderSkeletonCard()}
                ${renderSkeletonCard()}
            </div>
        `;

        try {
            const [profiles, queue, models, history] = await Promise.all([
                api.getProfiles(),
                api.getQueueTasks(),
                api.getAvailableModels(),
                api.getTtsHistory()
            ]);

            const healthyModels = models.filter(m => m.is_downloaded).length;
            const pendingTasks = queue.filter(t => t.status === 'pending' || t.status === 'running').length;

            mainContent.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    ${renderStatCard('Ses Profilleri', profiles.length, 'users', 'primary')}
                    ${renderStatCard('Bekleyen İşler', pendingTasks, 'list-checks', 'warning')}
                    ${renderStatCard('Hazır Modeller', healthyModels, 'box', 'success')}
                    ${renderStatCard('Ses Geçmişi', history.length, 'volume-2', 'accent')}
                </div>
                
                <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="font-semibold">Son Aktiviteler</h3>
                        </div>
                        <div class="card-content">
                            ${history.slice(0, 5).length > 0 ? `
                                <div class="space-y-3">
                                    ${history.slice(0, 5).map(h => `
                                        <div class="flex items-center gap-3 p-3 rounded-lg bg-muted/50">
                                            <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                                <i data-lucide="volume-2" class="w-4 h-4 text-primary"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium truncate">${h.text.substring(0, 50)}...</p>
                                                <p class="text-xs text-muted-foreground">${new Date(h.created_at).toLocaleString('tr-TR')}</p>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : '<p class="text-muted-foreground text-center py-8">Henüz aktivite yok</p>'}
                        </div>
                    </div>
                    
                    <!-- Model Status -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="font-semibold">Model Durumu</h3>
                        </div>
                        <div class="card-content">
                            <div class="space-y-3">
                                ${models.slice(0, 5).map(m => `
                                    <div class="flex items-center justify-between p-3 rounded-lg bg-muted/50">
                                        <div class="flex items-center gap-3">
                                            <div class="w-2 h-2 rounded-full ${m.is_downloaded ? 'bg-success' : 'bg-muted-foreground'}"></div>
                                            <span class="text-sm font-medium">${m.name}</span>
                                        </div>
                                        <span class="text-xs ${m.is_downloaded ? 'text-success' : 'text-muted-foreground'}">
                                            ${m.is_downloaded ? 'Hazır' : 'İndirilmedi'}
                                        </span>
                                    </div>
                                `).join('')}
                            </div>
                            <a href="#models" class="btn btn-outline w-full mt-4">
                                Tüm Modelleri Gör
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        } catch (e) {
            mainContent.innerHTML = renderError(e.message);
        }
    }

    function renderStatCard(title, value, icon, color) {
        const colors = {
            primary: 'bg-primary/10 text-primary',
            success: 'bg-success/10 text-success',
            warning: 'bg-warning/10 text-warning',
            accent: 'bg-accent text-accent-foreground'
        };
        
        return `
            <div class="card">
                <div class="card-content">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">${title}</p>
                            <p class="text-3xl font-bold mt-1">${value}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl ${colors[color]} flex items-center justify-center">
                            <i data-lucide="${icon}" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderSkeletonCard() {
        return `
            <div class="card">
                <div class="card-content">
                    <div class="flex items-center justify-between">
                        <div class="space-y-2">
                            <div class="skeleton h-4 w-24"></div>
                            <div class="skeleton h-8 w-16"></div>
                        </div>
                        <div class="skeleton w-12 h-12 rounded-xl"></div>
                    </div>
                </div>
            </div>
        `;
    }

    // ==================== Profiles ====================
    async function renderProfiles() {
        mainContent.innerHTML = renderLoading();

        try {
            const profiles = await api.getProfiles();

            mainContent.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">Ses Profilleri</h2>
                        <button onclick="showCreateProfileModal()" class="btn btn-primary">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Yeni Profil
                        </button>
                    </div>

                    ${profiles.length > 0 ? `
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            ${profiles.map(p => `
                                <div class="card group">
                                    <div class="card-content">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                                                    <i data-lucide="user" class="w-6 h-6 text-primary"></i>
                                                </div>
                                                <div>
                                                    <h3 class="font-semibold">${p.name}</h3>
                                                    <span class="badge badge-secondary text-xs">${p.engine?.toUpperCase() || 'GENEL'}</span>
                                                </div>
                                            </div>
                                            <button onclick="deleteProfile(${p.id})" class="p-2 rounded-lg hover:bg-destructive/10 hover:text-destructive transition-colors opacity-0 group-hover:opacity-100">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                        
                                        ${p.audio_file_path ? `
                                            <div class="mt-4">
                                                <audio controls class="w-full h-8">
                                                    <source src="${API_BASE}/data/uploads/profiles/${p.id}/reference.wav" type="audio/wav">
                                                </audio>
                                            </div>
                                        ` : '<p class="text-sm text-muted-foreground mt-4">Ses dosyası yok</p>'}
                                        
                                        <p class="text-xs text-muted-foreground mt-3">
                                            Oluşturulma: ${new Date(p.created_at).toLocaleDateString('tr-TR')}
                                        </p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : renderEmptyState('Ses Profili Yok', 'Henüz ses profili oluşturmadınız.', 'plus', 'showCreateProfileModal')}
                </div>
            `;
            lucide.createIcons();
        } catch (e) {
            mainContent.innerHTML = renderError(e.message);
        }
    }

    window.showCreateProfileModal = () => {
        // Simple inline form instead of modal for now
        mainContent.innerHTML = `
            <div class="max-w-2xl mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold">Yeni Ses Profili</h3>
                    </div>
                    <form id="createProfileForm" class="card-content space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Profil Adı</label>
                            <input type="text" id="profileName" required class="input" placeholder="Örn: Benim Sesim">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium">TTS Motoru</label>
                            <select id="profileEngine" class="input">
                                <option value="">Seçiniz</option>
                                <option value="xtts">XTTS V2</option>
                                <option value="bark">Bark</option>
                                <option value="tortoise">Tortoise</option>
                            </select>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Referans Ses</label>
                            <input type="file" id="profileAudio" accept="audio/*" class="input">
                            <p class="text-xs text-muted-foreground">Ses dosyası veya mikrofon kaydı yükleyin</p>
                        </div>
                        
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="renderProfiles()" class="btn btn-outline flex-1">İptal</button>
                            <button type="submit" class="btn btn-primary flex-1">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        lucide.createIcons();

        document.getElementById('createProfileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<div class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div> Kaydediliyor...';

            try {
                const profile = await api.createProfile({
                    name: document.getElementById('profileName').value,
                    engine: document.getElementById('profileEngine').value,
                    description: ''
                });

                const audioFile = document.getElementById('profileAudio').files[0];
                if (audioFile) {
                    await api.uploadProfileAudio(profile.id, audioFile);
                }

                notificationSystem?.showToast('Profil oluşturuldu', 'success');
                renderProfiles();
            } catch (error) {
                notificationSystem?.showToast('Hata: ' + error.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Kaydet';
                lucide.createIcons();
            }
        });
    };

    window.deleteProfile = async (id) => {
        if (!confirm('Bu ses profilini silmek istediğinize emin misiniz?')) return;
        
        try {
            await api.deleteProfile(id);
            notificationSystem?.showToast('Profil silindi', 'success');
            renderProfiles();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    // ==================== TTS ====================
    async function renderTts() {
        const hash = window.location.hash;
        const activeTab = hash.includes('tab=historyTab') ? 'history' : 'create';

        mainContent.innerHTML = renderLoading();

        try {
            const [profiles, tags, readyEngines] = await Promise.all([
                api.getProfiles(),
                api.getTags(),
                api.request('/tts/engines').catch(() => ['xtts']) // Fallback if endpoint not available
            ]);

            mainContent.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">Metin Okuma (TTS)</h2>
                        <button onclick="showTagManager()" class="btn btn-outline">
                            <i data-lucide="tags" class="w-4 h-4"></i>
                            Etiketler
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="tabs-list">
                        <button class="tab ${activeTab === 'create' ? 'active' : ''}" onclick="switchTtsTab('create')">
                            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                            Yeni Ses Üret
                        </button>
                        <button class="tab ${activeTab === 'history' ? 'active' : ''}" onclick="switchTtsTab('history')">
                            <i data-lucide="history" class="w-4 h-4 mr-2"></i>
                            Geçmiş
                        </button>
                    </div>

                    <!-- Create Tab -->
                    <div id="ttsCreateTab" class="${activeTab === 'create' ? '' : 'hidden'}">
                        <div class="card max-w-3xl">
                            <div class="card-content space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">TTS Motoru *</label>
                                        <select id="ttsEngine" class="input" required onchange="onTtsEngineChange()">
                                            <option value="">Motor seçin...</option>
                                            ${readyEngines.includes('xtts') ? '<option value="xtts">XTTS V2</option>' : ''}
                                            ${readyEngines.includes('bark') ? '<option value="bark">Bark</option>' : ''}
                                            ${readyEngines.includes('tortoise') ? '<option value="tortoise">Tortoise</option>' : ''}
                                            ${readyEngines.includes('musicgen-small') ? '<option value="musicgen-small">MusicGen Small (Hızlı)</option>' : ''}
                                            ${readyEngines.includes('musicgen-medium') ? '<option value="musicgen-medium">MusicGen Medium (Dengeli)</option>' : ''}
                                            ${readyEngines.includes('musicgen-large') ? '<option value="musicgen-large">MusicGen Large (En İyi Kalite)</option>' : ''}
                                            ${readyEngines.includes('musicgen-melody') ? '<option value="musicgen-melody">MusicGen Melody (Referanslı)</option>' : ''}
                                            ${readyEngines.length === 0 ? '<option value="" disabled>Model indirilmesi gerekiyor</option>' : ''}
                                        </select>
                                        ${readyEngines.length === 0 ? `
                                            <p class="text-xs text-destructive">
                                                <i data-lucide="alert-circle" class="w-3 h-3 inline mr-1"></i>
                                                Model Yöneticisi'nden model indirin
                                            </p>
                                        ` : ''}
                                    </div>
                                    
                                    <div class="space-y-2" id="profileSelectContainer">
                                        <label class="text-sm font-medium">Ses Profili <span id="profileRequired" class="text-destructive">*</span></label>
                                        <select id="ttsProfile" class="input">
                                            <option value="">Profil seçin...</option>
                                            ${profiles.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                                        </select>
                                    </div>
                                    
                                    <!-- MusicGen Duration Selector -->
                                    <div id="musicGenDurationContainer" class="space-y-2 md:col-span-2" style="display: none;">
                                        <label class="text-sm font-medium">Müzik Süresi (saniye)</label>
                                        <select id="musicGenDuration" class="input">
                                            <option value="256">5 saniye</option>
                                            <option value="512" selected>10 saniye</option>
                                            <option value="768">15 saniye</option>
                                            <option value="1024">20 saniye</option>
                                            <option value="1280">25 saniye</option>
                                            <option value="1500">30 saniye</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-medium">Etiketler</label>
                                        <button type="button" onclick="showTagManagerModal()" class="text-xs text-primary hover:underline">
                                            <i data-lucide="settings" class="w-3 h-3 inline mr-1"></i>
                                            Etiketleri Yönet
                                        </button>
                                    </div>
                                    <select id="ttsTags" multiple class="w-full">
                                        ${tags.map(t => `<option value="${t.id}">${t.name}</option>`).join('')}
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Metin *</label>
                                    <textarea id="ttsText" rows="6" required class="input resize-none"
                                        placeholder="Seslendirilecek metni buraya yazın..."></textarea>
                                </div>

                                <button type="button" onclick="submitTts()" class="btn btn-primary w-full" ${readyEngines.length === 0 ? 'disabled' : ''}>
                                    <i data-lucide="volume-2" class="w-4 h-4"></i>
                                    Ses Üret
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- History Tab -->
                    <div id="ttsHistoryTab" class="${activeTab === 'history' ? '' : 'hidden'}">
                        <div id="ttsHistoryContent">
                            <div class="flex items-center justify-center h-64">
                                <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Initialize Tom Select for tags with dark mode support
            if (document.getElementById('ttsTags')) {
                const tagSelect = new TomSelect('#ttsTags', {
                    plugins: ['remove_button'],
                    placeholder: 'Etiket seçin...',
                    create: false,
                    onType: function(str) {
                        // Store current input for create modal
                        tagSelect.currentInput = str;
                    },
                    onKeyDown: function(e) {
                        // Handle Enter key when no option is selected
                        if (e.keyCode === 13) {
                            const input = tagSelect.control_input.value.trim();
                            const options = tagSelect.options;
                            let exists = false;
                            
                            for (let key in options) {
                                if (options[key].text.toLowerCase() === input.toLowerCase()) {
                                    exists = true;
                                    break;
                                }
                            }
                            
                            if (input && !exists) {
                                e.preventDefault();
                                showCreateTagModal(input);
                            }
                        }
                    },
                    render: {
                        option: function(data, escape) {
                            return '<div class="px-3 py-2 hover:bg-accent cursor-pointer">' + escape(data.text) + '</div>';
                        },
                        item: function(data, escape) {
                            return '<div class="px-2 py-1 bg-primary/10 text-primary rounded text-sm">' + escape(data.text) + '</div>';
                        },
                        no_results: function(data, escape) {
                            const input = tagSelect.control_input.value.trim();
                            return '<div class="px-3 py-2 text-muted-foreground cursor-pointer hover:bg-accent" onclick="showCreateTagModal(\'' + escape(input) + '\')">' +
                                   '<i data-lucide="plus" class="w-4 h-4 inline mr-1"></i>' +
                                   '"' + escape(input) + '" etiketini oluştur' +
                                   '</div>';
                        }
                    }
                });
                
                // Apply dark mode styles to Tom Select
                const tsControl = document.querySelector('#ttsTags').nextElementSibling;
                if (tsControl) {
                    tsControl.classList.add('dark-mode-select');
                }
            }

            lucide.createIcons();

            // Load history if on history tab
            if (activeTab === 'history') {
                loadTtsHistory();
            }

        } catch (e) {
            mainContent.innerHTML = renderError(e.message);
        }
    }

    window.switchTtsTab = (tab) => {
        window.location.hash = `#tts?tab=${tab}Tab`;
    };

    async function loadTtsHistory() {
        const container = document.getElementById('ttsHistoryContent');
        if (!container) return;

        try {
            const history = await api.getTtsHistory();

            if (history.length === 0) {
                container.innerHTML = renderEmptyState('Ses Geçmişi Boş', 'Henüz ses üretmediniz.', 'volume-2', "switchTtsTab('create')");
                return;
            }

            container.innerHTML = `
                <div class="space-y-4">
                    ${history.map(h => `
                        <div class="card">
                            <div class="card-content">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="badge badge-secondary text-xs">${h.engine?.toUpperCase()}</span>
                                            ${h.tags?.map(t => `<span class="badge text-xs" style="background-color: ${t.color}; color: white;">${t.name}</span>`).join('') || ''}
                                        </div>
                                        <p class="text-sm text-foreground line-clamp-2">${h.text}</p>
                                        <p class="text-xs text-muted-foreground mt-2">
                                            ${new Date(h.created_at).toLocaleString('tr-TR')}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <audio controls class="h-8 w-32">
                                            <source src="${API_BASE}/${h.output_path}" type="audio/wav">
                                        </audio>
                                        <button onclick="deleteTtsOutput(${h.id})" class="p-2 rounded-lg hover:bg-destructive/10 hover:text-destructive transition-colors">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            lucide.createIcons();
        } catch (e) {
            container.innerHTML = renderError(e.message);
        }
    }

    window.onTtsEngineChange = () => {
        const engine = document.getElementById('ttsEngine')?.value || '';
        const durationContainer = document.getElementById('musicGenDurationContainer');
        const profileContainer = document.getElementById('profileSelectContainer');
        const profileSelect = document.getElementById('ttsProfile');
        const profileRequired = document.getElementById('profileRequired');
        
        // Show/hide duration selector for MusicGen
        if (engine.startsWith('musicgen')) {
            durationContainer.style.display = 'block';
        } else {
            durationContainer.style.display = 'none';
        }
        
        // Make profile optional for MusicGen models
        if (engine.startsWith('musicgen')) {
            profileContainer.style.opacity = '0.6';
            profileSelect.required = false;
            profileRequired.style.display = 'none';
        } else {
            profileContainer.style.opacity = '1';
            profileSelect.required = true;
            profileRequired.style.display = 'inline';
        }
    };

    // Keep old function name for backward compatibility
    window.toggleMusicGenDuration = window.onTtsEngineChange;

    window.submitTts = async () => {
        const profileId = document.getElementById('ttsProfile').value;
        const text = document.getElementById('ttsText').value;
        const engine = document.getElementById('ttsEngine')?.value || '';
        
        // Validate engine is selected
        if (!engine) {
            notificationSystem?.showToast('Lütfen bir TTS motoru seçin', 'warning');
            return;
        }
        
        // Validate text
        if (!text) {
            notificationSystem?.showToast('Lütfen metin girin', 'warning');
            return;
        }
        
        // Profile is required only for non-MusicGen engines
        const isMusicGen = engine.startsWith('musicgen');
        if (!isMusicGen && !profileId) {
            notificationSystem?.showToast('Lütfen ses profili seçin', 'warning');
            return;
        }

        const btn = document.querySelector('button[onclick="submitTts()"]');
        btn.disabled = true;
        btn.innerHTML = '<div class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin inline mr-2"></div> Gönderiliyor...';

        try {
            // Get selected tags
            const tagSelect = document.getElementById('ttsTags');
            const tagIds = tagSelect?.tomselect?.getValue() || [];
            
            // Prepare request payload
            const payload = {
                text,
                engine,
                tag_ids: tagIds.map(id => parseInt(id))
            };
            
            // Add profile_id only if selected (optional for MusicGen)
            if (profileId) {
                payload.profile_id = parseInt(profileId);
            }
            
            // Add MusicGen duration if applicable
            if (engine.startsWith('musicgen')) {
                const durationTokens = document.getElementById('musicGenDuration')?.value || '512';
                payload.max_length = parseInt(durationTokens);
            }

            await api.generateTts(payload);

            notificationSystem?.showToast('Ses üretme görevi kuyruğa eklendi', 'success');
            document.getElementById('ttsText').value = '';
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="volume-2" class="w-4 h-4"></i> Ses Üret';
            lucide.createIcons();
        }
    };

    window.deleteTtsOutput = async (id) => {
        if (!confirm('Bu ses kaydını silmek istediğinize emin misiniz?')) return;
        
        try {
            await api.request(`/tts/${id}`, 'DELETE');
            notificationSystem?.showToast('Ses kaydı silindi', 'success');
            loadTtsHistory();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    // ==================== Models ====================
    // Model category definitions
    const MODEL_CATEGORIES = {
        'tts': { label: 'TTS', label_tr: 'Metin Okuma', icon: 'volume-2', color: 'blue' },
        'stt': { label: 'STT', label_tr: 'Sesten Metne', icon: 'mic', color: 'green' },
        'music': { label: 'MUSIC', label_tr: 'Müzik', icon: 'music', color: 'purple' },
        'llm': { label: 'LLM', label_tr: 'Dil Modeli', icon: 'brain', color: 'orange' }
    };

    let currentModelTab = 'all';

    async function renderModels() {
        mainContent.innerHTML = renderLoading();

        try {
            const models = await api.getAvailableModels();

            mainContent.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">Model Yöneticisi</h2>
                        <button onclick="verifyAllModels()" class="btn btn-outline">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            Tümünü Doğrula
                        </button>
                    </div>

                    <!-- Category Tabs -->
                    <div class="border-b border-border">
                        <nav class="flex space-x-8 -mb-[2px]" aria-label="Model Categories">
                            <button onclick="switchModelTab('all')" 
                                class="model-tab ${currentModelTab === 'all' ? 'active' : ''} pb-4 px-1 font-medium text-sm relative z-10"
                                data-tab="all">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="layers" class="w-4 h-4"></i>
                                    Tümü
                                </span>
                            </button>
                            ${Object.entries(MODEL_CATEGORIES).map(([key, cat]) => `
                                <button onclick="switchModelTab('${key}')" 
                                    class="model-tab ${currentModelTab === key ? 'active' : ''} pb-4 px-1 font-medium text-sm relative z-10"
                                    data-tab="${key}">
                                    <span class="flex items-center gap-2">
                                        <i data-lucide="${cat.icon}" class="w-4 h-4"></i>
                                        ${cat.label_tr}
                                    </span>
                                </button>
                            `).join('')}
                        </nav>
                    </div>

                    <!-- Models Table -->
                    <div class="card">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Model</th>
                                        <th>Tür</th>
                                        <th>Boyut</th>
                                        <th>Durum</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="modelsTableBody">
                                    ${renderModelsTable(models, currentModelTab)}
                                </tbody>
                            </table>
                        </div>
                        ${getFilteredModels(models, currentModelTab).length === 0 ? `
                            <div class="p-8 text-center text-muted-foreground">
                                <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-4 opacity-50"></i>
                                <p>Bu kategoride model bulunmamaktadır.</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            lucide.createIcons();
        } catch (e) {
            mainContent.innerHTML = renderError(e.message);
        }
    }

    function getFilteredModels(models, tab) {
        if (tab === 'all') return models;
        return models.filter(m => m.type === tab);
    }

    function renderModelsTable(models, tab) {
        const filtered = getFilteredModels(models, tab);
        
        if (filtered.length === 0) return '';
        
        return filtered.map(m => {
            const cat = MODEL_CATEGORIES[m.type] || { icon: 'box', color: 'gray' };
            return `
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-${cat.color}-100 flex items-center justify-center">
                                <i data-lucide="${cat.icon}" class="w-5 h-5 text-${cat.color}-600"></i>
                            </div>
                            <div>
                                <p class="font-medium">${m.name}</p>
                                <p class="text-xs text-muted-foreground">${m.id}</p>
                                ${m.description ? `<p class="text-xs text-muted-foreground mt-1 max-w-md truncate">${m.description}</p>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-${cat.color} text-xs">${cat.label}</span>
                    </td>
                    <td class="text-muted-foreground">${formatFileSize(m.size_estimate_mb)}</td>
                    <td>
                        ${renderModelStatus(m)}
                    </td>
                    <td>
                        ${renderModelActions(m)}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function formatFileSize(mb) {
        if (mb >= 1000) {
            return (mb / 1000).toFixed(1) + ' GB';
        }
        return mb + ' MB';
    }

    window.switchModelTab = (tab) => {
        currentModelTab = tab;
        // Update URL hash with tab parameter
        const currentHash = window.location.hash;
        const baseRoute = currentHash.split('?')[0];
        window.location.hash = `${baseRoute}?tab=${tab}`;
        renderModels();
    };

    function renderModelStatus(model) {
        if (model.status === 'downloaded') {
            return `<span class="badge badge-success"><i data-lucide="check" class="w-3 h-3 mr-1"></i>Hazır</span>`;
        } else if (model.status === 'downloading') {
            return `<span class="badge badge-primary"><i data-lucide="loader-2" class="w-3 h-3 mr-1 animate-spin"></i>İndiriliyor</span>`;
        } else if (model.status === 'corrupted' || model.status === 'incomplete') {
            return `<span class="badge badge-destructive"><i data-lucide="alert-triangle" class="w-3 h-3 mr-1"></i>Bozuk</span>`;
        } else {
            return `<span class="badge badge-outline text-muted-foreground">İndirilmedi</span>`;
        }
    }

    function renderModelActions(model) {
        if (model.status === 'downloaded') {
            return `
                <button onclick="deleteModel('${model.id}')" class="p-2 rounded-lg hover:bg-destructive/10 hover:text-destructive transition-colors">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            `;
        } else if (model.status === 'downloading') {
            return `<span class="text-sm text-muted-foreground">İndiriliyor...</span>`;
        } else {
            return `
                <button onclick="startModelDownload('${model.id}', '${model.name}')" class="btn btn-primary btn-sm">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    İndir
                </button>
            `;
        }
    }

    window.startModelDownload = async (modelId, modelName) => {
        currentDownloadModelId = modelId;
        showDownloadModal(modelName);

        try {
            await api.startDownload(modelId);
            
            // Listen for download progress via WebSocket
            if (typeof wsClient !== 'undefined') {
                const unsubscribe = wsClient.on(`notification:download_progress`, (notification) => {
                    if (notification.data?.model_id === modelId) {
                        updateDownloadProgress(notification.data.progress);
                    }
                });
                
                const completeUnsubscribe = wsClient.on(`notification:download_completed`, (notification) => {
                    if (notification.data?.model_id === modelId) {
                        showDownloadSuccess();
                        unsubscribe();
                        completeUnsubscribe();
                        renderModels();
                    }
                });
                
                const failUnsubscribe = wsClient.on(`notification:download_failed`, (notification) => {
                    if (notification.data?.model_id === modelId) {
                        showDownloadError(notification.data.error || 'İndirme başarısız');
                        unsubscribe();
                        completeUnsubscribe();
                        failUnsubscribe();
                    }
                });
            }
        } catch (e) {
            showDownloadError(e.message);
        }
    };

    window.deleteModel = async (modelId) => {
        if (!confirm('Bu modeli silmek istediğinize emin misiniz?')) return;
        
        try {
            await api.deleteModelDownload(modelId);
            notificationSystem?.showToast('Model silindi', 'success');
            renderModels();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    window.verifyAllModels = async () => {
        notificationSystem?.showToast('Modeller doğrulanıyor...', 'info');
        renderModels();
    };

    // ==================== Queue ====================
    async function renderQueue() {
        mainContent.innerHTML = renderLoading();

        try {
            const [tasks, downloads] = await Promise.all([
                api.getQueueTasks(),
                api.getActiveDownloads()
            ]);

            const allTasks = [
                ...tasks.map(t => ({ ...t, itemType: 'task' })),
                ...downloads.map(d => ({ ...d, itemType: 'download', type: `Model: ${d.model_id}` }))
            ].sort((a, b) => new Date(b.created_at || b.started_at) - new Date(a.created_at || a.started_at));

            mainContent.innerHTML = `
                <div class="space-y-6">
                    <h2 class="text-2xl font-bold">İşlem Kuyruğu</h2>

                    <div class="card">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>İşlem</th>
                                        <th>Durum</th>
                                        <th>İlerleme</th>
                                        <th>Zaman</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${allTasks.length > 0 ? allTasks.map(t => `
                                        <tr>
                                            <td>
                                                <div>
                                                    <p class="font-medium">${t.type || 'İşlem'}</p>
                                                    <p class="text-xs text-muted-foreground">ID: ${t.id}</p>
                                                </div>
                                            </td>
                                            <td>${renderTaskStatus(t.status)}</td>
                                            <td>
                                                ${t.progress_pct !== undefined ? `
                                                    <div class="w-full max-w-xs">
                                                        <div class="progress h-2">
                                                            <div class="progress-bar" style="width: ${t.progress_pct}%"></div>
                                                        </div>
                                                        <span class="text-xs text-muted-foreground">%${t.progress_pct.toFixed(1)}</span>
                                                    </div>
                                                ` : '-'}
                                            </td>
                                            <td class="text-muted-foreground text-sm">
                                                ${t.created_at ? new Date(t.created_at).toLocaleString('tr-TR') : '-'}
                                            </td>
                                            <td>
                                                ${t.status === 'pending' || t.status === 'running' || t.status === 'downloading' ? `
                                                    <button onclick="cancelTask('${t.id}', ${t.itemType === 'download'})" class="p-2 rounded-lg hover:bg-destructive/10 hover:text-destructive transition-colors">
                                                        <i data-lucide="x" class="w-4 h-4"></i>
                                                    </button>
                                                ` : `
                                                    <button onclick="deleteTask('${t.id}', ${t.itemType === 'download'})" class="p-2 rounded-lg hover:bg-destructive/10 hover:text-destructive transition-colors">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                `}
                                            </td>
                                        </tr>
                                    `).join('') : `
                                        <tr>
                                            <td colspan="5" class="text-center py-8 text-muted-foreground">
                                                Kuyrukta işlem bulunmuyor
                                            </td>
                                        </tr>
                                    `}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        } catch (e) {
            mainContent.innerHTML = renderError(e.message);
        }
    }

    function renderTaskStatus(status) {
        const statusMap = {
            'pending': '<span class="badge badge-warning">Bekliyor</span>',
            'running': '<span class="badge badge-primary">Çalışıyor</span>',
            'downloading': '<span class="badge badge-primary">İndiriliyor</span>',
            'done': '<span class="badge badge-success">Tamamlandı</span>',
            'completed': '<span class="badge badge-success">Tamamlandı</span>',
            'failed': '<span class="badge badge-destructive">Başarısız</span>'
        };
        return statusMap[status] || `<span class="badge badge-outline">${status}</span>`;
    }

    window.cancelTask = async (id, isDownload) => {
        if (!confirm('Bu işlemi iptal etmek istediğinize emin misiniz?')) return;
        
        try {
            const endpoint = isDownload ? `/models/${id}?remove_files=true` : `/queue/${id}`;
            await api.request(endpoint, 'DELETE');
            notificationSystem?.showToast('İşlem iptal edildi', 'success');
            renderQueue();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    window.deleteTask = async (id, isDownload) => {
        if (!confirm('Bu işlemi silmek istediğinize emin misiniz?')) return;
        
        try {
            const endpoint = isDownload ? `/models/${id}?remove_files=false` : `/queue/${id}`;
            await api.request(endpoint, 'DELETE');
            notificationSystem?.showToast('İşlem silindi', 'success');
            renderQueue();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    // ==================== Settings ====================
    let currentSettingsTab = 'general';

    async function renderSettings() {
        mainContent.innerHTML = renderLoading();

        try {
            const [settings, hfTokenStatus, secretKeyStatus] = await Promise.all([
                api.getSettings(),
                api.getHFTokenStatus(),
                api.getSecretKeyStatus()
            ]);

            mainContent.innerHTML = `
                <div class="space-y-6">
                    <h2 class="text-2xl font-bold">Sistem Ayarları</h2>

                    <!-- Settings Tabs -->
                    <div class="border-b border-border">
                        <nav class="flex space-x-8 -mb-[2px]" aria-label="Settings Categories">
                            <button onclick="switchSettingsTab('general')" 
                                class="settings-tab ${currentSettingsTab === 'general' ? 'active' : ''} pb-4 px-1 font-medium text-sm relative z-10"
                                data-tab="general">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                    Genel
                                </span>
                            </button>
                            <button onclick="switchSettingsTab('api')" 
                                class="settings-tab ${currentSettingsTab === 'api' ? 'active' : ''} pb-4 px-1 font-medium text-sm relative z-10"
                                data-tab="api">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="shield" class="w-4 h-4"></i>
                                    API & Güvenlik
                                </span>
                            </button>
                            <button onclick="switchSettingsTab('services')" 
                                class="settings-tab ${currentSettingsTab === 'services' ? 'active' : ''} pb-4 px-1 font-medium text-sm relative z-10"
                                data-tab="services">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="cloud" class="w-4 h-4"></i>
                                    Harici Servisler
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- General Tab -->
                    <div id="settingsGeneralTab" class="${currentSettingsTab === 'general' ? '' : 'hidden'}">
                        <div class="card max-w-2xl">
                            <div class="card-header">
                                <h3 class="font-semibold">Genel Ayarlar</h3>
                            </div>
                            <div class="card-content space-y-4">
                                <div class="flex items-center justify-between py-3 border-b border-border">
                                    <div>
                                        <span class="text-muted-foreground">${t('app.version')}</span>
                                        <p class="text-xs text-muted-foreground">${t('app.version')}</p>
                                    </div>
                                    <span class="font-medium">1.0.0</span>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b border-border">
                                    <div>
                                        <span class="text-muted-foreground">${t('settings.language')}</span>
                                        <p class="text-xs text-muted-foreground">${t('settings.language.desc')}</p>
                                    </div>
                                    <select id="languageSelect" class="input w-40">
                                        <option value="tr" ${i18n.getLanguage() === 'tr' ? 'selected' : ''}>Türkçe</option>
                                        <option value="en" ${i18n.getLanguage() === 'en' ? 'selected' : ''}>English</option>
                                    </select>
                                </div>
                                <div class="flex items-center justify-between py-3">
                                    <div>
                                        <span class="text-muted-foreground">${t('settings.theme')}</span>
                                        <p class="text-xs text-muted-foreground">${t('settings.theme.desc')}</p>
                                    </div>
                                    <button id="themeToggle" class="btn btn-outline btn-icon" title="${document.documentElement.classList.contains('dark') ? t('settings.theme.light') : t('settings.theme.dark')}">
                                        <i data-lucide="${document.documentElement.classList.contains('dark') ? 'sun' : 'moon'}" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API & Security Tab -->
                    <div id="settingsApiTab" class="${currentSettingsTab === 'api' ? '' : 'hidden'}">
                        <div class="space-y-6">
                            <!-- Secret Key Card -->
                            <div class="card max-w-2xl">
                                <div class="card-header">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="key-round" class="w-5 h-5"></i>
                                        <h3 class="font-semibold">API Secret Key</h3>
                                    </div>
                                    <p class="text-sm text-muted-foreground mt-1">
                                        JWT token oluşturma ve API güvenliği için kullanılan gizli anahtar.
                                    </p>
                                </div>
                                <div class="card-content space-y-4">
                                    <div class="flex items-center gap-2 p-3 rounded-lg ${secretKeyStatus.has_key ? 'bg-success/10 text-success' : 'bg-destructive/10 text-destructive'}">
                                        <i data-lucide="${secretKeyStatus.has_key ? 'check-circle' : 'alert-circle'}" class="w-5 h-5"></i>
                                        <span class="text-sm font-medium">${secretKeyStatus.message}</span>
                                        ${secretKeyStatus.key_preview ? `<span class="ml-auto text-xs opacity-75">(${secretKeyStatus.key_preview})</span>` : ''}
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Yeni Secret Key</label>
                                        <div class="flex gap-2">
                                            <input 
                                                type="password" 
                                                id="secretKeyInput" 
                                                class="input flex-1" 
                                                placeholder="En az 16 karakter..."
                                                minlength="16"
                                            >
                                            <button id="toggleSecretKeyVisibility" class="btn btn-outline btn-icon" title="Göster/Gizle">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                            <button id="generateSecretKey" class="btn btn-outline btn-icon" title="Rastgele Oluştur">
                                                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                        <p class="text-xs text-muted-foreground">
                                            Güçlü bir key için en az 16 karakter kullanın veya rastgele oluştur butonuna tıklayın.
                                        </p>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <button id="saveSecretKey" class="btn btn-primary">
                                            <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                                            Secret Key Kaydet
                                        </button>
                                        ${secretKeyStatus.has_key ? `
                                            <button id="deleteSecretKey" class="btn btn-outline text-destructive hover:bg-destructive/10">
                                                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                                                Key'i Kaldır
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>

                            <!-- API Documentation Card -->
                            <div class="card max-w-2xl">
                                <div class="card-header">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="book-open" class="w-5 h-5"></i>
                                        <h3 class="font-semibold">API Dokümantasyonu</h3>
                                    </div>
                                    <p class="text-sm text-muted-foreground mt-1">
                                        Tüm API endpointlerini Swagger UI üzerinden inceleyin.
                                    </p>
                                </div>
                                <div class="card-content">
                                    <a href="http://localhost:5001/docs" target="_blank" class="btn btn-outline w-full">
                                        <i data-lucide="external-link" class="w-4 h-4 mr-2"></i>
                                        Swagger UI'yi Aç
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Services Tab -->
                    <div id="settingsServicesTab" class="${currentSettingsTab === 'services' ? '' : 'hidden'}">
                        <div class="card max-w-2xl">
                            <div class="card-header">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="key" class="w-5 h-5"></i>
                                    <h3 class="font-semibold">HuggingFace API Token</h3>
                                </div>
                                <p class="text-sm text-muted-foreground mt-1">
                                    Model indirme işlemlerinde rate limit sorununu önlemek için HuggingFace token ekleyin.
                                </p>
                            </div>
                            <div class="card-content space-y-4">
                                <div class="flex items-center gap-2 p-3 rounded-lg ${hfTokenStatus.has_token ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}">
                                    <i data-lucide="${hfTokenStatus.has_token ? 'check-circle' : 'alert-circle'}" class="w-5 h-5"></i>
                                    <span class="text-sm font-medium">${hfTokenStatus.message}</span>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">HuggingFace Token</label>
                                    <div class="flex gap-2">
                                        <input 
                                            type="password" 
                                            id="hfTokenInput" 
                                            class="input flex-1" 
                                            placeholder="hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                            value="${hfTokenStatus.has_token ? '••••••••••••••••••••••••••••••••' : ''}"
                                        >
                                        <button id="toggleTokenVisibility" class="btn btn-outline btn-icon" title="Göster/Gizle">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        Token <a href="https://huggingface.co/settings/tokens" target="_blank" class="text-primary hover:underline">HuggingFace Settings</a> sayfasından alınabilir.
                                    </p>
                                </div>
                                
                                <div class="flex gap-2">
                                    <button id="saveHFToken" class="btn btn-primary">
                                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                                        Token Kaydet
                                    </button>
                                    ${hfTokenStatus.has_token ? `
                                        <button id="deleteHFToken" class="btn btn-outline text-destructive hover:bg-destructive/10">
                                            <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                                            Tokeni Kaldır
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Setup all handlers
            setupHFTokenHandlers(hfTokenStatus.has_token);
            setupSecretKeyHandlers(secretKeyStatus.has_key);
            setupGeneralSettingsHandlers();

            lucide.createIcons();
        } catch (e) {
            mainContent.innerHTML = renderError(e.message);
        }
    }

    window.switchSettingsTab = (tab) => {
        currentSettingsTab = tab;
        // Update URL hash with tab parameter
        const currentHash = window.location.hash;
        const baseRoute = currentHash.split('?')[0];
        window.location.hash = `${baseRoute}?tab=${tab}`;
        renderSettings();
    };

    function setupHFTokenHandlers(hasToken) {
        // Toggle token visibility
        const toggleBtn = document.getElementById('toggleTokenVisibility');
        const tokenInput = document.getElementById('hfTokenInput');
        
        if (toggleBtn && tokenInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = tokenInput.type === 'password';
                tokenInput.type = isPassword ? 'text' : 'password';
                toggleBtn.innerHTML = `<i data-lucide="${isPassword ? 'eye-off' : 'eye'}" class="w-4 h-4"></i>`;
                lucide.createIcons();
            });
        }

        // Save token
        const saveBtn = document.getElementById('saveHFToken');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                const token = tokenInput.value.trim();
                if (!token || token === '••••••••••••••••••••••••••••••••') {
                    notificationSystem?.showToast('Lütfen geçerli bir token girin', 'error');
                    return;
                }
                
                if (!token.startsWith('hf_')) {
                    notificationSystem?.showToast('Token "hf_" ile başlamalıdır', 'error');
                    return;
                }

                try {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Kaydediliyor...';
                    lucide.createIcons();

                    await api.updateHFToken(token);
                    notificationSystem?.showToast('HuggingFace token başarıyla kaydedildi', 'success');
                    
                    // Re-render settings to update UI
                    await renderSettings();
                } catch (e) {
                    notificationSystem?.showToast('Hata: ' + e.message, 'error');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i>Token Kaydet';
                    lucide.createIcons();
                }
            });
        }

        // Delete token
        const deleteBtn = document.getElementById('deleteHFToken');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async () => {
                if (!confirm('HuggingFace tokeni kaldırmak istediğinize emin misiniz?')) {
                    return;
                }

                try {
                    deleteBtn.disabled = true;
                    await api.deleteHFToken();
                    notificationSystem?.showToast('HuggingFace token kaldırıldı', 'success');
                    await renderSettings();
                } catch (e) {
                    notificationSystem?.showToast('Hata: ' + e.message, 'error');
                    deleteBtn.disabled = false;
                }
            });
        }
    }

    function setupSecretKeyHandlers(hasKey) {
        // Toggle visibility
        const toggleBtn = document.getElementById('toggleSecretKeyVisibility');
        const keyInput = document.getElementById('secretKeyInput');
        
        if (toggleBtn && keyInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = keyInput.type === 'password';
                keyInput.type = isPassword ? 'text' : 'password';
                toggleBtn.innerHTML = `<i data-lucide="${isPassword ? 'eye-off' : 'eye'}" class="w-4 h-4"></i>`;
                lucide.createIcons();
            });
        }

        // Generate random key
        const generateBtn = document.getElementById('generateSecretKey');
        if (generateBtn && keyInput) {
            generateBtn.addEventListener('click', () => {
                const array = new Uint8Array(32);
                crypto.getRandomValues(array);
                const randomKey = btoa(String.fromCharCode(...array)).replace(/[^a-zA-Z0-9]/g, '').substring(0, 32);
                keyInput.value = randomKey;
                keyInput.type = 'text';
                toggleBtn.innerHTML = `<i data-lucide="eye-off" class="w-4 h-4"></i>`;
                lucide.createIcons();
            });
        }

        // Save key
        const saveBtn = document.getElementById('saveSecretKey');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                const key = keyInput.value.trim();
                if (!key) {
                    notificationSystem?.showToast('Lütfen bir secret key girin', 'error');
                    return;
                }
                
                if (key.length < 16) {
                    notificationSystem?.showToast('Secret key en az 16 karakter olmalıdır', 'error');
                    return;
                }

                try {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Kaydediliyor...';
                    lucide.createIcons();

                    await api.updateSecretKey(key);
                    notificationSystem?.showToast('Secret key başarıyla kaydedildi ve şifrelendi', 'success');
                    await renderSettings();
                } catch (e) {
                    notificationSystem?.showToast('Hata: ' + e.message, 'error');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i>Secret Key Kaydet';
                    lucide.createIcons();
                }
            });
        }

        // Delete key
        const deleteBtn = document.getElementById('deleteSecretKey');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async () => {
                if (!confirm('Secret key\'i kaldırmak istediğinize emin misiniz? Bu işlem tüm oturumları sonlandıracaktır.')) {
                    return;
                }

                try {
                    deleteBtn.disabled = true;
                    await api.deleteSecretKey();
                    notificationSystem?.showToast('Secret key kaldırıldı', 'success');
                    await renderSettings();
                } catch (e) {
                    notificationSystem?.showToast('Hata: ' + e.message, 'error');
                    deleteBtn.disabled = false;
                }
            });
        }
    }

    function setupGeneralSettingsHandlers() {
        // Theme toggle
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', async () => {
                document.documentElement.classList.toggle('dark');
                const isDark = document.documentElement.classList.contains('dark');
                const newTheme = isDark ? 'dark' : 'light';
                
                // Save to API
                const lang = i18n.getLanguage();
                const success = await i18n.savePreferences(newTheme, lang);
                
                if (success) {
                    localStorage.setItem('vc_theme', newTheme);
                    themeBtn.innerHTML = `<i data-lucide="${isDark ? 'sun' : 'moon'}" class="w-4 h-4"></i>`;
                    themeBtn.title = t(isDark ? 'settings.theme.light' : 'settings.theme.dark');
                    lucide.createIcons();
                    notificationSystem?.showToast(t('msg.saved'), 'success');
                } else {
                    notificationSystem?.showToast(t('msg.error'), 'error');
                }
            });
            
            // Set initial icon based on current theme
            const isDark = document.documentElement.classList.contains('dark');
            themeBtn.innerHTML = `<i data-lucide="${isDark ? 'sun' : 'moon'}" class="w-4 h-4"></i>`;
        }

        // Language select
        const langSelect = document.getElementById('languageSelect');
        if (langSelect) {
            langSelect.value = i18n.getLanguage();
            
            langSelect.addEventListener('change', async (e) => {
                const newLang = e.target.value;
                const isDark = document.documentElement.classList.contains('dark');
                const theme = isDark ? 'dark' : 'light';
                
                // Save to API
                const success = await i18n.savePreferences(theme, newLang);
                
                if (success) {
                    i18n.setLanguage(newLang);
                    localStorage.setItem('vc_language', newLang);
                    notificationSystem?.showToast(t('msg.saved') + '. ' + (newLang === 'tr' ? 'Sayfa yenilendiğinde aktif olacak.' : 'Will be active after page refresh.'), 'success');
                } else {
                    notificationSystem?.showToast(t('msg.error'), 'error');
                }
            });
        }
    }

    // ==================== STT ====================
    async function renderStt() {
        mainContent.innerHTML = `
            <div class="space-y-6">
                <h2 class="text-2xl font-bold">Sesten Metne (STT)</h2>

                <div class="card max-w-2xl">
                    <div class="card-content space-y-6">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Ses Dosyası</label>
                            <input type="file" id="sttFile" accept="audio/*" class="input">
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Dil</label>
                            <select id="sttLanguage" class="input">
                                <option value="">Otomatik Algıla</option>
                                <option value="tr">Türkçe</option>
                                <option value="en">English</option>
                            </select>
                        </div>

                        <button onclick="submitStt()" class="btn btn-primary w-full">
                            <i data-lucide="mic" class="w-4 h-4"></i>
                            Transkripsiyon Başlat
                        </button>
                    </div>
                </div>
            </div>
        `;
        lucide.createIcons();
    }

    window.submitStt = async () => {
        const file = document.getElementById('sttFile').files[0];
        const language = document.getElementById('sttLanguage').value;

        if (!file) {
            notificationSystem?.showToast('Lütfen bir ses dosyası seçin', 'warning');
            return;
        }

        const btn = document.querySelector('button[onclick="submitStt()"]');
        btn.disabled = true;
        btn.innerHTML = '<div class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin inline mr-2"></div> İşleniyor...';

        try {
            const result = await api.transcribeSttUpload(file, language || null, 'small', 'whisper');
            notificationSystem?.showToast(`Transkripsiyon başlatıldı (Görev #${result.task_id})`, 'success');
            document.getElementById('sttFile').value = '';
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="mic" class="w-4 h-4"></i> Transkripsiyon Başlat';
            lucide.createIcons();
        }
    };

    // ==================== Utility Functions ====================
    function renderLoading() {
        return `
            <div class="flex items-center justify-center h-64">
                <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
            </div>
        `;
    }

    function renderError(message) {
        return `
            <div class="alert alert-error">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
                <div>
                    <p class="font-medium">Bir hata oluştu</p>
                    <p class="text-sm mt-1">${message}</p>
                </div>
            </div>
        `;
    }

    function renderEmptyState(title, description, icon, action) {
        return `
            <div class="card">
                <div class="card-content text-center py-12">
                    <div class="w-16 h-16 rounded-xl bg-muted flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="${icon}" class="w-8 h-8 text-muted-foreground"></i>
                    </div>
                    <h3 class="font-semibold text-lg">${title}</h3>
                    <p class="text-muted-foreground mt-1">${description}</p>
                    ${action ? `
                        <button onclick="${action}" class="btn btn-primary mt-6">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Yeni Oluştur
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    // ==================== Download Modal Functions ====================
    function showDownloadModal(modelName) {
        document.getElementById('downloadModelName').textContent = modelName;
        document.getElementById('downloadStatus').textContent = 'İndirme başlatılıyor...';
        document.getElementById('downloadPercent').textContent = '0%';
        document.getElementById('downloadProgressBar').style.width = '0%';
        document.getElementById('downloadFiles').innerHTML = '';
        
        document.getElementById('downloadProgressContent').classList.remove('hidden');
        document.getElementById('downloadError').classList.add('hidden');
        document.getElementById('downloadSuccess').classList.add('hidden');
        document.getElementById('downloadModal').classList.remove('hidden');
    }

    window.closeDownloadModal = () => {
        document.getElementById('downloadModal').classList.add('hidden');
    };

    function updateDownloadProgress(progress) {
        document.getElementById('downloadPercent').textContent = progress.toFixed(1) + '%';
        document.getElementById('downloadProgressBar').style.width = progress + '%';
        document.getElementById('downloadStatus').textContent = 'İndiriliyor...';
    }

    function showDownloadSuccess() {
        document.getElementById('downloadProgressContent').classList.add('hidden');
        document.getElementById('downloadSuccess').classList.remove('hidden');
    }

    function showDownloadError(message) {
        document.getElementById('downloadProgressContent').classList.add('hidden');
        document.getElementById('downloadErrorMessage').textContent = message;
        document.getElementById('downloadError').classList.remove('hidden');
    }

    window.retryDownload = () => {
        if (currentDownloadModelId) {
            const modelName = document.getElementById('downloadModelName').textContent;
            startModelDownload(currentDownloadModelId, modelName);
        }
    };

    // ==================== Tag Manager Modal ====================
    window.showTagManagerModal = async () => {
        try {
            const tags = await api.getTags();
            
            // Create modal backdrop
            const modal = document.createElement('div');
            modal.id = 'tagManagerModal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm';
            modal.innerHTML = `
                <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-lg mx-4 max-h-[80vh] flex flex-col">
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <h3 class="text-lg font-semibold">Etiket Yönetimi</h3>
                        <button onclick="closeTagManagerModal()" class="p-1 rounded-lg hover:bg-accent transition-colors">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                    
                    <div class="p-4 space-y-4 overflow-y-auto">
                        <div class="flex gap-3">
                            <input type="text" id="modalNewTagName" placeholder="Etiket adı" class="input flex-1">
                            <input type="color" id="modalNewTagColor" value="#3b82f6" class="w-12 h-10 rounded-lg border border-input cursor-pointer bg-transparent">
                            <button onclick="createTagFromModal()" class="btn btn-primary">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                            </button>
                        </div>

                        <div id="modalTagList" class="flex flex-wrap gap-2">
                            ${tags.map(t => `
                                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium text-white" style="background-color: ${t.color}">
                                    ${t.name}
                                    <button onclick="deleteTagFromModal(${t.id})" class="hover:opacity-70">
                                        <i data-lucide="x" class="w-3 h-3"></i>
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    
                    <div class="p-4 border-t border-border flex justify-end">
                        <button onclick="closeTagManagerModal()" class="btn btn-outline">Kapat</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            lucide.createIcons();
            
            // Focus on input
            document.getElementById('modalNewTagName').focus();
            
            // Handle Enter key
            document.getElementById('modalNewTagName').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    createTagFromModal();
                }
            });
            
            // Close on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeTagManagerModal();
                }
            });
            
            // Close on Escape key
            document.addEventListener('keydown', handleTagManagerEscape);
            
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    window.closeTagManagerModal = () => {
        const modal = document.getElementById('tagManagerModal');
        if (modal) {
            modal.remove();
            document.removeEventListener('keydown', handleTagManagerEscape);
        }
    };

    function handleTagManagerEscape(e) {
        if (e.key === 'Escape') {
            closeTagManagerModal();
        }
    }

    window.createTagFromModal = async () => {
        const nameInput = document.getElementById('modalNewTagName');
        const colorInput = document.getElementById('modalNewTagColor');
        const name = nameInput.value.trim();
        const color = colorInput.value;
        
        if (!name) {
            notificationSystem?.showToast('Etiket adı girin', 'warning');
            return;
        }

        try {
            await api.createTag({ name, color });
            notificationSystem?.showToast('Etiket oluşturuldu', 'success');
            nameInput.value = '';
            nameInput.focus();
            // Refresh modal content
            await refreshTagManagerModal();
            // Also refresh the TTS form tag select
            await refreshTtsTagSelect();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    window.deleteTagFromModal = async (id) => {
        if (!confirm('Bu etiketi silmek istediğinize emin misiniz?')) return;
        
        try {
            await api.deleteTag(id);
            notificationSystem?.showToast('Etiket silindi', 'success');
            await refreshTagManagerModal();
            await refreshTtsTagSelect();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    async function refreshTagManagerModal() {
        const tags = await api.getTags();
        const tagList = document.getElementById('modalTagList');
        if (tagList) {
            tagList.innerHTML = tags.map(t => `
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium text-white" style="background-color: ${t.color}">
                    ${t.name}
                    <button onclick="deleteTagFromModal(${t.id})" class="hover:opacity-70">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>
                </div>
            `).join('');
            lucide.createIcons();
        }
    }

    async function refreshTtsTagSelect() {
        // If we're on TTS page, refresh the tag select
        if (window.location.hash.includes('tts')) {
            const tags = await api.getTags();
            const select = document.getElementById('ttsTags');
            if (select && select.tomselect) {
                const currentValue = select.tomselect.getValue();
                select.tomselect.clearOptions();
                tags.forEach(t => {
                    select.tomselect.addOption({ value: t.id, text: t.name });
                });
                select.tomselect.setValue(currentValue);
            }
        }
    }

    // ==================== Create Tag Modal (for inline creation) ====================
    window.showCreateTagModal = (tagName) => {
        // Close any existing modal
        const existingModal = document.getElementById('createTagModal');
        if (existingModal) existingModal.remove();
        
        const modal = document.createElement('div');
        modal.id = 'createTagModal';
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm';
        modal.innerHTML = `
            <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-md mx-4">
                <div class="p-4 border-b border-border">
                    <h3 class="text-lg font-semibold">Yeni Etiket Oluştur</h3>
                </div>
                
                <div class="p-4 space-y-4">
                    <p class="text-sm text-muted-foreground">
                        "<span class="font-medium text-foreground">${tagName}</span>" etiketi mevcut değil. Oluşturmak istiyor musunuz?
                    </p>
                    
                    <div class="flex items-center gap-3">
                        <label class="text-sm font-medium whitespace-nowrap">Renk:</label>
                        <input type="color" id="quickTagColor" value="#3b82f6" class="w-12 h-10 rounded-lg border border-input cursor-pointer bg-transparent">
                    </div>
                </div>
                
                <div class="p-4 border-t border-border flex justify-end gap-2">
                    <button id="cancelCreateTag" class="btn btn-outline">İptal</button>
                    <button id="confirmCreateTag" class="btn btn-primary">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                        Oluştur
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        lucide.createIcons();
        
        // Focus on create button for Enter key
        const confirmBtn = document.getElementById('confirmCreateTag');
        const cancelBtn = document.getElementById('cancelCreateTag');
        const colorInput = document.getElementById('quickTagColor');
        
        confirmBtn.focus();
        
        // Handle button clicks
        confirmBtn.addEventListener('click', async () => {
            await createQuickTag(tagName, colorInput.value);
            closeCreateTagModal();
        });
        
        cancelBtn.addEventListener('click', () => {
            closeCreateTagModal();
        });
        
        // Handle Enter (create) and Escape (cancel)
        const handleKeyDown = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmBtn.click();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeCreateTagModal();
            }
        };
        
        document.addEventListener('keydown', handleKeyDown);
        
        // Store handler for cleanup
        modal.keyHandler = handleKeyDown;
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeCreateTagModal();
            }
        });
    };

    window.closeCreateTagModal = () => {
        const modal = document.getElementById('createTagModal');
        if (modal) {
            document.removeEventListener('keydown', modal.keyHandler);
            modal.remove();
        }
    };

    async function createQuickTag(name, color) {
        try {
            const newTag = await api.createTag({ name, color });
            notificationSystem?.showToast(`"${name}" etiketi oluşturuldu`, 'success');
            
            // Add to current TTS form if present
            const select = document.getElementById('ttsTags');
            if (select && select.tomselect) {
                select.tomselect.addOption({ value: newTag.id, text: newTag.name });
                select.tomselect.addItem(newTag.id);
                select.tomselect.refreshOptions();
            }
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    }

    // Legacy functions for backward compatibility
    window.showTagManager = window.showTagManagerModal;

    // Listen for TTS completion to refresh history
    if (typeof wsClient !== 'undefined') {
        wsClient.on('notification:task_completed', (notification) => {
            if (notification.data?.task_type === 'tts' && window.location.hash.includes('tts')) {
                // Refresh history if on history tab
                if (window.location.hash.includes('historyTab')) {
                    loadTtsHistory();
                }
            }
        });
    }

    // Start the app
    init();
});
