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

  /* ---------- data loaded from the tricycle and assignment tables ---------- */
  const colorMap = { Red: '#E5484D', Blue: '#2456C7', Black: '#1B2A4A', White: '#E7E9EC', Green: '#2E9E4F', Yellow: '#F2B90F', Silver: '#B9BEC7', Maroon: '#7A1F2B' };
  const tricycleApi = '../controllers/tricycle_management.php';
  let tricycles = [];

  async function apiRequest(payload = null) {
    const options = payload ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) } : {};
    const response = await fetch(tricycleApi, options);
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Request failed.');
    return result;
  }

  function populateAssignmentOptions(result) {
    const driverSelect = document.getElementById('fDriver');
    const franchiseSelect = document.getElementById('fFranchise');
    driverSelect.innerHTML = '<option value="">Unassigned</option>' + result.drivers.filter(d => d.status === 'Approved').map(d => `<option value="${d.driver_id}">${d.full_name}</option>`).join('');
    franchiseSelect.innerHTML = '<option value="">Unassigned</option>' + result.franchises.map(f => `<option value="${f.franchise_id}">${f.franchise_name}</option>`).join('');
  }

  async function loadTricycles() {
    try {
      const result = await apiRequest();
      tricycles = result.tricycles;
      populateAssignmentOptions(result);
      updateStats();
      render();
    } catch (error) {
      emptyState.classList.remove('hidden');
      emptyState.querySelector('div').textContent = error.message;
    }
  }

  const tableBody = document.getElementById('trikeTableBody');
  const emptyState = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');
  const pageInfo = document.getElementById('pageInfo');
  const searchInput = document.getElementById('searchInput');
  const filterTabs = document.getElementById('filterTabs');

  let activeFilter = 'All';
  let searchTerm = '';

  function isAssigned(t) { return !!(t.franchise || t.driver); }

  function updateStats() {
    document.getElementById('statTotal').textContent = tricycles.length;
    document.getElementById('statFranchise').textContent = tricycles.filter(t => t.franchise).length;
    document.getElementById('statDriver').textContent = tricycles.filter(t => t.driver).length;
    document.getElementById('statUnassigned').textContent = tricycles.filter(t => (t.status || 'Pending') === 'Pending').length;
  }

  function render() {
    const term = searchTerm.trim().toLowerCase();
    const filtered = tricycles.filter(t => {
      const matchesFilter = activeFilter === 'All' || t.status === activeFilter || (!t.status && activeFilter === 'Pending');
      const matchesSearch = !term
        || t.plate.toLowerCase().includes(term)
        || t.engine.toLowerCase().includes(term)
        || t.chassis.toLowerCase().includes(term)
        || t.brand.toLowerCase().includes(term);
      return matchesFilter && matchesSearch;
    });

    tableBody.innerHTML = '';
    emptyState.classList.toggle('hidden', filtered.length !== 0);

    filtered.forEach(t => {
      const tr = document.createElement('tr');
      const dot = colorMap[t.color] || '#999';
      tr.innerHTML = `
        <td>
          <div class="trike-cell">
            <div class="trike-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="M6 17h6l3-8h4"/><path d="M9 9h4l2 8"/><path d="M9 9V6h3"/></svg>
            </div>
            <div>
              <div class="trike-brand">${t.brand}</div>
              <div class="trike-sub">#${String(t.id).padStart(4, '0')}</div>
            </div>
          </div>
        </td>
        <td><span class="mono">${t.sticker || '—'}</span></td>
        <td><span class="plate-pill">${t.plate}</span></td>
        <td><span class="mono">${t.engine}</span></td>
        <td><span class="mono">${t.chassis}</span></td>
        <td>${t.franchise ? t.franchise : '<span class="link-value">Unassigned</span>'}</td>
        <td>${t.driver ? t.driver : '<span class="link-value">Unassigned</span>'}</td>
        <td><span class="status-pill ${t.status === 'Pending' ? 'status-pending' : t.status === 'Active' ? 'status-active' : 'status-inactive'}">${t.status || 'Pending'}</span></td>
        <td class="actions-cell">
          <button class="icon-btn view-btn" data-id="${t.id}" title="View">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
          <button class="icon-btn edit-btn" data-id="${t.id}" title="Edit">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          ${t.status !== 'Active' ? `<button class="icon-btn approve-btn" data-id="${t.id}" title="Approve"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></button>` : ''}
          ${t.status !== 'Inactive' ? `<button class="icon-btn danger reject-btn" data-id="${t.id}" title="Reject"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>` : ''}
          <button class="icon-btn danger delete-btn" data-id="${t.id}" title="Remove">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
          </button>
        </td>
      `;
      tableBody.appendChild(tr);
    });

    resultCount.textContent = `${filtered.length} tricycle${filtered.length === 1 ? '' : 's'}`;
    pageInfo.textContent = `Showing ${filtered.length} of ${tricycles.length} tricycles`;

    tableBody.querySelectorAll('.view-btn').forEach(btn => btn.addEventListener('click', () => openViewModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => openEditModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.approve-btn').forEach(btn => btn.addEventListener('click', async () => {
      try {
        await apiRequest({ action: 'approve', id: Number(btn.dataset.id) });
        await loadTricycles();
      } catch (error) { alert(error.message); }
    }));
    tableBody.querySelectorAll('.reject-btn').forEach(btn => btn.addEventListener('click', async () => {
      try {
        await apiRequest({ action: 'reject', id: Number(btn.dataset.id) });
        await loadTricycles();
      } catch (error) { alert(error.message); }
    }));
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

  /* ---------- add / edit modal ---------- */
  const trikeModalOverlay = document.getElementById('trikeModalOverlay');
  const trikeModalTitle = document.getElementById('trikeModalTitle');
  const trikeForm = document.getElementById('trikeForm');
  let editingId = null;

  function openAddModal() {
    editingId = null;
    trikeModalTitle.textContent = 'Add Tricycle';
    trikeForm.reset();
    trikeModalOverlay.classList.add('open');
  }

  function openEditModal(id) {
    const t = tricycles.find(x => x.id === id);
    if (!t) return;
    editingId = id;
    trikeModalTitle.textContent = 'Edit Tricycle';
    document.getElementById('fBrand').value = t.brand;
    document.getElementById('fSticker').value = t.sticker || '';
    document.getElementById('fPlate').value = t.plate;
    document.getElementById('fEngine').value = t.engine;
    document.getElementById('fChassis').value = t.chassis;
    document.getElementById('fColor').value = t.color;
    document.getElementById('fFranchise').value = t.franchiseId || '';
    document.getElementById('fDriver').value = t.driverId || '';
    trikeModalOverlay.classList.add('open');
  }

  function closeTrikeModal() { trikeModalOverlay.classList.remove('open'); }

  document.getElementById('addTrikeBtn').addEventListener('click', openAddModal);
  document.getElementById('trikeModalClose').addEventListener('click', closeTrikeModal);
  document.getElementById('trikeCancelBtn').addEventListener('click', closeTrikeModal);
  trikeModalOverlay.addEventListener('click', (e) => { if (e.target === trikeModalOverlay) closeTrikeModal(); });

  trikeForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      brand: document.getElementById('fBrand').value.trim(),
      sticker: document.getElementById('fSticker').value.trim(),
      chassis: document.getElementById('fChassis').value.trim(),
      color: document.getElementById('fColor').value.trim(),
      franchise_id: document.getElementById('fFranchise').value || null,
      driver_id: document.getElementById('fDriver').value || null
    };

    try {
      await apiRequest({ ...payload, action: editingId ? 'update' : 'create', ...(editingId ? { id: editingId } : {}) });
      await loadTricycles();
      closeTrikeModal();
    } catch (error) {
      alert(error.message);
    }
  });

  /* ---------- view modal ---------- */
  const viewModalOverlay = document.getElementById('viewModalOverlay');

  function openViewModal(id) {
    const t = tricycles.find(x => x.id === id);
    if (!t) return;
    document.getElementById('vBrand').textContent = t.brand;
    document.getElementById('vSticker').textContent = t.sticker || '—';
    document.getElementById('vPlate').innerHTML = `<span class="plate-pill">${t.plate}</span>`;
    document.getElementById('vEngine').textContent = t.engine;
    document.getElementById('vChassis').textContent = t.chassis;
    document.getElementById('vColor').innerHTML = `<span class="color-chip"><span class="color-dot" style="background:${colorMap[t.color] || '#999'}"></span>${t.color || '—'}</span>`;
    document.getElementById('vId').textContent = `#${String(t.id).padStart(4, '0')}`;
    document.getElementById('vFranchise').textContent = t.franchise || 'Unassigned';
    document.getElementById('vDriver').textContent = t.driver || 'Unassigned';
    document.getElementById('vOrDocument').innerHTML = t.orDocument
      ? `<a href="${t.orDocument}" target="_blank" rel="noopener"><img class="document-preview" src="${t.orDocument}" alt="Uploaded OR document"></a>`
      : 'No document uploaded';
    viewModalOverlay.classList.add('open');
  }

  function closeViewModal() { viewModalOverlay.classList.remove('open'); }
  document.getElementById('viewModalClose').addEventListener('click', closeViewModal);
  document.getElementById('viewCloseBtn').addEventListener('click', closeViewModal);
  viewModalOverlay.addEventListener('click', (e) => { if (e.target === viewModalOverlay) closeViewModal(); });

  /* ---------- delete modal ---------- */
  const deleteModalOverlay = document.getElementById('deleteModalOverlay');
  let deletingId = null;

  function openDeleteModal(id) {
    const t = tricycles.find(x => x.id === id);
    if (!t) return;
    deletingId = id;
    document.getElementById('deleteTargetName').textContent = `${t.brand} (${t.plate})`;
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
        await loadTricycles();
      } catch (error) {
        alert(error.message);
      }
    }
    closeDeleteModal();
  });

  /* ---------- init ---------- */
  loadTricycles();
