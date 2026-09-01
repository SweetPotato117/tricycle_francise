  /* ---------- drawer (mobile sidebar) ---------- */
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('drawerOverlay');
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  function openDrawer() { sidebar.classList.add('open'); overlay.classList.add('open'); }
  function closeDrawer() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }
  hamburgerBtn.addEventListener('click', openDrawer);
  overlay.addEventListener('click', closeDrawer);

  /* ---------- data loaded from the `notifications` table ---------- */
  const notificationApi = '../controllers/notification.php';
  let notifications = [];

  const notifList = document.getElementById('notifList');
  const emptyState = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');
  const pageInfo = document.getElementById('pageInfo');
  const searchInput = document.getElementById('searchInput');
  const filterTabs = document.getElementById('filterTabs');
  const markAllBtn = document.getElementById('markAllBtn');

  let activeFilter = 'All';
  let searchTerm = '';

  async function apiRequest(payload) {
    const response = await fetch(notificationApi, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Request failed.');
    return result;
  }

  async function loadNotifications() {
    try {
      const response = await fetch(notificationApi);
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load notifications.');
      notifications = result.notifications;
      updateStats();
      render();
    } catch (error) {
      emptyState.classList.remove('hidden');
      emptyState.querySelector('div').textContent = error.message;
    }
  }

  function severityIcon(severity) {
    if (severity === 'urgent') {
      return `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;
    }
    if (severity === 'warning') {
      return `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
    }
    return `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;
  }

  function typeTagClass(type) {
    if (type === 'Admin') return 'type-admin';
    if (type === 'Renewal') return 'type-renewal';
    if (type === 'Franchise') return 'type-franchise';
    if (type === 'Tricycle') return 'type-tricycle';
    return 'type-driver';
  }

  function timeAgo(iso) {
    const now = new Date();
    const then = new Date(iso);
    const diffMs = now - then;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHr = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHr / 24);
    if (diffMin < 1) return 'Just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHr < 24) return `${diffHr}h ago`;
    if (diffDay < 7) return `${diffDay}d ago`;
    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }

  function updateStats() {
    document.getElementById('statTotal').textContent = notifications.length;
    document.getElementById('statUnread').textContent = notifications.filter(n => !n.isRead).length;
    document.getElementById('statUrgent').textContent = notifications.filter(n => n.severity === 'urgent').length;
    document.getElementById('statWarning').textContent = notifications.filter(n => n.severity === 'warning').length;

    document.getElementById('countAll').textContent = notifications.length;
    document.getElementById('countUnread').textContent = notifications.filter(n => !n.isRead).length;
    document.getElementById('countAdmin').textContent = notifications.filter(n => n.type === 'Admin').length;
    document.getElementById('countRenewal').textContent = notifications.filter(n => n.type === 'Renewal').length;
    document.getElementById('countFranchise').textContent = notifications.filter(n => n.type === 'Franchise').length;
    document.getElementById('countTricycle').textContent = notifications.filter(n => n.type === 'Tricycle').length;
    document.getElementById('countDriver').textContent = notifications.filter(n => n.type === 'Driver').length;

    const unreadCount = notifications.filter(n => !n.isRead).length;
    const bnBadge = document.getElementById('bnBadge');
    document.querySelectorAll('.notification-count').forEach(badge => {
      badge.textContent = unreadCount;
      badge.style.display = unreadCount > 0 ? 'flex' : 'none';
    });
    bnBadge.textContent = unreadCount;
    bnBadge.style.display = unreadCount > 0 ? 'flex' : 'none';

    markAllBtn.disabled = unreadCount === 0;
  }

  function render() {
    const term = searchTerm.trim().toLowerCase();
    const filtered = notifications.filter(n => {
      const matchesFilter = activeFilter === 'All'
        || (activeFilter === 'Unread' && !n.isRead)
        || n.type === activeFilter;
      const matchesSearch = !term
        || n.title.toLowerCase().includes(term)
        || n.message.toLowerCase().includes(term)
        || n.related.toLowerCase().includes(term);
      return matchesFilter && matchesSearch;
    });

    // sort: unread first, then newest first
    filtered.sort((a, b) => {
      if (a.isRead !== b.isRead) return a.isRead ? 1 : -1;
      return new Date(b.createdAt) - new Date(a.createdAt);
    });

    notifList.innerHTML = '';
    emptyState.classList.toggle('hidden', filtered.length !== 0);

    filtered.forEach(n => {
      const item = document.createElement('div');
      item.className = `notif-item ${n.isRead ? '' : 'unread'}`;
      item.innerHTML = `
        <div class="notif-icon severity-${n.severity}">${severityIcon(n.severity)}</div>
        <div class="notif-body">
          <div class="notif-top-row">
            <div class="notif-title-row">
              ${n.isRead ? '' : '<span class="unread-dot"></span>'}
              <span class="notif-title">${n.title}</span>
            </div>
            <span class="notif-time">${timeAgo(n.createdAt)}</span>
          </div>
          <div class="notif-message">${n.message}</div>
          <div class="notif-meta-row">
            <span class="type-tag ${typeTagClass(n.type)}">${n.type}</span>
          </div>
        </div>
        <div class="notif-actions">
          ${!n.isRead ? `
          <button class="icon-btn read-btn" data-id="${n.id}" title="Mark as read">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          </button>` : ''}
          <button class="icon-btn danger delete-btn" data-id="${n.id}" title="Delete">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
          </button>
        </div>
      `;

      item.addEventListener('click', async (e) => {
        if (e.target.closest('.notif-actions')) return;
        if (!n.isRead) {
          try {
            await apiRequest({ action: 'read', id: n.id });
            n.isRead = true;
            updateStats();
            render();
          } catch (error) { alert(error.message); }
        }
      });

      notifList.appendChild(item);
    });

    resultCount.textContent = `${filtered.length} notification${filtered.length === 1 ? '' : 's'}`;
    pageInfo.textContent = `Showing ${filtered.length} of ${notifications.length} notifications`;

    notifList.querySelectorAll('.read-btn').forEach(btn => btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const n = notifications.find(x => x.id === Number(btn.dataset.id));
      if (n) {
        try { await apiRequest({ action: 'read', id: n.id }); n.isRead = true; updateStats(); render(); }
        catch (error) { alert(error.message); }
      }
    }));

    notifList.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      try {
        await apiRequest({ action: 'delete', id: Number(btn.dataset.id) });
        notifications = notifications.filter(x => x.id !== Number(btn.dataset.id));
        updateStats();
        render();
      } catch (error) { alert(error.message); }
    }));
  }

  filterTabs.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      filterTabs.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeFilter = tab.dataset.filter;
      render();
    });
  });

  searchInput.addEventListener('input', (e) => { searchTerm = e.target.value; render(); });

  markAllBtn.addEventListener('click', async () => {
    try {
      await apiRequest({ action: 'mark_all_read' });
      notifications.forEach(n => n.isRead = true);
      updateStats();
      render();
    } catch (error) { alert(error.message); }
  });

  /* ---------- init ---------- */
  loadNotifications();
  fetch('../controllers/pending.php', { credentials: 'same-origin' }).then(response => response.json()).then(result => {
    const count = (result.groups || []).flatMap(group => group.requests || []).filter(request => request.status === 'Pending').length;
    const badge = document.getElementById('navBadge');
    if (badge) { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; }
  }).catch(() => {});
