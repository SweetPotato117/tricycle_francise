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

  /* ---------- data loaded from the drivers tables ---------- */
  const driverApi = '../controllers/driver_management.php';
  let drivers = [];
  const driverFiles = ['driverLicenseFile', 'driverLicenseBackFile', 'orCrFile', 'presidentCertificateFile'];
  const driverFileData = { driverLicenseData: '', driverLicenseBackData: '', orCrData: '', presidentCertificateData: '' };

  function readDriverFile(input) {
    const file = input.files[0];
    if (!file) return Promise.resolve();
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = event => {
        driverFileData[input.dataset.payload] = event.target.result;
        input.previousElementSibling.querySelector('span').textContent = file.name;
        resolve();
      };
      reader.onerror = () => reject(new Error(`Unable to read ${file.name}.`));
      reader.readAsDataURL(file);
    });
  }
  driverFiles.forEach(id => {
    const input = document.getElementById(id);
    input.dataset.payload = {
      driverLicenseFile: 'driverLicenseData',
      driverLicenseBackFile: 'driverLicenseBackData',
      orCrFile: 'orCrData',
      presidentCertificateFile: 'presidentCertificateData'
    }[id];
    input.dataset.defaultLabel = input.previousElementSibling.querySelector('span').textContent;
    input.style.display = 'none';
    input.previousElementSibling.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file) {
        driverFileData[input.dataset.payload] = '';
        input.previousElementSibling.querySelector('span').textContent = input.dataset.defaultLabel;
        return;
      }
      if (id === 'driverLicenseBackFile' && file && (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
        input.value = '';
        driverFileData[input.dataset.payload] = '';
        input.previousElementSibling.querySelector('span').textContent = input.dataset.defaultLabel;
        alert('The driver license back must be a JPEG, PNG, GIF, or WebP image no larger than 5 MB.');
        return;
      }
      readDriverFile(input).catch(error => alert(error.message));
    });
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

      actionButtons += `
        <button class="icon-btn danger delete-btn" data-id="${d.id}" title="Remove">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </button>`;

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
    tableBody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => openDeleteModal(Number(btn.dataset.id))));
    tableBody.querySelectorAll('.approve').forEach(btn => btn.addEventListener('click', async () => {
      const driver = drivers.find(d => d.id === Number(btn.dataset.id));
      if (!driver) return;
      setSubmitLoading(btn, true);
      try {
        await apiRequest({ action: 'approve', id: driver.id });
        driver.status = 'Approved';
        updateStats();
        render();
      } catch (error) {
        alert(error.message);
      } finally {
        setSubmitLoading(btn, false);
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
    driverFiles.forEach(id => {
      const input = document.getElementById(id);
      input.value = '';
      input.previousElementSibling.querySelector('span').textContent = input.dataset.defaultLabel;
    });
    driverFileData.driverLicenseData = '';
    driverFileData.driverLicenseBackData = '';
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
    document.getElementById('fLicenseNumber').value = d.driverLicenseNumber || '';
    document.getElementById('fOrCrNumber').value = d.orCrNumber || '';
    document.getElementById('fStatus').value = d.status;
    document.getElementById('fAddress').value = d.address;
    driverFiles.forEach(id => {
      const input = document.getElementById(id);
      input.value = '';
      input.previousElementSibling.querySelector('span').textContent = input.dataset.defaultLabel;
    });
    driverFileData.driverLicenseData = '';
    driverFileData.driverLicenseBackData = '';
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
      driver_license_number: document.getElementById('fLicenseNumber').value.trim(),
      or_cr_number: document.getElementById('fOrCrNumber').value.trim(),
      status: document.getElementById('fStatus').value,
      address: document.getElementById('fAddress').value.trim(),
      tricycle: "Unassigned"
    };
    const submitButton = e.submitter;
    const backImage = document.getElementById('driverLicenseBackFile').files[0];
    if (!editingId && !backImage) {
      alert("Please upload the driver's license back image.");
      return;
    }
    if (backImage && (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(backImage.type) || backImage.size > 5 * 1024 * 1024)) {
      alert('The driver license back must be a JPEG, PNG, GIF, or WebP image no larger than 5 MB.');
      return;
    }
    try {
      await Promise.all(driverFiles.map(id => readDriverFile(document.getElementById(id))));
    } catch (error) {
      alert(error.message);
      return;
    }
    setSubmitLoading(submitButton, true);

    try {
      await apiRequest({ ...payload, ...driverFileData, action: editingId ? 'update' : 'create', ...(editingId ? { id: editingId } : {}) });
      await loadDrivers();
      closeDriverModal();
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
    document.getElementById('vLicenseNumber').textContent = d.driverLicenseNumber || '—';
    document.getElementById('vOrCrNumber').textContent = d.orCrNumber || '—';
    const documentLinks = [d.driverLicense, d.driverLicenseBack, d.orCr, d.presidentCertificate];
    const documentLabels = ["Driver license front", "Driver license back", "OR/CR", "President's certificate"];
    document.querySelectorAll('#driverDocuments .doc-link').forEach((link, index) => {
      const url = documentLinks[index];
      const isPdf = /\.pdf$/i.test(url || '');
      link.textContent = url ? (isPdf ? 'Open PDF' : '') : 'No file';
      if (url && !isPdf) {
        const previewButton = document.createElement('button');
        previewButton.type = 'button';
        previewButton.className = 'document-preview-trigger';
        previewButton.setAttribute('aria-label', `View ${documentLabels[index]}`);
        const image = document.createElement('img');
        image.className = 'document-preview';
        image.src = url;
        image.alt = documentLabels[index];
        previewButton.appendChild(image);
        previewButton.addEventListener('click', () => openImageViewer(url, documentLabels[index]));
        link.appendChild(previewButton);
      }
      link.style.display = url && !isPdf ? 'block' : '';
      link.onclick = url && isPdf ? () => window.open(url, '_blank') : null;
    });
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
    const d = drivers.find(x => x.id === id);
    if (!d) return;
    deletingId = id;
    document.getElementById('deleteTargetName').textContent = d.name;
    deleteModalOverlay.classList.add('open');
  }

  function closeDeleteModal() { deleteModalOverlay.classList.remove('open'); deletingId = null; }

  document.getElementById('deleteModalClose').addEventListener('click', closeDeleteModal);
  document.getElementById('deleteCancelBtn').addEventListener('click', closeDeleteModal);
  deleteModalOverlay.addEventListener('click', (e) => { if (e.target === deleteModalOverlay) closeDeleteModal(); });

  document.getElementById('deleteConfirmBtn').addEventListener('click', async (e) => {
    if (deletingId != null) {
      const confirmButton = e.currentTarget;
      setSubmitLoading(confirmButton, true);
      try {
        await apiRequest({ action: 'delete', id: deletingId });
        await loadDrivers();
      } catch (error) {
        alert(error.message);
      } finally {
        setSubmitLoading(confirmButton, false);
      }
    }
    closeDeleteModal();
  });

  /* ---------- init ---------- */
  loadDrivers();
