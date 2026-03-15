(function () {
    if (window._calScriptLoaded) return;
    window._calScriptLoaded = true;

    // ── Registry of open popup windows ────────────────────────────────────────
    // key: String(noteId) for saved notes, 'new-<timestamp>' for unsaved new notes
    // value: WindowProxy reference
    window._calOpenPopups = new Map();

    // ── Persist open note IDs to localStorage ─────────────────────────────────
    function saveOpenIds() {
        var ids = [];
        window._calOpenPopups.forEach(function (win, key) {
            if (!key.startsWith('new-') && win && !win.closed) {
                ids.push(parseInt(key, 10));
            }
        });
        try { localStorage.setItem('calOpenNoteIds', JSON.stringify(ids)); } catch(e){}
    }

    // ── Remove a popup from the registry ──────────────────────────────────────
    window._calPopupClosed = function (noteId) {
        if (noteId) window._calOpenPopups.delete(String(noteId));
        // Also clean up any closed windows
        window._calOpenPopups.forEach(function (win, key) {
            if (!win || win.closed) window._calOpenPopups.delete(key);
        });
        saveOpenIds();
    };

    // ── When a new note gets its real ID after first save ─────────────────────
    window._calPopupRegistered = function (tempKey, noteId, win) {
        if (tempKey && window._calOpenPopups.has(tempKey)) {
            window._calOpenPopups.delete(tempKey);
        }
        if (noteId) {
            window._calOpenPopups.set(String(noteId), win);
            saveOpenIds();
        }
    };

    // ── Open (or focus) a sticky note popup ───────────────────────────────────
    // noteId   : number|null  – null for a brand-new note
    // content  : string       – current text content
    window._calPopOut = function (noteId, content) {
        var key = noteId ? String(noteId) : ('new-' + Date.now());

        // Re-focus if already open
        if (window._calOpenPopups.has(key)) {
            var existing = window._calOpenPopups.get(key);
            if (existing && !existing.closed) {
                try { existing.focus(); } catch(e){}
                return;
            }
            window._calOpenPopups.delete(key);
        }

        // Build URL — content passed via query param (max 500 chars, safe)
        var qs = [];
        if (noteId)  qs.push('id='      + noteId);
        if (content) qs.push('content=' + encodeURIComponent(content));
        if (!noteId) qs.push('_key='    + encodeURIComponent(key)); // so popup can register itself
        var url = '/note-popup' + (qs.length ? '?' + qs.join('&') : '');

        // Cascade position so multiple notes don't stack exactly
        var offset = window._calOpenPopups.size * 30;
        var popup  = window.open(
            url, '_blank',
            'width=380,height=460,' +
            'top='  + (120 + offset) + ',' +
            'left=' + (200 + offset) + ',' +
            'resizable=yes,scrollbars=no,status=no,toolbar=no,menubar=no,location=no'
        );

        if (!popup) {
            // Popups blocked — let the Alpine component know
            window.dispatchEvent(new CustomEvent('cal-popup-blocked', {
                detail: { noteId: noteId, content: content }
            }));
            return;
        }

        window._calOpenPopups.set(key, popup);
        if (noteId) saveOpenIds();
    };

    // ── Alpine component init — called from x-data init() ────────────────────
    window._calInit = function (self) {
        // Note saved in a popup → update local list
        window.addEventListener('cal-note-saved', function (e) {
            var note = e.detail.note;
            if (!note || !note.id) return;
            var idx = self.notes.findIndex(function (n) { return n.id === note.id; });
            if (idx >= 0) self.notes[idx] = note;
            else self.notes.unshift(note);
        });

        // Note deleted in a popup → remove from local list
        window.addEventListener('cal-note-deleted', function (e) {
            self.notes = self.notes.filter(function (n) { return n.id !== e.detail.id; });
        });

        // Popup closed → clean up registry
        window.addEventListener('cal-popup-closed', function (e) {
            if (window._calPopupClosed) window._calPopupClosed(e.detail.noteId);
        });

        // New note got its real ID after first save → update registry key
        window.addEventListener('cal-popup-registered', function (e) {
            if (window._calPopupRegistered) {
                window._calPopupRegistered(e.detail.tempKey, e.detail.noteId, e.detail.win);
            }
        });

        // Popups blocked → nudge user
        window.addEventListener('cal-popup-blocked', function () {
            alert('Popups are blocked. Please allow popups for this site to use sticky notes.');
        });

        // Restore any notes that were open before navigating away
        self.$nextTick(function () {
            if (typeof window._calRestoreNotes === 'function') {
                window._calRestoreNotes(self.notes);
            }
        });
    };

    // ── Restore notes that were open before navigation ─────────────────────────
    window._calRestoreNotes = function (notes) {
        var ids = [];
        try { ids = JSON.parse(localStorage.getItem('calOpenNoteIds') || '[]'); } catch(e){}
        ids.forEach(function (id) {
            var key = String(id);
            var alreadyOpen = window._calOpenPopups.has(key) && !window._calOpenPopups.get(key).closed;
            if (!alreadyOpen) {
                var note = notes.find(function (n) { return n.id === id; });
                if (note) window._calPopOut(note.id, note.content);
            }
        });
    };
})();
