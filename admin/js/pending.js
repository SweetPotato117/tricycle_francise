  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('drawerOverlay');
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  function openDrawer() { sidebar.classList.add('open'); overlay.classList.add('open'); }
  function closeDrawer() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }
  hamburgerBtn.addEventListener('click', openDrawer);
  overlay.addEventListener('click', closeDrawer);

  function mockDocImage(label, tint) {
    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" width="320" height="220" viewBox="0 0 320 220">
        <rect width="320" height="220" fill="${tint}"/>
        <rect x="14" y="14" width="292" height="192" rx="8" fill="#ffffff" fill-opacity="0.9" stroke="#ffffff" stroke-width="2" stroke-opacity="0.6"/>
        <text x="160" y="105" text-anchor="middle" font-family="Arial" font-size="15" font-weight="700" fill="#1B2A4A">${label}</text>
        <text x="160" y="128" text-anchor="middle" font-family="Arial" font-size="11" fill="#5B6472">Submitted by Franchise Owner</text>
      </svg>`;
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  let franchiseGroups = [];
  /*
  let mockFranchiseGroups = [
    {
      id: 1, franchiseName: "Santos Franchise", owner: "Ricardo Santos", contact: "0917 123 4567",
      requests: [
        { id: 101, type: "Tricycle Addition", title: "Add new tricycle — Plate ABC-5566", description: "Requesting to register an additional tricycle under this franchise. Unit was recently purchased and is ready for deployment.", submittedAt: "2026-08-27T09:15:00", status: "Pending", denialReason: "",
          docs: [ { label: "OR/CR", img: mockDocImage("OR/CR", "#FCF0D2") }, { label: "Tricycle Photo", img: mockDocImage("TRICYCLE PHOTO", "#DCE7FB") } ] },
        { id: 102, type: "Renewal Submission", title: "2026 Renewal Payment Receipt", description: "Submitted proof of payment for this year's franchise renewal.", submittedAt: "2026-08-26T13:40:00", status: "Pending", denialReason: "",
          docs: [ { label: "Payment Receipt", img: mockDocImage("RECEIPT", "#DCF3E1") } ] }
      ]
    },
    {
      id: 2, franchiseName: "Reyes Franchise", owner: "Marlon Reyes", contact: "0928 456 7890",
      requests: [
        { id: 103, type: "Driver Addition", title: "Add new driver — Bienvenido Cruz", description: "Requesting to assign an additional driver to this franchise's tricycle fleet.", submittedAt: "2026-08-25T10:00:00", status: "Pending", denialReason: "",
          docs: [ { label: "Driver's License", img: mockDocImage("LICENSE", "#DCE7FB") }, { label: "Valid ID", img: mockDocImage("VALID ID", "#FCF0D2") } ] },
        { id: 104, type: "Info Update", title: "Update franchise address", description: "Owner relocated the operating address and is requesting the franchise record be updated accordingly.", submittedAt: "2026-08-24T15:22:00", status: "Pending", denialReason: "",
          docs: [ { label: "Proof of Address", img: mockDocImage("PROOF OF ADDRESS", "#E7DEFB") } ] },
        { id: 105, type: "Tricycle Addition", title: "Add new tricycle — Plate DEF-9981", description: "New unit acquired, requesting registration under franchise.", submittedAt: "2026-08-22T08:50:00", status: "Approved", denialReason: "",
          docs: [ { label: "OR/CR", img: mockDocImage("OR/CR", "#FCF0D2") } ] }
      ]
    },
    {
      id: 3, franchiseName: "Mendoza Franchise", owner: "Carlo Mendoza", contact: "0945 321 0987",
      requests: [
        { id: 106, type: "Renewal Submission", title: "2026 Renewal Payment Receipt", description: "Payment receipt for franchise renewal submitted for review.", submittedAt: "2026-08-27T07:30:00", status: "Pending", denialReason: "",
          docs: [ { label: "Payment Receipt", img: mockDocImage("RECEIPT", "#DCF3E1") } ] },
        { id: 107, type: "Driver Addition", title: "Add new driver — Teresita Ramos", description: "Requesting to add a driver for the newly acquired tricycle unit.", submittedAt: "2026-08-21T11:15:00", status: "Denied",
          denialReason: "Submitted driver's license photo is expired. Please resubmit with a currently valid license.",
          docs: [ { label: "Driver's License", img: mockDocImage("LICENSE (EXPIRED)", "#FBDCDD") } ] },
        { id: 108, type: "Tricycle Addition", title: "Add new tricycle — Plate JKL-7723", description: "Requesting registration of an additional unit under this franchise.", submittedAt: "2026-08-19T14:05:00", status: "Approved", denialReason: "",
          docs: [ { label: "OR/CR", img: mockDocImage("OR/CR", "#FCF0D2") }, { label: "Tricycle Photo", img: mockDocImage("TRICYCLE PHOTO", "#DCE7FB") } ] }
      ]
    },
    {
      id: 4, franchiseName: "Fernandez Franchise", owner: "Liza Fernandez", contact: "0918 654 3210",
      requests: [
        { id: 109, type: "Info Update", title: "Update owner contact number", description: "Owner requesting update of contact information on file.", submittedAt: "2026-08-26T16:10:00", status: "Pending", denialReason: "",
          docs: [ { label: "Valid ID", img: mockDocImage("VALID ID", "#FCF0D2") } ] }
      ]
    }
  ]; */

  const tableBody = document.getElementById('franchiseTableBody');
  const emptyState = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');
  const pageInfo = document.getElementById('pageInfo');
  const searchInput = document.getElementById('searchInput');
  const filterTabs = document.getElementById('filterTabs');

  let activeFilter = 'All';
  let searchTerm = '';
  const pendingApi = '../controllers/pending.php';

  async function pendingRequest(payload = null) {
    const response = await fetch(pendingApi, payload ? {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    } : {});
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Request failed.');
    return result;
  }

  async function loadPendingRequests() {
    try {
      const result = await pendingRequest();
      franchiseGroups = result.groups.map(group => ({
        ...group,
        requests: group.requests.map(request => ({
          ...request,
          docs: (request.docs || []).map(document => ({ label: document.label, img: document.url, value: document.value }))
        }))
      }));
      updateStats();
      render();
    } catch (error) {
      emptyState.classList.remove('hidden');
      emptyState.querySelector('div').textContent = error.message;
    }
  }

  function typeChipClass(type) {
    if (type === 'Tricycle Addition') return 'chip-tricycle';
    if (type === 'Driver Addition') return 'chip-driver';
    if (type === 'Renewal Submission') return 'chip-renewal';
    return 'chip-info';
  }

  function statusPillClass(status) {
    if (status === 'Pending') return 'status-pending';
    if (status === 'Approved') return 'status-approved';
    return 'status-denied';
  }

  function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) +
      ' · ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  }

  function pendingRequests(group) { return group.requests.filter(r => r.status === 'Pending'); }
  function uniqueTypes(group) { return [...new Set(pendingRequests(group).map(r => r.type))]; }
  function latestSubmission(group) {
    return group.requests.reduce((latest, r) => !latest || new Date(r.submittedAt) > new Date(latest) ? r.submittedAt : latest, null);
  }

  function updateStats() {
    const groupsWithPending = franchiseGroups.filter(g => pendingRequests(g).length > 0);
    const allRequests = franchiseGroups.flatMap(g => g.requests);

    document.getElementById('statFranchises').textContent = groupsWithPending.length;
    document.getElementById('statPending').textContent = allRequests.filter(r => r.status === 'Pending').length;
    document.getElementById('statApproved').textContent = allRequests.filter(r => r.status === 'Approved').length;
    document.getElementById('statDenied').textContent = allRequests.filter(r => r.status === 'Denied').length;
    document.getElementById('countTricycle').textContent = allRequests.filter(r => r.type === 'Tricycle Addition' && r.status === 'Pending').length;
    document.getElementById('countDriver').textContent = allRequests.filter(r => r.type === 'Driver Addition' && r.status === 'Pending').length;
    document.getElementById('countRenewal').textContent = allRequests.filter(r => r.type === 'Renewal Submission' && r.status === 'Pending').length;
    document.getElementById('countFranchise').textContent = allRequests.filter(r => r.type === 'Franchise Application' && r.status === 'Pending').length;

    const totalPending = allRequests.filter(r => r.status === 'Pending').length;
    const navBadge = document.getElementById('navBadge');
    const bnBadge = document.getElementById('bnBadge');
    navBadge.textContent = totalPending;
    navBadge.style.display = totalPending > 0 ? 'flex' : 'none';
    bnBadge.textContent = totalPending;
    bnBadge.style.display = totalPending > 0 ? 'flex' : 'none';
  }

  function render() {
    const term = searchTerm.trim().toLowerCase();

    let visible = franchiseGroups.filter(g => pendingRequests(g).length > 0);

    if (activeFilter !== 'All') {
      visible = visible.filter(g => uniqueTypes(g).includes(activeFilter));
    }

    if (term) {
      visible = visible.filter(g => g.franchiseName.toLowerCase().includes(term) || g.owner.toLowerCase().includes(term));
    }

    visible.sort((a, b) => new Date(latestSubmission(b)) - new Date(latestSubmission(a)));

    tableBody.innerHTML = '';
    emptyState.classList.toggle('hidden', visible.length !== 0);

    visible.forEach(g => {
      const tr = document.createElement('tr');
      const pending = pendingRequests(g);
      const types = uniqueTypes(g);

      tr.innerHTML = `
        <td>
          <div class="reg-cell">
            <div class="franchise-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
            <div>
              <div class="reg-name">${g.franchiseName}</div>
              <div class="reg-sub">${g.contact}</div>
            </div>
          </div>
        </td>
        <td>${g.owner}</td>
        <td>
          <div class="type-chips">
            ${types.map(t => `<span class="type-chip ${typeChipClass(t)}">${t}</span>`).join('')}
          </div>
        </td>
        <td><span class="pending-count-badge">🕒 ${pending.length} pending</span></td>
        <td>${formatDate(latestSubmission(g))}</td>
        <td class="actions-cell">
          <button class="view-all-btn view-all" data-id="${g.id}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            View All
          </button>
        </td>
      `;
      tr.addEventListener('click', (e) => {
        if (!e.target.closest('.view-all')) openFsModal(g.id);
      });
      tableBody.appendChild(tr);
    });

    resultCount.textContent = `${visible.length} franchise${visible.length === 1 ? '' : 's'}`;
    pageInfo.textContent = `Showing ${visible.length} of ${franchiseGroups.filter(g => pendingRequests(g).length > 0).length} franchises`;

    tableBody.querySelectorAll('.view-all').forEach(btn => btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openFsModal(Number(btn.dataset.id));
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

  const lightboxOverlay = document.getElementById('lightboxOverlay');
  const lightboxImg = document.getElementById('lightboxImg');
  function openLightbox(src) { lightboxImg.src = src; lightboxOverlay.classList.add('open'); }
  function closeLightbox() { lightboxOverlay.classList.remove('open'); lightboxImg.src = ''; }
  document.getElementById('lightboxCloseBtn').addEventListener('click', closeLightbox);
  lightboxOverlay.addEventListener('click', (e) => { if (e.target === lightboxOverlay) closeLightbox(); });

  const fsModalOverlay = document.getElementById('fsModalOverlay');
  const fsModalBody = document.getElementById('fsModalBody');
  const approveAllBtn = document.getElementById('approveAllBtn');
  let currentGroupId = null;

  function openFsModal(groupId) {
    currentGroupId = groupId;
    renderFsModal();
    fsModalOverlay.classList.add('open');
  }

  function closeFsModal() {
    fsModalOverlay.classList.remove('open');
    currentGroupId = null;
    updateStats();
    render();
  }

  document.getElementById('fsModalClose').addEventListener('click', closeFsModal);

  function renderFsModal() {
    const g = franchiseGroups.find(x => x.id === currentGroupId);
    if (!g) return;

    document.getElementById('fsFranchiseName').textContent = g.franchiseName;
    document.getElementById('fsFranchiseSub').textContent = `${g.owner} · ${g.contact}`;

    const pending = pendingRequests(g);
    document.getElementById('fsToolbarCount').innerHTML = `${pending.length} pending <span>· ${g.requests.length} total requests</span>`;
    approveAllBtn.disabled = pending.length === 0;

    const sorted = [...g.requests].sort((a, b) => {
      if (a.status !== b.status) {
        if (a.status === 'Pending') return -1;
        if (b.status === 'Pending') return 1;
      }
      return new Date(b.submittedAt) - new Date(a.submittedAt);
    });

    fsModalBody.innerHTML = sorted.map(r => `
      <div class="request-card ${r.status !== 'Pending' ? 'resolved' : ''}" data-req-id="${r.id}">
        <div class="request-top">
          <div class="request-title-group">
            <div class="request-title">${r.title}</div>
            <div class="request-meta">
              <span class="type-chip ${typeChipClass(r.type)}">${r.type}</span>
              <span class="request-time">${formatDate(r.submittedAt)}</span>
            </div>
          </div>
          <span class="status-pill ${statusPillClass(r.status)}">${r.status}</span>
        </div>
        <div class="request-desc">${r.description}</div>
        <div class="doc-strip">
          ${r.docs.map(d => d.value
            ? `<div class="doc-thumb-wrap"><div class="doc-caption">${d.label}</div><strong>${d.value}</strong></div>`
            : `<div class="doc-thumb-wrap"><img src="${d.img}" data-src="${d.img}" class="req-doc-thumb" title="Click to enlarge"><div class="doc-caption">${d.label}</div></div>`
          ).join('')}
        </div>
        ${r.status === 'Pending' ? `
          <div class="request-actions">
            <button class="btn-approve-req req-approve-btn" data-req-id="${r.id}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Approve
            </button>
            ${['franchise', 'tricycle'].includes(r.actionType) ? `<button class="btn-deny-req req-deny-toggle-btn" data-req-id="${r.id}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Deny
            </button>` : ''}
          </div>
          <div class="deny-box" id="denyBox-${r.id}">
            <label>Reason for Denial</label>
            <textarea id="denyText-${r.id}" placeholder="e.g. Uploaded document is unclear, please resubmit."></textarea>
            <div class="field-hint" id="denyHint-${r.id}">Required — this will be shown to the franchise owner.</div>
            <div class="deny-box-actions">
              <button class="btn-sm-secondary req-deny-cancel-btn" data-req-id="${r.id}">Cancel</button>
              <button class="btn-sm-danger req-deny-confirm-btn" data-req-id="${r.id}">Confirm Denial</button>
            </div>
          </div>
        ` : ''}
        ${r.status === 'Denied' ? `
          <div class="denial-box">
            <div class="dlabel">Reason Provided</div>
            <div class="dtext">${r.denialReason || '—'}</div>
          </div>
        ` : ''}
      </div>
    `).join('');

    fsModalBody.querySelectorAll('.req-doc-thumb').forEach(img => img.addEventListener('click', () => openLightbox(img.dataset.src)));

    fsModalBody.querySelectorAll('.req-approve-btn').forEach(btn => btn.addEventListener('click', () => {
      approveRequest(Number(btn.dataset.reqId));
    }));

    fsModalBody.querySelectorAll('.req-deny-toggle-btn').forEach(btn => btn.addEventListener('click', () => {
      const box = document.getElementById(`denyBox-${btn.dataset.reqId}`);
      box.classList.toggle('open');
    }));

    fsModalBody.querySelectorAll('.req-deny-cancel-btn').forEach(btn => btn.addEventListener('click', () => {
      document.getElementById(`denyBox-${btn.dataset.reqId}`).classList.remove('open');
    }));

    fsModalBody.querySelectorAll('.req-deny-confirm-btn').forEach(btn => btn.addEventListener('click', () => {
      const reqId = Number(btn.dataset.reqId);
      const textarea = document.getElementById(`denyText-${reqId}`);
      const hint = document.getElementById(`denyHint-${reqId}`);
      const reason = textarea.value.trim();
      if (!reason) {
        hint.textContent = 'Please provide a reason for denial.';
        hint.classList.add('error');
        textarea.focus();
        return;
      }
      denyRequest(reqId, reason);
    }));
  }

  async function approveRequest(reqId) {
    const g = franchiseGroups.find(x => x.id === currentGroupId);
    if (!g) return;
    const r = g.requests.find(x => x.id === reqId);
    if (!r) return;
    try {
      await pendingRequest({ action: 'approve', type: r.actionType, id: r.id });
      r.status = 'Approved'; r.denialReason = ''; renderFsModal(); updateStats(); render();
    } catch (error) { alert(error.message); }
  }

  async function denyRequest(reqId, reason) {
    const g = franchiseGroups.find(x => x.id === currentGroupId);
    if (!g) return;
    const r = g.requests.find(x => x.id === reqId);
    if (!r) return;
    try {
      await pendingRequest({ action: 'deny', type: r.actionType, id: r.id, reason });
      r.status = 'Denied'; r.denialReason = reason; renderFsModal(); updateStats(); render();
    } catch (error) { alert(error.message); }
  }

  approveAllBtn.addEventListener('click', async () => {
    const g = franchiseGroups.find(x => x.id === currentGroupId);
    if (!g) return;
    for (const request of g.requests.filter(request => request.status === 'Pending')) {
      try {
        await pendingRequest({ action: 'approve', type: request.actionType, id: request.id });
        request.status = 'Approved'; request.denialReason = '';
      } catch (error) { alert(error.message); break; }
    }
    renderFsModal();
    updateStats();
    render();
  });

  loadPendingRequests();

  fetch('../controllers/notification.php', { credentials: 'same-origin' }).then(response => response.json()).then(result => {
    const count = (result.notifications || []).filter(notification => !notification.isRead).length;
    document.querySelectorAll('.notification-count').forEach(badge => { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; });
  }).catch(() => {});
  fetch('../controllers/pending.php', { credentials: 'same-origin' }).then(response => response.json()).then(result => {
    const count = (result.groups || []).flatMap(group => group.requests || []).filter(request => request.status === 'Pending').length;
    const badge = document.getElementById('navBadge');
    if (badge) { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; }
  }).catch(() => {});
