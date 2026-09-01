if (location.protocol === 'file:') {
  const target = 'http://localhost/tricycle_franchise/rider/rider_app.html';
  if (location.href !== target) {
    location.replace(target);
  }
}

const riderApi = '../controllers/rider_app.php';
const loginApi = '../controllers/rider_login.php';

const state = {
  profile: null,
  franchise: null,
  drivers: [],
  tricycles: [],
  notifications: [],
  renewals: [],
  activeNav: 'franchise',
  activeTricycleFilter: 'all',
  activeDriverFilter: 'all',
  tricycleSearch: '',
  driverSearch: '',
  refreshTimer: null
};

let editingTricycleId = null;
let editingDriverId = null;

function showToast(message, type = 'success') {
  const toast = document.getElementById('toast');
  const toastMsg = document.getElementById('toast-msg');
  if (!toast || !toastMsg) return;

  toast.classList.remove('error', 'success');
  toast.classList.add(type === 'error' ? 'error' : 'success');
  toastMsg.textContent = message;
  toast.classList.add('show');

  clearTimeout(showToast.timer);
  showToast.timer = setTimeout(() => {
    toast.classList.remove('show');
  }, 2200);
}

function setLoginError(message) {
  const errorBox = document.getElementById('login-error');
  const errorMsg = document.getElementById('login-error-msg');
  if (!errorBox || !errorMsg) return;

  errorMsg.textContent = message || 'Please enter both your username and password.';
  errorBox.style.display = message ? 'flex' : 'none';
}

function setAppVisible(visible) {
  const app = document.getElementById('app');
  const loginScreen = document.getElementById('login-screen');
  if (!app || !loginScreen) return;

  app.style.display = visible ? 'flex' : 'none';
  loginScreen.style.display = visible ? 'none' : 'flex';
  loginScreen.classList.toggle('hidden', visible);
  app.classList.toggle('is-visible', visible);
  app.setAttribute('aria-hidden', String(!visible));
  loginScreen.setAttribute('aria-hidden', String(visible));
}

function setActiveNav(nav) {
  state.activeNav = nav;
  document.querySelectorAll('.nav-btn').forEach((button) => {
    const active = button.dataset.nav === nav;
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-current', active ? 'page' : 'false');
  });

  document.querySelectorAll('.screen').forEach((screen) => {
    screen.classList.toggle('active', screen.id === `screen-${nav}`);
  });
}

function bindAccordionToggles() {
  document.querySelectorAll('[data-acc-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const accordion = button.closest('[data-acc]');
      if (accordion) accordion.classList.toggle('is-open');
    });
  });
}

function openDocumentViewer(url, title = 'Document') {
  const viewer = document.getElementById('doc-viewer');
  const image = document.getElementById('viewer-img');
  const viewerTitle = document.getElementById('viewer-title');
  if (!viewer || !image || !url) return;

  image.src = url;
  image.alt = title;
  if (viewerTitle) viewerTitle.textContent = title;
  viewer.classList.add('active');
}

function closeDocumentViewer() {
  const viewer = document.getElementById('doc-viewer');
  const image = document.getElementById('viewer-img');
  if (!viewer) return;
  viewer.classList.remove('active');
  if (image) image.src = '';
}

function bindDocumentViewer() {
  document.getElementById('viewer-close')?.addEventListener('click', closeDocumentViewer);
  document.getElementById('viewer-stage')?.addEventListener('click', (event) => {
    if (event.target === event.currentTarget) closeDocumentViewer();
  });
  document.getElementById('viewer-img')?.addEventListener('click', closeDocumentViewer);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeDocumentViewer();
  });
}

function readFileAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    if (!file) {
      resolve('');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => resolve(reader.result || '');
    reader.onerror = () => reject(new Error('Unable to read uploaded file.'));
    reader.readAsDataURL(file);
  });
}

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    },
    ...options
  });

  const contentType = response.headers.get('content-type') || '';
  const payload = contentType.includes('application/json') ? await response.json() : { success: false, message: 'Unexpected response from server.' };

  if (!response.ok || !payload.success) {
    throw new Error(payload.message || 'Request failed.');
  }

  return payload;
}

function normalizeStatus(raw) {
  const status = (raw || '').toString().trim();
  return status || 'Pending';
}

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function statBadgeClass(status) {
  const value = normalizeStatus(status).toLowerCase();
  if (value.includes('confirm')) return 'approved';
  if (value.includes('reject') || value.includes('fail') || value.includes('expired')) return 'expired';
  if (value.includes('submit') || value.includes('review') || value.includes('pending')) return 'pending';
  if (value === 'active') return 'approved';
  if (value === 'inactive') return 'approved';
  if (value === 'pending') return 'pending';
  if (value === 'expired') return 'expired';
  return 'approved';
}

function getRenewalTimelineEntries(renewal) {
  const status = normalizeStatus((renewal && renewal.status) || 'Submitted');
  const statusKey = status.toLowerCase();
  const submittedDate = renewal?.submittedAt || renewal?.createdAt || null;
  const confirmedDate = renewal?.confirmedAt || submittedDate;

  const steps = [
    {
      key: 'submitted',
      title: 'Renewal submitted',
      date: submittedDate,
      detail: 'Your payment receipt has been received and is awaiting review by the admin team.',
      tone: 'pending',
      label: 'Submitted'
    },
    {
      key: 'review',
      title: 'Admin reviewing renewal',
      date: submittedDate,
      detail: 'The renewal request is being checked to confirm the payment details and franchise validity.',
      tone: 'pending',
      label: 'Reviewing'
    }
  ];

  if (statusKey.includes('confirm')) {
    steps.push({
      key: 'success',
      title: 'Renewal success',
      date: confirmedDate,
      detail: 'Your franchise renewal has been successfully approved and is now active.',
      tone: 'success',
      label: 'Success'
    });
  } else if (statusKey.includes('reject') || statusKey.includes('fail') || statusKey.includes('decline')) {
    steps.push({
      key: 'error',
      title: 'Renewal failed',
      date: confirmedDate,
      detail: 'The renewal request was not approved. Please contact the admin team for the next steps or additional documents.',
      tone: 'error',
      label: 'Failed'
    });
  }

  return steps;
}

function renderFranchiseSummary() {
  const franchise = state.franchise || {};
  const registered = Boolean(franchise.registered);

  const registeredView = document.getElementById('franchise-registered-view');
  const emptyView = document.getElementById('franchise-empty-view');
  if (registeredView && emptyView) {
    registeredView.style.display = registered ? 'block' : 'none';
    emptyView.style.display = registered ? 'none' : 'block';
  }

  const summaryName = document.getElementById('summary-franchise-name');
  const summaryStatus = document.getElementById('summary-franchise-status');
  if (summaryName) {
    summaryName.textContent = franchise.name || 'No franchise yet';
  }
  if (summaryStatus) {
    summaryStatus.textContent = franchise.status || 'Pending';
    summaryStatus.className = `badge ${statBadgeClass(franchise.status)}`;
  }

  const topbarName = document.getElementById('franchise-topbar-name');
  if (topbarName) {
    topbarName.textContent = franchise.name || 'Franchise Manager';
  }

  const emptyTitle = document.getElementById('franchise-empty-title');
  const emptyMessage = document.getElementById('franchise-empty-message');
  if (!registered && emptyTitle) {
    emptyTitle.textContent = franchise.hasApplication ? 'Application Under Review' : 'No Franchise Registered';
  }
  if (!registered && emptyMessage) {
    emptyMessage.textContent = franchise.hasApplication
      ? 'Your franchise application is waiting for admin review.'
      : 'You do not have a registered franchise yet. Register your franchise to start adding tricycles and drivers.';
  }

  const applicationStatus = document.getElementById('franchise-application-status');
  const applicationStatusMessage = document.getElementById('franchise-application-status-message');
  if (applicationStatus && applicationStatusMessage) {
    const expiryDate = franchise.expiry ? new Date(`${franchise.expiry}T00:00:00`) : null;
    const daysUntilExpiry = expiryDate && !Number.isNaN(expiryDate.getTime())
      ? Math.ceil((expiryDate - new Date(new Date().setHours(0, 0, 0, 0))) / 86400000)
      : null;
    const isExpiringSoon = franchise.registered && franchise.status === 'Active' && daysUntilExpiry !== null && daysUntilExpiry >= 0 && daysUntilExpiry <= 20;
    const isExpired = franchise.registered && (franchise.status === 'Expired' || (daysUntilExpiry !== null && daysUntilExpiry < 0));
    applicationStatus.style.display = isExpiringSoon || isExpired || (franchise.registered && franchise.status !== 'Active') ? 'block' : 'none';
    applicationStatus.className = `application-status ${isExpired ? 'not-approved' : isExpiringSoon ? 'warning' : ''}`;
    if (isExpiringSoon) {
      applicationStatusMessage.textContent = `Your franchise is due to expire in ${daysUntilExpiry} day${daysUntilExpiry === 1 ? '' : 's'}. Please submit your renewal before the due date.`;
    } else if (isExpired) {
      applicationStatusMessage.textContent = 'Your franchise has expired. Please submit a renewal application immediately.';
    } else if (franchise.registered && franchise.status !== 'Active') {
      applicationStatusMessage.textContent = `Your franchise status is ${franchise.status}. Please contact the LGU for assistance.`;
    }
  }

  const pendingBanner = document.getElementById('pending-approval-banner');
  const pendingMessage = document.getElementById('pending-approval-message');
  if (pendingBanner && pendingMessage) {
    const show = Boolean(franchise.hasApplication && !franchise.registered);
    pendingBanner.style.display = show ? 'block' : 'none';
    pendingMessage.textContent = franchise.hasApplication ? 'Your application is currently being reviewed by the admin.' : 'Waiting for approval.';
  }

  const renewalStatus = document.getElementById('renewal-status-summary');
  const currentStatus = normalizeStatus((franchise && franchise.status) || 'Pending');
  if (renewalStatus) {
    renewalStatus.textContent = currentStatus;
    renewalStatus.className = `num ${currentStatus.toLowerCase() === 'active' ? 'approved' : currentStatus.toLowerCase() === 'expired' ? 'expired' : 'pending'}`;
  }
}

function buildTricycleListItem(tricycle) {
  const card = document.createElement('button');
  card.type = 'button';
  card.className = 'card';
  card.dataset.id = tricycle.id;
  card.innerHTML = `
    <div class="card-icon gold">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="7" cy="17" r="2"></circle>
        <circle cx="17" cy="17" r="2"></circle>
        <path d="M5 17H3v-5l2-5h9l3 5h2a2 2 0 0 1 2 2v3h-2"></path>
        <path d="M14 7v5h5"></path>
      </svg>
    </div>
    <div class="card-body">
      <div class="card-title-row">
        <span class="card-title">${tricycle.brand || 'Tricycle'}</span>
        <span class="badge ${statBadgeClass(tricycle.status)}">${normalizeStatus(tricycle.status)}</span>
      </div>
      <div class="card-sub">
        <span>${tricycle.unit || 'No sticker number'}</span>
        <span class="dot"></span>
        <span>${tricycle.plate || 'No plate'}</span>
        <span class="dot"></span>
        <span>${tricycle.driver || 'Unassigned'}</span>
      </div>
    </div>
    <div class="card-chevron">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"></path></svg>
    </div>
  `;
  card.addEventListener('click', () => {
    const detail = document.getElementById('detail-tricycle');
    if (!detail) return;
    document.getElementById('td-unit-no').textContent = tricycle.unit || 'Not specified';
    document.getElementById('td-plate-sub').textContent = tricycle.plate || 'No plate';
    document.getElementById('td-vehicle-fields').innerHTML = `
      <div class="field-row"><span>Body Number / Sticker</span><strong>${tricycle.unit || '—'}</strong></div>
      <div class="field-row"><span>Brand</span><strong>${tricycle.brand || 'Not specified'}</strong></div>
      <div class="field-row"><span>Plate No.</span><strong>${tricycle.plate || '—'}</strong></div>
      <div class="field-row"><span>Engine No.</span><strong>${tricycle.engine || '—'}</strong></div>
      <div class="field-row"><span>Chassis No.</span><strong>${tricycle.chassis || '—'}</strong></div>
      <div class="field-row"><span>Color</span><strong>${tricycle.color || '—'}</strong></div>
    `;
    const documents = tricycle.docs || [];
    document.getElementById('td-documents').innerHTML = documents.length
      ? documents.map((document) => {
          const isPdf = /\.pdf$/i.test(document.url || '');
          return isPdf
            ? `<a class="doc-item" href="${document.url}" target="_blank" rel="noopener" aria-label="Open ${document.name}"></a>`
            : `<button type="button" class="doc-item" aria-label="View ${document.name}"><img src="${document.url}" alt="${document.name}"></button>`;
        }).join('')
      : '<div class="empty-doc">No documents uploaded yet.</div>';
    document.querySelectorAll('#td-documents img').forEach((image) => {
      image.closest('.doc-item')?.addEventListener('click', () => openDocumentViewer(image.src, image.alt));
    });
    const editButton = document.getElementById('td-more');
    editButton.style.display = normalizeStatus(tricycle.status).toLowerCase() === 'pending' ? 'inline-flex' : 'none';
    editButton.dataset.tricycleId = tricycle.id;
    detail.classList.add('active');
  });
  return card;
}

function buildDriverListItem(driver) {
  const card = document.createElement('button');
  card.type = 'button';
  card.className = 'card';
  card.dataset.id = driver.id;
  card.innerHTML = `
    <div class="card-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="8" r="4"></circle>
        <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
      </svg>
    </div>
    <div class="card-body">
      <div class="card-title-row">
        <span class="card-title">${driver.name || 'Driver'}</span>
        <span class="badge ${statBadgeClass(driver.status)}">${normalizeStatus(driver.status)}</span>
      </div>
      <div class="card-sub">
        <span>${driver.license || 'No license'}</span>
        <span class="dot"></span>
        <span>${driver.contact || 'No contact'}</span>
      </div>
    </div>
    <div class="card-chevron">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"></path></svg>
    </div>
  `;
  card.addEventListener('click', () => {
    const detail = document.getElementById('detail-driver');
    if (!detail) return;
    document.getElementById('dd-name').textContent = driver.name || 'Driver';
    document.getElementById('dd-sub').textContent = `${driver.license || 'No license'} • ${driver.contact || 'No contact'}`;
    document.getElementById('dd-personal-fields').innerHTML = `
      <div class="field-row"><span>Full Name</span><strong>${driver.name || '—'}</strong></div>
      <div class="field-row"><span>Date of Birth</span><strong>${driver.dob || '—'}</strong></div>
      <div class="field-row"><span>Address</span><strong>${driver.address || '—'}</strong></div>
      <div class="field-row"><span>Contact</span><strong>${driver.contact || '—'}</strong></div>
    `;
    document.getElementById('dd-driver-fields').innerHTML = `
      <div class="field-row"><span>License</span><strong>${driver.license || '—'}</strong></div>
      <div class="field-row"><span>Status</span><strong>${normalizeStatus(driver.status)}</strong></div>
      <div class="field-row"><span>Assigned Tricycle</span><strong>${driver.tricycle || 'Unassigned'}</strong></div>
    `;
    document.getElementById('dd-documents').innerHTML = (driver.docs && driver.docs.length)
      ? driver.docs.map((doc) => {
          const isPdf = /\.pdf$/i.test(doc.url || '');
          return isPdf
            ? `<a class="doc-item" href="${doc.url}" target="_blank" rel="noopener" aria-label="Open ${doc.name}">${doc.name}</a>`
            : `<button type="button" class="doc-item" aria-label="View ${doc.name}"><img src="${doc.url}" alt="${doc.name}"></button>`;
        }).join('')
      : '<div class="empty-doc">No documents uploaded yet.</div>';
    document.querySelectorAll('#dd-documents img').forEach((image) => {
      image.closest('.doc-item')?.addEventListener('click', () => openDocumentViewer(image.src, image.alt));
    });
    const editButton = document.getElementById('dd-edit-btn');
    editButton.style.display = normalizeStatus(driver.status).toLowerCase() === 'pending' ? 'inline-flex' : 'none';
    editButton.dataset.driverId = driver.id;
    const assignButton = document.getElementById('dd-assign-btn');
    const tricycleSelect = document.getElementById('dd-tricycle-select');
    const canAssign = !driver.tricycleId && normalizeStatus(driver.status).toLowerCase() !== 'inactive';
    if (assignButton && tricycleSelect) {
      const availableTricycles = (state.tricycles || []).filter((tricycle) => {
        return normalizeStatus(tricycle.status).toLowerCase() === 'active' && !state.drivers.some((item) => item.tricycleId === tricycle.id);
      });
      tricycleSelect.innerHTML = '<option value="">Select tricycle</option>' + availableTricycles.map((tricycle) => `<option value="${tricycle.id}">${tricycle.brand || 'Tricycle'} - ${tricycle.unit || tricycle.plate || `Unit ${tricycle.id}`}</option>`).join('');
      tricycleSelect.style.display = canAssign && availableTricycles.length ? 'inline-flex' : 'none';
      assignButton.style.display = canAssign && availableTricycles.length ? 'inline-flex' : 'none';
      assignButton.dataset.driverId = driver.id;
      assignButton.onclick = () => assignDriver(driver.id, tricycleSelect.value);
    }
    detail.classList.add('active');
  });
  return card;
}

function renderTricycles() {
  const list = document.getElementById('tricycle-list');
  if (!list) return;

  const term = state.tricycleSearch.trim().toLowerCase();
  const filtered = (state.tricycles || []).filter((tricycle) => {
    const matchesFilter = state.activeTricycleFilter === 'all' || normalizeStatus(tricycle.status) === state.activeTricycleFilter;
    const haystack = [tricycle.unit, tricycle.plate, tricycle.driver, tricycle.id].join(' ').toLowerCase();
    const matchesSearch = !term || haystack.includes(term);
    return matchesFilter && matchesSearch;
  });

  list.innerHTML = '';
  filtered.forEach((tricycle) => list.appendChild(buildTricycleListItem(tricycle)));
}

function renderDrivers() {
  const list = document.getElementById('driver-list');
  if (!list) return;

  const term = state.driverSearch.trim().toLowerCase();
  const filtered = (state.drivers || []).filter((driver) => {
    const matchesFilter = state.activeDriverFilter === 'all' || normalizeStatus(driver.status) === state.activeDriverFilter;
    const haystack = [driver.name, driver.license, driver.contact].join(' ').toLowerCase();
    const matchesSearch = !term || haystack.includes(term);
    return matchesFilter && matchesSearch;
  });

  list.innerHTML = '';
  filtered.forEach((driver) => list.appendChild(buildDriverListItem(driver)));
}

async function assignDriver(driverId, tricycleId) {
  if (!tricycleId) {
    showToast('Please select an available tricycle first.', 'error');
    return;
  }
  try {
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({ action: 'assign-driver', driver_id: driverId, tricycle_id: tricycleId })
    });
    showToast('Tricycle assigned successfully.');
    document.querySelector('[data-close="detail-driver"]')?.click();
    await refreshDashboard();
  } catch (error) {
    showToast(error.message || 'Unable to assign tricycle.', 'error');
  }
}

function populateActiveDriverOptions() {
  const select = document.getElementById('t-driver');
  if (!select) return;

  const activeDrivers = (state.drivers || []).filter((driver) => normalizeStatus(driver.status).toLowerCase() === 'active');
  select.innerHTML = '<option value="">— Unassigned —</option>' + activeDrivers
    .map((driver) => `<option value="${driver.id}">${driver.name}</option>`)
    .join('');
}

function populateActiveTricycleOptions() {
  const select = document.getElementById('f-tricycle');
  if (!select) return;

  const activeTricycles = (state.tricycles || []).filter((tricycle) => normalizeStatus(tricycle.status).toLowerCase() === 'active');
  select.innerHTML = '<option value="">— Unassigned —</option>' + activeTricycles
    .map((tricycle) => `<option value="${tricycle.id}">${tricycle.brand || 'Tricycle'} - ${tricycle.unit || tricycle.plate || `Unit ${tricycle.id}`}</option>`)
    .join('');
}

function renderNotifications() {
  const container = document.getElementById('notification-list');
  if (!container) return;

  const notifications = state.notifications || [];
  const getIcon = (severity) => {
    if (severity === 'danger') {
      return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"></path></svg>';
    }
    if (severity === 'warning') {
      return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 19h20L12 2Z"></path><path d="M12 8v5"></path><path d="M12 17h.01"></path></svg>';
    }
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z"></path><path d="m9 12 2 2 4-4"></path></svg>';
  };

  container.innerHTML = notifications.length
    ? notifications.map((row) => {
        const severity = row.severity || 'info';
        return `
          <div class="notif-card ${row.isRead ? '' : 'unread'}">
            <div class="notif-icon ${severity}">${getIcon(severity)}</div>
            <div style="flex:1; min-width:0;">
              <div class="notif-title">${row.title || 'Alert'}</div>
              <div class="notif-desc">${row.message || ''}</div>
              <div class="notif-time">${formatDate(row.created_at || row.createdAt)}</div>
            </div>
          </div>
        `;
      }).join('')
    : '<div class="empty-state"><p>No notifications yet.</p></div>';

  const count = notifications.filter((item) => !item.isRead).length;
  document.querySelectorAll('.nbadge').forEach((badge) => {
    badge.textContent = count > 0 ? (count > 9 ? '9+' : String(count)) : '0';
    badge.style.display = count > 0 ? 'inline-flex' : 'none';
  });
}

function renderRenewals() {
  const container = document.getElementById('renewal-list');
  const emptyState = document.getElementById('renewal-empty-state');
  const historyWrap = document.getElementById('renewal-history');
  const renewals = Array.isArray(state.renewals) ? state.renewals : [];

  if (!container || !emptyState || !historyWrap) return;

  if (!renewals.length) {
    historyWrap.style.display = 'none';
    emptyState.style.display = 'block';
    emptyState.innerHTML = '<p>No renewal has been submitted yet.</p>';
    return;
  }

  emptyState.style.display = 'none';
  historyWrap.style.display = 'block';
  container.innerHTML = renewals.map((renewal) => {
    const status = normalizeStatus(renewal.status || 'Submitted');
    const timelineEntries = getRenewalTimelineEntries(renewal);
    return `
      <article class="renewal-history-card">
        <div class="renewal-history-heading">
          <div>
            <div class="renewal-history-label">Renewal for</div>
            <h3>Year ${renewal.year || '—'}</h3>
          </div>
          <span class="badge ${statBadgeClass(status)}">${status}</span>
        </div>
        <div class="renewal-timeline">
          ${timelineEntries.map((step) => `
            <div class="timeline-item ${step.tone}">
              <div class="timeline-node" aria-hidden="true"></div>
              <div class="timeline-content">
                <div class="timeline-top">
                  <span class="timeline-title">${step.title}</span>
                  <span class="timeline-badge ${step.tone}">${step.label}</span>
                </div>
                <div class="timeline-date">${formatDate(step.date)}</div>
                <div class="timeline-detail">${step.detail}</div>
              </div>
            </div>
          `).join('')}
        </div>
      </article>
    `;
  }).join('');
}

function hydrateProfile() {
  const profile = state.profile || {};
  const name = profile.name || 'Rider';
  const role = profile.role || 'Rider';
  const email = profile.email || '';
  const avatar = document.querySelector('.profile-avatar');

  if (avatar) avatar.textContent = name.charAt(0).toUpperCase();
  document.getElementById('profile-display-name').textContent = name;
  document.getElementById('profile-display-role').textContent = role;
  document.getElementById('as-name').value = name;
  document.getElementById('as-email').value = email;
  document.getElementById('as-contact').value = profile.contact || '';
}

async function handleApplyRenewal(evt) {
  evt.preventDefault();
  const franchise = state.franchise || {};
  if (!franchise.registered || !franchise.id) {
    showToast('You need a registered franchise before applying for renewal.', 'error');
    return;
  }

  const file = document.getElementById('renewal-receipt')?.files?.[0];
  if (!file) {
    showToast('Please upload the payment receipt photo.', 'error');
    return;
  }

  try {
    const receiptDataUrl = await readFileAsDataUrl(file);
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        action: 'apply-renewal',
        franchise_id: franchise.id,
        receiptDataUrl
      })
    });

    evt.target.reset();
    showToast('Renewal application submitted successfully. Our admin team is reviewing your receipt.');
    await refreshDashboard();
    setActiveNav('renew');
  } catch (error) {
    showToast(error.message || 'Unable to submit renewal.', 'error');
  }
}

async function refreshDashboard(resetNavigation = true) {
  try {
    const result = await requestJson(riderApi, { method: 'GET' });
    state.profile = result.profile || {};
    state.franchise = result.franchise || {};
    state.drivers = result.drivers || [];
    state.tricycles = result.tricycles || [];
    state.notifications = result.notifications || [];
    state.renewals = result.renewals || [];
    populateActiveDriverOptions();
    populateActiveTricycleOptions();

    renderFranchiseSummary();
    renderTricycles();
    renderDrivers();
    renderNotifications();
    renderRenewals();
    hydrateProfile();
    setAppVisible(true);
    if (resetNavigation) setActiveNav('franchise');
    return true;
  } catch (error) {
    if (resetNavigation) {
      setAppVisible(false);
      setLoginError('');
    }
    return false;
  }
}

async function handleLogin(evt) {
  evt.preventDefault();
  const username = document.getElementById('login-username').value.trim();
  const password = document.getElementById('login-password').value;

  if (!username || !password) {
    setLoginError('Please enter both your email/username and password.');
    return;
  }

  try {
    const response = await requestJson(loginApi, {
      method: 'POST',
      body: JSON.stringify({ action: 'login', username, password })
    });

    setLoginError('');
    const dashboardLoaded = await refreshDashboard();
    if (!dashboardLoaded) {
      setLoginError('Session could not be restored. Please try again.');
      return;
    }
    showToast(`${response.name || 'Welcome'} logged in successfully.`);
  } catch (error) {
    setAppVisible(false);
    setLoginError(error.message || 'Unable to log in.');
  }
}

async function handleRegister(evt) {
  evt.preventDefault();
  const name = document.getElementById('register-name').value.trim();
  const email = document.getElementById('register-email').value.trim();
  const password = document.getElementById('register-password').value;

  if (!name || !email || password.length < 8) {
    showToast('Please complete the account form with a valid email and 8-character password.', 'error');
    return;
  }

  try {
    const response = await requestJson(loginApi, {
      method: 'POST',
      body: JSON.stringify({ action: 'register', name, email, password })
    });

    showToast(response.message || 'Account created successfully.');
    document.getElementById('register-name').value = '';
    document.getElementById('register-email').value = '';
    document.getElementById('register-password').value = '';
    document.getElementById('show-rider-login').click();
  } catch (error) {
    showToast(error.message || 'Unable to create account.', 'error');
  }
}

async function handleCreateFranchise(evt) {
  evt.preventDefault();
  const name = document.getElementById('rf-name').value.trim();
  const owner = document.getElementById('rf-owner').value.trim();
  const address = document.getElementById('rf-address').value.trim();
  const file = document.getElementById('rf-receipt').files[0];

  if (!name || !owner) {
    showToast('Please complete the franchise form fields.', 'error');
    return;
  }

  try {
    const receiptDataUrl = await readFileAsDataUrl(file);
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        action: 'create-franchise',
        name,
        owner,
        address,
        receiptDataUrl
      })
    });

    showToast('Franchise application submitted.');
    evt.target.reset();
    document.querySelector('[data-close="screen-register-franchise"]').click();
    await refreshDashboard();
  } catch (error) {
    showToast(error.message || 'Unable to submit franchise application.', 'error');
  }
}

async function handleCreateDriver(evt) {
  evt.preventDefault();
  const name = document.getElementById('f-name').value.trim();
  const dob = document.getElementById('f-dob').value;
  const contact = document.getElementById('f-contact').value.trim();
  const address = document.getElementById('f-address').value.trim();
  const tricycleId = document.getElementById('f-tricycle').value || null;
  const fileInputLicense = document.getElementById('f-license');
  const fileInputOr = document.getElementById('f-or');
  const fileInputPresident = document.getElementById('f-president');

  if (!name || !dob || !contact) {
    showToast('Please provide your full name, date of birth, and contact number.', 'error');
    return;
  }

  try {
    const payload = {
      action: 'create',
      name,
      dob,
      contact,
      address,
      tricycle_id: tricycleId,
      licenseData: await readFileAsDataUrl(fileInputLicense?.files?.[0] || null),
      orcrData: await readFileAsDataUrl(fileInputOr?.files?.[0] || null),
      presidentsData: await readFileAsDataUrl(fileInputPresident?.files?.[0] || null)
    };

    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        ...payload,
        action: editingDriverId ? 'update-driver' : 'create',
        ...(editingDriverId ? { id: editingDriverId } : {})
      })
    });

    showToast(editingDriverId ? 'Driver submission updated.' : 'Driver registered successfully.');
    editingDriverId = null;
    document.getElementById('driver-form-title').textContent = 'Add Driver';
    document.getElementById('driver-submit-label').textContent = 'Register Driver';
    document.getElementById('f-license').required = true;
    document.getElementById('f-dob').required = true;
    document.getElementById('f-license-exp').required = true;
    evt.target.reset();
    document.querySelector('[data-close="screen-add-driver"]').click();
    await refreshDashboard();
  } catch (error) {
    showToast(error.message || 'Unable to save driver.', 'error');
  }
}

async function handleCreateTricycle(evt) {
  evt.preventDefault();
  const brand = document.getElementById('t-brand').value.trim();
  const sticker = document.getElementById('t-unit').value.trim();
  const plate = document.getElementById('t-plate').value.trim();
  const engine = document.getElementById('t-engine').value.trim();
  const chassis = document.getElementById('t-chassis').value.trim();
  const color = document.getElementById('t-color').value.trim();
  const orDocument = await readFileAsDataUrl(document.getElementById('t-or')?.files?.[0] || null);

  const driverId = document.getElementById('t-driver').value || null;
  if (!brand || !sticker || !plate || !engine || !chassis || !color) {
    showToast('Please complete all required tricycle details.', 'error');
    return;
  }

  try {
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        action: editingTricycleId ? 'update-tricycle' : 'create-tricycle',
        ...(editingTricycleId ? { id: editingTricycleId } : {}),
        brand,
        sticker,
        plate,
        engine,
        chassis,
        color,
        orDocumentData: orDocument,
        driver_id: driverId,
        status: 'Pending'
      })
    });

    showToast(editingTricycleId ? 'Tricycle submission updated.' : 'Tricycle added successfully.');
    editingTricycleId = null;
    document.getElementById('tricycle-form-title').textContent = 'Add Tricycle';
    document.getElementById('tricycle-submit-label').textContent = 'Register Tricycle';
    evt.target.reset();
    document.querySelector('[data-close="screen-add-tricycle"]').click();
    await refreshDashboard();
  } catch (error) {
    showToast(error.message || 'Unable to add tricycle.', 'error');
  }
}

async function handleUpdateProfile(evt) {
  evt.preventDefault();
  const name = document.getElementById('as-name').value.trim();
  const email = document.getElementById('as-email').value.trim();
  const contact = document.getElementById('as-contact').value.trim();

  if (!name || !email) {
    showToast('Name and email are required.', 'error');
    return;
  }

  try {
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        action: 'update-profile',
        id: state.profile?.id,
        name,
        email,
        contact
      })
    });

    showToast('Profile updated successfully.');
    document.querySelector('[data-close="screen-account-settings"]').click();
    await refreshDashboard();
  } catch (error) {
    showToast(error.message || 'Unable to save profile.', 'error');
  }
}

function ensureUploadGroups() {
  const driverGroup = document.getElementById('upload-groups');
  if (driverGroup && driverGroup.children.length === 0) {
    const documents = [
      { id: 'f-license', label: "Driver's License", required: true },
      { id: 'f-or', label: 'OR/CR', required: false },
      { id: 'f-president', label: "President's Certificate", required: false }
    ];

    documents.forEach((doc) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'field';
      wrapper.innerHTML = `
        <label for="${doc.id}">${doc.label}</label>
        <input id="${doc.id}" type="file" accept="image/*,.pdf" ${doc.required ? 'required' : ''}>
      `;
      driverGroup.appendChild(wrapper);
    });
  }

  const tricycleGroup = document.getElementById('tricycle-upload-groups');
  if (tricycleGroup && tricycleGroup.children.length === 0) {
    const docs = [
      { id: 't-or', label: 'OR Document', required: true }
    ];

    docs.forEach((doc) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'field';
      wrapper.innerHTML = `
        <label for="${doc.id}">${doc.label}</label>
        <input id="${doc.id}" type="file" accept="image/*,.pdf" ${doc.required ? 'required' : ''}>
      `;
      tricycleGroup.appendChild(wrapper);
    });
  }
}

function bindScreenActions() {
  ensureUploadGroups();
  bindAccordionToggles();
  bindDocumentViewer();

  document.querySelectorAll('.nav-btn').forEach((button) => {
    button.addEventListener('click', () => {
      const nav = button.dataset.nav;
      if (nav) setActiveNav(nav);
    });
  });

  document.querySelectorAll('[data-close]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = button.dataset.close;
      const screen = document.getElementById(target);
      if (screen) screen.classList.remove('active');
      if (target === 'screen-register-franchise') {
        document.getElementById('register-franchise-form').reset();
      }
    });
  });

  document.getElementById('login-toggle-pw')?.addEventListener('click', () => {
    const input = document.getElementById('login-password');
    const password = input.type === 'password' ? 'text' : 'password';
    input.type = password;
  });

  document.getElementById('show-rider-register')?.addEventListener('click', () => {
    document.getElementById('login-form').style.display = 'none';
    document.getElementById('rider-register-form').style.display = 'block';
  });

  document.getElementById('show-rider-login')?.addEventListener('click', () => {
    document.getElementById('rider-register-form').style.display = 'none';
    document.getElementById('login-form').style.display = 'block';
  });

  document.getElementById('login-form')?.addEventListener('submit', handleLogin);
  document.getElementById('rider-register-form')?.addEventListener('submit', handleRegister);
  document.getElementById('register-franchise-form')?.addEventListener('submit', handleCreateFranchise);
  document.getElementById('renewal-form')?.addEventListener('submit', handleApplyRenewal);
  document.getElementById('add-driver-form')?.addEventListener('submit', handleCreateDriver);
  document.getElementById('dd-edit-btn')?.addEventListener('click', () => {
    const driver = state.drivers.find((item) => String(item.id) === String(document.getElementById('dd-edit-btn').dataset.driverId));
    if (!driver || normalizeStatus(driver.status).toLowerCase() !== 'pending') return;
    editingDriverId = driver.id;
    document.getElementById('driver-form-title').textContent = 'Edit Driver Submission';
    document.getElementById('driver-submit-label').textContent = 'Save Changes';
    document.getElementById('add-driver-form').reset();
    document.getElementById('f-license').required = false;
    document.getElementById('f-dob').required = false;
    document.getElementById('f-license-exp').required = false;
    document.getElementById('f-name').value = driver.name || '';
    document.getElementById('f-contact').value = driver.contact || '';
    document.getElementById('f-address').value = driver.address || '';
    document.getElementById('f-tricycle').value = driver.tricycleId || '';
    document.querySelector('[data-close="detail-driver"]').click();
    document.getElementById('screen-add-driver').classList.add('active');
  });
  document.getElementById('add-tricycle-form')?.addEventListener('submit', handleCreateTricycle);
  document.getElementById('td-more')?.addEventListener('click', () => {
    const tricycle = state.tricycles.find((item) => String(item.id) === String(document.getElementById('td-more').dataset.tricycleId));
    if (!tricycle || normalizeStatus(tricycle.status).toLowerCase() !== 'pending') return;
    editingTricycleId = tricycle.id;
    document.getElementById('tricycle-form-title').textContent = 'Edit Tricycle Submission';
    document.getElementById('tricycle-submit-label').textContent = 'Save Changes';
    document.getElementById('add-tricycle-form').reset();
    document.getElementById('t-or').required = false;
    document.getElementById('t-brand').value = tricycle.brand || '';
    document.getElementById('t-unit').value = tricycle.unit || '';
    document.getElementById('t-plate').value = tricycle.plate || '';
    document.getElementById('t-engine').value = tricycle.engine || '';
    document.getElementById('t-chassis').value = tricycle.chassis || '';
    document.getElementById('t-color').value = tricycle.color || '';
    const driver = state.drivers.find((item) => item.name === tricycle.driver);
    document.getElementById('t-driver').value = driver?.id || '';
    document.querySelector('[data-close="detail-tricycle"]').click();
    document.getElementById('screen-add-tricycle').classList.add('active');
  });
  document.getElementById('account-settings-form')?.addEventListener('submit', handleUpdateProfile);

  document.getElementById('btn-open-register-franchise')?.addEventListener('click', () => {
    const screen = document.getElementById('screen-register-franchise');
    if (screen) screen.classList.add('active');
  });

  document.getElementById('fab-add-driver')?.addEventListener('click', () => {
    editingDriverId = null;
    document.getElementById('driver-form-title').textContent = 'Add Driver';
    document.getElementById('driver-submit-label').textContent = 'Register Driver';
    document.getElementById('f-license').required = true;
    document.getElementById('f-dob').required = true;
    document.getElementById('f-license-exp').required = true;
    const screen = document.getElementById('screen-add-driver');
    if (screen) screen.classList.add('active');
  });

  document.getElementById('fab-add-tricycle')?.addEventListener('click', () => {
    editingTricycleId = null;
    document.getElementById('tricycle-form-title').textContent = 'Add Tricycle';
    document.getElementById('tricycle-submit-label').textContent = 'Register Tricycle';
    document.getElementById('t-or').required = true;
    const screen = document.getElementById('screen-add-tricycle');
    if (screen) screen.classList.add('active');
  });

  document.getElementById('open-account-settings')?.addEventListener('click', () => {
    const screen = document.getElementById('screen-account-settings');
    if (screen) screen.classList.add('active');
  });

  document.getElementById('logout-btn')?.addEventListener('click', async () => {
    try {
      await requestJson(loginApi, {
        method: 'POST',
        body: JSON.stringify({ action: 'logout' })
      });
      setAppVisible(false);
      setLoginError('');
      document.getElementById('login-form')?.reset();
      document.getElementById('rider-register-form')?.reset();
      document.getElementById('login-password').value = '';
      document.getElementById('login-username').focus();
    } catch (error) {
      showToast(error.message || 'Unable to log out.', 'error');
    }
  });

  document.getElementById('tricycle-search')?.addEventListener('input', (event) => {
    state.tricycleSearch = event.target.value;
    renderTricycles();
  });

  document.getElementById('driver-search')?.addEventListener('input', (event) => {
    state.driverSearch = event.target.value;
    renderDrivers();
  });

  document.querySelectorAll('#tricycle-filters .chip').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelectorAll('#tricycle-filters .chip').forEach((chip) => chip.classList.toggle('is-active', chip === button));
      state.activeTricycleFilter = button.dataset.filter || 'all';
      renderTricycles();
    });
  });

  document.querySelectorAll('#driver-filters .chip').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelectorAll('#driver-filters .chip').forEach((chip) => chip.classList.toggle('is-active', chip === button));
      state.activeDriverFilter = button.dataset.filter || 'all';
      renderDrivers();
    });
  });
}

async function initializeApp() {
  ensureUploadGroups();
  bindScreenActions();
  setLoginError('');
  setAppVisible(false);

  const hasSession = await refreshDashboard();
  if (!hasSession) {
    document.getElementById('login-username')?.focus();
    return;
  }

  state.refreshTimer = window.setInterval(() => refreshDashboard(false), 30000);
}

document.addEventListener('DOMContentLoaded', initializeApp);
