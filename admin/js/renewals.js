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

  /* ---------- mock receipt image generator (stand-in for uploaded photos) ---------- */
  function mockReceiptImage(franchiseName, amount) {
    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" width="400" height="500" viewBox="0 0 400 500">
        <rect width="400" height="500" fill="#F7F8FA"/>
        <rect x="20" y="20" width="360" height="460" rx="10" fill="#ffffff" stroke="#E3E5E9" stroke-width="2"/>
        <text x="200" y="65" text-anchor="middle" font-family="Arial" font-size="18" font-weight="700" fill="#1B2A4A">OFFICIAL RECEIPT</text>
        <line x1="45" y1="85" x2="355" y2="85" stroke="#E3E5E9" stroke-width="2"/>
        <text x="45" y="120" font-family="Arial" font-size="12" fill="#5B6472">Paid To:</text>
        <text x="45" y="140" font-family="Arial" font-size="14" font-weight="600" fill="#1B2A4A">LGU - Bayombong</text>
        <text x="45" y="175" font-family="Arial" font-size="12" fill="#5B6472">Franchise:</text>
        <text x="45" y="195" font-family="Arial" font-size="14" font-weight="600" fill="#1B2A4A">${franchiseName}</text>
        <text x="45" y="230" font-family="Arial" font-size="12" fill="#5B6472">Amount Paid:</text>
        <text x="45" y="252" font-family="Arial" font-size="20" font-weight="700" fill="#2E9E4F">₱${amount}</text>
        <line x1="45" y1="280" x2="355" y2="280" stroke="#E3E5E9" stroke-width="1" stroke-dasharray="4 4"/>
        <text x="45" y="310" font-family="Arial" font-size="11" fill="#9AA3B2">Franchise Renewal Fee</text>
        <text x="45" y="330" font-family="Arial" font-size="11" fill="#9AA3B2">Tricycle Franchise Management</text>
        <rect x="45" y="360" width="120" height="120" fill="#EDEEF1" stroke="#E3E5E9"/>
        <text x="105" y="425" text-anchor="middle" font-family="Arial" font-size="10" fill="#B9BEC7">[ QR / Stamp ]</text>
      </svg>`;
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  /* ---------- data loaded from the renewal tables ---------- */
  const renewalApi = '../controllers/renewals.php';
  let renewals = [];
  let franchises = [];

  async function apiRequest(payload = null) {
    const options = payload ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) } : {};
    const response = await fetch(renewalApi, options);
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Request failed.');
    return result;
  }

  function populateFranchises(franchises) {
    const select = document.getElementById('fFranchise');
    select.innerHTML = '<option value="" disabled selected>Select franchise</option>' + franchises.map(f => `<option value="${f.franchise_id}">${f.franchise_name}</option>`).join('');
  }

  function renewalRequestData(r, overrides = {}) {
    return {
      franchise_id: r.franchiseId,
      year: r.year,
      status: r.status,
      receiptDataUrl: r.receiptDataUrl,
      ...overrides
    };
  }

  async function loadRenewals() {
    try {
      const result = await apiRequest();
      renewals = result.renewals;
      franchises = result.franchises;
      populateFranchises(franchises);
      updateStats();
      render();
    } catch (error) {
      emptyState.classList.remove('hidden');
      emptyState.querySelector('div').textContent = error.message;
    }
  }

  const tableBody = document.getElementById('renewalTableBody');
  const emptyState = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');
  const pageInfo = document.getElementById('pageInfo');
  const searchInput = document.getElementById('searchInput');
  const filterTabs = document.getElementById('filterTabs');
  const franchiseTableBody = document.getElementById('franchiseRenewalTableBody');
  const franchiseEmptyState = document.getElementById('franchiseEmptyState');
  const franchiseResultCount = document.getElementById('franchiseResultCount');

  let activeFilter = 'All';
  let searchTerm = '';

  function statusPillClass(status) {
    if (status === 'Not Submitted') return 'status-not-submitted';
    if (status === 'Submitted') return 'status-submitted';
    if (status === 'Rejected') return 'status-rejected';
    return 'status-confirmed';
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
    const expiringCount = franchises.filter(franchise => {
      const daysLeft = daysUntil(franchise.expiry_date);
      return franchise.renewal_status === 'Active' && daysLeft >= 0 && daysLeft <= 10;
    }).length;
    document.getElementById('statExpiring').textContent = expiringCount;
    document.getElementById('statActiveFranchise').textContent = franchises.filter(franchise => franchise.renewal_status === 'Active').length;
    document.getElementById('statPendingFranchise').textContent = franchises.filter(franchise => franchise.renewal_status === 'Pending Renewal').length;
    document.getElementById('statExpiredFranchise').textContent = franchises.filter(franchise => franchise.renewal_status === 'Expired').length;
  }

  function render() {
    const term = searchTerm.trim().toLowerCase();
    const filtered = renewals.filter(r => {
      const matchesFilter = activeFilter === 'All' || r.status === activeFilter;
      const matchesSearch = !term || r.franchise.toLowerCase().includes(term);
      return matchesFilter && matchesSearch;
    });

    tableBody.innerHTML = '';
    emptyState.classList.toggle('hidden', filtered.length !== 0);

    filtered.forEach(r => {
      const tr = document.createElement('tr');

      let actionButtons = `
        <button class="icon-btn view-btn" data-id="${r.id}" title="View">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>`;

      if (r.status === 'Submitted') {
        actionButtons += `
        <button class="icon-btn confirm confirm-btn" data-id="${r.id}" title="Confirm Receipt">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </button>`;
        actionButtons += `
        <button class="icon-btn danger reject-btn" data-id="${r.id}" title="Reject Renewal">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>`;
      }

      actionButtons += `
        <button class="icon-btn danger delete-btn" data-id="${r.id}" title="Remove">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </button>`;

      tr.innerHTML = `
        <td>
          <div class="renewal-cell">
            <div class="renewal-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M22 6l-10 7L2 6"/></svg>
            </div>
            <div>
              <div class="renewal-name">${r.franchise}</div>
              <div class="renewal-sub">#${String(r.id).padStart(4, '0')}</div>
            </div>
          </div>
        </td>
        <td>${r.year}</td>
        <td>${r.receiptDataUrl
          ? `<img src="${r.receiptDataUrl}" class="receipt-indicator receipt-thumb-click" data-id="${r.id}" title="View receipt">`
          : `<div class="receipt-indicator-empty" title="No receipt"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>`}</td>
        <td><span class="status-pill ${statusPillClass(r.status)}">${r.status}</span></td>
        <td class="actions-cell">${actionButtons}</td>
      `;
      tableBody.appendChild(tr);
    });

    resultCount.textContent = `${filtered.length} renewal${filtered.length === 1 ? '' : 's'}`;
    pageInfo.textContent = `Showing ${filtered.length} of ${renewals.length} renewals`;

    const matchingFranchises = franchises.filter(franchise => {
      const matchesSearch = !term
        || franchise.franchise_name.toLowerCase().includes(term)
        || franchise.owner_name.toLowerCase().includes(term);
      return matchesSearch;
    });
    franchiseTableBody.innerHTML = matchingFranchises.map(franchise => {
      const daysLeft = daysUntil(franchise.expiry_date);
      const expiryNote = daysLeft < 0
        ? '<div class="franchise-expiry-note">Expired</div>'
        : daysLeft <= 30
          ? `<div class="franchise-expiry-note soon">Expires in ${daysLeft}d</div>`
          : '';
      const statusClass = franchise.renewal_status === 'Expired'
        ? 'franchise-status-expired'
        : franchise.renewal_status === 'Pending Renewal'
          ? 'franchise-status-pending'
          : 'franchise-status-active';
      return `<tr>
        <td>${franchise.franchise_name}</td>
        <td>${franchise.owner_name}</td>
        <td><div class="franchise-expiry"><div>${formatDate(franchise.expiry_date)}</div>${expiryNote}</div></td>
        <td><span class="status-pill ${statusClass}">${franchise.renewal_status}</span></td>
      </tr>`;
    }).join('');
    franchiseEmptyState.classList.toggle('hidden', matchingFranchises.length !== 0);
    franchiseResultCount.textContent = `${matchingFranchises.length} franchise${matchingFranchises.length === 1 ? '' : 's'}`;

    tableBody.querySelectorAll('.view-btn').forEach(btn => btn.addEventListener('click', () => openViewModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.confirm-btn').forEach(btn => btn.addEventListener('click', () => openConfirmModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.reject-btn').forEach(btn => btn.addEventListener('click', () => rejectRenewal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => openDeleteModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.receipt-thumb-click').forEach(img => img.addEventListener('click', () => {
      const r = renewals.find(x => x.id === Number(img.dataset.id));
      if (r && r.receiptDataUrl) openLightbox(r.receiptDataUrl);
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
  const renewalModalOverlay = document.getElementById('renewalModalOverlay');
  const renewalModalTitle = document.getElementById('renewalModalTitle');
  const renewalForm = document.getElementById('renewalForm');
  const fReceiptArea = document.getElementById('fReceiptArea');
  const fReceiptFile = document.getElementById('fReceiptFile');
  let editingId = null;
  let formReceipt = { dataUrl: '', name: '', uploadedBy: '', submittedAt: '' };

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
        formReceipt = { dataUrl: '', name: '', uploadedBy: '', submittedAt: '' };
        renderFormReceiptArea();
      });
    } else {
      fReceiptArea.innerHTML = `
        <div class="upload-zone compact" id="fUploadZone">
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
      formReceipt = {
        dataUrl: e.target.result,
        name: file.name,
        uploadedBy: 'Admin',
        submittedAt: new Date().toISOString().slice(0, 10)
      };
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
    renewalModalTitle.textContent = 'Add Renewal';
    renewalForm.reset();
    formReceipt = { dataUrl: '', name: '', uploadedBy: '', submittedAt: '' };
    renderFormReceiptArea();
    renewalModalOverlay.classList.add('open');
  }

  function closeRenewalModal() { renewalModalOverlay.classList.remove('open'); }

  document.getElementById('addRenewalBtn').addEventListener('click', openAddModal);
  document.getElementById('emptyAddRenewalBtn').addEventListener('click', openAddModal);
  document.getElementById('renewalModalClose').addEventListener('click', closeRenewalModal);
  document.getElementById('renewalCancelBtn').addEventListener('click', closeRenewalModal);
  renewalModalOverlay.addEventListener('click', (e) => { if (e.target === renewalModalOverlay) closeRenewalModal(); });

  renewalForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      franchise_id: document.getElementById('fFranchise').value,
      receiptDataUrl: formReceipt.dataUrl,
      receipt: formReceipt.name
    };

    try {
      if (!payload.receiptDataUrl) throw new Error('Please attach the payment receipt before saving this renewal.');
      await apiRequest({ ...payload, action: 'create' });
      await loadRenewals();
      closeRenewalModal();
    } catch (error) {
      alert(error.message);
    }
  });

  /* ---------- view modal ---------- */
  const viewModalOverlay = document.getElementById('viewModalOverlay');
  const receiptPanel = document.getElementById('receiptPanel');
  const receiptFileInput = document.getElementById('receiptFileInput');
  let currentViewId = null;

  function renderReceiptPanel(r) {
    if (r.receiptDataUrl) {
      receiptPanel.innerHTML = `
        <div class="receipt-preview">
          <div class="receipt-thumb-wrap">
            <img src="${r.receiptDataUrl}" class="receipt-thumb" id="receiptThumbImg" title="Click to enlarge">
          </div>
          <div class="receipt-meta">
            <span class="uploaded-by-tag ${r.uploadedBy === 'Admin' ? 'admin' : 'rider'}">
              ${r.uploadedBy === 'Admin' ? '🛠️ Uploaded by Admin' : '📱 Uploaded by Rider'}
            </span>
            <div class="receipt-filename">${r.receipt || 'receipt.jpg'}</div>
            <div class="receipt-submitted-at">Submitted: ${r.receiptSubmittedAt ? formatDate(r.receiptSubmittedAt) : '—'}</div>
            <div class="receipt-actions">
              <button type="button" class="btn-sm" id="replaceReceiptBtn">Replace Photo</button>
              ${r.status === 'Submitted' ? `<button type="button" class="btn-sm confirm-sm" id="confirmFromPanelBtn">Confirm Payment</button>` : ''}
            </div>
          </div>
        </div>
      `;
      document.getElementById('receiptThumbImg').addEventListener('click', () => openLightbox(r.receiptDataUrl));
      document.getElementById('replaceReceiptBtn').addEventListener('click', () => receiptFileInput.click());
      const confirmBtn = document.getElementById('confirmFromPanelBtn');
      if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
          apiRequest({ action: 'confirm', id: r.id }).then(loadRenewals).then(() => openViewModal(r.id)).catch(error => alert(error.message));
        });
      }
    } else {
      receiptPanel.innerHTML = `
        <div class="upload-zone" id="uploadZone">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div class="uz-title">Upload Payment Receipt</div>
          <div class="uz-sub">On behalf of the rider — click to browse or drag a photo here</div>
        </div>
      `;
      const zone = document.getElementById('uploadZone');
      zone.addEventListener('click', () => receiptFileInput.click());
      zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
      zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
      zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) handleReceiptFile(e.dataTransfer.files[0], r);
      });
    }
  }

  function handleReceiptFile(file, r) {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const updated = { ...r, receiptDataUrl: e.target.result, status: r.status === 'Not Submitted' ? 'Submitted' : r.status };
      apiRequest({ ...renewalRequestData(updated), action: 'update', id: r.id })
        .then(loadRenewals)
        .then(() => openViewModal(r.id))
        .catch(error => alert(error.message));
    };
    reader.readAsDataURL(file);
  }

  receiptFileInput.addEventListener('change', (e) => {
    if (e.target.files && e.target.files[0] && currentViewId != null) {
      const r = renewals.find(x => x.id === currentViewId);
      if (r) handleReceiptFile(e.target.files[0], r);
    }
    receiptFileInput.value = '';
  });

  function openViewModal(id) {
    const r = renewals.find(x => x.id === id);
    if (!r) return;
    currentViewId = id;
    document.getElementById('vFranchise').textContent = r.franchise;
    document.getElementById('vYear').textContent = r.year;
    document.getElementById('vConfirmedBy').textContent = r.confirmedBy || '—';
    document.getElementById('vConfirmedAt').textContent = r.confirmedAt ? formatDate(r.confirmedAt) : '—';

    renderReceiptPanel(r);
    viewModalOverlay.classList.add('open');
  }

  function closeViewModal() { viewModalOverlay.classList.remove('open'); currentViewId = null; }
  document.getElementById('viewModalClose').addEventListener('click', closeViewModal);
  document.getElementById('viewCloseBtn').addEventListener('click', closeViewModal);
  viewModalOverlay.addEventListener('click', (e) => { if (e.target === viewModalOverlay) closeViewModal(); });

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

  /* ---------- confirm receipt modal ---------- */
  const confirmModalOverlay = document.getElementById('confirmModalOverlay');
  let confirmingId = null;

  function openConfirmModal(id) {
    const r = renewals.find(x => x.id === id);
    if (!r) return;
    confirmingId = id;
    document.getElementById('confirmTargetName').textContent = `${r.franchise} (${r.year})`;
    confirmModalOverlay.classList.add('open');
  }

  function closeConfirmModal() { confirmModalOverlay.classList.remove('open'); confirmingId = null; }

  document.getElementById('confirmModalClose').addEventListener('click', closeConfirmModal);
  document.getElementById('confirmCancelBtn').addEventListener('click', closeConfirmModal);
  confirmModalOverlay.addEventListener('click', (e) => { if (e.target === confirmModalOverlay) closeConfirmModal(); });

  document.getElementById('confirmYesBtn').addEventListener('click', async () => {
    if (confirmingId != null) {
      try { await apiRequest({ action: 'confirm', id: confirmingId }); await loadRenewals(); }
      catch (error) { alert(error.message); }
    }
    closeConfirmModal();
  });

  async function rejectRenewal(id) {
    const renewal = renewals.find(item => item.id === id);
    if (!renewal) return;
    const reason = window.prompt(`Reason for rejecting ${renewal.franchise} (${renewal.year}):`, 'The receipt could not be verified. Please submit a clear receipt or contact the admin team.');
    if (reason === null) return;
    try {
      await apiRequest({ action: 'reject', id, reason: reason.trim() || 'Please contact the admin team for assistance.' });
      await loadRenewals();
    } catch (error) {
      alert(error.message);
    }
  }

  /* ---------- delete modal ---------- */
  const deleteModalOverlay = document.getElementById('deleteModalOverlay');
  let deletingId = null;

  function openDeleteModal(id) {
    const r = renewals.find(x => x.id === id);
    if (!r) return;
    deletingId = id;
    document.getElementById('deleteTargetName').textContent = `${r.franchise} (${r.year})`;
    deleteModalOverlay.classList.add('open');
  }

  function closeDeleteModal() { deleteModalOverlay.classList.remove('open'); deletingId = null; }

  document.getElementById('deleteModalClose').addEventListener('click', closeDeleteModal);
  document.getElementById('deleteCancelBtn').addEventListener('click', closeDeleteModal);
  deleteModalOverlay.addEventListener('click', (e) => { if (e.target === deleteModalOverlay) closeDeleteModal(); });

  document.getElementById('deleteConfirmBtn').addEventListener('click', async () => {
    if (deletingId != null) {
      try { await apiRequest({ action: 'delete', id: deletingId }); await loadRenewals(); }
      catch (error) { alert(error.message); }
    }
    closeDeleteModal();
  });

  /* ---------- init ---------- */
  loadRenewals();
