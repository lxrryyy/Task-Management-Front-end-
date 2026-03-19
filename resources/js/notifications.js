export function registerNotifications() {
  // Prevent double-registration across hot reloads
  if (window.__notifDropdownRegistered) return;
  window.__notifDropdownRegistered = true;

  window.notifDropdown = function notifDropdown() {
    const csrf =
      document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';

    window.__notifToastState = window.__notifToastState || { lastKey: '', lastAt: 0 };

    const norm = (n) => {
      const id = parseInt(n.id ?? n.Id ?? 0, 10) || 0;
      const msg = (n.message ?? n.Message ?? '').toString();
      const isRead = !!(n.isRead ?? n.IsRead ?? false);
      const createdAtRaw = (n.createdAt ?? n.CreatedAt ?? '').toString();
      let createdAtLabel = createdAtRaw;
      if (createdAtRaw) {
        const d = new Date(createdAtRaw);
        if (!Number.isNaN(d.getTime())) {
          createdAtLabel = d.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
          });
        }
      }
      return { id, message: msg, isRead, createdAt: createdAtRaw, createdAtLabel };
    };

    const showToast = (message) => {
      const key = String(message || '').trim();
      const now = Date.now();
      if (
        key &&
        window.__notifToastState.lastKey === key &&
        now - window.__notifToastState.lastAt < 1500
      ) {
        return;
      }
      window.__notifToastState.lastKey = key;
      window.__notifToastState.lastAt = now;

      const container = document.getElementById('toast-container');
      if (!container) return;

      const toast = document.createElement('div');
      toast.className = [
        'pointer-events-auto',
        'flex items-center gap-3',
        'px-4 py-3',
        'rounded-xl shadow-xl',
        'bg-slate-900 text-slate-50',
        'text-sm',
        'w-full',
        'translate-x-full opacity-0',
        'transition transform duration-200 ease-out',
      ].join(' ');
      toast.innerHTML = `
        <svg class="w-5 h-5 shrink-0 text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span class="flex-1 leading-snug">${key}</span>
        <button class="text-slate-200 hover:text-white text-base leading-none px-0.5" aria-label="Close">&times;</button>
      `;
      toast.querySelector('button')?.addEventListener('click', () => toast.remove());
      toast.setAttribute('data-toast', '');
      container.appendChild(toast);

      setTimeout(() => toast.classList.remove('translate-x-full', 'opacity-0'), 10);
      setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
      }, 4000);
    };

    return {
      open: false,
      loading: false,
      items: [],
      unreadCount: 0,
      _baselineSet: false,
      _pollHandle: null,

      async init() {
        if (window.__notifPollingStarted) return;
        window.__notifPollingStarted = true;

        await this.refreshUnread();
        this._baselineSet = true;
        this._pollHandle = setInterval(async () => {
          const prevCount = this.unreadCount;
          await this.refreshUnread();
          if (this._baselineSet && this.unreadCount > prevCount) {
            const diff = this.unreadCount - prevCount;
            showToast(`You have ${diff} new notification${diff > 1 ? 's' : ''}!`);
          }
        }, 10000);
      },

      toggle() {
        this.open = !this.open;
        if (this.open) this.load();
      },

      async refreshUnread() {
        try {
          const r = await fetch('/notifications/unread', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
          });
          const data = await r.json();
          this.unreadCount = Array.isArray(data)
            ? data.filter((x) => !(x.isRead ?? x.IsRead)).length
            : 0;
        } catch (e) {
          // ignore
        }
      },

      async load() {
        this.loading = true;
        try {
          const r = await fetch('/notifications', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
          });
          const data = await r.json();
          this.items = Array.isArray(data) ? data.map(norm) : [];
          this.unreadCount = this.items.filter((x) => !x.isRead).length;
        } catch (e) {
          this.items = [];
        } finally {
          this.loading = false;
        }
      },

      async markRead(n) {
        if (!n || !n.id || n.isRead) return;
        try {
          await fetch(`/notifications/${n.id}/read`, {
            method: 'PUT',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
          });
          n.isRead = true;
          this.unreadCount = Math.max(0, this.items.filter((x) => !x.isRead).length);
        } catch (e) {}
      },

      async markAllRead() {
        if (this.unreadCount <= 0) return;
        try {
          await fetch('/notifications/read-all', {
            method: 'PUT',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
          });
          this.items = this.items.map((x) => ({ ...x, isRead: true }));
          this.unreadCount = 0;
        } catch (e) {}
      },

      async remove(n) {
        if (!n || !n.id) return;
        try {
          await fetch(`/notifications/${n.id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
          });
          this.items = this.items.filter((x) => x.id !== n.id);
          this.unreadCount = Math.max(0, this.items.filter((x) => !x.isRead).length);
        } catch (e) {}
      },
    };
  };
}

