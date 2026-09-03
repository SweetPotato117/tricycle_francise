  fetch('../controllers/notification.php', { credentials: 'same-origin' }).then(response => response.json()).then(result => {
    const count = (result.notifications || []).filter(notification => !notification.isRead).length;
    document.querySelectorAll('.notification-count').forEach(badge => { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; });
  }).catch(() => {});
  fetch('../controllers/pending.php', { credentials: 'same-origin' }).then(response => response.json()).then(result => {
    const count = (result.groups || []).flatMap(group => group.requests || []).filter(request => request.status === 'Pending').length;
    const badge = document.getElementById('navBadge');
    if (badge) { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; }
  }).catch(() => {});

  /* ---------- drawer (mobile sidebar) ---------- */
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('drawerOverlay');
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  function openDrawer() { sidebar.classList.add('open'); overlay.classList.add('open'); }
  function closeDrawer() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }
  hamburgerBtn.addEventListener('click', openDrawer);
  overlay.addEventListener('click', closeDrawer);

  /* ---------- data loaded from the `admins` table ---------- */
  const accountApi = '../controllers/account_management.php';
  let accounts = [];

  const tableBody = document.getElementById('accountTableBody');
  const emptyState = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');
  const pageInfo = document.getElementById('pageInfo');
  const searchInput = document.getElementById('searchInput');
  const filterTabs = document.getElementById('filterTabs');

  let activeFilter = 'All';
  let searchTerm = '';

  async function apiRequest(payload = null) {
    const options = payload ? {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    } : {};
    const response = await fetch(accountApi, options);
    const result = await response.json();
    if (!response.ok || !result.success) {
      const error = new Error(result.message || 'Request failed.');
      error.status = response.status;
      throw error;
    }
    return result;
  }

  async function loadAccounts() {
    try {
      const result = await apiRequest();
      accounts = (result.accounts || []).map(account => ({
        ...account,
        id: Number(account.admin_id ?? account.id ?? 0),
        admin_id: Number(account.admin_id ?? account.id ?? 0)
      }));
      updateStats();
      render();
    } catch (error) {
      if (error.status === 403) {
        window.location.replace('dashboard.html');
        return;
      }
      emptyState.classList.remove('hidden');
      emptyState.querySelector('div').textContent = error.message;
    }
  }

  function initials(first, last) {
    return `${(first[0] || '').toUpperCase()}${(last[0] || '').toUpperCase()}`;
  }

  function formatDateTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) +
      ' · ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  }

  function updateStats() {
    document.getElementById('statTotal').textContent = accounts.length;
    document.getElementById('statSuper').textContent = accounts.filter(a => a.role === 'Super Admin').length;
    document.getElementById('statAdmin').textContent = accounts.filter(a => a.role === 'Admin').length;
    document.getElementById('statInactive').textContent = accounts.filter(a => a.status === 'Inactive').length;
  }

  function render() {
    const term = searchTerm.trim().toLowerCase();
    const filtered = accounts.filter(a => {
      const fullName = `${a.firstName} ${a.lastName}`.toLowerCase();
      const matchesFilter = activeFilter === 'All'
        || (activeFilter === 'Inactive' && a.status === 'Inactive')
        || a.role === activeFilter;
      const matchesSearch = !term
        || fullName.includes(term)
        || a.username.toLowerCase().includes(term)
        || a.email.toLowerCase().includes(term);
      return matchesFilter && matchesSearch;
    });

    tableBody.innerHTML = '';
    emptyState.classList.toggle('hidden', filtered.length !== 0);

    filtered.forEach(a => {
      const tr = document.createElement('tr');
      const isSuper = a.role === 'Super Admin';
      const isActive = a.status === 'Active';

      tr.innerHTML = `
        <td>
          <div class="account-cell">
            <div class="avatar ${isSuper ? 'super' : ''}">${initials(a.firstName, a.lastName)}</div>
            <div>
              <div class="account-name">${a.firstName} ${a.lastName}</div>
              <div class="account-sub">#${String(a.id).padStart(4, '0')}</div>
            </div>
          </div>
        </td>
        <td>${a.username}</td>
        <td>${a.email}</td>
        <td><span class="role-pill ${isSuper ? 'role-super' : 'role-admin'}">${a.role}</span></td>
        <td><span class="status-pill ${isActive ? 'status-active' : 'status-inactive'}">${a.status}</span></td>
        <td><span class="last-login ${a.lastLogin ? '' : 'never'}">${a.lastLogin ? formatDateTime(a.lastLogin) : 'Never logged in'}</span></td>
        <td class="actions-cell">
          <button class="icon-btn edit-btn" data-id="${a.id}" title="Edit">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="icon-btn ${isActive ? 'toggle-on' : 'toggle-off'} status-btn" data-id="${a.id}" title="${isActive ? 'Deactivate' : 'Activate'}">
            ${isActive
              ? `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.9" y1="4.9" x2="19.1" y2="19.1"/></svg>`
              : `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>`}
          </button>
          <button class="icon-btn danger delete-btn" data-id="${a.id}" title="Remove">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
          </button>
        </td>
      `;
      tableBody.appendChild(tr);
    });

    resultCount.textContent = `${filtered.length} account${filtered.length === 1 ? '' : 's'}`;
    pageInfo.textContent = `Showing ${filtered.length} of ${accounts.length} accounts`;

    tableBody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => openEditModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.status-btn').forEach(btn => btn.addEventListener('click', () => openStatusModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => openDeleteModal(Number(btn.dataset.id))));
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

  /* ---------- password visibility toggles ---------- */
  document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });

  /* ---------- role note ---------- */
  const roleSelect = document.getElementById('fRole');
  const roleNote = document.getElementById('roleNote');
  function updateRoleNote() {
    roleNote.textContent = roleSelect.value === 'Super Admin'
      ? 'Super Admins have full access, including creating, editing, and deactivating other accounts.'
      : 'Admins can manage drivers, tricycles, franchises, and renewals, but cannot manage other accounts.';
  }
  roleSelect.addEventListener('change', updateRoleNote);

  /* ---------- add / edit modal ---------- */
  const accountModalOverlay = document.getElementById('accountModalOverlay');
  const accountModalTitle = document.getElementById('accountModalTitle');
  const accountForm = document.getElementById('accountForm');
  const fPassword = document.getElementById('fPassword');
  const fPasswordConfirm = document.getElementById('fPasswordConfirm');
  const fPasswordLabel = document.getElementById('fPasswordLabel');
  const passwordHint = document.getElementById('passwordHint');
  let editingId = null;

  function openAddModal() {
    editingId = null;
    accountModalTitle.textContent = 'Add Account';
    accountForm.reset();
    fPasswordLabel.textContent = 'Password';
    fPassword.placeholder = 'Enter password';
    fPassword.required = true;
    fPasswordConfirm.required = true;
    passwordHint.textContent = 'Minimum 8 characters.';
    passwordHint.classList.remove('error');
    updateRoleNote();
    accountModalOverlay.classList.add('open');
  }

  function openEditModal(id) {
    const a = accounts.find(x => x.id === id);
    if (!a) return;
    editingId = id;
    accountModalTitle.textContent = 'Edit Account';
    document.getElementById('fFirstName').value = a.firstName;
    document.getElementById('fLastName').value = a.lastName;
    document.getElementById('fUsername').value = a.username;
    document.getElementById('fEmail').value = a.email;
    document.getElementById('fAddress').value = a.address || '';
    document.getElementById('fRole').value = a.role;
    document.getElementById('fStatus').value = a.status;
    fPassword.value = '';
    fPasswordConfirm.value = '';
    fPasswordLabel.textContent = 'New Password (optional)';
    fPassword.placeholder = 'Leave blank to keep current password';
    fPassword.required = false;
    fPasswordConfirm.required = false;
    passwordHint.textContent = 'Leave blank to keep the current password.';
    passwordHint.classList.remove('error');
    updateRoleNote();
    accountModalOverlay.classList.add('open');
  }

  function closeAccountModal() { accountModalOverlay.classList.remove('open'); }

  document.getElementById('addAccountBtn').addEventListener('click', openAddModal);
  document.getElementById('accountModalClose').addEventListener('click', closeAccountModal);
  document.getElementById('accountCancelBtn').addEventListener('click', closeAccountModal);
  accountModalOverlay.addEventListener('click', (e) => { if (e.target === accountModalOverlay) closeAccountModal(); });

  accountForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const pw = fPassword.value;
    const pwConfirm = fPasswordConfirm.value;

    // password validation: required for new accounts, optional for edits
    if (!editingId || pw || pwConfirm) {
      if (pw.length < 8) {
        passwordHint.textContent = 'Password must be at least 8 characters.';
        passwordHint.classList.add('error');
        fPassword.focus();
        return;
      }
      if (pw !== pwConfirm) {
        passwordHint.textContent = 'Passwords do not match.';
        passwordHint.classList.add('error');
        fPasswordConfirm.focus();
        return;
      }
    }

    const payload = {
      firstName: document.getElementById('fFirstName').value.trim(),
      lastName: document.getElementById('fLastName').value.trim(),
      username: document.getElementById('fUsername').value.trim(),
      email: document.getElementById('fEmail').value.trim(),
      address: document.getElementById('fAddress').value.trim(),
      role: document.getElementById('fRole').value,
      status: document.getElementById('fStatus').value,
      password: pw
    };

    try {
      await apiRequest({ action: editingId ? 'update' : 'create', ...(editingId ? { id: editingId } : {}), ...payload });
      await loadAccounts();
      closeAccountModal();
    } catch (error) {
      passwordHint.textContent = error.message;
      passwordHint.classList.add('error');
    }
  });

  /* ---------- activate / deactivate modal ---------- */
  const statusModalOverlay = document.getElementById('statusModalOverlay');
  let statusTargetId = null;

  function openStatusModal(id) {
    const a = accounts.find(x => x.id === id);
    if (!a) return;
    statusTargetId = id;
    const willDeactivate = a.status === 'Active';
    document.getElementById('statusModalTitle').textContent = willDeactivate ? 'Deactivate Account' : 'Activate Account';
    document.getElementById('statusTargetName').textContent = `${a.firstName} ${a.lastName}`;
    document.getElementById('statusModalText').innerHTML = willDeactivate
      ? `Are you sure you want to deactivate <b>${a.firstName} ${a.lastName}</b>? They will no longer be able to log in.`
      : `Are you sure you want to reactivate <b>${a.firstName} ${a.lastName}</b>? They will regain access to the system.`;
    const confirmBtn = document.getElementById('statusConfirmBtn');
    confirmBtn.textContent = willDeactivate ? 'Deactivate' : 'Activate';
    confirmBtn.className = willDeactivate ? 'btn-danger' : 'btn-primary';
    statusModalOverlay.classList.add('open');
  }

  function closeStatusModal() { statusModalOverlay.classList.remove('open'); statusTargetId = null; }

  document.getElementById('statusModalClose').addEventListener('click', closeStatusModal);
  document.getElementById('statusCancelBtn').addEventListener('click', closeStatusModal);
  statusModalOverlay.addEventListener('click', (e) => { if (e.target === statusModalOverlay) closeStatusModal(); });

  document.getElementById('statusConfirmBtn').addEventListener('click', async () => {
    if (statusTargetId != null) {
      const a = accounts.find(x => x.id === statusTargetId);
      if (a) {
        try {
          await apiRequest({ action: 'status', id: a.id, status: a.status === 'Active' ? 'Inactive' : 'Active' });
          await loadAccounts();
        } catch (error) {
          alert(error.message);
        }
      }
    }
    closeStatusModal();
  });

  /* ---------- delete modal ---------- */
  const deleteModalOverlay = document.getElementById('deleteModalOverlay');
  let deletingId = null;

  function openDeleteModal(id) {
    const a = accounts.find(x => x.id === id);
    if (!a) return;
    deletingId = id;
    document.getElementById('deleteTargetName').textContent = `${a.firstName} ${a.lastName}`;
    deleteModalOverlay.classList.add('open');
  }

  function closeDeleteModal() { deleteModalOverlay.classList.remove('open'); deletingId = null; }

  document.getElementById('deleteModalClose').addEventListener('click', closeDeleteModal);
  document.getElementById('deleteCancelBtn').addEventListener('click', closeDeleteModal);
  deleteModalOverlay.addEventListener('click', (e) => { if (e.target === deleteModalOverlay) closeDeleteModal(); });

  document.getElementById('deleteConfirmBtn').addEventListener('click', async () => {
    if (deletingId != null) {
      try {
        await apiRequest({ action: 'delete', id: deletingId });
        await loadAccounts();
      } catch (error) {
        alert(error.message);
      }
    }
    closeDeleteModal();
  });

  /* ---------- init ---------- */
  loadAccounts();
