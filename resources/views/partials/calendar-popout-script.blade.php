<script>
// Calendar sticky-note Pop Out (Picture-in-Picture window)
// Called by Alpine methods openNewNote/openViewNote via window._calPopOut(id, content)
(function () {
    window._calCsrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';

    async function fetchNotes() {
        const r = await fetch('/notes', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        const data = await r.json();
        return Array.isArray(data) ? data : [];
    }

    window._calPopOut = async function (noteId, noteContent) {
        if (!('documentPictureInPicture' in window)) {
            const currentUrl = window.location.origin || window.location.href || 'this site';
            const httpsHint = window.isSecureContext
                ? 'Document Picture-in-Picture is not available in this browser.'
                : 'This feature requires a secure URL (HTTPS), or localhost in development.';
            alert(
                'Unable to open Pop Out notes.\n\n' +
                httpsHint + '\n' +
                'Current URL: ' + currentUrl + '\n\n' +
                'Please open this project using HTTPS to enable this feature.'
            );
            return;
        }

        const pip = await documentPictureInPicture.requestWindow({ width: 320, height: 420 });

        pip.document.head.innerHTML =
            '<meta charset="UTF-8">' +
            '<link href="https://fonts.bunny.net/css?family=ubuntu:400,500,600&display=swap" rel="stylesheet">' +
            '<style>' +
            '*, *::before, *::after { box-sizing:border-box; }' +
            'html, body { width:100%; height:100%; margin:0; font-family:Ubuntu, sans-serif; background:#F0EFEF; }' +
            '.hdr{background:#102B3C;color:#fff;padding:10px 12px;display:flex;align-items:center;justify-content:space-between;}' +
            '.ttl{font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;}' +
            '.btn{background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.22);color:#fff;border-radius:6px;padding:4px 8px;font-size:11px;cursor:pointer;}' +
            '.btn:disabled{opacity:.5;cursor:not-allowed;}' +
            '.wrap{padding:10px 12px;display:flex;flex-direction:column;gap:10px;height:calc(100% - 44px);}' +
            '#editor{flex:1;display:flex;flex-direction:column;}' +
            '.row{display:flex;gap:8px;}' +
            'input,textarea{width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;font-size:12px;outline:none;background:#fff;}' +
            'textarea{min-height:110px;resize:none;}' +
            '.list{flex:1;overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;}' +
            '.item{padding:10px 10px;border-bottom:1px solid #f3f4f6;cursor:pointer;display:flex;align-items:flex-start;justify-content:space-between;gap:8px;}' +
            '.item:last-child{border-bottom:none;}' +
            '.item p{margin:0;font-size:12px;line-height:1.35;color:#374151;white-space:pre-wrap;word-break:break-word;flex:1;}' +
            '.x{border:none;background:none;color:#9ca3af;cursor:pointer;font-size:14px;line-height:1;padding:0 2px;}' +
            '.x:hover{color:#ef4444;}' +
            '.muted{color:#9ca3af;font-size:12px;text-align:center;padding:18px 10px;}' +
            '</style>';

        pip.document.body.innerHTML =
            '<div class="hdr">' +
                '<div class="ttl">To-do</div>' +
                '<button id="modeBtn" class="btn"></button>' +
            '</div>' +
            '<div class="wrap">' +
                '<div id="editor"></div>' +
                '<div class="list" id="list"></div>' +
            '</div>';

        const modeBtn = pip.document.getElementById('modeBtn');
        const editor = pip.document.getElementById('editor');
        const listEl = pip.document.getElementById('list');

        let mode = noteId ? 'edit' : 'new'; // 'new' | 'edit' | 'list'
        let currentId = noteId;

        function escapeHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        async function refreshCalendarList() {
            // force Livewire/Alpine section to refresh by reloading notes via a small fetch and storage in window var
            try { window.dispatchEvent(new CustomEvent('cal-notes-refresh')); } catch (e) {}
        }

        async function renderList() {
            const notes = await fetchNotes();
            if (!notes.length) {
                listEl.innerHTML = '<div class="muted">No notes yet.</div>';
                return;
            }
            listEl.innerHTML = notes.map(n =>
                '<div class="item" data-id="' + n.id + '">' +
                    '<p>' + escapeHtml((n.content ?? '')).slice(0, 240) + '</p>' +
                    '<button class="x" data-del="' + n.id + '">✕</button>' +
                '</div>'
            ).join('');

            listEl.querySelectorAll('[data-id]').forEach(el => {
                el.onclick = (e) => {
                    if (e.target && e.target.getAttribute('data-del')) return;
                    const id = parseInt(el.getAttribute('data-id'), 10);
                    const note = notes.find(nn => nn.id === id);
                    currentId = id;
                    mode = 'edit';
                    render();
                    if (note) {
                        pip.document.getElementById('content').value = note.content || '';
                    }
                };
            });
            listEl.querySelectorAll('[data-del]').forEach(btn => {
                btn.onclick = async (e) => {
                    e.stopPropagation();
                    const id = parseInt(btn.getAttribute('data-del'), 10);
                    await fetch('/notes/' + id, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window._calCsrf },
                        credentials: 'same-origin',
                    });
                    await renderList();
                    await refreshCalendarList();
                };
            });
        }

        async function render() {
            if (mode === 'new') {
                modeBtn.textContent = 'View list';
                editor.style.display = 'flex';
                listEl.style.display = 'none';
                editor.innerHTML =
                    '<div class="row">' +
                        '<input id="content" type="text" placeholder="Write a note..." />' +
                        '<button id="save" class="btn" style="background-color:#102b3c;">Add</button>' +
                    '</div>';
                pip.document.getElementById('save').onclick = async () => {
                    const content = pip.document.getElementById('content').value.trim();
                    if (!content) return;
                    await fetch('/notes', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window._calCsrf },
                        body: JSON.stringify({ content, isPinned: false }),
                        credentials: 'same-origin',
                    });
                    pip.document.getElementById('content').value = '';
                    await refreshCalendarList();
                };
                pip.document.getElementById('content').onkeydown = (e) => {
                    if (e.key === 'Enter') pip.document.getElementById('save').click();
                };
                return;
            }

            if (mode === 'edit') {
                modeBtn.textContent = 'View list';
                editor.style.display = 'flex';
                editor.innerHTML =
                    '<div style="display:flex;flex-direction:column;height:100%;">' +
                        '<textarea id="content" placeholder="Edit note..." style="flex:1;resize:none;"></textarea>' +
                        '<div class="row" style="justify-content:flex-end;margin-top:8px;">' +
                            '<button id="update" class="btn" style="background-color:#102b3c;">Save</button>' +
                        '</div>' +
                    '</div>';
                listEl.style.display = 'none';
                pip.document.getElementById('content').value = noteContent || '';
                pip.document.getElementById('update').onclick = async () => {
                    const content = pip.document.getElementById('content').value.trim();
                    await fetch('/notes/' + currentId, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window._calCsrf },
                        body: JSON.stringify({ content }),
                        credentials: 'same-origin',
                    });
                    await refreshCalendarList();
                };
                return;
            }

            // list mode
            modeBtn.textContent = 'New note';
            editor.innerHTML = '';
            editor.style.display = 'none';
            listEl.style.display = 'block';
            await renderList();
        }

        modeBtn.onclick = async () => {
            if (mode === 'list') {
                mode = 'new';
            } else {
                mode = 'list';
            }
            await render();
        };

        // If opened from clicking an existing note, go edit; otherwise new.
        if (noteId) {
            mode = 'edit';
        }
        await render();

        // Mark closed
        pip.addEventListener('pagehide', function () {
            try { if (window._calPopupClosed) window._calPopupClosed(currentId); } catch (e) {}
        });
    };
})();
</script>
