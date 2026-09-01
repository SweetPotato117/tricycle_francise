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

  /* ---------- data loaded from the drivers tables ---------- */
  const driverApi = '../controllers/driver_management.php';
  let drivers = [];
  const driverFiles = ['driverLicenseFile', 'orCrFile', 'presidentCertificateFile'];
  const driverFileData = { driverLicenseData: '', orCrData: '', presidentCertificateData: '' };

  function readDriverFile(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = event => { driverFileData[input.dataset.payload] = event.target.result; input.previousElementSibling.querySelector('span').textContent = file.name; };
    reader.readAsDataURL(file);
  }
  driverFiles.forEach(id => {
    const input = document.getElementById(id);
    input.dataset.payload = id === 'driverLicenseFile' ? 'driverLicenseData' : id === 'orCrFile' ? 'orCrData' : 'presidentCertificateData';
    input.style.display = 'none';
    input.previousElementSibling.addEventListener('click', () => input.click());
    input.addEventListener('change', () => readDriverFile(input));
  });

  async function apiRequest(payload = null) {
    const options = payload ? {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    } : {};
    const response = await fetch(driverApi, options);
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Request failed.');
    return result;
  }

  async function loadDrivers() {
    try {
      const result = await apiRequest();
      drivers = result.drivers;
      updateStats();
      render();
    } catch (error) {
      emptyState.classList.remove('hidden');
      emptyState.querySelector('div').textContent = error.message;
    }
  }

  const tableBody = document.getElementById('driverTableBody');
  const emptyState = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');
  const pageInfo = document.getElementById('pageInfo');
  const searchInput = document.getElementById('searchInput');
  const filterTabs = document.getElementById('filterTabs');

  let activeFilter = 'All';
  let searchTerm = '';

  function statusPillClass(status) {
    if (status === 'Pending') return 'status-pending';
    if (status === 'For Review') return 'status-review';
    return 'status-approved';
  }

  function initials(name) {
    return name.split(' ').filter(Boolean).slice(0, 2).map(p => p[0].toUpperCase()).join('');
  }

  function updateStats() {
    document.getElementById('statTotal').textContent = drivers.length;
    document.getElementById('statPending').textContent = drivers.filter(d => d.status === 'Pending').length;
    document.getElementById('statReview').textContent = drivers.filter(d => d.status === 'For Review').length;
    document.getElementById('statApproved').textContent = drivers.filter(d => d.status === 'Approved').length;
  }

  function render() {
    const term = searchTerm.trim().toLowerCase();
    const filtered = drivers.filter(d => {
      const matchesFilter = activeFilter === 'All' || d.status === activeFilter;
      const matchesSearch = !term || d.name.toLowerCase().includes(term) || d.contact.toLowerCase().includes(term);
      return matchesFilter && matchesSearch;
    });

    tableBody.innerHTML = '';

    if (filtered.length === 0) {
      emptyState.classList.remove('hidden');
    } else {
      emptyState.classList.add('hidden');
    }

    filtered.forEach(d => {
      const tr = document.createElement('tr');

      let actionButtons = `
        <button class="icon-btn view-btn" data-id="${d.id}" title="View">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button class="icon-btn edit-btn" data-id="${d.id}" title="Edit">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>`;

      if (d.status !== 'Approved') {
        actionButtons += `
        <button class="icon-btn approve" data-id="${d.id}" title="Approve">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </button>`;
      }

      tr.innerHTML = `
        <td>
          <div class="driver-cell">
            <div class="avatar">${initials(d.name)}</div>
            <div>
              <div class="driver-name">${d.name}</div>
              <div class="driver-sub">${d.gender}, ${d.age} yrs</div>
            </div>
          </div>
        </td>
        <td>${d.contact}</td>
        <td>${d.age} / ${d.gender}</td>
        <td>${d.tricycle}</td>
        <td><span class="status-pill ${statusPillClass(d.status)}">${d.status}</span></td>
        <td class="actions-cell">${actionButtons}</td>
      `;
      tableBody.appendChild(tr);
    });

    resultCount.textContent = `${filtered.length} driver${filtered.length === 1 ? '' : 's'}`;
    pageInfo.textContent = `Showing ${filtered.length} of ${drivers.length} drivers`;

    tableBody.querySelectorAll('.view-btn').forEach(btn => btn.addEventListener('click', () => openViewModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => openEditModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.approve').forEach(btn => btn.addEventListener('click', async () => {
      const driver = drivers.find(d => d.id === Number(btn.dataset.id));
      if (!driver) return;
      try {
        await apiRequest({ action: 'approve', id: driver.id });
        driver.status = 'Approved';
        updateStats();
        render();
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
  const driverModalOverlay = document.getElementById('driverModalOverlay');
  const driverModalTitle = document.getElementById('driverModalTitle');
  const driverForm = document.getElementById('driverForm');
  let editingId = null;

  function openAddModal() {
    editingId = null;
    driverModalTitle.textContent = 'Add Driver';
    driverForm.reset();
    driverFiles.forEach(id => { document.getElementById(id).value = ''; });
    driverFileData.driverLicenseData = '';
    driverFileData.orCrData = '';
    driverFileData.presidentCertificateData = '';
    driverModalOverlay.classList.add('open');
  }

  function openEditModal(id) {
    const d = drivers.find(x => x.id === id);
    if (!d) return;
    editingId = id;
    driverModalTitle.textContent = 'Edit Driver';
    document.getElementById('fFullName').value = d.name;
    document.getElementById('fContact').value = d.contact;
    document.getElementById('fAge').value = d.age;
    document.getElementById('fGender').value = d.gender;
    document.getElementById('fStatus').value = d.status;
    document.getElementById('fAddress').value = d.address;
    driverFiles.forEach(id => { document.getElementById(id).value = ''; });
    driverFileData.driverLicenseData = '';
    driverFileData.orCrData = '';
    driverFileData.presidentCertificateData = '';
    driverModalOverlay.classList.add('open');
  }

  function closeDriverModal() { driverModalOverlay.classList.remove('open'); }

  document.getElementById('addDriverBtn').addEventListener('click', openAddModal);
  document.getElementById('driverModalClose').addEventListener('click', closeDriverModal);
  document.getElementById('driverCancelBtn').addEventListener('click', closeDriverModal);
  driverModalOverlay.addEventListener('click', (e) => { if (e.target === driverModalOverlay) closeDriverModal(); });

  driverForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      name: document.getElementById('fFullName').value.trim(),
      contact: document.getElementById('fContact').value.trim(),
      age: Number(document.getElementById('fAge').value),
      gender: document.getElementById('fGender').value,
      status: document.getElementById('fStatus').value,
      address: document.getElementById('fAddress').value.trim(),
      tricycle: "Unassigned"
    };

    try {
      await apiRequest({ ...payload, ...driverFileData, action: editingId ? 'update' : 'create', ...(editingId ? { id: editingId } : {}) });
      await loadDrivers();
      closeDriverModal();
    } catch (error) {
      alert(error.message);
    }
  });

  /* ---------- view modal ---------- */
  const viewModalOverlay = document.getElementById('viewModalOverlay');

  function openViewModal(id) {
    const d = drivers.find(x => x.id === id);
    if (!d) return;
    document.getElementById('vFullName').textContent = d.name;
    document.getElementById('vStatus').innerHTML = `<span class="status-pill ${statusPillClass(d.status)}">${d.status}</span>`;
    document.getElementById('vContact').textContent = d.contact;
    document.getElementById('vAgeGender').textContent = `${d.age} yrs, ${d.gender}`;
    document.getElementById('vTricycle').textContent = d.tricycle;
    document.getElementById('vId').textContent = `#${String(d.id).padStart(4, '0')}`;
    document.getElementById('vAddress').textContent = d.address;
    const documentLinks = [d.driverLicense, d.orCr, d.presidentCertificate];
    document.querySelectorAll('#driverDocuments .doc-link').forEach((link, index) => {
      const url = documentLinks[index];
      const isPdf = /\.pdf$/i.test(url || '');
      link.textContent = url ? (isPdf ? 'Open PDF' : '') : 'No file';
      link.innerHTML = url && !isPdf
        ? `<img class="document-preview" src="${url}" alt="${['Driver license', 'OR/CR', "President's certificate"][index]}">`
        : link.textContent;
      link.style.display = url && !isPdf ? 'block' : '';
      link.onclick = url && isPdf ? () => window.open(url, '_blank') : null;
    });
    viewModalOverlay.classList.add('open');
  }

  function closeViewModal() { viewModalOverlay.classList.remove('open'); }

  document.getElementById('viewModalClose').addEventListener('click', closeViewModal);
  document.getElementById('viewCloseBtn').addEventListener('click', closeViewModal);
  viewModalOverlay.addEventListener('click', (e) => { if (e.target === viewModalOverlay) closeViewModal(); });

  /* ---------- init ---------- */
  loadDrivers();
