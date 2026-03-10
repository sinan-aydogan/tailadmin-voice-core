/**
 * API Wrapper for Voice Core
 */
const API_BASE = 'http://localhost:5001';

class ApiClient {
    constructor() {
        this.token = localStorage.getItem('vc_token');
    }

    setToken(token) {
        this.token = token;
        localStorage.setItem('vc_token', token);
    }

    clearToken() {
        this.token = null;
        localStorage.removeItem('vc_token');
    }

    async request(endpoint, method = 'GET', data = null, isFormData = false) {
        const headers = {};

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        if (!isFormData && data && (method === 'POST' || method === 'PUT')) {
            headers['Content-Type'] = 'application/json';
        }

        const config = {
            method,
            headers,
        };

        if (data) {
            if (isFormData) {
                config.body = data;
            } else if (method === 'POST' || method === 'PUT') {
                config.body = JSON.stringify(data);
            }
        }

        try {
            const response = await fetch(`${API_BASE}${endpoint}`, config);

            if (response.status === 401 && endpoint !== '/auth/login') {
                // Token expired or invalid
                this.clearToken();
                window.location.reload();
                throw new Error("Unauthorized");
            }

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.detail || `HTTP Error ${response.status}`);
            }

            // Return empty object for 204 No Content
            if (response.status === 204) return {};
            return await response.json();

        } catch (error) {
            console.error(`API Request failed for ${endpoint}:`, error);
            throw error;
        }
    }

    // Auth
    async login(username, password) {
        const formData = new URLSearchParams();
        formData.append('username', username);
        formData.append('password', password);

        const res = await fetch(`${API_BASE}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });

        if (!res.ok) throw new Error("Giriş başarısız");
        const data = await res.json();
        this.setToken(data.access_token);
        return await this.getMe();
    }

    async getMe() {
        return this.request('/auth/me');
    }

    // Profiles
    async getProfiles() {
        return this.request('/profiles/');
    }

    async createProfile(data) {
        return this.request('/profiles/', 'POST', data);
    }

    async deleteProfile(id) {
        return this.request(`/profiles/${id}`, 'DELETE');
    }

    async uploadProfileAudio(id, file) {
        const formData = new FormData();
        formData.append('file', file);
        return this.request(`/profiles/${id}/audio`, 'POST', formData, true);
    }

    // Models
    async getAvailableModels() {
        return this.request('/models/available');
    }

    async getActiveDownloads() {
        return this.request('/models/downloads');
    }

    async startDownload(modelId) {
        return this.request(`/models/download/${modelId}`, 'POST');
    }

    async deleteModelDownload(modelId) {
        return this.request(`/models/${modelId}`, 'DELETE');
    }

    // Tags
    async getTags() {
        return this.request('/tags/');
    }

    async createTag(data) {
        return this.request('/tags/', 'POST', data);
    }

    async deleteTag(tagId) {
        return this.request(`/tags/${tagId}`, 'DELETE');
    }

    // TTS / STT
    async generateTts(data) {
        return this.request('/tts/generate', 'POST', data);
    }

    async getTtsHistory(tagId = null, engine = null, profileId = null) {
        const params = new URLSearchParams();
        if (tagId) params.append('tag_id', tagId);
        if (engine) params.append('engine', engine);
        if (profileId) params.append('profile_id', profileId);

        const queryString = params.toString();
        const url = `/tts/history${queryString ? '?' + queryString : ''}`;
        return this.request(url);
    }

    async updateTtsTags(outputId, tagIds) {
        return this.request(`/tts/${outputId}/tags`, 'PATCH', tagIds);
    }

    async getTtsResult(id) {
        return this.request(`/tts/result/${id}`);
    }

    async transcribeSttFromUrl(data) {
        return this.request('/stt/transcribe', 'POST', data);
    }

    async transcribeSttUpload(file, language, model_size, engine) {
        const formData = new FormData();
        formData.append('file', file);
        if (language) formData.append('language', language);
        if (model_size) formData.append('model_size', model_size);
        if (engine) formData.append('engine', engine);

        return this.request('/stt/transcribe/upload', 'POST', formData, true);
    }

    // Queue
    async getQueueTasks() {
        return this.request('/queue/');
    }

    async getTaskLogs(taskId) {
        return this.request(`/queue/${taskId}/logs`);
    }

    // Settings & Logs
    async getSettings() {
        return this.request('/settings/');
    }

    async updateSetting(key, val) {
        return this.request(`/settings/${key}`, 'PUT', { value: val });
    }

    async getLogs(lines = 100) {
        return this.request(`/logs/?lines=${lines}`);
    }

    // HuggingFace Token
    async getHFTokenStatus() {
        return this.request('/settings/hf-token/status');
    }

    async updateHFToken(token) {
        return this.request('/settings/hf-token/update', 'POST', { token });
    }

    async deleteHFToken() {
        return this.request('/settings/hf-token', 'DELETE');
    }

    // Secret Key
    async getSecretKeyStatus() {
        return this.request('/settings/secret-key/status');
    }

    async updateSecretKey(secretKey) {
        return this.request('/settings/secret-key/update', 'POST', { secret_key: secretKey });
    }

    async deleteSecretKey() {
        return this.request('/settings/secret-key', 'DELETE');
    }

    // Playlists
    async getPlaylists() {
        return this.request('/playlists');
    }

    async getPlaylist(id) {
        return this.request(`/playlists/${id}`);
    }

    async createPlaylist(data) {
        return this.request('/playlists', 'POST', data);
    }

    async updatePlaylist(id, data) {
        return this.request(`/playlists/${id}`, 'PUT', data);
    }

    async deletePlaylist(id) {
        return this.request(`/playlists/${id}`, 'DELETE');
    }

    async addPlaylistItem(playlistId, data) {
        return this.request(`/playlists/${playlistId}/items`, 'POST', data);
    }

    async updatePlaylistItem(playlistId, itemId, data) {
        return this.request(`/playlists/${playlistId}/items/${itemId}`, 'PUT', data);
    }

    async deletePlaylistItem(playlistId, itemId) {
        return this.request(`/playlists/${playlistId}/items/${itemId}`, 'DELETE');
    }

    async reorderPlaylistItems(playlistId, itemIds) {
        return this.request(`/playlists/${playlistId}/reorder`, 'POST', { item_ids: itemIds });
    }

    async processPlaylist(id) {
        return this.request(`/playlists/${id}/process`, 'POST');
    }

    async pausePlaylist(id) {
        return this.request(`/playlists/${id}/pause`, 'POST');
    }

    async resumePlaylist(id) {
        return this.request(`/playlists/${id}/resume`, 'POST');
    }

    async stopPlaylist(id) {
        return this.request(`/playlists/${id}/stop`, 'POST');
    }
}

const api = new ApiClient();
