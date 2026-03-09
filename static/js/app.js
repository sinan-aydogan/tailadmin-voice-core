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
        'playlists': { title: 'İş Listeleri', render: () => window.renderPlaylists() },
        'settings': { title: 'Ayarlar', render: renderSettings }
    };

    function handleRoute() {
        const hash = window.location.hash || '#dashboard';
        const page = hash.substring(1).split('?')[0];
        
        // Clear playlist refresh interval when leaving playlists page
        if (page !== 'playlists' && window.playlistRefreshInterval) {
            clearInterval(window.playlistRefreshInterval);
            window.playlistRefreshInterval = null;
        }
        
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
        let currentTab = 'upload'; // 'upload' or 'record'
        let recordedBlob = null;
        let mediaRecorder = null;
        let audioChunks = [];
        let isRecording = false;
        
        const renderForm = () => {
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
                            
                            <!-- Tabs -->
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Referans Ses</label>
                                <div class="flex gap-2 p-1 bg-muted rounded-lg">
                                    <button type="button" id="tabUpload" class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors ${currentTab === 'upload' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}">
                                        <i data-lucide="upload" class="w-4 h-4 inline mr-2"></i>
                                        Dosya Yükle
                                    </button>
                                    <button type="button" id="tabRecord" class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors ${currentTab === 'record' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}">
                                        <i data-lucide="mic" class="w-4 h-4 inline mr-2"></i>
                                        Ses Kaydet
                                    </button>
                                </div>
                                
                                <!-- Upload Tab Content -->
                                <div id="uploadContent" class="${currentTab === 'upload' ? '' : 'hidden'}">
                                    <div id="profileAudioUpload"></div>
                                </div>
                                
                                <!-- Record Tab Content -->
                                <div id="recordContent" class="${currentTab === 'record' ? '' : 'hidden'}">
                                    <div class="flex flex-col items-center gap-4 p-6 border-2 border-dashed border-border rounded-lg">
                                        <!-- Record Button -->
                                        <button type="button" id="recordBtn" class="relative w-24 h-24 rounded-full bg-primary hover:bg-primary/90 transition-all flex items-center justify-center group">
                                            <div id="recordPulse" class="absolute inset-0 rounded-full bg-primary opacity-0"></div>
                                            <i data-lucide="mic" class="w-10 h-10 text-primary-foreground relative z-10"></i>
                                        </button>
                                        <p id="recordStatus" class="text-sm text-muted-foreground">Kaydetmek için tıklayın</p>
                                        
                                        <!-- Audio Preview -->
                                        <div id="audioPreview" class="hidden w-full">
                                            <audio id="recordedAudio" controls class="w-full"></audio>
                                            <button type="button" id="deleteRecording" class="mt-2 text-sm text-destructive hover:underline">
                                                <i data-lucide="trash-2" class="w-4 h-4 inline mr-1"></i>
                                                Kaydı Sil
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Sample Text Collapsible -->
                                    <div class="mt-4 border border-border rounded-lg overflow-hidden">
                                        <button type="button" id="toggleSampleText" class="w-full px-4 py-3 flex items-center justify-between bg-muted/50 hover:bg-muted transition-colors">
                                            <span class="text-sm font-medium">Örnek Ses Profili Metni</span>
                                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" id="sampleTextIcon"></i>
                                        </button>
                                        <div id="sampleTextContent" class="hidden px-4 py-3 text-sm text-muted-foreground bg-card">
                                            <p class="mb-2">Ses kaydı için aşağıdaki metni okuyabilirsiniz:</p>
                                            <blockquote class="border-l-2 border-primary pl-4 italic">
                                                "Merhaba, benim adım... Bu ses kaydı, yapay zeka tarafından sesimi taklit etmek için kullanılacak. 
                                                Ses tonumun doğal ve net bir şekilde duyulması için sessiz bir ortamda kayıt yapıyorum. 
                                                Türkçe karakterleri doğru telaffuz ettiğimden emin olmak için şu cümleleri okuyorum: 
                                                ç, ğ, ı, ö, ş, ü harfleri Türk alfabesinin önemli karakterleridir."
                                            </blockquote>
                                        </div>
                                    </div>
                                </div>
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
            setupEventListeners();
        };
        
        const setupEventListeners = () => {
            // Tab switching
            document.getElementById('tabUpload')?.addEventListener('click', () => {
                currentTab = 'upload';
                renderForm();
            });
            document.getElementById('tabRecord')?.addEventListener('click', () => {
                currentTab = 'record';
                renderForm();
            });
            
            // Initialize file upload component if on upload tab
            let fileUpload = null;
            if (currentTab === 'upload') {
                fileUpload = createFileUploadComponent('profileAudioUpload', {
                    accept: 'audio/*',
                    maxSize: 10 * 1024 * 1024,
                    onChange: (file) => {
                        console.log('Selected file:', file?.name);
                    }
                });
            }
            
            // Sample text toggle
            document.getElementById('toggleSampleText')?.addEventListener('click', () => {
                const content = document.getElementById('sampleTextContent');
                const icon = document.getElementById('sampleTextIcon');
                content.classList.toggle('hidden');
                icon.style.transform = content.classList.contains('hidden') ? '' : 'rotate(180deg)';
            });
            
            // Recording functionality
            const recordBtn = document.getElementById('recordBtn');
            const recordPulse = document.getElementById('recordPulse');
            const recordStatus = document.getElementById('recordStatus');
            const audioPreview = document.getElementById('audioPreview');
            const recordedAudio = document.getElementById('recordedAudio');
            const deleteRecording = document.getElementById('deleteRecording');
            
            recordBtn?.addEventListener('click', async () => {
                if (!isRecording) {
                    // Start recording
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        mediaRecorder = new MediaRecorder(stream);
                        audioChunks = [];
                        
                        mediaRecorder.ondataavailable = (event) => {
                            audioChunks.push(event.data);
                        };
                        
                        mediaRecorder.onstop = () => {
                            recordedBlob = new Blob(audioChunks, { type: 'audio/wav' });
                            const audioUrl = URL.createObjectURL(recordedBlob);
                            recordedAudio.src = audioUrl;
                            audioPreview.classList.remove('hidden');
                            recordBtn.classList.add('hidden');
                            recordStatus.textContent = 'Kayıt tamamlandı';
                        };
                        
                        mediaRecorder.start();
                        isRecording = true;
                        
                        // Update UI
                        recordPulse.classList.remove('opacity-0');
                        recordPulse.classList.add('animate-ping', 'opacity-75');
                        recordBtn.classList.add('bg-red-500', 'hover:bg-red-600');
                        recordBtn.classList.remove('bg-primary', 'hover:bg-primary/90');
                        recordStatus.textContent = 'Kaydediliyor... (Durdurmak için tıklayın)';
                        
                    } catch (err) {
                        notificationSystem?.showToast('Mikrofon erişimi reddedildi: ' + err.message, 'error');
                    }
                } else {
                    // Stop recording
                    mediaRecorder?.stop();
                    mediaRecorder?.stream.getTracks().forEach(track => track.stop());
                    isRecording = false;
                    
                    // Update UI
                    recordPulse.classList.add('opacity-0');
                    recordPulse.classList.remove('animate-ping', 'opacity-75');
                    recordBtn.classList.remove('bg-red-500', 'hover:bg-red-600');
                    recordBtn.classList.add('bg-primary', 'hover:bg-primary/90');
                }
            });
            
            deleteRecording?.addEventListener('click', () => {
                recordedBlob = null;
                audioPreview.classList.add('hidden');
                recordBtn.classList.remove('hidden');
                recordStatus.textContent = 'Kaydetmek için tıklayın';
                recordedAudio.src = '';
            });
            
            // Form submission
            document.getElementById('createProfileForm')?.addEventListener('submit', async (e) => {
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

                    // Upload audio based on selected tab
                    if (currentTab === 'upload' && fileUpload?.getFile()) {
                        await api.uploadProfileAudio(profile.id, fileUpload.getFile());
                    } else if (currentTab === 'record' && recordedBlob) {
                        const file = new File([recordedBlob], 'recording.wav', { type: 'audio/wav' });
                        await api.uploadProfileAudio(profile.id, file);
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
        
        renderForm();
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
                                <div class="flex items-start justify-between gap-4 mb-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                                            <span class="badge badge-secondary text-xs">${h.engine?.toUpperCase()}</span>
                                            ${h.profile_name ? `<span class="badge badge-primary text-xs"><i data-lucide="user" class="w-3 h-3 inline mr-1"></i>${h.profile_name}</span>` : ''}
                                            ${h.tags?.map(t => `<span class="badge text-xs" style="background-color: ${t.color}; color: white;">${t.name}</span>`).join('') || ''}
                                        </div>
                                        <p class="text-sm text-foreground line-clamp-2">${h.text}</p>
                                        <p class="text-xs text-muted-foreground mt-2">
                                            ${new Date(h.created_at).toLocaleString('tr-TR')}
                                        </p>
                                    </div>
                                    <button onclick="deleteTtsOutput(${h.id})" class="p-2 rounded-lg hover:bg-destructive/10 hover:text-destructive transition-colors flex-shrink-0">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <div class="w-full">
                                    <audio controls class="h-10 w-full">
                                        <source src="${API_BASE}/${h.output_path}" type="audio/wav">
                                    </audio>
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
                            <div id="sttAudioUpload"></div>
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

        // Initialize file upload component
        window.sttFileUpload = createFileUploadComponent('sttAudioUpload', {
            accept: 'audio/*',
            maxSize: 50 * 1024 * 1024, // 50MB for STT
            placeholder: 'Ses dosyası yüklemek için tıklayın veya sürükleyin',
            hint: 'MP3, WAV, M4A desteklenir. Maksimum: 50MB'
        });
    }

    window.submitStt = async () => {
        const file = window.sttFileUpload?.getFile();
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
            window.sttFileUpload?.clear();
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

                </div>
            </div>
        `;
    }

    // ==================== File Upload Component ====================
    window.createFileUploadComponent = function(containerId, options = {}) {
        const {
            accept = '*/*',
            maxSize = 10 * 1024 * 1024, // 10MB default
            onChange = null,
            placeholder = 'Dosya yüklemek için tıklayın veya sürükleyin',
            hint = 'Maksimum dosya boyutu: 10MB'
        } = options;

        const container = document.getElementById(containerId);
        if (!container) return null;

        let currentFile = null;

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function render() {
            if (currentFile) {
                // Show file preview
                container.innerHTML = `
                    <div class="file-preview">
                        <div class="file-preview-icon">
                            <i data-lucide="file-audio" class="w-5 h-5"></i>
                        </div>
                        <div class="file-preview-info">
                            <div class="file-preview-name">${currentFile.name}</div>
                            <div class="file-preview-size">${formatFileSize(currentFile.size)}</div>
                        </div>
                        <button type="button" class="file-preview-remove" onclick="this.closest('.file-preview').dispatchEvent(new CustomEvent('removeFile'))">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                `;
            } else {
                // Show upload zone - no native input in DOM, created dynamically on click
                container.innerHTML = `
                    <div class="file-upload-zone relative p-8 text-center" id="${containerId}_zone">
                        <div class="flex flex-col items-center gap-3">
                            <div class="file-upload-icon">
                                <i data-lucide="upload-cloud" class="w-full h-full"></i>
                            </div>
                            <div>
                                <p class="file-upload-text">${placeholder}</p>
                                <p class="file-upload-hint mt-1">${hint}</p>
                            </div>
                        </div>
                    </div>
                `;
            }
            lucide.createIcons();
        }

        function handleFileSelect(file) {
            if (!file) return;

            if (file.size > maxSize) {
                notificationSystem?.showToast(`Dosya çok büyük. Maksimum: ${formatFileSize(maxSize)}`, 'error');
                return;
            }

            currentFile = file;
            render();

            if (onChange) {
                onChange(currentFile);
            }
        }

        function setupEventListeners() {
            const zone = container.querySelector(`#${containerId}_zone`);

            if (zone) {
                // Click on zone to create and trigger file input dynamically
                zone.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Create file input dynamically
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = accept;
                    input.style.display = 'none';
                    input.style.visibility = 'hidden';
                    input.style.position = 'absolute';
                    input.style.width = '0';
                    input.style.height = '0';
                    input.style.opacity = '0';
                    
                    // Handle file selection
                    input.addEventListener('change', (e) => {
                        if (e.target.files.length > 0) {
                            handleFileSelect(e.target.files[0]);
                        }
                        // Remove input after selection
                        input.remove();
                    });
                    
                    // Trigger click
                    document.body.appendChild(input);
                    input.click();
                });

                // Drag and drop events
                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    zone.classList.add('drag-over');
                });

                zone.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    zone.classList.remove('drag-over');
                });

                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    zone.classList.remove('drag-over');
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        handleFileSelect(files[0]);
                    }
                });
            }

            // Remove file event
            container.addEventListener('removeFile', () => {
                currentFile = null;
                render();
                setupEventListeners();
                if (onChange) {
                    onChange(null);
                }
            });
        }

        // Initial render
        render();
        setupEventListeners();

        // Return public API
        return {
            getFile: () => currentFile,
            setFile: (file) => handleFileSelect(file),
            clear: () => {
                currentFile = null;
                render();
                setupEventListeners();
                if (onChange) {
                    onChange(null);
                }
            }
        };
    };

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
    // ==================== Playlists ====================
    let currentPlaylistId = null;
    let playlists = [];
    let dragSrcEl = null;

    window.renderPlaylists = async function() {
        mainContent.innerHTML = renderLoading();
        
        try {
            playlists = await api.getPlaylists();
            
            mainContent.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">İş Listeleri</h2>
                        <button onclick="showCreatePlaylistModal()" class="btn btn-primary">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Yeni Liste
                        </button>
                    </div>
                    
                    ${playlists.length === 0 ? `
                        <div class="card">
                            <div class="card-content py-12 text-center">
                                <i data-lucide="list-video" class="w-12 h-12 mx-auto text-muted-foreground mb-4"></i>
                                <h3 class="text-lg font-medium mb-2">Henüz liste yok</h3>
                                <p class="text-muted-foreground mb-4">TTS işlerini toplu olarak planlamak için bir liste oluşturun</p>
                                <button onclick="showCreatePlaylistModal()" class="btn btn-primary">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    İlk Listeyi Oluştur
                                </button>
                            </div>
                        </div>
                    ` : `
                        <div class="space-y-4">
                            ${playlists.map(playlist => `
                                <div class="card playlist-card" data-playlist-id="${playlist.id}">
                                    <div class="card-header cursor-pointer" onclick="togglePlaylist(${playlist.id})">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex items-center gap-3">
                                                <i data-lucide="chevron-right" class="w-5 h-5 transition-transform playlist-toggle-icon" id="toggle-icon-${playlist.id}"></i>
                                                <div>
                                                    <h3 class="font-semibold">${playlist.name}</h3>
                                                    <p class="text-sm text-muted-foreground">
                                                        ${playlist.total_items} iş • 
                                                        ${playlist.status === 'completed' ? 'Tamamlandı' : 
                                                          playlist.status === 'processing' ? 'İşleniyor' :
                                                          playlist.status === 'paused' ? 'Duraklatıldı' : 'Bekliyor'}
                                                        ${playlist.duration_seconds ? ` • ${formatDuration(playlist.duration_seconds)}` : ''}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                ${getPlaylistStatusBadge(playlist)}
                                                ${playlist.status === 'pending' ? `
                                                    <button onclick="event.stopPropagation(); startPlaylist(${playlist.id})" class="btn btn-primary btn-sm">
                                                        <i data-lucide="play" class="w-4 h-4"></i>
                                                    </button>
                                                ` : playlist.status === 'processing' ? `
                                                    <button onclick="event.stopPropagation(); pausePlaylist(${playlist.id})" class="btn btn-outline btn-sm">
                                                        <i data-lucide="pause" class="w-4 h-4"></i>
                                                    </button>
                                                ` : playlist.status === 'paused' ? `
                                                    <button onclick="event.stopPropagation(); resumePlaylist(${playlist.id})" class="btn btn-primary btn-sm">
                                                        <i data-lucide="play" class="w-4 h-4"></i>
                                                    </button>
                                                ` : ''}
                                                <button onclick="event.stopPropagation(); showCreatePlaylistItemModal(${playlist.id})" class="btn btn-outline btn-sm">
                                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                                </button>
                                                <button onclick="event.stopPropagation(); showEditPlaylistModal(${playlist.id})" class="btn btn-outline btn-sm">
                                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                                </button>
                                                <button onclick="event.stopPropagation(); deletePlaylist(${playlist.id})" class="btn btn-outline btn-sm text-destructive hover:bg-destructive/10">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="hidden playlist-items" id="playlist-items-${playlist.id}">
                                        <div class="border-t border-border">
                                            ${playlist.total_items === 0 ? `
                                                <div class="p-4 text-center text-muted-foreground">
                                                    Henüz iş eklenmemiş
                                                </div>
                                            ` : `
                                                <div class="divide-y divide-border" id="items-container-${playlist.id}">
                                                    <!-- Items will be loaded here -->
                                                </div>
                                            `}
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `}
                </div>
            `;
            
            lucide.createIcons();
            
            // Auto-refresh if any playlist is processing
            const hasProcessing = playlists.some(p => p.status === 'processing');
            if (hasProcessing) {
                if (window.playlistRefreshInterval) {
                    clearInterval(window.playlistRefreshInterval);
                }
                window.playlistRefreshInterval = setInterval(async () => {
                    // Refresh only if still on playlists page
                    if (document.querySelector('[data-playlist-id]')) {
                        await window.refreshPlaylistsData();
                    }
                }, 2000); // Refresh every 2 seconds
            } else {
                if (window.playlistRefreshInterval) {
                    clearInterval(window.playlistRefreshInterval);
                    window.playlistRefreshInterval = null;
                }
            }
            
        } catch (e) {
            mainContent.innerHTML = renderError('Listeler yüklenirken hata oluştu');
        }
    }
    
    // Function to refresh playlists data without full re-render
    window.refreshPlaylistsData = async function() {
        try {
            const newPlaylists = await api.getPlaylists();
            
            // Update status badges and progress
            newPlaylists.forEach(playlist => {
                const badge = document.querySelector(`[data-playlist-id="${playlist.id}"] .badge`);
                if (badge) {
                    badge.outerHTML = window.getPlaylistStatusBadge(playlist);
                }
                
                // Update status text
                const statusText = document.querySelector(`[data-playlist-id="${playlist.id}"] .text-muted-foreground`);
                if (statusText) {
                    const statusLabel = playlist.status === 'completed' ? 'Tamamlandı' : 
                                       playlist.status === 'processing' ? 'İşleniyor' :
                                       playlist.status === 'paused' ? 'Duraklatıldı' : 'Bekliyor';
                    statusText.innerHTML = `${playlist.total_items} iş • ${statusLabel}${playlist.duration_seconds ? ` • ${window.formatDuration(playlist.duration_seconds)}` : ''}`;
                }
                
                // Refresh items if playlist is expanded
                const itemsDiv = document.getElementById(`playlist-items-${playlist.id}`);
                if (itemsDiv && !itemsDiv.classList.contains('hidden')) {
                    window.loadPlaylistItems(playlist.id);
                }
            });
            
            // Stop auto-refresh if no longer processing
            const hasProcessing = newPlaylists.some(p => p.status === 'processing');
            if (!hasProcessing && window.playlistRefreshInterval) {
                clearInterval(window.playlistRefreshInterval);
                window.playlistRefreshInterval = null;
            }
        } catch (e) {
            console.error('Failed to refresh playlists:', e);
        }
    };

    window.getPlaylistStatusBadge = function(playlist) {
        const progress = playlist.progress_percentage;
        if (playlist.status === 'completed') {
            return `<span class="badge badge-success">%100</span>`;
        } else if (playlist.status === 'processing') {
            return `<span class="badge badge-primary">%${progress}</span>`;
        } else if (playlist.status === 'paused') {
            return `<span class="badge badge-warning">%${progress}</span>`;
        } else if (playlist.status === 'failed') {
            return `<span class="badge badge-destructive">Hata</span>`;
        }
        return `<span class="badge badge-secondary">Bekliyor</span>`;
    }

    window.formatDuration = function(seconds) {
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)}dk`;
        const hours = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        return `${hours}s ${mins}dk`;
    }

    window.togglePlaylist = async function(playlistId) {
        const itemsDiv = document.getElementById(`playlist-items-${playlistId}`);
        const icon = document.getElementById(`toggle-icon-${playlistId}`);
        
        if (itemsDiv.classList.contains('hidden')) {
            itemsDiv.classList.remove('hidden');
            icon.classList.add('rotate-90');
            await window.loadPlaylistItems(playlistId);
        } else {
            itemsDiv.classList.add('hidden');
            icon.classList.remove('rotate-90');
        }
    }

    window.loadPlaylistItems = async function(playlistId) {
        try {
            const playlist = await api.getPlaylist(playlistId);
            const container = document.getElementById(`items-container-${playlistId}`);
            
            if (!container) return;
            
            container.innerHTML = playlist.items.map((item, index) => `
                <div class="flex items-center gap-3 p-3 hover:bg-accent/50 draggable-item" 
                     draggable="true"
                     data-item-id="${item.id}"
                     data-playlist-id="${playlistId}">
                    <div class="cursor-move text-muted-foreground hover:text-foreground">
                        <i data-lucide="grip-vertical" class="w-4 h-4"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm truncate">${item.text}</p>
                        <p class="text-xs text-muted-foreground">
                            ${item.status === 'completed' ? '✓ Tamamlandı' : 
                              item.status === 'processing' ? '⏳ İşleniyor' :
                              item.status === 'failed' ? '✗ Hata' : '⏸ Sırada'}
                        </p>
                    </div>
                    <button onclick="deletePlaylistItem(${playlistId}, ${item.id})" class="p-1 rounded hover:bg-destructive/10 text-muted-foreground hover:text-destructive">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            `).join('');
            
            // Setup drag and drop
            setupDragAndDrop(container, playlistId);
            lucide.createIcons();
        } catch (e) {
            console.error('Failed to load playlist items:', e);
        }
    }

    window.setupDragAndDrop = function(container, playlistId) {
        const items = container.querySelectorAll('.draggable-item');
        
        items.forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragenter', handleDragEnter);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('dragleave', handleDragLeave);
            item.addEventListener('drop', handleDrop);
            item.addEventListener('dragend', handleDragEnd);
        });
    }

    window.handleDragStart = function(e) {
        dragSrcEl = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
        this.classList.add('opacity-50');
    }

    window.handleDragOver = function(e) {
        if (e.preventDefault) e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    window.handleDragEnter = function(e) {
        this.classList.add('bg-accent');
    }

    window.handleDragLeave = function(e) {
        this.classList.remove('bg-accent');
    }

    window.handleDrop = function(e) {
        if (e.stopPropagation) e.stopPropagation();
        
        if (dragSrcEl !== this) {
            const container = dragSrcEl.parentNode;
            const items = Array.from(container.children);
            const srcIndex = items.indexOf(dragSrcEl);
            const targetIndex = items.indexOf(this);
            
            if (srcIndex < targetIndex) {
                this.after(dragSrcEl);
            } else {
                this.before(dragSrcEl);
            }
            
            // Save new order
            const playlistId = dragSrcEl.dataset.playlistId;
            const newOrder = Array.from(container.children).map(el => parseInt(el.dataset.itemId));
            api.reorderPlaylistItems(playlistId, newOrder);
        }
        
        return false;
    }

    window.handleDragEnd = function(e) {
        this.classList.remove('opacity-50');
        document.querySelectorAll('.draggable-item').forEach(item => {
            item.classList.remove('bg-accent');
        });
    }

    // Playlist actions
    window.showCreatePlaylistModal = async function() {
        // Load tags for the modal
        let tags = [];
        try {
            tags = await api.getTags();
        } catch (e) {
            console.log('Could not load tags');
        }
        
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm';
        modal.innerHTML = `
            <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-md mx-4">
                <div class="p-4 border-b border-border">
                    <h3 class="text-lg font-semibold">Yeni İş Listesi</h3>
                </div>
                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Liste Adı</label>
                        <input type="text" id="playlistName" class="input w-full" placeholder="Örn: Lunera Olumlama">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Açıklama</label>
                        <textarea id="playlistDescription" class="input w-full" rows="2" placeholder="Liste açıklaması (isteğe bağlı)"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="useSingleModel" class="rounded border-border">
                        <label for="useSingleModel" class="text-sm">Tüm işler için aynı ayarları kullan</label>
                    </div>
                    <div id="singleConfigContainer" class="hidden space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">TTS Modeli</label>
                            <select id="singleModelId" class="input w-full">
                                <option value="xtts-v2">Coqui XTTS v2</option>
                                <option value="bark">Suno Bark</option>
                                <option value="tortoise">Tortoise TTS</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Etiket</label>
                            <select id="singleTagId" class="input w-full">
                                <option value="">Etiket seçin (isteğe bağlı)</option>
                                ${tags.map(tag => `
                                    <option value="${tag.id}">${tag.name}</option>
                                `).join('')}
                            </select>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border">
                    <button onclick="closeModal(this)" class="btn btn-outline">İptal</button>
                    <button onclick="createPlaylist()" class="btn btn-primary">Oluştur</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Setup checkbox toggle
        const checkbox = document.getElementById('useSingleModel');
        const configContainer = document.getElementById('singleConfigContainer');
        if (checkbox && configContainer) {
            checkbox.addEventListener('change', (e) => {
                if (e.target.checked) {
                    configContainer.classList.remove('hidden');
                } else {
                    configContainer.classList.add('hidden');
                }
            });
        }
        
        lucide.createIcons();
    };

    window.showEditPlaylistModal = async function(playlistId) {
        try {
            const [playlist, tags] = await Promise.all([
                api.getPlaylist(playlistId),
                api.getTags()
            ]);
            
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm';
            modal.innerHTML = `
                <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-md mx-4">
                    <div class="p-4 border-b border-border">
                        <h3 class="text-lg font-semibold">Listeyi Düzenle</h3>
                    </div>
                    <div class="p-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Liste Adı</label>
                            <input type="text" id="editPlaylistName" class="input w-full" value="${playlist.name}" placeholder="Liste adı">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Açıklama</label>
                            <textarea id="editPlaylistDescription" class="input w-full" rows="2" placeholder="Liste açıklaması (isteğe bağlı)">${playlist.description || ''}</textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="editUseSingleModel" class="rounded border-border" ${playlist.use_single_model ? 'checked' : ''}>
                            <label for="editUseSingleModel" class="text-sm">Tüm işler için aynı ayarları kullan</label>
                        </div>
                        <div id="singleConfigContainer" class="${playlist.use_single_model ? '' : 'hidden'} space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">TTS Modeli</label>
                                <select id="editSingleModelId" class="input w-full">
                                    <option value="xtts-v2" ${playlist.single_model_id === 'xtts-v2' ? 'selected' : ''}>Coqui XTTS v2</option>
                                    <option value="bark" ${playlist.single_model_id === 'bark' ? 'selected' : ''}>Suno Bark</option>
                                    <option value="tortoise" ${playlist.single_model_id === 'tortoise' ? 'selected' : ''}>Tortoise TTS</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Etiket</label>
                                <select id="editSingleTagId" class="input w-full">
                                    <option value="">Etiket seçin (isteğe bağlı)</option>
                                    ${tags.map(tag => `
                                        <option value="${tag.id}" ${playlist.single_tag_id === tag.id ? 'selected' : ''}>
                                            ${tag.name}
                                        </option>
                                    `).join('')}
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-border">
                        <button onclick="closeModal(this)" class="btn btn-outline">İptal</button>
                        <button onclick="updatePlaylist(${playlistId})" class="btn btn-primary">Kaydet</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Setup checkbox toggle for single model config
            const checkbox = document.getElementById('editUseSingleModel');
            const configContainer = document.getElementById('singleConfigContainer');
            if (checkbox && configContainer) {
                checkbox.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        configContainer.classList.remove('hidden');
                    } else {
                        configContainer.classList.add('hidden');
                    }
                });
            }
            
            lucide.createIcons();
        } catch (e) {
            notificationSystem?.showToast('Liste bilgileri yüklenirken hata', 'error');
        }
    };

    window.updatePlaylist = async function(playlistId) {
        const name = document.getElementById('editPlaylistName').value;
        const description = document.getElementById('editPlaylistDescription').value;
        const useSingleModel = document.getElementById('editUseSingleModel').checked;
        
        if (!name) {
            notificationSystem?.showToast('Liste adı gerekli', 'error');
            return;
        }
        
        const data = {
            name,
            description,
            use_single_model: useSingleModel
        };
        
        if (useSingleModel) {
            const modelSelect = document.getElementById('editSingleModelId');
            const tagSelect = document.getElementById('editSingleTagId');
            if (modelSelect) data.single_model_id = modelSelect.value;
            if (tagSelect) data.single_tag_id = tagSelect.value ? parseInt(tagSelect.value) : null;
        } else {
            data.single_model_id = null;
            data.single_profile_id = null;
            data.single_tag_id = null;
        }
        
        try {
            await api.updatePlaylist(playlistId, data);
            closeModal(document.querySelector('.fixed.z-50'));
            notificationSystem?.showToast('Liste güncellendi', 'success');
            await window.renderPlaylists();
        } catch (e) {
            notificationSystem?.showToast('Liste güncellenirken hata', 'error');
        }
    };

    window.createPlaylist = async function() {
        const name = document.getElementById('playlistName').value;
        const description = document.getElementById('playlistDescription').value;
        const useSingleModel = document.getElementById('useSingleModel').checked;
        
        if (!name) {
            notificationSystem?.showToast('Liste adı gerekli', 'error');
            return;
        }
        
        const data = {
            name,
            description,
            use_single_model: useSingleModel,
            items: []
        };
        
        if (useSingleModel) {
            const modelSelect = document.getElementById('singleModelId');
            const tagSelect = document.getElementById('singleTagId');
            if (modelSelect) data.single_model_id = modelSelect.value;
            if (tagSelect) data.single_tag_id = tagSelect.value ? parseInt(tagSelect.value) : null;
        }
        
        try {
            await api.createPlaylist(data);
            
            closeModal(document.querySelector('.fixed.z-50'));
            notificationSystem?.showToast('Liste oluşturuldu', 'success');
            renderPlaylists();
        } catch (e) {
            notificationSystem?.showToast('Liste oluşturulurken hata', 'error');
        }
    };

    window.showCreatePlaylistItemModal = async function(playlistId) {
        const playlist = await api.getPlaylist(playlistId);
        
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm';
        modal.innerHTML = `
            <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-2xl mx-4 max-h-[90vh] flex flex-col">
                <div class="p-4 border-b border-border">
                    <h3 class="text-lg fontibold">İş Ekle: ${playlist.name}</h3>
                </div>
                <div class="p-4 space-y-4 overflow-y-auto flex-1">
                    ${playlist.use_single_model ? `
                        <div class="bg-muted p-3 rounded-lg text-sm">
                            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                            Bu liste için tek model modu aktif. Tüm işler aynı modelle yapılacak.
                        </div>
                    ` : ''}
                    <div>
                        <label class="block text-sm font-medium mb-1">Metin</label>
                        <textarea id="itemText" class="input w-full" rows="4" placeholder="Seslendirilecek metin..."></textarea>
                    </div>
                    ${!playlist.use_single_model ? `
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">TTS Modeli</label>
                                <select id="itemModel" class="input w-full">
                                    <option value="xtts-v2">Coqui XTTS v2</option>
                                    <option value="bark">Suno Bark</option>
                                    <option value="tortoise">Tortoise TTS</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Ses Profili</label>
                                <select id="itemProfile" class="input w-full">
                                    <option value="">Varsayılan</option>
                                </select>
                            </div>
                        </div>
                    ` : ''}
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border">
                    <button onclick="closeModal(this)" class="btn btn-outline">İptal</button>
                    <button onclick="addPlaylistItem(${playlistId})" class="btn btn-primary">Ekle</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        lucide.createIcons();
    };

    window.addPlaylistItem = async function(playlistId) {
        const text = document.getElementById('itemText').value;
        if (!text) {
            notificationSystem?.showToast('Metin gerekli', 'error');
            return;
        }
        
        const data = { text };
        
        const modelSelect = document.getElementById('itemModel');
        const profileSelect = document.getElementById('itemProfile');
        
        if (modelSelect) data.model_id = modelSelect.value;
        if (profileSelect) data.profile_id = profileSelect.value || null;
        
        try {
            await api.addPlaylistItem(playlistId, data);
            closeModal(document.querySelector('.fixed.z-50'));
            notificationSystem?.showToast('İş eklendi', 'success');
            
            // Refresh playlist view to update item count
            await window.renderPlaylists();
            
            // If playlist was open, reopen it and show items
            const itemsDiv = document.getElementById(`playlist-items-${playlistId}`);
            if (itemsDiv) {
                itemsDiv.classList.remove('hidden');
                const icon = document.getElementById(`toggle-icon-${playlistId}`);
                if (icon) icon.classList.add('rotate-90');
                await window.loadPlaylistItems(playlistId);
            }
        } catch (e) {
            notificationSystem?.showToast('İş eklenirken hata', 'error');
        }
    };

    window.startPlaylist = async function(playlistId) {
        console.log('startPlaylist called for playlist:', playlistId);
        try {
            notificationSystem?.showToast('Liste başlatılıyor...', 'info');
            const result = await api.processPlaylist(playlistId);
            console.log('processPlaylist result:', result);
            notificationSystem?.showToast(result.message || 'Liste işleme alındı', 'success');
            renderPlaylists();
        } catch (e) {
            console.error('Start playlist error:', e);
            notificationSystem?.showToast('Başlatılırken hata: ' + (e.message || 'Bilinmeyen hata'), 'error');
        }
    };

    window.pausePlaylist = async function(playlistId) {
        try {
            await api.pausePlaylist(playlistId);
            notificationSystem?.showToast('Liste duraklatıldı', 'success');
            renderPlaylists();
        } catch (e) {
            notificationSystem?.showToast('Duraklatılırken hata', 'error');
        }
    };

    window.resumePlaylist = async function(playlistId) {
        try {
            await api.resumePlaylist(playlistId);
            notificationSystem?.showToast('Liste devam ediyor', 'success');
            renderPlaylists();
        } catch (e) {
            notificationSystem?.showToast('Devam ettirilirken hata', 'error');
        }
    };

    window.deletePlaylist = async function(playlistId) {
        if (!confirm('Bu listeyi silmek istediğinize emin misiniz?')) return;
        
        try {
            await api.deletePlaylist(playlistId);
            notificationSystem?.showToast('Liste silindi', 'success');
            renderPlaylists();
        } catch (e) {
            notificationSystem?.showToast('Silinirken hata', 'error');
        }
    };

    window.deletePlaylistItem = async function(playlistId, itemId) {
        try {
            await api.deletePlaylistItem(playlistId, itemId);
            notificationSystem?.showToast('İş silindi', 'success');
            await window.loadPlaylistItems(playlistId);
        } catch (e) {
            notificationSystem?.showToast('Silinirken hata', 'error');
        }
    };

    window.closeModal = function(element) {
        const modal = element.closest('.fixed.z-50');
        if (modal) modal.remove();
    };

    init();
});
