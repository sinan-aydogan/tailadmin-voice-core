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
        
        // Update active sidebar item
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.route === page) {
                item.classList.add('active');
            }
        });

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
                                        <label class="text-sm font-medium">Ses Profili *</label>
                                        <select id="ttsProfile" required class="input">
                                            <option value="">Profil seçin...</option>
                                            ${profiles.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                                        </select>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">TTS Motoru</label>
                                        <select id="ttsEngine" class="input" onchange="toggleMusicGenDuration()">
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
                                    
                                    <!-- MusicGen Duration Selector -->
                                    <div id="musicGenDurationContainer" class="space-y-2" style="display: none;">
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
                                    <label class="text-sm font-medium">Etiketler</label>
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

            // Initialize Tom Select for tags
            if (document.getElementById('ttsTags')) {
                new TomSelect('#ttsTags', {
                    plugins: ['remove_button'],
                    placeholder: 'Etiket seçin...'
                });
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

    window.toggleMusicGenDuration = () => {
        const engine = document.getElementById('ttsEngine')?.value || '';
        const durationContainer = document.getElementById('musicGenDurationContainer');
        
        if (engine.startsWith('musicgen')) {
            durationContainer.style.display = 'block';
        } else {
            durationContainer.style.display = 'none';
        }
    };

    window.submitTts = async () => {
        const profileId = document.getElementById('ttsProfile').value;
        const text = document.getElementById('ttsText').value;
        const engine = document.getElementById('ttsEngine')?.value || 'xtts';
        
        if (!profileId || !text) {
            notificationSystem?.showToast('Lütfen profil ve metin girin', 'warning');
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
                profile_id: parseInt(profileId),
                engine,
                tag_ids: tagIds.map(id => parseInt(id))
            };
            
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
                                <tbody>
                                    ${models.map(m => `
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                                        <i data-lucide="${m.type === 'tts' ? 'volume-2' : 'mic'}" class="w-5 h-5 text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium">${m.name}</p>
                                                        <p class="text-xs text-muted-foreground">${m.id}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary text-xs">${m.type.toUpperCase()}</span>
                                            </td>
                                            <td class="text-muted-foreground">${m.size_estimate_mb} MB</td>
                                            <td>
                                                ${renderModelStatus(m)}
                                            </td>
                                            <td>
                                                ${renderModelActions(m)}
                                            </td>
                                        </tr>
                                    `).join('')}
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
    async function renderSettings() {
        mainContent.innerHTML = renderLoading();

        try {
            const [settings, hfTokenStatus] = await Promise.all([
                api.getSettings(),
                api.getHFTokenStatus()
            ]);

            mainContent.innerHTML = `
                <div class="space-y-6">
                    <h2 class="text-2xl font-bold">Sistem Ayarları</h2>

                    <!-- HuggingFace Token Card -->
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

                    <div class="card max-w-2xl">
                        <div class="card-header">
                            <h3 class="font-semibold">Genel Ayarlar</h3>
                        </div>
                        <div class="card-content space-y-4">
                            ${settings.map(s => `
                                <div class="flex items-center justify-between py-3 border-b border-border last:border-0">
                                    <div>
                                        <p class="font-medium">${s.description || s.key}</p>
                                        <p class="text-sm text-muted-foreground">${s.key}</p>
                                    </div>
                                    <code class="px-2 py-1 rounded bg-muted text-sm">${s.value}</code>
                                </div>
                            `).join('')}
                        </div>
                    </div>

                    <div class="card max-w-2xl">
                        <div class="card-header">
                            <h3 class="font-semibold">Sistem Bilgisi</h3>
                        </div>
                        <div class="card-content space-y-4">
                            <div class="flex items-center justify-between py-3 border-b border-border">
                                <span class="text-muted-foreground">Versiyon</span>
                                <span class="font-medium">1.0.0</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-border">
                                <span class="text-muted-foreground">WebSocket Bağlantısı</span>
                                <span id="wsStatus" class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-muted-foreground"></span>
                                    <span class="text-sm">Kontrol ediliyor...</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Setup HF Token handlers
            setupHFTokenHandlers(hfTokenStatus.has_token);

            // Update WebSocket status
            setTimeout(() => {
                const wsStatus = document.getElementById('wsStatus');
                if (wsStatus) {
                    const isConnected = wsClient?.isConnected;
                    wsStatus.innerHTML = `
                        <span class="w-2 h-2 rounded-full ${isConnected ? 'bg-success' : 'bg-destructive'}"></span>
                        <span class="text-sm">${isConnected ? 'Bağlı' : 'Bağlı değil'}</span>
                    `;
                }
            }, 500);

            lucide.createIcons();
        } catch (e) {
            mainContent.innerHTML = renderError(e.message);
        }
    }

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

    // ==================== Tag Manager ====================
    window.showTagManager = async () => {
        try {
            const tags = await api.getTags();
            
            mainContent.innerHTML = `
                <div class="max-w-2xl mx-auto">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold">Etiket Yönetimi</h2>
                        <button onclick="renderTts()" class="btn btn-outline">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            Geri Dön
                        </button>
                    </div>

                    <div class="card">
                        <div class="card-content space-y-4">
                            <div class="flex gap-3">
                                <input type="text" id="newTagName" placeholder="Etiket adı" class="input flex-1">
                                <input type="color" id="newTagColor" value="#3b82f6" class="w-12 h-10 rounded-lg border border-input cursor-pointer">
                                <button onclick="createTag()" class="btn btn-primary">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    Ekle
                                </button>
                            </div>

                            <div class="flex flex-wrap gap-2 pt-4">
                                ${tags.map(t => `
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium text-white" style="background-color: ${t.color}">
                                        ${t.name}
                                        <button onclick="deleteTag(${t.id})" class="hover:opacity-70">
                                            <i data-lucide="x" class="w-3 h-3"></i>
                                        </button>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    window.createTag = async () => {
        const name = document.getElementById('newTagName').value;
        const color = document.getElementById('newTagColor').value;
        
        if (!name) return;

        try {
            await api.createTag({ name, color });
            notificationSystem?.showToast('Etiket oluşturuldu', 'success');
            showTagManager();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

    window.deleteTag = async (id) => {
        if (!confirm('Bu etiketi silmek istediğinize emin misiniz?')) return;
        
        try {
            await api.deleteTag(id);
            notificationSystem?.showToast('Etiket silindi', 'success');
            showTagManager();
        } catch (e) {
            notificationSystem?.showToast('Hata: ' + e.message, 'error');
        }
    };

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
