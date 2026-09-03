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

  /* ---------- lightbox ---------- */
  const lightboxOverlay = document.getElementById('lightboxOverlay');
  const lightboxImg = document.getElementById('lightboxImg');

  function openLightbox(src) {
    lightboxImg.src = src;
    lightboxOverlay.classList.add('open');
  }

  function closeLightbox() { lightboxOverlay.classList.remove('open'); lightboxImg.src = ''; }
  document.getElementById('lightboxCloseBtn').addEventListener('click', closeLightbox);
  lightboxOverlay.addEventListener('click', (e) => { if (e.target === lightboxOverlay) closeLightbox(); });

  /* ---------- data loaded from the franchise tables ---------- */
  const franchiseApi = '../controllers/franchise_management.php';
  let franchises = [];
  let ownerAccounts = [];

  async function apiRequest(payload = null) {
    const options = payload ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) } : {};
    const response = await fetch(franchiseApi, options);
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Request failed.');
    return result;
  }

  async function loadFranchises() {
    try {
      const result = await apiRequest();
      franchises = result.franchises;
      ownerAccounts = result.owners || [];
      populateOwnerAccounts();
      updateStats();
      render();
    } catch (error) {
      emptyState.classList.remove('hidden');
      emptyState.querySelector('div').textContent = error.message;
    }
  }

  const tableBody = document.getElementById('franchiseTableBody');
  const emptyState = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');
  const pageInfo = document.getElementById('pageInfo');
  const searchInput = document.getElementById('searchInput');
  const filterTabs = document.getElementById('filterTabs');

  function populateOwnerAccounts() {
    document.getElementById('fOwner').innerHTML = '<option value="" selected disabled>Select an Admin username</option>' + ownerAccounts
      .map(account => `<option value="${account.username}">${account.username}</option>`)
      .join('');
  }

  document.getElementById('fOwner').addEventListener('change', event => {
    const account = ownerAccounts.find(item => item.username === event.target.value);
    document.getElementById('fOwnerEmail').value = account ? account.email : '';
  });

  let activeFilter = 'All';
  let searchTerm = '';

  function statusPillClass(status) {
    if (status === 'Active') return 'status-active';
    if (status === 'Expired') return 'status-expired';
    return 'status-pending';
  }

  function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function daysUntil(iso) {
    const today = new Date();
    today.setHours(0,0,0,0);
    const target = new Date(iso + 'T00:00:00');
    return Math.round((target - today) / 86400000);
  }

  function updateStats() {
    document.getElementById('statTotal').textContent = franchises.length;
    document.getElementById('statActive').textContent = franchises.filter(f => f.status === 'Active').length;
    document.getElementById('statPending').textContent = franchises.filter(f => f.status === 'Pending Renewal').length;
    document.getElementById('statExpired').textContent = franchises.filter(f => f.status === 'Expired').length;
  }

  function render() {
    const term = searchTerm.trim().toLowerCase();
    const filtered = franchises.filter(f => {
      const matchesFilter = activeFilter === 'All' || f.status === activeFilter;
      const matchesSearch = !term || f.name.toLowerCase().includes(term) || f.owner.toLowerCase().includes(term);
      return matchesFilter && matchesSearch;
    });

    tableBody.innerHTML = '';
    emptyState.classList.toggle('hidden', filtered.length !== 0);

    filtered.forEach(f => {
      const tr = document.createElement('tr');
      const dLeft = daysUntil(f.expiry);
      let expiryWarning = '';
      if (f.status !== 'Expired' && dLeft <= 30 && dLeft >= 0) {
        expiryWarning = `<div class="expiry-warning soon">Expires in ${dLeft}d</div>`;
      } else if (f.status === 'Expired' || dLeft < 0) {
        expiryWarning = `<div class="expiry-warning">Expired</div>`;
      }

      tr.innerHTML = `
        <td>
          <div class="franchise-cell">
            <div class="franchise-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
            <div>
              <div class="franchise-name">${f.name}</div>
              <div class="franchise-sub">#${String(f.id).padStart(4, '0')}</div>
            </div>
          </div>
        </td>
        <td>${f.owner}</td>
        <td>${f.address || '—'}</td>
        <td>${formatDate(f.issue)}</td>
        <td>
          <div class="expiry-cell">
            <div>${formatDate(f.expiry)}</div>
            ${expiryWarning}
          </div>
        </td>
        <td><span class="trike-count">🛺 ${f.tricycles.length}</span></td>
        <td><span class="status-pill ${statusPillClass(f.status)}">${f.status}</span></td>
        <td class="actions-cell">
          <button class="icon-btn view-btn" data-id="${f.id}" title="View">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
          <button class="icon-btn edit-btn" data-id="${f.id}" title="Edit">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          ${f.status !== 'Active' ? `
          <button class="icon-btn renew" data-id="${f.id}" title="Mark as Renewed">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          </button>` : ''}
          <button class="icon-btn danger delete-btn" data-id="${f.id}" title="Remove">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
          </button>
        </td>
      `;
      tableBody.appendChild(tr);
    });

    resultCount.textContent = `${filtered.length} franchise${filtered.length === 1 ? '' : 's'}`;
    pageInfo.textContent = `Showing ${filtered.length} of ${franchises.length} franchises`;

    tableBody.querySelectorAll('.view-btn').forEach(btn => btn.addEventListener('click', () => openViewModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => openEditModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => openDeleteModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.renew').forEach(btn => btn.addEventListener('click', async () => {
      const f = franchises.find(x => x.id === Number(btn.dataset.id));
      if (!f) return;
      try {
        await apiRequest({ action: 'update', id: f.id, name: f.name, owner: f.owner, address: f.address, issue: f.issue, expiry: f.expiry, status: 'Active' });
        await loadFranchises();
      } catch (error) {
        alert(error.message);
      }
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

  /* ---------- add / edit modal ---------- */
  const franchiseModalOverlay = document.getElementById('franchiseModalOverlay');
  const franchiseModalTitle = document.getElementById('franchiseModalTitle');
  const franchiseForm = document.getElementById('franchiseForm');
  const fReceiptArea = document.getElementById('fReceiptArea');
  const fReceiptFile = document.getElementById('fReceiptFile');
  let editingId = null;
  let formReceipt = { dataUrl: '', name: '', uploadedBy: '' };

  function renderFormReceiptArea() {
    if (formReceipt.dataUrl) {
      fReceiptArea.innerHTML = `
        <div class="form-receipt-preview">
          <img id="fReceiptThumb" src="${formReceipt.dataUrl}" title="Click to enlarge">
          <div class="frp-meta">
            <div class="frp-name">${formReceipt.name || 'receipt.jpg'}</div>
            <div class="frp-actions">
              <button type="button" class="frp-link" id="fReceiptReplaceBtn">Replace</button>
              <button type="button" class="frp-remove" id="fReceiptRemoveBtn">Remove</button>
            </div>
          </div>
        </div>
      `;
      document.getElementById('fReceiptThumb').addEventListener('click', () => openLightbox(formReceipt.dataUrl));
      document.getElementById('fReceiptReplaceBtn').addEventListener('click', () => fReceiptFile.click());
      document.getElementById('fReceiptRemoveBtn').addEventListener('click', () => {
        formReceipt = { dataUrl: '', name: '', uploadedBy: '' };
        renderFormReceiptArea();
      });
    } else {
      fReceiptArea.innerHTML = `
        <div class="upload-zone" id="fUploadZone">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div class="uz-title">Attach Receipt Photo</div>
          <div class="uz-sub">Click to browse or drag an image here (optional)</div>
        </div>
      `;
      const zone = document.getElementById('fUploadZone');
      zone.addEventListener('click', () => fReceiptFile.click());
      zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
      zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
      zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) handleFormReceiptFile(e.dataTransfer.files[0]);
      });
    }
  }

  function handleFormReceiptFile(file) {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      formReceipt = { dataUrl: e.target.result, name: file.name, uploadedBy: 'Admin' };
      renderFormReceiptArea();
    };
    reader.readAsDataURL(file);
  }

  fReceiptFile.addEventListener('change', (e) => {
    if (e.target.files && e.target.files[0]) handleFormReceiptFile(e.target.files[0]);
    fReceiptFile.value = '';
  });

  function openAddModal() {
    editingId = null;
    franchiseModalTitle.textContent = 'Add Franchise';
    franchiseForm.reset();
    formReceipt = { dataUrl: '', name: '', uploadedBy: '' };
    renderFormReceiptArea();
    franchiseModalOverlay.classList.add('open');
  }

  function openEditModal(id) {
    const f = franchises.find(x => x.id === id);
    if (!f) return;
    editingId = id;
    franchiseModalTitle.textContent = 'Edit Franchise';
    document.getElementById('fName').value = f.name;
    const ownerAccount = ownerAccounts.find(account => account.email === f.ownerEmail);
    document.getElementById('fOwner').value = ownerAccount ? ownerAccount.username : '';
    document.getElementById('fOwnerEmail').value = ownerAccount ? ownerAccount.email : '';
    document.getElementById('fAddress').value = f.address || '';
    document.getElementById('fIssueDate').value = f.issue;
    document.getElementById('fExpiryDate').value = f.expiry;
    document.getElementById('fStatus').value = f.status;
    formReceipt = { dataUrl: f.receiptDataUrl || '', name: f.receipt || '', uploadedBy: f.receiptUploadedBy || '' };
    renderFormReceiptArea();
    franchiseModalOverlay.classList.add('open');
  }

  function closeFranchiseModal() { franchiseModalOverlay.classList.remove('open'); }

  document.getElementById('addFranchiseBtn').addEventListener('click', openAddModal);
  document.getElementById('franchiseModalClose').addEventListener('click', closeFranchiseModal);
  document.getElementById('franchiseCancelBtn').addEventListener('click', closeFranchiseModal);
  franchiseModalOverlay.addEventListener('click', (e) => { if (e.target === franchiseModalOverlay) closeFranchiseModal(); });

  franchiseForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      name: document.getElementById('fName').value.trim(),
      owner: document.getElementById('fOwner').value.trim(),
      ownerEmail: document.getElementById('fOwnerEmail').value.trim(),
      address: document.getElementById('fAddress').value.trim(),
      issue: document.getElementById('fIssueDate').value,
      expiry: document.getElementById('fExpiryDate').value,
      status: document.getElementById('fStatus').value,
      receiptDataUrl: formReceipt.dataUrl,
      receipt: formReceipt.name,
      receiptUploadedBy: formReceipt.uploadedBy
    };

    try {
      await apiRequest({ ...payload, action: editingId ? 'update' : 'create', ...(editingId ? { id: editingId } : {}) });
      await loadFranchises();
      closeFranchiseModal();
    } catch (error) {
      alert(error.message);
    }
  });

  /* ---------- view modal ---------- */
  const viewModalOverlay = document.getElementById('viewModalOverlay');

  function openViewModal(id) {
    const f = franchises.find(x => x.id === id);
    if (!f) return;
    document.getElementById('vName').textContent = f.name;
    document.getElementById('vStatus').innerHTML = `<span class="status-pill ${statusPillClass(f.status)}">${f.status}</span>`;
    document.getElementById('vOwner').textContent = f.owner;
    document.getElementById('vOwnerEmail').textContent = f.ownerEmail || '—';
    document.getElementById('vId').textContent = `#${String(f.id).padStart(4, '0')}`;
    document.getElementById('vIssue').textContent = formatDate(f.issue);
    document.getElementById('vExpiry').textContent = formatDate(f.expiry);
    document.getElementById('vAddress').textContent = f.address || '—';

    const trikesEl = document.getElementById('vTrikes');
    if (f.tricycles.length === 0) {
      trikesEl.innerHTML = `<div class="trike-row"><span class="tname">No tricycles assigned</span></div>`;
    } else {
      trikesEl.innerHTML = f.tricycles.map(plate => `
        <div class="trike-row">
          <span class="tname">🛺 Tricycle</span>
          <span class="plate-tag">${plate}</span>
        </div>
      `).join('');
    }

    const vReceiptArea = document.getElementById('vReceiptArea');
    if (f.receiptDataUrl) {
      vReceiptArea.innerHTML = `
        <div class="form-receipt-preview">
          <img id="vReceiptThumb" src="${f.receiptDataUrl}" title="Click to enlarge">
          <div class="frp-meta">
            <div class="frp-name">${f.receipt || 'receipt.jpg'}</div>
            <div class="frp-actions">
              <span style="font-size:12.5px; color: var(--text-gray);">${f.receiptUploadedBy ? 'Uploaded by ' + f.receiptUploadedBy : ''}</span>
            </div>
          </div>
        </div>
      `;
      document.getElementById('vReceiptThumb').addEventListener('click', () => openLightbox(f.receiptDataUrl));
    } else {
      vReceiptArea.innerHTML = `<div class="trike-row"><span class="tname">No receipt uploaded</span></div>`;
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

  function openDeleteModal(id) {
    const f = franchises.find(x => x.id === id);
    if (!f) return;
    deletingId = id;
    document.getElementById('deleteTargetName').textContent = f.name;
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
        await loadFranchises();
      } catch (error) {
        alert(error.message);
      }
    }
    closeDeleteModal();
  });

  /* ---------- init ---------- */
  loadFranchises();

  (() => {
    const applicationTableBody = document.getElementById('applicationTableBody');
    const applicationEmptyState = document.getElementById('applicationEmptyState');
    const applicationResultCount = document.getElementById('applicationResultCount');
    const applicationModalOverlay = document.getElementById('applicationModalOverlay');
    let applications = [];

    function applicationStatusClass(status) {
      return status === 'Approved' ? 'status-active' : status === 'Rejected' ? 'status-expired' : 'status-pending';
    }

    function formatApplicationDate(value) {
      return value ? new Date(value.replace(' ', 'T')).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
    }

    async function loadApplications() {
      const response = await fetch(`${franchiseApi}?resource=applications`);
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load franchise applications.');
      applications = result.applications;
      applicationTableBody.innerHTML = applications.map(application => `
        <tr>
          <td><strong>${application.franchiseName}</strong><div class="application-date">${application.address || 'No address provided'}</div></td>
          <td>${application.riderName}<div class="application-date">${application.riderEmail}</div></td>
          <td>${formatApplicationDate(application.applicationDate)}</td>
          <td><span class="status-pill ${applicationStatusClass(application.status)}">${application.status}</span></td>
          <td class="application-actions">
            <button class="icon-btn application-view" data-id="${application.id}" title="View application"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            ${application.status === 'Pending' ? `<button class="icon-btn approve application-approve" data-id="${application.id}" title="Approve application"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></button><button class="icon-btn reject application-reject" data-id="${application.id}" title="Reject application"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>` : ''}
          </td>
        </tr>`).join('');
      applicationEmptyState.classList.toggle('hidden', applications.length !== 0);
      applicationResultCount.textContent = `${applications.length} application${applications.length === 1 ? '' : 's'}`;
      applicationTableBody.querySelectorAll('.application-view').forEach(button => button.addEventListener('click', () => openApplicationModal(Number(button.dataset.id))));
      applicationTableBody.querySelectorAll('.application-approve').forEach(button => button.addEventListener('click', () => updateApplication(Number(button.dataset.id), 'approve-application')));
      applicationTableBody.querySelectorAll('.application-reject').forEach(button => button.addEventListener('click', () => updateApplication(Number(button.dataset.id), 'reject-application')));
    }

    function openApplicationModal(id) {
      const application = applications.find(item => item.id === id);
      if (!application) return;
      document.getElementById('applicationName').textContent = application.franchiseName;
      document.getElementById('applicationStatus').innerHTML = `<span class="status-pill ${applicationStatusClass(application.status)}">${application.status}</span>`;
      document.getElementById('applicationOwner').textContent = application.riderName;
      document.getElementById('applicationEmail').textContent = application.riderEmail;
      document.getElementById('applicationIssueDate').textContent = formatDate(application.issueDate);
      document.getElementById('applicationExpiryDate').textContent = formatDate(application.expiryDate);
      document.getElementById('applicationAddress').textContent = application.address || '-';
      document.getElementById('applicationSubmittedDate').textContent = formatApplicationDate(application.applicationDate);
      document.getElementById('applicationReceipt').innerHTML = application.receiptUrl ? `<img class="application-receipt" src="${application.receiptUrl}" alt="Application receipt">` : 'No receipt uploaded.';
      applicationModalOverlay.classList.add('open');
    }

    async function updateApplication(id, action) {
      if (!confirm(`Are you sure you want to ${action === 'approve-application' ? 'approve' : 'reject'} this application?`)) return;
      const response = await fetch(franchiseApi, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, id }) });
      const result = await response.json();
      if (!response.ok || !result.success) return alert(result.message || 'Unable to update application.');
      await loadApplications();
      await loadFranchises();
    }

    function closeApplicationModal() { applicationModalOverlay.classList.remove('open'); }
    document.getElementById('applicationModalClose').addEventListener('click', closeApplicationModal);
    document.getElementById('applicationCloseBtn').addEventListener('click', closeApplicationModal);
    applicationModalOverlay.addEventListener('click', event => { if (event.target === applicationModalOverlay) closeApplicationModal(); });
    loadApplications().catch(error => { applicationEmptyState.classList.remove('hidden'); applicationEmptyState.querySelector('div').textContent = error.message; });
  })();
