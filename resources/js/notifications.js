export function registerNotifications() {
  // Prevent double-registration across hot reloads
  if (window.__notifDropdownRegistered) return;
  window.__notifDropdownRegistered = true;

  window.notifDropdown = function notifDropdown() {
    const csrf =
      document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';

    window.__notifToastState = window.__notifToastState || { lastKey: '', lastAt: 0 };

    const mergePayload = (raw) => {
      const base = { ...(raw && typeof raw === 'object' ? raw : {}) };
      const blobs = [
        base.payload,
        base.Payload,
        base.data,
        base.Data,
        base.meta,
        base.Meta,
        base.details,
        base.Details,
      ];
      for (const b of blobs) {
        let p = b;
        if (typeof p === 'string') {
          try {
            p = JSON.parse(p);
          } catch {
            continue;
          }
        }
        if (p && typeof p === 'object' && !Array.isArray(p)) {
          Object.assign(base, p);
        }
      }
      return base;
    };

    const pickInt = (obj, keys) => {
      if (!obj || typeof obj !== 'object') return 0;
      for (const k of keys) {
        if (!(k in obj)) continue;
        const n = parseInt(obj[k], 10);
        if (!Number.isNaN(n) && n > 0) return n;
      }
      return 0;
    };

    const norm = (n) => {
      const topLevelId =
        parseInt(
          n?.id ??
            n?.Id ??
            n?.notificationId ??
            n?.NotificationId ??
            n?.notificationID ??
            n?.NotificationID ??
            0,
          10
        ) || 0;

      const merged = mergePayload(n);
      // IMPORTANT: prefer top-level notification id.
      // Payload blobs can contain their own `id` (e.g., entity/task id), which must not override notification id.
      const id =
        topLevelId ||
        parseInt(
          merged.notificationId ??
            merged.NotificationId ??
            merged.notificationID ??
            merged.NotificationID ??
            merged.id ??
            merged.Id ??
            0,
          10
        ) || 0;
      const msg = (merged.message ?? merged.Message ?? merged.title ?? merged.Title ?? '').toString();
      const isRead = !!(merged.isRead ?? merged.IsRead ?? false);
      const createdAtRaw = (merged.createdAt ?? merged.CreatedAt ?? '').toString();
      const notificationType = (
        merged.notificationType ??
        merged.NotificationType ??
        merged.type ??
        merged.Type ??
        ''
      )
        .toString()
        .trim();
      const entity = (merged.entity ?? merged.Entity ?? '').toString().trim();

      let projectId = pickInt(merged, ['projectId', 'ProjectId', 'projectID', 'ProjectID']);
      let taskId = pickInt(merged, ['taskId', 'TaskId', 'relatedTaskId', 'RelatedTaskId']);
      if (taskId === 0) {
        const eid = pickInt(merged, ['entityId', 'EntityId']);
        const et = `${entity} ${notificationType}`.toLowerCase();
        if (eid > 0 && /task/.test(et)) taskId = eid;
      }
      let commentId = pickInt(merged, ['commentId', 'CommentId', 'taskCommentId', 'TaskCommentId']);

      if (commentId === 0 && /comment/i.test(`${notificationType} ${entity} ${msg}`)) {
        commentId = pickInt(merged, ['commentId', 'CommentId', 'taskCommentId', 'TaskCommentId']);
      }

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
      return {
        id,
        _key: `${id}:${createdAtRaw}:${msg}`,
        message: msg,
        isRead,
        createdAt: createdAtRaw,
        createdAtLabel,
        projectId,
        taskId,
        commentId,
        notificationType,
        entity,
      };
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
      selectedIds: [],
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
        else this.selectedIds = [];
      },

      async refreshUnread() {
        try {
          const r = await fetch('/notifications/unread/count', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
          });
          const data = await r.json();
          this.unreadCount = Math.max(0, parseInt(data?.count ?? 0, 10) || 0);
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
          this.selectedIds = [];
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

      async markUnread(n) {
        if (!n || !n.id || !n.isRead) return;
        try {
          await fetch(`/notifications/${n.id}/unread`, {
            method: 'PUT',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
          });
          n.isRead = false;
          this.unreadCount = Math.max(0, this.items.filter((x) => !x.isRead).length);
        } catch (e) {}
      },

      async toggleReadState(n) {
        if (!n || !n.id) return;
        if (n.isRead) {
          await this.markUnread(n);
        } else {
          await this.markRead(n);
        }
      },

      isSelected(id) {
        const nid = parseInt(id, 10) || 0;
        return this.selectedIds.includes(nid);
      },

      toggleSelect(id) {
        const nid = parseInt(id, 10) || 0;
        if (nid <= 0) return;
        if (this.isSelected(nid)) {
          this.selectedIds = this.selectedIds.filter((x) => x !== nid);
        } else {
          this.selectedIds = [...this.selectedIds, nid];
        }
      },

      toggleSelectAll() {
        const allIds = Array.from(
          new Set(this.items.map((x) => parseInt(x.id, 10) || 0).filter((x) => x > 0))
        );
        if (allIds.length === 0) return;
        if (this.selectedIds.length === allIds.length) {
          this.selectedIds = [];
        } else {
          this.selectedIds = allIds;
        }
      },

      get allSelected() {
        const allIds = Array.from(
          new Set(this.items.map((x) => parseInt(x.id, 10) || 0).filter((x) => x > 0))
        );
        return (
          allIds.length > 0 &&
          allIds.every((id) => this.selectedIds.includes(id))
        );
      },

      get selectedCount() {
        return this.selectedIds.length;
      },

      async markSelectedRead() {
        const ids = [...this.selectedIds];
        if (ids.length === 0) return;
        try {
          await fetch('/notifications/read', {
            method: 'PUT',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ids }),
          });
          this.items = this.items.map((x) => (ids.includes(x.id) ? { ...x, isRead: true } : x));
          this.unreadCount = Math.max(0, this.items.filter((x) => !x.isRead).length);
        } catch (e) {}
      },

      async deleteSelected() {
        const ids = [...this.selectedIds];
        if (ids.length === 0) return;
        try {
          await fetch('/notifications/delete', {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ids }),
          });
          this.items = this.items.filter((x) => !ids.includes(x.id));
          this.selectedIds = [];
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
          });
          this.items = this.items.map((x) => ({ ...x, isRead: true }));
          this.unreadCount = 0;
        } catch (e) {}
      },

      async openNotification(n) {
        if (!n || !n.id) return;
        if (!n.isRead) {
          try {
            await this.markRead(n);
          } catch (e) {}
        }

        let projectId = n.projectId || 0;
        const taskId = n.taskId || 0;
        const commentId = n.commentId || 0;

        if (taskId > 0 && projectId <= 0) {
          try {
            const r = await fetch(
              `/notifications/resolve-task-project?taskId=${encodeURIComponent(String(taskId))}`,
              { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
            );
            if (r.ok) {
              const d = await r.json();
              projectId = parseInt(d.projectId, 10) || 0;
            }
          } catch (e) {}
        }

        if (taskId > 0 && projectId > 0) {
          let url = `/projects/${projectId}/tasks?openTask=${taskId}`;
          if (commentId > 0) url += `&comment=${commentId}`;
          window.location.assign(url);
          return;
        }

        if (projectId > 0) {
          window.location.assign(`/projects/${projectId}/tasks`);
          return;
        }

        window.location.assign('/projects');
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
          this.selectedIds = this.selectedIds.filter((x) => x !== n.id);
          this.unreadCount = Math.max(0, this.items.filter((x) => !x.isRead).length);
        } catch (e) {}
      },
    };
  };
}

