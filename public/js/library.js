/**
 * Library management: upload, scan, delete songs
 */

function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => toast.classList.add('show'));
    });

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}

async function uploadMp3(file) {
    const formData = new FormData();
    formData.append('mp3', file);

    const resp = await fetch('/songs', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    let data;
    try {
        data = await resp.json();
    } catch (e) {
        if (resp.status === 403 || resp.status === 401) {
            throw new Error('Wymagane zalogowanie jako Administrator.');
        }
        throw new Error('Błąd serwera (kod ' + resp.status + ').');
    }

    if (!resp.ok) {
        throw new Error(data.error || 'Błąd przesyłania pliku MP3.');
    }

    return data.song;
}

async function scanLibrary() {
    const resp = await fetch('/library/scan', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    let data;
    try {
        data = await resp.json();
    } catch (e) {
        if (resp.status === 403 || resp.status === 401) {
            throw new Error('Wymagane zalogowanie jako Administrator.');
        }
        throw new Error('Błąd połączenia z serwerem podczas skanowania.');
    }

    if (!resp.ok) {
        throw new Error(data.error || 'Błąd skanowania biblioteki.');
    }

    return data;
}

async function deleteSong(songId, deleteFile = true) {
    const resp = await fetch(`/songs/${songId}?deleteFile=${deleteFile}`, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    let data;
    try {
        data = await resp.json();
    } catch (e) {
        throw new Error('Brak uprawnień do usunięcia pliku.');
    }

    if (!resp.ok) {
        throw new Error(data.error || 'Błąd usuwania utworu.');
    }

    return data;
}

// Get or dynamically create file input
function getOrCreateUploadInput() {
    let input = document.querySelector('#upload-input');
    if (!input) {
        input = document.createElement('input');
        input.type = 'file';
        input.id = 'upload-input';
        input.accept = '.mp3,audio/mpeg';
        input.multiple = true;
        input.style.display = 'none';
        document.body.appendChild(input);
    }
    return input;
}

function handleFilesUpload(files) {
    const mp3Files = Array.from(files).filter(f => f.name.toLowerCase().endsWith('.mp3'));
    if (!mp3Files.length) {
        showToast('Wybierz pliki w formacie .mp3', 'error');
        return;
    }

    mp3Files.forEach(async (file) => {
        try {
            showToast(`Wgrywanie: ${file.name}...`, 'info');
            const song = await uploadMp3(file);
            showToast(`Dodano: ${song.title}`, 'success');
            addSongRow(song);
        } catch (err) {
            showToast(err.message, 'error');
        }
    });
}

function initLibrary() {
    const uploadInput = getOrCreateUploadInput();
    uploadInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files.length) {
            handleFilesUpload(e.target.files);
            uploadInput.value = '';
        }
    });

    // Global click listener for all scan and upload buttons (ID or data-action)
    document.addEventListener('click', async (e) => {
        // Upload button clicked
        const uploadBtn = e.target.closest('#btn-upload, [data-action="upload"], .btn-upload');
        if (uploadBtn) {
            e.preventDefault();
            const input = getOrCreateUploadInput();
            input.click();
            return;
        }

        // Scan button clicked
        const scanBtn = e.target.closest('#btn-scan, [data-action="scan"], .btn-scan');
        if (scanBtn) {
            e.preventDefault();
            scanBtn.disabled = true;
            const originalHtml = scanBtn.innerHTML;
            scanBtn.innerHTML = '<span>Skanowanie...</span>';
            scanBtn.style.opacity = '0.7';

            try {
                showToast('Rozpoczęto skanowanie biblioteki...', 'info');
                const result = await scanLibrary();
                showToast(result.message, 'success');
                if (result.added > 0) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                scanBtn.disabled = false;
                scanBtn.innerHTML = originalHtml;
                scanBtn.style.opacity = '1';
            }
            return;
        }

        // Delete button clicked
        const deleteBtn = e.target.closest('[data-delete-song]');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const songId = deleteBtn.dataset.deleteSong;
            const songName = deleteBtn.dataset.songName || 'ten utwór';

            if (!confirm(`Czy na pewno chcesz usunąć "${songName}" trwale z dysku serwera?`)) return;

            try {
                await deleteSong(songId, true);
                const row = document.querySelector(`[data-song-id="${songId}"]`);
                row?.remove();
                if (window._songQueue) {
                    window._songQueue = window._songQueue.filter(s => s.id !== parseInt(songId));
                }
                showToast('Utwór został usunięty.', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
            return;
        }
    });

    // Drag and drop anywhere on window
    window.addEventListener('dragover', (e) => {
        e.preventDefault();
    });

    window.addEventListener('drop', (e) => {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
            handleFilesUpload(e.dataTransfer.files);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLibrary);
} else {
    initLibrary();
}

function addSongRow(song) {
    const tbody = document.querySelector('#songs-tbody');
    if (!tbody) {
        setTimeout(() => location.reload(), 1200);
        return;
    }

    const count = tbody.querySelectorAll('tr').length + 1;
    const tr = document.createElement('tr');
    tr.dataset.songId = song.id;
    tr.innerHTML = `
        <td class="col-num">
            <span class="row-num">${count}</span>
            <svg class="playing-indicator" viewBox="0 0 24 24" fill="currentColor">
                <rect x="2" y="10" width="4" height="10" rx="1"><animate attributeName="height" values="10;4;10" dur="1s" repeatCount="indefinite"/><animate attributeName="y" values="10;14;10" dur="1s" repeatCount="indefinite"/></rect>
                <rect x="10" y="6" width="4" height="14" rx="1"><animate attributeName="height" values="14;6;14" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="6;10;6" dur="0.8s" repeatCount="indefinite"/></rect>
                <rect x="18" y="8" width="4" height="12" rx="1"><animate attributeName="height" values="12;5;12" dur="1.2s" repeatCount="indefinite"/><animate attributeName="y" values="8;12;8" dur="1.2s" repeatCount="indefinite"/></rect>
            </svg>
        </td>
        <td class="col-title">${escHtml(song.title)}</td>
        <td>${escHtml(song.artist || '')}</td>
        <td>${escHtml(song.album || '')}</td>
        <td class="col-plays"><span class="plays-badge">${song.playCount || 0}</span></td>
        <td class="col-duration">${song.formattedDuration || '--:--'}</td>
        <td class="col-actions">
            <button class="btn btn-ghost btn-sm btn-icon btn-action" data-add-to-playlist="${song.id}" title="Dodaj do playlisty">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <button class="btn btn-ghost btn-sm btn-icon btn-action btn-danger" data-delete-song="${song.id}" data-song-name="${escHtml(song.title)}" title="Usuń utwór">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    if (window._songQueue) window._songQueue.push(song);
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}