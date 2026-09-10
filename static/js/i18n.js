/**
 * Internationalization (i18n) System for Voice Core
 * Supports Turkish (tr) and English (en)
 */

const i18n = {
    // Current language
    currentLang: 'tr',
    
    // Translation dictionary
    translations: {
        tr: {
            // Common
            'app.name': 'Voice Core',
            'app.tagline': 'AI Ses Yönetim Sistemi',
            'app.version': 'Versiyon',
            
            // Navigation
            'nav.dashboard': 'Dashboard',
            'nav.profiles': 'Ses Profilleri',
            'nav.tts': 'Metin Okuma',
            'nav.stt': 'Sesten Metne',
            'nav.models': 'Model Yöneticisi',
            'nav.queue': 'İşlem Kuyruğu',
            'nav.settings': 'Ayarlar',
            'nav.logout': 'Çıkış Yap',
            
            // Settings
            'settings.title': 'Sistem Ayarları',
            'settings.general': 'Genel',
            'settings.api': 'API & Güvenlik',
            'settings.services': 'Harici Servisler',
            'settings.language': 'Dil',
            'settings.language.desc': 'Arayüz dili',
            'settings.theme': 'Tema',
            'settings.theme.desc': 'Karanlık/Aydınlık mod',
            'settings.theme.light': 'Aydınlık',
            'settings.theme.dark': 'Karanlık',
            
            // API Settings
            'settings.secret_key': 'API Secret Key',
            'settings.secret_key.desc': 'JWT token oluşturma ve API güvenliği için kullanılan gizli anahtar',
            'settings.secret_key.placeholder': 'En az 16 karakter...',
            'settings.secret_key.save': 'Secret Key Kaydet',
            'settings.secret_key.generate': 'Rastgele Oluştur',
            'settings.swagger': 'API Dokümantasyonu',
            'settings.swagger.desc': 'Tüm API endpointlerini Swagger UI üzerinden inceleyin',
            'settings.swagger.open': 'Swagger UI\'yi Aç',
            
            // Services
            'settings.hf_token': 'HuggingFace API Token',
            'settings.hf_token.desc': 'Model indirme işlemlerinde rate limit sorununu önlemek için HuggingFace token ekleyin',
            'settings.hf_token.placeholder': 'hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'settings.hf_token.save': 'Token Kaydet',
            'settings.hf_token.remove': 'Tokeni Kaldır',
            
            // TTS
            'tts.title': 'Metin Okuma (TTS)',
            'tts.new': 'Yeni Ses Üret',
            'tts.history': 'Geçmiş',
            'tts.profile': 'Ses Profili',
            'tts.engine': 'TTS Motoru',
            'tts.tags': 'Etiketler',
            'tts.text': 'Metin',
            'tts.text.placeholder': 'Seslendirilecek metni buraya yazın...',
            'tts.generate': 'Ses Üret',
            'tts.manage_tags': 'Etiketleri Yönet',
            'tts.duration': 'Müzik Süresi',
            
            // Model Manager
            'models.title': 'Model Yöneticisi',
            'models.all': 'Tümü',
            'models.tts': 'Metin Okuma',
            'models.stt': 'Sesten Metne',
            'models.music': 'Müzik',
            'models.llm': 'Dil Modeli',
            'models.verify_all': 'Tümünü Doğrula',
            'models.model': 'Model',
            'models.type': 'Tür',
            'models.size': 'Boyut',
            'models.status': 'Durum',
            'models.action': 'İşlem',
            'models.status.ready': 'Hazır',
            'models.status.not_downloaded': 'İndirilmedi',
            'models.status.downloading': 'İndiriliyor...',
            'models.status.corrupted': 'Bozuk',
            'models.download': 'İndir',
            'models.delete': 'Sil',
            
            // Common Actions
            'action.save': 'Kaydet',
            'action.cancel': 'İptal',
            'action.delete': 'Sil',
            'action.edit': 'Düzenle',
            'action.create': 'Oluştur',
            'action.close': 'Kapat',
            'action.back': 'Geri Dön',
            'action.confirm': 'Onayla',
            
            // Messages
            'msg.success': 'Başarılı',
            'msg.error': 'Hata',
            'msg.warning': 'Uyarı',
            'msg.saved': 'Kaydedildi',
            'msg.deleted': 'Silindi',
            'msg.created': 'Oluşturuldu',
            'msg.loading': 'Yükleniyor...',
            
            // WebSocket
            'ws.connected': 'Canlı',
            'ws.disconnected': 'Bağlı Değil',
            'ws.connecting': 'Bağlanıyor...',
            
            // Tag Manager
            'tags.title': 'Etiket Yönetimi',
            'tags.name': 'Etiket adı',
            'tags.color': 'Renk',
            'tags.add': 'Ekle',
            'tags.create_new': 'Yeni Etiket Oluştur',
            'tags.create_confirm': 'etiketi mevcut değil. Oluşturmak istiyor musunuz?',
        },
        en: {
            // Common
            'app.name': 'Voice Core',
            'app.tagline': 'AI Voice Management System',
            'app.version': 'Version',
            
            // Navigation
            'nav.dashboard': 'Dashboard',
            'nav.profiles': 'Voice Profiles',
            'nav.tts': 'Text to Speech',
            'nav.stt': 'Speech to Text',
            'nav.models': 'Model Manager',
            'nav.queue': 'Task Queue',
            'nav.settings': 'Settings',
            'nav.logout': 'Logout',
            
            // Settings
            'settings.title': 'System Settings',
            'settings.general': 'General',
            'settings.api': 'API & Security',
            'settings.services': 'External Services',
            'settings.language': 'Language',
            'settings.language.desc': 'Interface language',
            'settings.theme': 'Theme',
            'settings.theme.desc': 'Dark/Light mode',
            'settings.theme.light': 'Light',
            'settings.theme.dark': 'Dark',
            
            // API Settings
            'settings.secret_key': 'API Secret Key',
            'settings.secret_key.desc': 'Secret key used for JWT token generation and API security',
            'settings.secret_key.placeholder': 'At least 16 characters...',
            'settings.secret_key.save': 'Save Secret Key',
            'settings.secret_key.generate': 'Generate Random',
            'settings.swagger': 'API Documentation',
            'settings.swagger.desc': 'Browse all API endpoints via Swagger UI',
            'settings.swagger.open': 'Open Swagger UI',
            
            // Services
            'settings.hf_token': 'HuggingFace API Token',
            'settings.hf_token.desc': 'Add HuggingFace token to prevent rate limit issues during model downloads',
            'settings.hf_token.placeholder': 'hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'settings.hf_token.save': 'Save Token',
            'settings.hf_token.remove': 'Remove Token',
            
            // TTS
            'tts.title': 'Text to Speech (TTS)',
            'tts.new': 'Generate New Voice',
            'tts.history': 'History',
            'tts.profile': 'Voice Profile',
            'tts.engine': 'TTS Engine',
            'tts.tags': 'Tags',
            'tts.text': 'Text',
            'tts.text.placeholder': 'Type text to be spoken...',
            'tts.generate': 'Generate Voice',
            'tts.manage_tags': 'Manage Tags',
            'tts.duration': 'Music Duration',
            
            // Model Manager
            'models.title': 'Model Manager',
            'models.all': 'All',
            'models.tts': 'Text to Speech',
            'models.stt': 'Speech to Text',
            'models.music': 'Music',
            'models.llm': 'Language Model',
            'models.verify_all': 'Verify All',
            'models.model': 'Model',
            'models.type': 'Type',
            'models.size': 'Size',
            'models.status': 'Status',
            'models.action': 'Action',
            'models.status.ready': 'Ready',
            'models.status.not_downloaded': 'Not Downloaded',
            'models.status.downloading': 'Downloading...',
            'models.status.corrupted': 'Corrupted',
            'models.download': 'Download',
            'models.delete': 'Delete',
            
            // Common Actions
            'action.save': 'Save',
            'action.cancel': 'Cancel',
            'action.delete': 'Delete',
            'action.edit': 'Edit',
            'action.create': 'Create',
            'action.close': 'Close',
            'action.back': 'Back',
            'action.confirm': 'Confirm',
            
            // Messages
            'msg.success': 'Success',
            'msg.error': 'Error',
            'msg.warning': 'Warning',
            'msg.saved': 'Saved',
            'msg.deleted': 'Deleted',
            'msg.created': 'Created',
            'msg.loading': 'Loading...',
            
            // WebSocket
            'ws.connected': 'Live',
            'ws.disconnected': 'Disconnected',
            'ws.connecting': 'Connecting...',
            
            // Tag Manager
            'tags.title': 'Tag Management',
            'tags.name': 'Tag name',
            'tags.color': 'Color',
            'tags.add': 'Add',
            'tags.create_new': 'Create New Tag',
            'tags.create_confirm': 'tag does not exist. Would you like to create it?',
        }
    },
    
    /**
     * Get translation for a key
     */
    t(key, params = {}) {
        const translation = this.translations[this.currentLang]?.[key] || 
                           this.translations['tr'][key] || 
                           key;
        
        // Simple parameter replacement
        return translation.replace(/\{\{(\w+)\}\}/g, (match, param) => {
            return params[param] !== undefined ? params[param] : match;
        });
    },
    
    /**
     * Set current language
     */
    setLanguage(lang) {
        if (this.translations[lang]) {
            this.currentLang = lang;
            document.documentElement.lang = lang;
            this.updatePageTitle();
            return true;
        }
        return false;
    },
    
    /**
     * Get current language
     */
    getLanguage() {
        return this.currentLang;
    },
    
    /**
     * Update page title based on current language
     */
    updatePageTitle() {
        const title = document.querySelector('title');
        if (title) {
            title.textContent = `${this.t('app.name')} - ${this.t('app.tagline')}`;
        }
    },
    
    /**
     * Initialize i18n with user's preference
     */
    async init() {
        try {
            // Try to get user's preference from API
            const response = await fetch(`${(window.VOICE_CORE_CONFIG && window.VOICE_CORE_CONFIG.apiBase) || (location.protocol + '//' + location.hostname + ':5001')}/auth/me/preferences`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('vc_token')}`
                }
            });
            
            if (response.ok) {
                const prefs = await response.json();
                if (prefs.language) {
                    this.setLanguage(prefs.language);
                }
                if (prefs.theme) {
                    this.applyTheme(prefs.theme);
                }
            }
        } catch (e) {
            console.log('Could not load user preferences, using defaults');
        }
    },
    
    /**
     * Apply theme to document
     */
    applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    },
    
    /**
     * Save user preferences to API
     */
    async savePreferences(theme, language) {
        try {
            const response = await fetch(`${(window.VOICE_CORE_CONFIG && window.VOICE_CORE_CONFIG.apiBase) || (location.protocol + '//' + location.hostname + ':5001')}/auth/me/preferences`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('vc_token')}`
                },
                body: JSON.stringify({ theme, language })
            });
            
            if (response.ok) {
                const prefs = await response.json();
                this.setLanguage(prefs.language);
                this.applyTheme(prefs.theme);
                return true;
            }
            return false;
        } catch (e) {
            console.error('Failed to save preferences:', e);
            return false;
        }
    }
};

// Global translation function
window.t = (key, params) => i18n.t(key, params);
