/**
 * MusicPlayer — Vanilla JS audio player with Web Audio API Volume Boost (up to 200%)
 */
class MusicPlayer {
    constructor() {
        this.audio = new Audio();
        this.audio.crossOrigin = "anonymous";
        this.queue = [];
        this.currentIndex = -1;
        this.shuffle = false;
        this.repeat = 'none'; // 'none' | 'all' | 'one'
        this.shuffledQueue = [];
        this.isDragging = false;
        this.volume = 1.0; // 0.0 to 2.0 (1.0 = 100%, 2.0 = 200%)

        // Web Audio API for volume boost (> 100%)
        this.audioCtx = null;
        this.gainNode = null;
        this.sourceNode = null;

        this._bindAudioEvents();
        this._bindUIEvents();
    }

    _initAudioContext() {
        if (this.audioCtx) {
            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            return;
        }
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                this.audioCtx = new AudioContext();
                this.gainNode = this.audioCtx.createGain();
                this.gainNode.gain.value = this.audio.muted ? 0 : this.volume;
                this.sourceNode = this.audioCtx.createMediaElementSource(this.audio);
                this.sourceNode.connect(this.gainNode);
                this.gainNode.connect(this.audioCtx.destination);
            }
        } catch (e) {
            console.warn("Web Audio API not initialized (direct audio fallback):", e);
        }
    }

    setQueue(songs, startIndex = 0) {
        if (!songs || !songs.length) return;
        this.queue = songs;
        this._buildShuffledQueue();
        this.playAt(startIndex);
    }

    playAt(index) {
        if (index < 0 || index >= this.queue.length) return;
        this.currentIndex = index;
        const song = this.queue[index];
        this._loadSong(song);
    }

    _loadSong(song) {
        if (!song || !song.streamUrl) return;
        this._initAudioContext();
        this.audio.src = song.streamUrl;
        this.audio.load();
        this.audio.play().catch(err => {
            console.warn("Autoplay / playback prevented:", err);
        });
        this._updateTrackInfo(song);
        this._highlightRow(song.id);
        this._updateMediaSession(song);
    }

    play() {
        this._initAudioContext();
        if (!this.audio.src && this.queue.length) {
            this.playAt(0);
            return;
        }
        this.audio.play().catch(() => {});
    }

    pause() {
        this.audio.pause();
    }

    togglePlay() {
        if (this.audio.paused) {
            this.play();
        } else {
            this.pause();
        }
    }

    stop() {
        this.audio.pause();
        this.audio.currentTime = 0;
    }

    prev() {
        if (this.audio.currentTime > 3) {
            this.audio.currentTime = 0;
            return;
        }
        const idx = this._prevIndex();
        if (idx !== -1) this.playAt(idx);
    }

    next() {
        const idx = this._nextIndex();
        if (idx !== -1) {
            this.playAt(idx);
        } else {
            this.stop();
        }
    }

    toggleShuffle() {
        this.shuffle = !this.shuffle;
        this._buildShuffledQueue();
        const btn = document.querySelector('#btn-shuffle');
        btn?.classList.toggle('active', this.shuffle);
    }

    cycleRepeat() {
        const modes = ['none', 'all', 'one'];
        const next = modes[(modes.indexOf(this.repeat) + 1) % modes.length];
        this.repeat = next;
        this._updateRepeatUI();
    }

    _updateRepeatUI() {
        const btn = document.querySelector('#btn-repeat');
        const badge = document.querySelector('#repeat-badge-1');
        if (!btn) return;

        btn.classList.remove('active', 'repeat-all', 'repeat-one');
        if (badge) badge.classList.add('hidden');

        if (this.repeat === 'all') {
            btn.classList.add('active', 'repeat-all');
            btn.title = 'Powtarzanie: cała playlista';
        } else if (this.repeat === 'one') {
            btn.classList.add('active', 'repeat-one');
            btn.title = 'Powtarzanie: 1 utwór';
            if (badge) badge.classList.remove('hidden');
        } else {
            btn.title = 'Powtarzanie: wyłączone';
        }
    }

    setVolume(value) {
        // value is 0.0 to 2.0 (1.0 = 100%, 2.0 = 200%)
        this._initAudioContext();
        this.volume = Math.max(0, Math.min(2, parseFloat(value) || 0));

        if (this.gainNode && this.audioCtx) {
            this.gainNode.gain.setValueAtTime(this.audio.muted ? 0 : this.volume, this.audioCtx.currentTime);
            this.audio.volume = 1.0;
        } else {
            this.audio.volume = Math.min(1.0, this.volume);
        }

        this._updateVolumeUI();
    }

    _updateVolumeUI() {
        const slider = document.querySelector('#volume-slider');
        const label = document.querySelector('#volume-label');
        const percent = Math.round(this.volume * 100);

        if (label) {
            label.textContent = `${percent}%`;
            label.classList.toggle('is-boosted', percent > 100);
        }

        if (slider) {
            slider.value = this.volume;
            // Slider fill gradient: 0% to 100% of slider width corresponds to 0% to 200% volume
            const fillRatio = (this.volume / 2) * 100;
            const boostColor = percent > 100 ? '#ec4899' : 'var(--accent)';
            slider.style.background = `linear-gradient(to right, ${boostColor} 0%, ${boostColor} ${fillRatio}%, rgba(255, 255, 255, 0.1) ${fillRatio}%, rgba(255, 255, 255, 0.1) 100%)`;
        }
    }

    seek(ratio) {
        if (isFinite(this.audio.duration)) {
            this.audio.currentTime = ratio * this.audio.duration;
        }
    }

    _nextIndex() {
        if (this.queue.length === 0) return -1;
        if (this.repeat === 'one') return this.currentIndex;
        if (this.shuffle) return this._nextShuffled();
        const next = this.currentIndex + 1;
        if (next >= this.queue.length) return this.repeat === 'all' ? 0 : -1;
        return next;
    }

    _prevIndex() {
        if (this.repeat === 'one') return this.currentIndex;
        const prev = this.currentIndex - 1;
        if (prev < 0) return this.repeat === 'all' ? this.queue.length - 1 : 0;
        return prev;
    }

    _nextShuffled() {
        if (!this.shuffledQueue.length) this._buildShuffledQueue();
        const idx = this.shuffledQueue.shift();
        if (this.shuffledQueue.length === 0) this._buildShuffledQueue();
        return idx;
    }

    _buildShuffledQueue() {
        const indices = this.queue.map((_, i) => i).filter(i => i !== this.currentIndex);
        for (let i = indices.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [indices[i], indices[j]] = [indices[j], indices[i]];
        }
        this.shuffledQueue = indices;
    }

    _bindAudioEvents() {
        this.audio.addEventListener('play', () => this._onPlayStateChange(true));
        this.audio.addEventListener('pause', () => this._onPlayStateChange(false));
        this.audio.addEventListener('ended', () => this.next());
        this.audio.addEventListener('timeupdate', () => this._onTimeUpdate());
        this.audio.addEventListener('durationchange', () => this._onTimeUpdate());
        this.audio.addEventListener('error', (e) => {
            console.warn('Audio stream error:', e);
            const el = document.querySelector('#player-title');
            if (el) el.textContent = 'Błąd odtwarzania pliku';
        });
    }

    _onPlayStateChange(isPlaying) {
        const icon = document.querySelector('#btn-play-pause .icon-play-pause');
        if (icon) {
            icon.setAttribute('d', isPlaying ? 'M6 4h4v16H6zm8 0h4v16h-4z' : 'M8 5v14l11-7L8 5z');
        }
        const btn = document.querySelector('#btn-play-pause');
        btn?.classList.toggle('is-playing', isPlaying);
    }

    _onTimeUpdate() {
        if (this.isDragging) return;
        const current = this.audio.currentTime || 0;
        const duration = this.audio.duration || 0;
        const ratio = duration ? current / duration : 0;

        const fill = document.querySelector('#progress-fill');
        const thumb = document.querySelector('#progress-thumb');
        if (fill) fill.style.width = (ratio * 100) + '%';
        if (thumb) thumb.style.left = (ratio * 100) + '%';

        const elCurrent = document.querySelector('#time-current');
        const elDuration = document.querySelector('#time-duration');
        if (elCurrent) elCurrent.textContent = this._formatTime(current);
        if (elDuration) elDuration.textContent = this._formatTime(duration);
    }

    _formatTime(sec) {
        if (!isFinite(sec) || isNaN(sec)) return '0:00';
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

    _bindUIEvents() {
        const attachControls = () => {
            document.querySelector('#btn-play-pause')?.addEventListener('click', () => this.togglePlay());
            document.querySelector('#btn-prev')?.addEventListener('click', () => this.prev());
            document.querySelector('#btn-next')?.addEventListener('click', () => this.next());
            document.querySelector('#btn-shuffle')?.addEventListener('click', () => this.toggleShuffle());
            document.querySelector('#btn-repeat')?.addEventListener('click', () => this.cycleRepeat());

            const progressTrack = document.querySelector('#progress-track');
            if (progressTrack) {
                progressTrack.addEventListener('click', (e) => {
                    const rect = progressTrack.getBoundingClientRect();
                    this.seek((e.clientX - rect.left) / rect.width);
                });
                progressTrack.addEventListener('mousedown', (e) => {
                    this.isDragging = true;
                    const move = (e2) => {
                        const rect = progressTrack.getBoundingClientRect();
                        const ratio = Math.max(0, Math.min(1, (e2.clientX - rect.left) / rect.width));
                        const fill = document.querySelector('#progress-fill');
                        if (fill) fill.style.width = (ratio * 100) + '%';
                    };
                    const up = (e2) => {
                        this.isDragging = false;
                        const rect = progressTrack.getBoundingClientRect();
                        this.seek(Math.max(0, Math.min(1, (e2.clientX - rect.left) / rect.width)));
                        document.removeEventListener('mousemove', move);
                        document.removeEventListener('mouseup', up);
                    };
                    document.addEventListener('mousemove', move);
                    document.addEventListener('mouseup', up);
                });
            }

            const volumeSlider = document.querySelector('#volume-slider');
            if (volumeSlider) {
                volumeSlider.addEventListener('input', () => this.setVolume(volumeSlider.value));
                this._updateVolumeUI();
            }

            document.querySelector('#btn-mute')?.addEventListener('click', () => {
                this.audio.muted = !this.audio.muted;
                if (this.gainNode && this.audioCtx) {
                    this.gainNode.gain.setValueAtTime(this.audio.muted ? 0 : this.volume, this.audioCtx.currentTime);
                }
                document.querySelector('#btn-mute')?.classList.toggle('active', this.audio.muted);
                const label = document.querySelector('#volume-label');
                if (label) {
                    label.textContent = this.audio.muted ? '0%' : `${Math.round(this.volume * 100)}%`;
                }
            });

            // Play track on table row click
            document.addEventListener('click', (e) => {
                const row = e.target.closest('[data-song-id]');
                if (!row || e.target.closest('.btn-action, button, a')) return;
                const songId = parseInt(row.dataset.songId);
                const queue = window._songQueue || [];
                const idx = queue.findIndex(s => s.id === songId);
                if (idx !== -1) {
                    this.setQueue(queue, idx);
                }
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachControls);
        } else {
            attachControls();
        }

        // Global shortcuts
        document.addEventListener('keydown', (e) => {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
            if (e.code === 'Space') {
                e.preventDefault();
                this.togglePlay();
            }
            if (e.code === 'ArrowRight') this.next();
            if (e.code === 'ArrowLeft') this.prev();
        });
    }

    _updateTrackInfo(song) {
        const el = document.querySelector('#player-title');
        const ea = document.querySelector('#player-artist');
        if (el) el.textContent = song.title || 'Nieznany utwór';
        if (ea) ea.textContent = song.artist || song.album || '';
        document.title = song.artist ? `${song.artist} — ${song.title}` : song.title;
    }

    _highlightRow(songId) {
        document.querySelectorAll('[data-song-id]').forEach(row => {
            const isPlaying = parseInt(row.dataset.songId) === songId;
            row.classList.toggle('playing', isPlaying);
        });
    }

    _updateMediaSession(song) {
        if (!('mediaSession' in navigator)) return;
        navigator.mediaSession.metadata = new MediaMetadata({
            title: song.title,
            artist: song.artist || '',
            album: song.album || '',
        });
        navigator.mediaSession.setActionHandler('play', () => this.play());
        navigator.mediaSession.setActionHandler('pause', () => this.pause());
        navigator.mediaSession.setActionHandler('previoustrack', () => this.prev());
        navigator.mediaSession.setActionHandler('nexttrack', () => this.next());
    }
}

window.player = new MusicPlayer();