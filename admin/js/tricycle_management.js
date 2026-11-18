  fetch('../controllers/notification.php', { credentials: 'same-origin' }).then(response => response.json()).then(result => {
    const count = (result.notifications || []).filter(notification => !notification.isRead).length;
    document.querySelectorAll('.notification-count').forEach(badge => { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; });
  }).catch(() => {});
  fetch('../controllers/pending.php', { credentials: 'same-origin' }).then(response => response.json()).then(result => {
    const count = (result.groups || []).flatMap(group => group.requests || []).filter(request => request.status === 'Pending').length;
    const badge = document.getElementById('navBadge');
    if (badge) { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; }
  }).catch(() => {});

  function setSubmitLoading(button, loading) {
    if (!button) return;
    if (loading) {
      button.dataset.originalContent = button.innerHTML;
      button.innerHTML = '<span class="submit-spinner" aria-hidden="true"></span><span>Submitting...</span>';
      button.disabled = true;
    } else {
      button.innerHTML = button.dataset.originalContent || button.innerHTML;
      button.disabled = false;
    }
  }

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
  let ownerTricycles = [];

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
    const riderSelect = document.getElementById('fRider');
    driverSelect.innerHTML = '<option value="">Unassigned</option>' + result.drivers.filter(d => d.status === 'Approved').map(d => `<option value="${d.driver_id}">${d.full_name}</option>`).join('');
    franchiseSelect.innerHTML = '<option value="">Unassigned</option>' + result.franchises.map(f => `<option value="${f.franchise_id}">${f.franchise_name}</option>`).join('');
    riderSelect.replaceChildren(new Option('Unassigned', ''), ...result.riders.map(r => new Option(`${r.full_name} (${r.email})`, r.rider_id)));
  }

  async function loadTricycles() {
    try {
      const result = await apiRequest();
      tricycles = result.tricycles;
      ownerTricycles = result.ownerTricycles || [];
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
    document.getElementById('statTotal').textContent = tricycles.length + ownerTricycles.length;
    document.getElementById('statFranchise').textContent = tricycles.filter(t => t.franchise).length + ownerTricycles.length;
    document.getElementById('statDriver').textContent = tricycles.filter(t => t.driver).length;
    document.getElementById('statUnassigned').textContent = tricycles.filter(t => (t.status || 'Pending') === 'Pending').length
      + ownerTricycles.filter(t => (t.status || 'Pending') === 'Pending').length;
  }

  function render() {
    const term = searchTerm.trim().toLowerCase();
    const allTricycles = [
      ...tricycles.map(tricycle => ({ ...tricycle, isOwnerSubmission: false })),
      ...ownerTricycles.map(tricycle => ({ ...tricycle, rider: tricycle.owner || '', isOwnerSubmission: true }))
    ];
    const filtered = allTricycles.filter(t => {
      const matchesFilter = activeFilter === 'All' || t.status === activeFilter || (!t.status && activeFilter === 'Pending');
      const searchFields = [t.plate, t.engine, t.chassis, t.brand, t.driver, t.rider, t.ownerEmail, t.franchise];
      const matchesSearch = !term || searchFields.some(value => String(value || '').toLowerCase().includes(term));
      return matchesFilter && matchesSearch;
    });

    tableBody.innerHTML = '';
    emptyState.classList.toggle('hidden', filtered.length !== 0);

    filtered.forEach(t => {
      const tr = document.createElement('tr');
      const riderName = t.isOwnerSubmission ? t.owner || t.rider : t.driver || t.rider;
      let actions;
      if (t.isOwnerSubmission) {
        const ownerSource = `data-owner-submission="true"`;
        actions = `
          <td class="actions-cell">
            <button class="icon-btn view-btn" data-id="${t.id}" ${ownerSource} title="View">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="icon-btn edit-btn" data-id="${t.id}" ${ownerSource} title="Edit">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            ${t.status !== 'Active' ? `<button class="icon-btn approve-btn" data-id="${t.id}" ${ownerSource} title="Approve"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></button>` : ''}
            ${t.status !== 'Inactive' ? `<button class="icon-btn danger reject-btn" data-id="${t.id}" ${ownerSource} title="Reject"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>` : ''}
            <button class="icon-btn danger delete-btn" data-id="${t.id}" ${ownerSource} title="Remove">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </td>`;
      } else {
        actions = `
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
          </td>`;
      }
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
        <td class="rider-cell">${riderName || '<span class="link-value">Unassigned</span>'}</td>
        <td>${t.franchise ? t.franchise : '<span class="link-value">Unassigned</span>'}</td>
        ${actions}
      `;
      tableBody.appendChild(tr);
    });

    resultCount.textContent = `${filtered.length} tricycle${filtered.length === 1 ? '' : 's'}`;
    pageInfo.textContent = `Showing ${filtered.length} of ${allTricycles.length} tricycles`;

    tableBody.querySelectorAll('.view-btn').forEach(btn => btn.addEventListener('click', () => openViewModal(Number(btn.dataset.id), btn.dataset.ownerSubmission === 'true')));
    tableBody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => openEditModal(Number(btn.dataset.id), btn.dataset.ownerSubmission === 'true')));
    tableBody.querySelectorAll('.approve-btn').forEach(btn => btn.addEventListener('click', async () => {
      const isOwnerSubmission = btn.dataset.ownerSubmission === 'true';
      try {
        if (isOwnerSubmission && !window.confirm('Approve this franchise-owner tricycle?')) return;
        await apiRequest({
          action: isOwnerSubmission ? 'approve-owner-tricycle' : 'approve',
          id: Number(btn.dataset.id)
        });
        await loadTricycles();
      } catch (error) { alert(error.message); }
    }));
    tableBody.querySelectorAll('.reject-btn').forEach(btn => btn.addEventListener('click', async () => {
      const isOwnerSubmission = btn.dataset.ownerSubmission === 'true';
      try {
        if (isOwnerSubmission && !window.confirm('Reject this franchise-owner tricycle?')) return;
        await apiRequest({
          action: isOwnerSubmission ? 'reject-owner-tricycle' : 'reject',
          id: Number(btn.dataset.id)
        });
        await loadTricycles();
      } catch (error) { alert(error.message); }
    }));
    tableBody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => openDeleteModal(Number(btn.dataset.id), btn.dataset.ownerSubmission === 'true')));
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
  let editingOwnerSubmission = false;
  const assignmentSelects = ['fRider', 'fFranchise', 'fDriver'].map(id => document.getElementById(id));

  function openAddModal() {
    editingId = null;
    editingOwnerSubmission = false;
    trikeModalTitle.textContent = 'Add Tricycle';
    trikeForm.reset();
    assignmentSelects.forEach(select => { select.disabled = false; });
    trikeModalOverlay.classList.add('open');
  }

  function openEditModal(id, isOwnerSubmission = false) {
    const t = isOwnerSubmission
      ? ownerTricycles.find(x => x.id === id)
      : tricycles.find(x => x.id === id);
    if (!t) return;
    editingId = id;
    editingOwnerSubmission = isOwnerSubmission;
    trikeModalTitle.textContent = 'Edit Tricycle';
    document.getElementById('fBrand').value = t.brand;
    document.getElementById('fSticker').value = t.sticker || '';
    document.getElementById('fOrNumber').value = t.orNumber || '';
    document.getElementById('fPlate').value = t.plate;
    document.getElementById('fEngine').value = t.engine;
    document.getElementById('fChassis').value = t.chassis;
    document.getElementById('fColor').value = t.color;
    document.getElementById('fRider').value = t.riderId || '';
    document.getElementById('fFranchise').value = t.franchiseId || '';
    document.getElementById('fDriver').value = t.driverId || '';
    assignmentSelects.forEach(select => { select.disabled = isOwnerSubmission; });
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
      orNumber: document.getElementById('fOrNumber').value.trim(),
      plate: document.getElementById('fPlate').value.trim(),
      engine: document.getElementById('fEngine').value.trim(),
      chassis: document.getElementById('fChassis').value.trim(),
      color: document.getElementById('fColor').value.trim(),
      rider_id: document.getElementById('fRider').value || null,
      franchise_id: document.getElementById('fFranchise').value || null,
      driver_id: document.getElementById('fDriver').value || null
    };
    const submitButton = e.submitter;
    setSubmitLoading(submitButton, true);

    try {
      const action = editingId
        ? editingOwnerSubmission ? 'update-owner-tricycle' : 'update'
        : 'create';
      await apiRequest({ ...payload, action, ...(editingId ? { id: editingId } : {}) });
      await loadTricycles();
      closeTrikeModal();
    } catch (error) {
      alert(error.message);
    } finally {
      setSubmitLoading(submitButton, false);
    }
  });

  /* ---------- view modal ---------- */
  const viewModalOverlay = document.getElementById('viewModalOverlay');
  const imageViewer = document.getElementById('imageViewer');
  const imageViewerImage = document.getElementById('imageViewerImage');
  const imageViewerClose = document.getElementById('imageViewerClose');

  function closeImageViewer() {
    imageViewer.classList.remove('open');
    imageViewer.setAttribute('aria-hidden', 'true');
    imageViewerImage.src = '';
  }

  function openImageViewer(url, alt) {
    imageViewerImage.src = url;
    imageViewerImage.alt = alt;
    imageViewer.classList.add('open');
    imageViewer.setAttribute('aria-hidden', 'false');
    imageViewerClose.focus();
  }

  imageViewerClose.addEventListener('click', closeImageViewer);
  imageViewer.addEventListener('click', (event) => {
    if (event.target === imageViewer) closeImageViewer();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && imageViewer.classList.contains('open')) closeImageViewer();
  });

  function openViewModal(id, isOwnerSubmission = false) {
    const t = isOwnerSubmission
      ? ownerTricycles.find(x => x.id === id)
      : tricycles.find(x => x.id === id);
    if (!t) return;
    document.getElementById('vBrand').textContent = t.brand;
    document.getElementById('vSticker').textContent = t.sticker || '—';
    document.getElementById('vPlate').innerHTML = `<span class="plate-pill">${t.plate}</span>`;
    document.getElementById('vEngine').textContent = t.engine;
    document.getElementById('vChassis').textContent = t.chassis;
    document.getElementById('vColor').innerHTML = `<span class="color-chip"><span class="color-dot" style="background:${colorMap[t.color] || '#999'}"></span>${t.color || '—'}</span>`;
    document.getElementById('vId').textContent = `#${String(t.id).padStart(4, '0')}`;
    document.getElementById('vRider').textContent = (isOwnerSubmission ? t.owner : t.rider) || 'Unassigned';
    document.getElementById('vFranchise').textContent = t.franchise || 'Unassigned';
    document.getElementById('vDriver').textContent = (isOwnerSubmission ? '' : t.driver) || 'Unassigned';
    const documentUrl = t.orDocument || t.orCrDocument;
    const documentContainer = document.getElementById('vOrDocument');
    documentContainer.replaceChildren();
    if (documentUrl) {
      const previewButton = document.createElement('button');
      previewButton.type = 'button';
      previewButton.className = 'document-preview-trigger';
      previewButton.setAttribute('aria-label', 'View uploaded OR/CR document');
      const image = document.createElement('img');
      image.className = 'document-preview';
      image.src = documentUrl;
      image.alt = 'Uploaded OR/CR document';
      previewButton.appendChild(image);
      previewButton.addEventListener('click', () => openImageViewer(documentUrl, image.alt));
      documentContainer.appendChild(previewButton);
    } else {
      documentContainer.textContent = 'No document uploaded';
    }
    viewModalOverlay.classList.add('open');
  }

  function closeViewModal() { viewModalOverlay.classList.remove('open'); }
  document.getElementById('viewModalClose').addEventListener('click', closeViewModal);
  document.getElementById('viewCloseBtn').addEventListener('click', closeViewModal);
  viewModalOverlay.addEventListener('click', (e) => { if (e.target === viewModalOverlay) closeViewModal(); });

  /* ---------- delete modal ---------- */
  const deleteModalOverlay = document.getElementById('deleteModalOverlay');
  let deletingId = null;
  let deletingOwnerSubmission = false;

  function openDeleteModal(id, isOwnerSubmission = false) {
    const t = isOwnerSubmission
      ? ownerTricycles.find(x => x.id === id)
      : tricycles.find(x => x.id === id);
    if (!t) return;
    deletingId = id;
    deletingOwnerSubmission = isOwnerSubmission;
    document.getElementById('deleteTargetName').textContent = `${t.brand} (${t.plate})`;
    deleteModalOverlay.classList.add('open');
  }

  function closeDeleteModal() {
    deleteModalOverlay.classList.remove('open');
    deletingId = null;
    deletingOwnerSubmission = false;
  }

  document.getElementById('deleteModalClose').addEventListener('click', closeDeleteModal);
  document.getElementById('deleteCancelBtn').addEventListener('click', closeDeleteModal);
  deleteModalOverlay.addEventListener('click', (e) => { if (e.target === deleteModalOverlay) closeDeleteModal(); });

  document.getElementById('deleteConfirmBtn').addEventListener('click', async (e) => {
    if (deletingId != null) {
      const confirmButton = e.currentTarget;
      setSubmitLoading(confirmButton, true);
      try {
        await apiRequest({
          action: deletingOwnerSubmission ? 'delete-owner-tricycle' : 'delete',
          id: deletingId
        });
        await loadTricycles();
      } catch (error) {
        alert(error.message);
      } finally {
        setSubmitLoading(confirmButton, false);
      }
    }
    closeDeleteModal();
  });

  /* ---------- init ---------- */
  loadTricycles();
