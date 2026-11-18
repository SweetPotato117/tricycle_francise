if (location.protocol === 'file:') {
  const target = 'http://localhost/tricycle_franchise/rider/rider_app.html';
  if (location.href !== target) {
    location.replace(target);
  }
}

const riderApi = '../controllers/rider_app.php';
const loginApi = '../controllers/rider_login.php';
const TFMS_LOADER_ENABLED = false;

const TFMSLoader = (() => {
  let frameId = null;
  let finishTimer = null;
  let stepTimer = null;
  let resolveDone = null;

  function getElements() {
    const overlay = document.getElementById('tfms-loader-overlay');
    if (!overlay) return null;
    return {
      overlay,
      ring: overlay.querySelector('.tfms-ring-fg'),
      percent: overlay.querySelector('.tfms-pct'),
      bar: overlay.querySelector('.tfms-bar-fill'),
      step: overlay.querySelector('.tfms-step'),
      icon: overlay.querySelector('.tfms-ring-icon')
    };
  }

  function hide() {
    if (frameId) cancelAnimationFrame(frameId);
    clearTimeout(finishTimer);
    clearTimeout(stepTimer);
    frameId = null;
    const elements = getElements();
    if (elements) {
      elements.overlay.classList.remove('tfms-visible', 'tfms-loader-success');
      elements.overlay.setAttribute('aria-hidden', 'true');
    }
    if (resolveDone) {
      resolveDone();
      resolveDone = null;
    }
  }

  function show(options = {}) {
    hide();
    if (!TFMS_LOADER_ENABLED) return Promise.resolve();
    const elements = getElements();
    if (!elements) return Promise.resolve();

    const donePromise = new Promise((resolve) => { resolveDone = resolve; });
    const duration = options.duration || 8000;
    const steps = options.steps || ['Validating details...', 'Uploading documents...', 'Sending to LGU for review...', 'Almost done...'];
    const circumference = 2 * Math.PI * 52;
    const start = performance.now();
    let lastStep = 0;
    elements.ring.style.strokeDasharray = circumference;
    elements.ring.style.strokeDashoffset = circumference;
    elements.percent.textContent = '0%';
    elements.bar.style.width = '0%';
    elements.step.textContent = steps[0] || '';
    elements.icon.innerHTML = '<svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><circle cx="16" cy="46" r="7" stroke="currentColor" stroke-width="3"/><circle cx="48" cy="46" r="7" stroke="currentColor" stroke-width="3"/><path d="M16 46 L26 24 H40 L48 46" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M26 24 L22 14 H14" stroke="var(--gold-500)" stroke-width="3" stroke-linecap="round"/><path d="M40 24 L44 34 H52" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><rect x="30" y="30" width="10" height="8" rx="1.5" stroke="var(--gold-500)" stroke-width="2.5"/></svg>';
    elements.overlay.querySelector('.tfms-title').textContent = options.title || 'Submitting your request';
    elements.overlay.classList.add('tfms-visible');
    elements.overlay.setAttribute('aria-hidden', 'false');

    function finish() {
      elements.overlay.classList.add('tfms-loader-success');
      elements.step.textContent = 'Submitted successfully';
      elements.icon.innerHTML = '<svg class="tfms-check" viewBox="0 0 44 44" fill="none" aria-hidden="true"><circle cx="22" cy="22" r="20"/><path d="M13 22.5 L19 28.5 L31 15.5"/></svg>';
      finishTimer = window.setTimeout(hide, 700);
    }

    function frame(now) {
      const progress = Math.min((now - start) / duration, 1);
      const percent = Math.round(progress * 100);
      elements.ring.style.strokeDashoffset = circumference * (1 - progress);
      elements.bar.style.width = `${percent}%`;
      elements.percent.textContent = `${percent}%`;
      const stepIndex = Math.min(steps.length - 1, Math.floor(progress * steps.length));
      if (stepIndex !== lastStep) {
        elements.step.style.opacity = '0';
        stepTimer = window.setTimeout(() => {
          elements.step.textContent = steps[stepIndex] || '';
          elements.step.style.opacity = '1';
        }, 120);
        lastStep = stepIndex;
      }
      if (progress < 1) frameId = requestAnimationFrame(frame);
      else finish();
    }
    frameId = requestAnimationFrame(frame);
    return donePromise;
  }

  return { show, hide };
})();

function setSubmitLoading(form, loading, label = 'Submitting...') {
  const button = form?.querySelector('button[type="submit"]');
  if (!button) return;
  if (loading) {
    button.dataset.originalContent = button.innerHTML;
    button.innerHTML = `<span class="submit-spinner" aria-hidden="true"></span><span>${label}</span>`;
    button.disabled = true;
    button.classList.add('is-loading');
    button.setAttribute('aria-busy', 'true');
  } else {
    button.innerHTML = button.dataset.originalContent || button.innerHTML;
    button.disabled = false;
    button.classList.remove('is-loading');
    button.removeAttribute('aria-busy');
  }
}

function setButtonLoading(button, loading, label = 'Processing...') {
  if (!button) return;
  if (loading) {
    button.dataset.originalContent = button.innerHTML;
    button.innerHTML = `<span class="submit-spinner" aria-hidden="true"></span><span>${label}</span>`;
    button.disabled = true;
    button.classList.add('is-loading');
  } else {
    button.innerHTML = button.dataset.originalContent || button.innerHTML;
    button.disabled = false;
    button.classList.remove('is-loading');
  }
}

function setFieldRequired(id, required) {
  const field = document.getElementById(id);
  if (field) field.required = required;
}

function setElementText(id, text) {
  const element = document.getElementById(id);
  if (element) element.textContent = text;
}

function clickElement(selector) {
  document.querySelector(selector)?.click();
}

const state = {
  profile: null,
  franchise: null,
  drivers: [],
  tricycles: [],
  franchiseOwnerTricycles: [],
  notifications: [],
  renewals: [],
  activeNav: 'franchise',
  activeTricycleFilter: 'all',
  activeDriverFilter: 'all',
  tricycleSearch: '',
  driverSearch: '',
  lastRefreshError: null,
  refreshTimer: null
};

let localFranchiseSubmission = sessionStorage.getItem('franchiseRegistrationPending') === 'true';

let editingTricycleId = null;
let editingDriverId = null;
let currentVehiclePhotoTarget = null;
let pendingStatusAction = null;
let statusActionBusy = false;

function showToast(message, type = 'success', duration = 2200, animationClass = '') {
  const toast = document.getElementById('toast');
  const toastMsg = document.getElementById('toast-msg');
  if (!toast || !toastMsg) return;

  toast.classList.remove('error', 'success', 'tricycle-registration-success');
  toast.classList.add(type === 'error' ? 'error' : 'success');
  if (animationClass) toast.classList.add(animationClass);
  toastMsg.textContent = message;
  toast.classList.add('show');

  clearTimeout(showToast.timer);
  showToast.timer = setTimeout(() => {
    toast.classList.remove('show');
  }, duration);
}

function setLoginError(message) {
  const errorBox = document.getElementById('login-error');
  const errorMsg = document.getElementById('login-error-msg');
  if (!errorBox || !errorMsg) return;

  errorMsg.textContent = message || 'Please enter both your username and password.';
  errorBox.style.display = message ? 'flex' : 'none';
}

function setSignupError(message) {
  const errorBox = document.getElementById('signup-error');
  const errorMsg = document.getElementById('signup-error-msg');
  if (!errorBox || !errorMsg) return;

  errorMsg.textContent = message || 'Please complete all fields.';
  errorBox.style.display = message ? 'flex' : 'none';
}

function setPasswordResetStatus(message, success = false) {
  const status = document.getElementById('login-reset-status');
  if (!status) return;
  status.textContent = message;
  status.classList.toggle('show', Boolean(message));
  status.classList.toggle('success', success);
}

function setAuthMode(mode) {
  const loginForm = document.getElementById('login-form');
  const signupForm = document.getElementById('signup-form');
  const resetRequestForm = document.getElementById('password-reset-request-form');
  const switchText = document.getElementById('auth-switch-text');
  const switchBtn = document.getElementById('auth-switch-btn');
  const switchRow = document.getElementById('auth-switch-row');
  const welcomeHeading = document.getElementById('login-welcome-heading');
  const headingTitle = document.getElementById('login-heading-title');
  const headingDescription = document.getElementById('login-heading-description');
  if (!loginForm || !signupForm) return;

  const isSignup = mode === 'signup';
  loginForm.classList.toggle('rider-hidden', isSignup);
  signupForm.classList.toggle('rider-hidden', !isSignup);
  resetRequestForm?.classList.add('rider-hidden');
  switchRow?.classList.remove('rider-hidden');
  welcomeHeading?.classList.remove('rider-hidden');
  if (switchText) switchText.textContent = isSignup ? 'Already have an account?' : "Don't have an account?";
  if (switchBtn) switchBtn.textContent = isSignup ? 'Sign In' : 'Sign Up';
  if (headingTitle) headingTitle.textContent = isSignup ? 'Create your account' : 'Welcome Back!';
  if (headingDescription) headingDescription.textContent = isSignup
    ? 'Get started managing your franchise and units.'
    : 'Please sign in to continue to your account.';
  setLoginError('');
  setSignupError('');
  setPasswordResetStatus('');
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
      if (!accordion) return;
      const isOpen = accordion.classList.toggle('is-open');
      const content = accordion.querySelector('.acc-content');
      if (content) {
        if (isOpen) {
          content.style.setProperty('--acc-content-height', `${content.scrollHeight}px`);
        } else {
          content.style.removeProperty('--acc-content-height');
        }
      }
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

async function readOptionalVehicleImage(inputId) {
  const file = document.getElementById(inputId)?.files?.[0];
  if (!file) return '';
  if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
    throw new Error('Choose a JPEG, PNG, GIF, or WebP vehicle image.');
  }
  if (file.size > 5 * 1024 * 1024) {
    throw new Error('The vehicle image must be 5 MB or smaller.');
  }
  return readFileAsDataUrl(file);
}

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'include',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    },
    ...options
  });

  const contentType = response.headers.get('content-type') || '';
  const payload = contentType.includes('application/json') ? await response.json() : { success: false, message: 'Unexpected response from server.' };

  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Request failed.');
    error.status = response.status;
    throw error;
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
      title: 'Renewal successful',
      date: confirmedDate,
      detail: renewal?.nextRenewalDate
        ? `Your franchise renewal has been successfully approved. Your next renewal date is ${formatDate(renewal.nextRenewalDate)}.`
        : 'Your franchise renewal has been successfully approved and is now active.',
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
  const hasPendingApplication = Boolean(franchise.hasApplication || (!registered && localFranchiseSubmission));

  if (registered && localFranchiseSubmission) {
    localFranchiseSubmission = false;
    sessionStorage.removeItem('franchiseRegistrationPending');
  }

  const tricycleCount = document.getElementById('stat-total');
  const driverCount = document.getElementById('stat-inactive');
  if (tricycleCount) tricycleCount.textContent = String((state.tricycles || []).length);
  if (driverCount) driverCount.textContent = String((state.drivers || []).length);

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
  const registerFranchiseButton = document.getElementById('btn-open-register-franchise');
  const processingBanner = document.getElementById('franchise-processing-banner');
  if (!registered && emptyTitle) {
    emptyTitle.textContent = hasPendingApplication ? 'Franchise Application Submitted' : 'No Franchise Registered';
  }
  if (!registered && emptyMessage) {
    emptyMessage.textContent = hasPendingApplication
      ? `${franchise.name || 'Your franchise'} has been submitted for LGU review.`
      : 'You do not have a registered franchise yet. Register your franchise to start adding tricycles and drivers.';
  }
  if (registerFranchiseButton) registerFranchiseButton.style.display = hasPendingApplication ? 'none' : '';
  if (processingBanner) processingBanner.hidden = !hasPendingApplication;

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
      applicationStatusMessage.textContent = `Your franchise expired on ${formatDate(franchise.expiry)} and is no longer active. Please submit a renewal application.`;
    } else if (franchise.registered && franchise.status !== 'Active') {
      applicationStatusMessage.textContent = `Your franchise status is ${franchise.status}. Please contact the LGU for assistance.`;
    }
  }

  const pendingBanner = document.getElementById('pending-approval-banner');
  const pendingMessage = document.getElementById('pending-approval-message');
  if (pendingBanner && pendingMessage) {
    const show = Boolean(hasPendingApplication && !franchise.registered);
    pendingBanner.style.display = show ? 'block' : 'none';
    pendingMessage.textContent = hasPendingApplication ? 'Your application is currently being reviewed by the LGU.' : 'Waiting for approval.';
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
    openTricycleDetails(tricycle);
  });
  return card;
}

function openTricycleDetails(tricycle, isOwnerTricycle = false) {
  const detail = document.getElementById('detail-tricycle');
  if (!detail) return;
  currentVehiclePhotoTarget = { id: tricycle.id, type: isOwnerTricycle ? 'owner_tricycle' : 'tricycle' };
  document.getElementById('td-unit-no').textContent = tricycle.unit || 'Not specified';
  document.getElementById('td-plate-sub').textContent = tricycle.plate || 'No plate';
  renderVehiclePhoto(tricycle.image || '');
  document.getElementById('td-vehicle-fields').innerHTML = `
    <div class="field-row"><span>Body Number / Sticker</span><strong>${tricycle.unit || '—'}</strong></div>
    <div class="field-row"><span>Brand</span><strong>${tricycle.brand || 'Not specified'}</strong></div>
    <div class="field-row"><span>Plate No.</span><strong>${tricycle.plate || '—'}</strong></div>
    <div class="field-row"><span>Engine No.</span><strong>${tricycle.engine || '—'}</strong></div>
    <div class="field-row"><span>Chassis No.</span><strong>${tricycle.chassis || '—'}</strong></div>
    <div class="field-row"><span>Color</span><strong>${tricycle.color || '—'}</strong></div>
    <div class="field-row"><span>Official Receipt No.</span><strong>${tricycle.orNumber || '—'}</strong></div>
  `;
  const documentsContainer = document.getElementById('td-documents');
  documentsContainer.replaceChildren();
  if (tricycle.docs && tricycle.docs.length) {
    tricycle.docs.forEach((doc) => {
      const isPdf = /\.pdf$/i.test(doc.url || '');
      const item = document.createElement(isPdf ? 'a' : 'button');
      item.className = 'driver-doc-item';
      item.setAttribute('aria-label', `${isPdf ? 'Open' : 'View'} ${doc.name || 'document'}`);
      if (isPdf) {
        item.href = doc.url;
        item.target = '_blank';
        item.rel = 'noopener';
      } else {
        item.type = 'button';
      }

      const preview = document.createElement('span');
      preview.className = 'driver-doc-preview';
      if (isPdf) {
        preview.innerHTML = '<svg class="driver-doc-file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>';
      } else {
        const image = document.createElement('img');
        image.src = doc.url;
        image.alt = '';
        preview.appendChild(image);
        item.addEventListener('click', () => openDocumentViewer(doc.url, doc.name || 'Document'));
      }
      item.appendChild(preview);

      const name = document.createElement('span');
      name.className = 'driver-doc-name';
      name.textContent = doc.name || 'Document';
      item.appendChild(name);
      documentsContainer.appendChild(item);
    });
  } else {
    documentsContainer.innerHTML = '<div class="empty-doc">No documents uploaded yet.</div>';
  }

  const status = normalizeStatus(tricycle.status).toLowerCase();
  const editButton = document.getElementById('td-more');
  if (editButton) {
    editButton.style.display = isOwnerTricycle ? 'none' : 'inline-flex';
    editButton.dataset.tricycleId = tricycle.id;
  }
  const removeButton = document.getElementById('td-remove-btn');
  if (removeButton) {
    removeButton.style.display = isOwnerTricycle || status !== 'pending' ? 'inline-flex' : 'none';
    removeButton.textContent = isOwnerTricycle ? 'Delete My Unit' : 'Remove';
    removeButton.dataset.tricycleId = tricycle.id;
    removeButton.dataset.recordType = isOwnerTricycle ? 'owner_tricycle' : 'tricycle';
  }
  const statusButton = document.getElementById('td-status-btn');
  if (statusButton) {
    statusButton.style.display = isOwnerTricycle ? (status === 'pending' ? 'none' : 'inline-flex') : 'inline-flex';
    statusButton.disabled = false;
    statusButton.textContent = isOwnerTricycle
      ? (status === 'inactive' ? 'Re-enable Unit' : 'Disable Unit')
      : status === 'pending' ? 'Cancel Submission' : status === 'inactive' ? 'Set Active' : 'Set Inactive';
    statusButton.className = `btn ${status === 'inactive' ? 'primary' : 'danger'}`;
    statusButton.dataset.statusType = isOwnerTricycle ? 'owner_tricycle' : 'tricycle';
    statusButton.dataset.statusId = tricycle.id;
    statusButton.dataset.currentStatus = status;
  }
  detail.classList.add('active');
}

function renderVehiclePhoto(imageUrl) {
  const button = document.getElementById('td-photo-view');
  const resetButton = document.getElementById('td-photo-reset');
  if (!button || !resetButton) return;
  button.replaceChildren();
  if (imageUrl) {
    const image = document.createElement('img');
    image.src = imageUrl;
    image.alt = 'Custom tricycle image';
    button.appendChild(image);
    button.setAttribute('aria-label', 'View tricycle image');
    resetButton.classList.remove('rider-hidden');
    return;
  }
  button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M5 17H3v-5l2-5h9l3 5h2a2 2 0 0 1 2 2v3h-2"/><path d="M14 7v5h5"/></svg>';
  button.setAttribute('aria-label', 'Upload a tricycle image');
  resetButton.classList.add('rider-hidden');
}

async function uploadVehicleImage(file) {
  if (!currentVehiclePhotoTarget) return;
  if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
    showToast('Choose a JPEG, PNG, GIF, or WebP image.', 'error');
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    showToast('The tricycle image must be 5 MB or smaller.', 'error');
    return;
  }
  try {
    const result = await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        action: 'update-vehicle-image',
        ...currentVehiclePhotoTarget,
        vehicleImageData: await readFileAsDataUrl(file)
      })
    });
    const collection = currentVehiclePhotoTarget.type === 'owner_tricycle' ? state.franchiseOwnerTricycles : state.tricycles;
    const vehicle = collection.find((item) => String(item.id) === String(currentVehiclePhotoTarget.id));
    if (vehicle) vehicle.image = result.image || '';
    renderVehiclePhoto(result.image || '');
    showToast('Tricycle image updated.');
  } catch (error) {
    showToast(error.message || 'Unable to upload tricycle image.', 'error');
  }
}

async function resetVehicleImage() {
  if (!currentVehiclePhotoTarget) return;
  try {
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({ action: 'update-vehicle-image', ...currentVehiclePhotoTarget, removeImage: true })
    });
    const collection = currentVehiclePhotoTarget.type === 'owner_tricycle' ? state.franchiseOwnerTricycles : state.tricycles;
    const vehicle = collection.find((item) => String(item.id) === String(currentVehiclePhotoTarget.id));
    if (vehicle) vehicle.image = '';
    renderVehiclePhoto('');
    showToast('Default tricycle icon restored.');
  } catch (error) {
    showToast(error.message || 'Unable to restore the default tricycle icon.', 'error');
  }
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
        <span>${driver.licenseNumber || 'No license number'}</span>
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
    document.getElementById('dd-sub').textContent = `${driver.licenseNumber || 'No license number'} • ${driver.contact || 'No contact'}`;
    document.getElementById('dd-personal-fields').innerHTML = `
      <div class="field-row"><span>Full Name</span><strong>${driver.name || '—'}</strong></div>
      <div class="field-row"><span>Date of Birth</span><strong>${driver.dob || '—'}</strong></div>
      <div class="field-row"><span>Gender</span><strong>${driver.gender || '—'}</strong></div>
      <div class="field-row"><span>Driver's License Number</span><strong>${driver.licenseNumber || '—'}</strong></div>
      <div class="field-row"><span>OR/CR Number</span><strong>${driver.orCrNumber || '—'}</strong></div>
      <div class="field-row"><span>Address</span><strong>${driver.address || '—'}</strong></div>
      <div class="field-row"><span>Contact</span><strong>${driver.contact || '—'}</strong></div>
    `;
    document.getElementById('dd-driver-fields').innerHTML = `
      <div class="field-row"><span>License</span><strong>${driver.license || '—'}</strong></div>
      <div class="field-row"><span>Status</span><strong>${normalizeStatus(driver.status)}</strong></div>
      <div class="field-row"><span>Assigned Tricycle</span><strong>${driver.tricycle || 'Unassigned'}</strong></div>
    `;
    const documentsContainer = document.getElementById('dd-documents');
    documentsContainer.replaceChildren();
    if (driver.docs && driver.docs.length) {
      driver.docs.forEach((doc) => {
        const isPdf = /\.pdf$/i.test(doc.url || '');
        const item = document.createElement(isPdf ? 'a' : 'button');
        item.className = 'driver-doc-item';
        item.setAttribute('aria-label', `${isPdf ? 'Open' : 'View'} ${doc.name || 'document'}`);
        if (isPdf) {
          item.href = doc.url;
          item.target = '_blank';
          item.rel = 'noopener';
        } else {
          item.type = 'button';
        }

        const preview = document.createElement('span');
        preview.className = 'driver-doc-preview';
        if (isPdf) {
          preview.innerHTML = '<svg class="driver-doc-file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>';
        } else {
          const image = document.createElement('img');
          image.src = doc.url;
          image.alt = '';
          preview.appendChild(image);
          item.addEventListener('click', () => openDocumentViewer(doc.url, doc.name || 'Document'));
        }
        item.appendChild(preview);

        const name = document.createElement('span');
        name.className = 'driver-doc-name';
        name.textContent = doc.name || 'Document';
        item.appendChild(name);
        documentsContainer.appendChild(item);
      });
    } else {
      documentsContainer.innerHTML = '<div class="empty-doc">No documents uploaded yet.</div>';
    }
    const editButton = document.getElementById('dd-edit-btn');
    editButton.style.display = 'inline-flex';
    editButton.dataset.driverId = driver.id;
    const removeButton = document.getElementById('dd-remove-btn');
    if (removeButton) {
      const status = normalizeStatus(driver.status).toLowerCase();
      removeButton.style.display = status === 'pending' ? 'none' : 'inline-flex';
      removeButton.dataset.driverId = driver.id;
    }
    const statusButton = document.getElementById('dd-status-btn');
    if (statusButton) {
      const status = normalizeStatus(driver.status).toLowerCase();
      statusButton.style.display = 'inline-flex';
      statusButton.disabled = false;
      statusButton.textContent = status === 'pending' ? 'Cancel Submission' : status === 'inactive' ? 'Set Active' : 'Set Inactive';
      statusButton.className = `btn ${status === 'inactive' ? 'primary' : 'danger'}`;
      statusButton.dataset.statusType = 'driver';
      statusButton.dataset.statusId = driver.id;
      statusButton.dataset.currentStatus = status;
    }
    const assignButton = document.getElementById('dd-assign-btn');
    const tricycleSelect = document.getElementById('dd-tricycle-select');
    const canAssign = !driver.tricycleId && normalizeStatus(driver.status).toLowerCase() !== 'inactive';
    if (assignButton && tricycleSelect) {
      const availableTricycles = (state.tricycles || []).filter((tricycle) => {
        return normalizeStatus(tricycle.status).toLowerCase() === 'active' && !state.drivers.some((item) => item.tricycleId === tricycle.id);
      });
      tricycleSelect.innerHTML = '<option value="">Select tricycle</option>' + availableTricycles.map((tricycle) => `<option value="${tricycle.id}">${tricycle.brand || 'Tricycle'} - ${tricycle.unit || tricycle.plate || `Unit ${tricycle.id}`}</option>`).join('');
      tricycleSelect.value = '';
      assignButton.style.display = canAssign && availableTricycles.length ? 'inline-flex' : 'none';
      assignButton.dataset.driverId = driver.id;
      assignButton.onclick = () => {
        closeDriverActionsMenu();
        document.getElementById('assign-tricycle-overlay')?.classList.add('active');
        tricycleSelect.focus();
      };
    }
    closeDriverActionsMenu();
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

function renderFranchiseOwnerTricycles() {
  const list = document.getElementById('owner-tricycle-list');
  const emptyState = document.getElementById('owner-tricycle-empty');
  const unavailableState = document.getElementById('owner-tricycle-unavailable');
  const addButton = document.getElementById('btn-add-owner-tricycle');
  if (!list || !emptyState || !unavailableState || !addButton) return;

  const hasFranchise = Boolean(state.franchise?.registered);
  addButton.classList.toggle('rider-hidden', !hasFranchise);
  list.classList.toggle('rider-hidden', !hasFranchise);
  emptyState.classList.toggle('rider-hidden', !hasFranchise || state.franchiseOwnerTricycles.length > 0);
  unavailableState.classList.toggle('rider-hidden', hasFranchise);
  list.replaceChildren();

  state.franchiseOwnerTricycles.forEach((tricycle) => {
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'card';
    card.setAttribute('aria-label', `View ${tricycle.brand || 'tricycle'} details`);
    const icon = document.createElement('div');
    icon.className = 'card-icon gold';
    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="17" r="2"></circle><circle cx="17" cy="17" r="2"></circle><path d="M5 17H3v-5l2-5h9l3 5h2a2 2 0 0 1 2 2v3h-2"></path><path d="M14 7v5h5"></path></svg>';
    const body = document.createElement('div');
    body.className = 'card-body';
    const titleRow = document.createElement('div');
    titleRow.className = 'card-title-row';
    const title = document.createElement('span');
    title.className = 'card-title';
    title.textContent = tricycle.brand || 'Tricycle';
    const status = document.createElement('span');
    status.className = `badge ${statBadgeClass(tricycle.status)}`;
    status.textContent = normalizeStatus(tricycle.status);
    titleRow.append(title, status);
    const details = document.createElement('div');
    details.className = 'card-sub';
    [tricycle.unit || 'No sticker number', tricycle.plate || 'No plate'].forEach((value, index) => {
      if (index > 0) {
        const separator = document.createElement('span');
        separator.className = 'dot';
        details.appendChild(separator);
      }
      const item = document.createElement('span');
      item.textContent = value;
      details.appendChild(item);
    });
    body.append(titleRow, details);
    const chevron = document.createElement('div');
    chevron.className = 'card-chevron';
    chevron.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"></path></svg>';
    card.append(icon, body, chevron);
    card.addEventListener('click', () => openTricycleDetails(tricycle, true));
    list.appendChild(card);
  });
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
    document.getElementById('assign-tricycle-overlay')?.classList.remove('active');
    showToast('Tricycle assigned successfully.');
    document.querySelector('[data-close="detail-driver"]')?.click();
    await refreshDashboard();
  } catch (error) {
    showToast(error.message || 'Unable to assign tricycle.', 'error');
  }
}

function closeDriverActionsMenu() {
  const menu = document.getElementById('dd-actions-menu');
  const toggle = document.getElementById('dd-actions-toggle');
  menu?.classList.remove('is-open');
  toggle?.setAttribute('aria-expanded', 'false');
}

async function cancelSubmission(type, id, confirmed = false) {
  const label = type === 'driver' ? 'driver' : 'tricycle';
  if (!confirmed && !window.confirm(`Cancel this pending ${label} submission?`)) return;
  try {
    await requestJson(riderApi, { method: 'POST', body: JSON.stringify({ action: 'cancel-submission', type, id }) });
    showToast(`${label[0].toUpperCase() + label.slice(1)} submission cancelled.`);
    document.querySelector(`[data-close="detail-${type}"]`)?.click();
    await refreshDashboard();
  } catch (error) {
    showToast(error.message || `Unable to cancel ${label} submission.`, 'error');
  }
}

async function updateOwnedStatus(type, id, status) {
  const label = type === 'driver' ? 'driver' : 'tricycle';
  try {
    await requestJson(riderApi, { method: 'POST', body: JSON.stringify({ action: 'update-status', type, id, status }) });
    showToast(type === 'owner_tricycle'
      ? status === 'Active' ? 'Your tricycle has been re-enabled. Super Admins have been notified.' : 'Your tricycle has been disabled. Super Admins have been notified.'
      : `${label[0].toUpperCase() + label.slice(1)} set to ${status.toLowerCase()}.`);
    document.querySelector(`[data-close="detail-${type === 'owner_tricycle' ? 'tricycle' : type}"]`)?.click();
    await refreshDashboard(type !== 'owner_tricycle');
  } catch (error) {
    showToast(error.message || `Unable to update ${label} status.`, 'error');
  }
}

async function removeOwnedRecord(type, id) {
  const label = type === 'driver' ? 'driver' : 'tricycle';
  try {
    await requestJson(riderApi, { method: 'POST', body: JSON.stringify({ action: 'remove-record', type, id }) });
    showToast(type === 'owner_tricycle'
      ? 'Your tricycle was archived. Super Admins have been notified.'
      : `${label[0].toUpperCase() + label.slice(1)} removed successfully.`);
    document.querySelector(`[data-close="detail-${type === 'owner_tricycle' ? 'tricycle' : type}"]`)?.click();
    await refreshDashboard(type !== 'owner_tricycle');
  } catch (error) {
    showToast(error.message || `Unable to remove ${label}.`, 'error');
  }
}

function closeStatusConfirmation() {
  document.getElementById('confirm-overlay')?.classList.remove('active');
  pendingStatusAction = null;
}

function openStatusConfirmation(type, id, currentStatus) {
  if (statusActionBusy) return;
  const overlay = document.getElementById('confirm-overlay');
  const title = document.getElementById('confirm-title');
  const description = document.getElementById('confirm-desc');
  const confirmButton = document.getElementById('confirm-ok');
  if (!overlay || !title || !description || !confirmButton) return;

  const label = type === 'driver' ? 'driver' : type === 'owner_tricycle' ? 'unit' : 'tricycle';
  const nextStatus = currentStatus === 'inactive' ? 'Active' : 'Inactive';
  pendingStatusAction = { kind: 'status', type, id, currentStatus };
  title.textContent = type === 'owner_tricycle'
    ? currentStatus === 'inactive' ? 'Re-enable this unit?' : 'Disable this unit?'
    : currentStatus === 'pending' ? `Cancel ${label} submission?` : `Set ${label} ${nextStatus.toLowerCase()}?`;
  description.textContent = currentStatus === 'pending'
    ? `This will cancel the pending ${label} submission.`
    : type === 'owner_tricycle'
      ? `This will ${currentStatus === 'inactive' ? 're-enable' : 'disable'} your unit and notify all active Super Admins by email and in-app notification.`
      : `This will set this ${label} to ${nextStatus.toLowerCase()}.`;
  confirmButton.textContent = type === 'owner_tricycle'
    ? currentStatus === 'inactive' ? 'Re-enable Unit' : 'Disable Unit'
    : currentStatus === 'pending' ? 'Cancel Submission' : `Set ${nextStatus}`;
  overlay.classList.add('active');
}

function openRemoveConfirmation(type, id) {
  if (statusActionBusy) return;
  const overlay = document.getElementById('confirm-overlay');
  const title = document.getElementById('confirm-title');
  const description = document.getElementById('confirm-desc');
  const confirmButton = document.getElementById('confirm-ok');
  if (!overlay || !title || !description || !confirmButton) return;

  const label = type === 'driver' ? 'driver' : type === 'owner_tricycle' ? 'unit' : 'tricycle';
  pendingStatusAction = { kind: 'remove', type, id };
  title.textContent = type === 'owner_tricycle' ? 'Delete this unit?' : `Remove ${label}?`;
  description.textContent = type === 'owner_tricycle'
    ? 'This will archive the unit so it no longer appears in My Units. The record will be retained, and all active Super Admins will be notified by email and in-app notification.'
    : `This will permanently remove this ${label} from your franchise and notify the LGU. This cannot be undone.`;
  confirmButton.textContent = type === 'owner_tricycle' ? 'Archive Unit' : 'Remove';
  overlay.classList.add('active');
}

function handleStatusButtonClick(event) {
  const button = event.currentTarget;
  const type = button.dataset.statusType;
  const id = Number(button.dataset.statusId);
  const currentStatus = button.dataset.currentStatus;
  if (!type || !id || !currentStatus) return;
  openStatusConfirmation(type, id, currentStatus);
}

async function handleStatusConfirmation() {
  const action = pendingStatusAction;
  const confirmButton = document.getElementById('confirm-ok');
  if (!action || statusActionBusy) return;

  statusActionBusy = true;
  if (action.kind === 'remove') {
    setButtonLoading(confirmButton, true, action.type === 'owner_tricycle' ? 'Archiving...' : 'Removing...');
    try {
      await removeOwnedRecord(action.type, action.id);
    } finally {
      closeStatusConfirmation();
      statusActionBusy = false;
      setButtonLoading(confirmButton, false);
    }
    return;
  }
  setButtonLoading(confirmButton, true, action.currentStatus === 'pending' ? 'Cancelling...' : 'Updating...');
  try {
    if (action.currentStatus === 'pending') {
      await cancelSubmission(action.type, action.id, true);
      return;
    }
    await updateOwnedStatus(action.type, action.id, action.currentStatus === 'inactive' ? 'Active' : 'Inactive');
  } finally {
    closeStatusConfirmation();
    statusActionBusy = false;
    setButtonLoading(confirmButton, false);
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
  const processingBanner = document.getElementById('renewal-processing-banner');
  const renewals = Array.isArray(state.renewals) ? state.renewals : [];
  const renewalForm = document.getElementById('renewal-form');
  const renewalStateBanner = document.getElementById('renewal-state-banner');
  const renewalStateLabel = document.getElementById('renewal-state-label');
  const renewalStateMessage = document.getElementById('renewal-state-message');

  const latestRenewal = renewals[0];
  const latestStatus = normalizeStatus(latestRenewal?.status || '').toLowerCase();
  const isProcessing = Boolean(latestRenewal && !latestStatus.includes('confirm') && !latestStatus.includes('approv') && !latestStatus.includes('reject') && !latestStatus.includes('fail') && !latestStatus.includes('declin'));
  if (processingBanner) processingBanner.style.display = isProcessing ? 'block' : 'none';

  const currentYear = new Date().getFullYear();
  const expiryYear = state.franchise?.expiry ? new Date(`${state.franchise.expiry}T00:00:00`).getFullYear() : 0;
  const renewalYear = expiryYear > 0 ? expiryYear : currentYear;
  const currentYearRenewal = renewals.find((renewal) => Number(renewal.year) === renewalYear);
  const currentYearStatus = normalizeStatus(currentYearRenewal?.status || '').toLowerCase();
  const hasCurrentYearRenewal = Boolean(currentYearRenewal);
  const hasRegisteredFranchise = Boolean(state.franchise?.registered);
  const renewalOpeningDate = state.franchise?.expiry ? new Date(`${state.franchise.expiry}T00:00:00`) : new Date();
  renewalOpeningDate.setDate(renewalOpeningDate.getDate() - 20);
  const renewalWindowOpen = new Date() >= renewalOpeningDate;
  const currentYearProcessing = hasCurrentYearRenewal && !currentYearStatus.includes('confirm') && !currentYearStatus.includes('approv') && !currentYearStatus.includes('reject') && !currentYearStatus.includes('fail') && !currentYearStatus.includes('declin');
  const currentYearSuccessful = hasCurrentYearRenewal && (currentYearStatus.includes('confirm') || currentYearStatus.includes('approv'));
  const expiryText = state.franchise?.expiry ? formatDate(state.franchise.expiry) : 'the franchise expiry date';
  const nextRenewalText = currentYearRenewal?.nextRenewalDate ? formatDate(currentYearRenewal.nextRenewalDate) : expiryText;
  const expiryDate = state.franchise?.expiry ? new Date(`${state.franchise.expiry}T00:00:00`) : null;
  const daysUntilExpiry = expiryDate && !Number.isNaN(expiryDate.getTime())
    ? Math.ceil((expiryDate - new Date(new Date().setHours(0, 0, 0, 0))) / 86400000)
    : null;
  const currentStatus = normalizeStatus(state.franchise?.status || 'Pending');
  const franchiseExpired = hasRegisteredFranchise && (currentStatus === 'Expired' || (daysUntilExpiry !== null && daysUntilExpiry < 0));
  const franchiseExpiringSoon = hasRegisteredFranchise && currentStatus === 'Active' && daysUntilExpiry !== null && daysUntilExpiry >= 0 && daysUntilExpiry <= 20;
  if (renewalStateBanner && renewalStateLabel && renewalStateMessage) {
    renewalStateBanner.className = `application-status renewal-state-banner ${currentYearSuccessful ? 'approved' : franchiseExpired ? 'not-approved' : franchiseExpiringSoon ? 'warning' : currentYearProcessing ? 'processing-status' : hasCurrentYearRenewal ? 'not-approved' : ''}`;
    renewalStateLabel.textContent = hasCurrentYearRenewal ? 'Renewal Status' : 'Franchise Status';
    renewalStateMessage.textContent = !hasRegisteredFranchise
      ? 'Renewal applications become available after your franchise registration has been approved by the LGU.'
      : currentYearSuccessful
      ? `Renewal successful. Your next renewal date is ${nextRenewalText}.`
      : franchiseExpired
      ? `Your franchise expired on ${expiryText} and is no longer active. Please submit a renewal application.`
      : franchiseExpiringSoon
      ? `Your franchise is still active, but it will expire on ${expiryText}. Please submit your renewal before the expiration date.`
      : currentYearProcessing
      ? `Your ${renewalYear} franchise renewal application has been received by the LGU and is currently under review. Please wait for the result before submitting another renewal.`
      : hasCurrentYearRenewal
        ? `A ${renewalYear} renewal application has already been processed. Only one renewal may be submitted each year.`
        : !renewalWindowOpen
          ? `Franchise currently active. Renewal applications open on ${formatDate(renewalOpeningDate)}.`
          : `Franchise currently active. Your renewal is due on ${expiryText}.`;
  }
  if (renewalForm) {
    const canSubmit = !hasCurrentYearRenewal && hasRegisteredFranchise && renewalWindowOpen;
    renewalForm.style.display = canSubmit ? '' : 'none';
  }

  if (!container || !emptyState || !historyWrap) return;

  if (!renewals.length) {
    historyWrap.hidden = true;
    emptyState.hidden = false;
    historyWrap.style.display = 'none';
    emptyState.style.display = 'block';
    emptyState.innerHTML = '<p>No renewal has been submitted yet.</p>';
    return;
  }

  emptyState.style.display = 'none';
  historyWrap.hidden = false;
  emptyState.hidden = true;
  historyWrap.style.display = 'block';
  container.innerHTML = renewals.map((renewal) => {
    const status = normalizeStatus(renewal.status || 'Submitted');
    const successful = /confirm|approv/i.test(status);
    const expiry = renewal.nextRenewalDate;
    const expired = expiry && new Date(expiry + 'T23:59:59') < new Date();
    const label = successful ? (expired ? 'Expired' : 'Active') : status;
    const tone = successful ? (expired ? 'expired' : 'active') : statBadgeClass(status);
    const timelineEntries = getRenewalTimelineEntries(renewal);
    return `
      <details class="renewal-record">
        <summary class="renewal-record-summary">
          <span class="renewal-record-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/></svg></span>
          <span class="renewal-record-copy"><span class="renewal-record-title">Franchise Renewal · ${renewal.year || '—'}</span><span class="renewal-record-date">${successful ? 'Valid until' : 'Proposed valid until'}: ${formatDate(expiry)}</span></span>
          <span class="badge ${tone}">${label}</span>
          <svg class="renewal-record-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
        </summary>
        <div class="renewal-record-detail">
          <h3>Renewal timeline</h3>
          <div class="renewal-timeline">
            ${timelineEntries.map((step) => `
              <div class="timeline-item ${step.tone}">
                <div class="timeline-node" aria-hidden="true"></div>
                <div class="timeline-content">
                  <div class="timeline-top"><span class="timeline-title">${step.title}</span><span class="timeline-badge ${step.tone}">${step.label}</span></div>
                  <div class="timeline-date">${formatDate(step.date)}</div>
                  <div class="timeline-detail">${step.detail}</div>
                </div>
              </div>`).join('')}
          </div>
        </div>
      </details>`;
  }).join('');
}

function hydrateProfile() {
  const profile = state.profile || {};
  const name = profile.name || 'Rider';
  const role = profile.role || 'Rider';
  const email = profile.email || '';
  const avatar = document.querySelector('.profile-avatar');

  if (avatar) {
    avatar.replaceChildren();
    if (profile.photoUrl) {
      const image = document.createElement('img');
      image.src = profile.photoUrl;
      image.alt = 'Your profile picture';
      image.addEventListener('error', () => { avatar.textContent = name.charAt(0).toUpperCase(); }, { once: true });
      avatar.appendChild(image);
    } else avatar.textContent = name.charAt(0).toUpperCase();
  }
  const photoButton = document.getElementById('profile-photo-button');
  if (photoButton) photoButton.hidden = false;
  const uploadButton = document.getElementById('profile-photo-upload');
  if (uploadButton) {
    uploadButton.hidden = true;
    uploadButton.textContent = profile.photoUrl ? 'Change profile photo' : 'Upload profile photo';
  }
  const photoHint = document.getElementById('profile-photo-hint');
  if (photoHint) photoHint.hidden = false;
  document.getElementById('profile-display-name').textContent = name;
  document.getElementById('profile-display-role').textContent = role;
  document.getElementById('as-name').value = profile.baseName || name;
  document.getElementById('as-middle-name').value = profile.middleName || '';
  document.getElementById('as-role').value = role;
  document.getElementById('as-franchise').value = profile.franchise || (role === 'Rider' ? 'Not registered' : 'Not assigned');
  document.getElementById('as-email').value = email;
  const contactInput = document.getElementById('as-contact');
  contactInput.value = profile.contact || '';
  contactInput.readOnly = role !== 'Rider';
  const profileDetails = {
    name: profile.baseName || name,
    middle: profile.middleName || '—',
    role,
    franchise: document.getElementById('as-franchise').value,
    email: email || 'Not provided',
    contact: profile.contact || 'Not provided',
  };
  Object.entries(profileDetails).forEach(([key, value]) => {
    const element = document.getElementById(`profile-info-${key}`);
    if (element) element.textContent = value;
  });
  renderAccountDocuments();
}

function renderAccountDocuments() {
  const preview = document.getElementById('as-document-preview');
  if (!preview) return;

  const documents = Array.isArray(state.franchise?.documents) ? state.franchise.documents : [];
  preview.replaceChildren();
  if (!documents.length) {
    const empty = document.createElement('div');
    empty.className = 'empty-state';
    empty.textContent = 'Application receipt, Cedula, and driver’s license documents will appear here when available.';
    preview.appendChild(empty);
    return;
  }

  documents.forEach((documentItem) => {
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'account-document-card';
    card.setAttribute('aria-label', `View ${documentItem.name}`);

    const image = document.createElement('img');
    image.className = 'account-document-image';
    image.src = documentItem.url;
    image.alt = documentItem.name;

    const caption = document.createElement('span');
    caption.className = 'account-document-caption';
    const title = document.createElement('span');
    title.className = 'account-document-title';
    title.textContent = documentItem.name;
    const action = document.createElement('span');
    action.className = 'account-document-action';
    action.textContent = 'Tap to view';
    caption.append(title, action);
    card.append(image, caption);
    card.addEventListener('click', () => openDocumentViewer(documentItem.url, documentItem.name));
    preview.appendChild(card);
  });
}

async function handleApplyRenewal(evt) {
  evt.preventDefault();
  const form = evt.currentTarget;
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
  setSubmitLoading(form, true);
  const loaderDone = TFMSLoader.show();

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

    await loaderDone;
    form.reset();
    showToast('Renewal application submitted for LGU review.');
    await refreshDashboard();
    setActiveNav('renew');
  } catch (error) {
    TFMSLoader.hide();
    showToast(error.message || 'Unable to submit renewal.', 'error');
  } finally {
    setSubmitLoading(form, false);
  }
}

async function refreshDashboard(resetNavigation = true, showApp = true) {
  try {
    const result = await requestJson(riderApi, { method: 'GET' });
    state.lastRefreshError = null;
    state.profile = result.profile || {};
    state.franchise = result.franchise || {};
    if (!state.franchise.registered && localFranchiseSubmission) {
      state.franchise.hasApplication = true;
    }
    state.drivers = result.drivers || [];
    state.tricycles = result.tricycles || [];
    state.franchiseOwnerTricycles = result.franchiseOwnerTricycles || [];
    state.notifications = result.notifications || [];
    state.renewals = result.renewals || [];
    populateActiveDriverOptions();
    populateActiveTricycleOptions();

    renderFranchiseSummary();
    renderTricycles();
    renderFranchiseOwnerTricycles();
    renderDrivers();
    renderNotifications();
    renderRenewals();
    hydrateProfile();
    if (showApp) setAppVisible(true);
    if (resetNavigation) setActiveNav('franchise');
    return true;
  } catch (error) {
    state.lastRefreshError = error;
    if (resetNavigation) {
      setAppVisible(false);
      setLoginError('');
    }
    return false;
  }
}

async function handleLogin(evt) {
  evt.preventDefault();
  const form = evt.currentTarget;
  const username = document.getElementById('login-username').value.trim();
  const password = document.getElementById('login-password').value;

  if (!username || !password) {
    setLoginError('Please enter both your email/username and password.');
    return;
  }
  setSubmitLoading(form, true, 'Signing in...');

  try {
    const response = await requestJson(loginApi, {
      method: 'POST',
      body: JSON.stringify({ action: 'login', username, password })
    });

    setLoginError('');
    const dashboardLoaded = await refreshDashboard(true, false);
    if (!dashboardLoaded) {
      const detail = state.lastRefreshError?.message || 'The dashboard request was rejected.';
      setLoginError(`Unable to load your account: ${detail}`);
      return;
    }
    const progress = document.getElementById('login-progress');
    const progressMessage = progress?.querySelector('.login-progress-message');
    if (progressMessage) progressMessage.textContent = `Login successful. Welcome, ${response.name || 'Rider'}!`;
    progress?.classList.remove('rider-hidden');
    progress?.setAttribute('aria-hidden', 'false');
    await new Promise(resolve => window.setTimeout(resolve, 3000));
    progress?.classList.add('rider-hidden');
    progress?.setAttribute('aria-hidden', 'true');
    setAppVisible(true);
    showToast(`${response.name || 'Welcome'} logged in successfully.`);
  } catch (error) {
    setAppVisible(false);
    setLoginError(error.message || 'Unable to log in.');
  } finally {
    setSubmitLoading(form, false);
  }
}

async function handlePasswordResetRequest(evt) {
  evt.preventDefault();
  const form = evt.currentTarget;
  const emailInput = document.getElementById('reset-email');
  const email = emailInput.value.trim();
  if (!emailInput.checkValidity()) {
    setPasswordResetStatus('Enter a valid email address.');
    emailInput.focus();
    return;
  }

  setButtonLoading(form.querySelector('button[type="submit"]'), true, 'Sending...');
  try {
    await requestJson(loginApi, {
      method: 'POST',
      body: JSON.stringify({ action: 'request-password-reset', email })
    });
    setPasswordResetStatus('If an active account uses that email, a password reset link will be sent. Check your inbox.', true);
  } catch (error) {
    setPasswordResetStatus(error.message || 'Unable to request a password reset. Please try again.');
  } finally {
    setButtonLoading(form.querySelector('button[type="submit"]'), false);
  }
}

async function handleSignup(evt) {
  evt.preventDefault();
  const form = evt.currentTarget;
  const name = document.getElementById('signup-name').value.trim();
  const email = document.getElementById('signup-email').value.trim();
  const password = document.getElementById('signup-password').value;

  if (!name || !email || !password) {
    setSignupError('Please complete all fields.');
    return;
  }
  if (password.length < 8) {
    setSignupError('Password must be at least 8 characters.');
    return;
  }
  setSubmitLoading(form, true);

  try {
    const response = await requestJson(loginApi, {
      method: 'POST',
      body: JSON.stringify({ action: 'register', name, email, password })
    });

    setSignupError('');
    const dashboardLoaded = await refreshDashboard();
    if (!dashboardLoaded) {
      const detail = state.lastRefreshError?.message || 'The dashboard request was rejected.';
      setSignupError(`Unable to load your account: ${detail}`);
      return;
    }
    showToast(`${response.name || 'Welcome'} account created successfully.`);
  } catch (error) {
    setAppVisible(false);
    setSignupError(error.message || 'Unable to create your account.');
  } finally {
    setSubmitLoading(form, false);
  }
}

async function handleCreateFranchise(evt) {
  evt.preventDefault();
  const form = evt.currentTarget;
  const name = document.getElementById('rf-name').value.trim();
  const owner = document.getElementById('rf-owner').value.trim();
  const address = document.getElementById('rf-address').value.trim();
  const file = document.getElementById('rf-receipt').files[0];
  const cedulaFile = document.getElementById('rf-cedula').files[0];
  const driversLicense = document.getElementById('rf-drivers-license').value.trim();
  const driversLicensePhoto = document.getElementById('rf-drivers-license-photo').files[0];
  const driversLicenseBackPhoto = document.getElementById('rf-drivers-license-back-photo').files[0];

  if (!name || !owner || !file || !cedulaFile || !driversLicense || !driversLicensePhoto || !driversLicenseBackPhoto) {
    showToast('Please complete the franchise form fields.', 'error');
    return;
  }
  for (const [label, image] of [['Cedula', cedulaFile], ["Driver's license front", driversLicensePhoto], ["Driver's license back", driversLicenseBackPhoto]]) {
    if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(image.type) || image.size > 5 * 1024 * 1024) {
      showToast(`${label} must be a JPEG, PNG, GIF, or WebP image no larger than 5 MB.`, 'error');
      return;
    }
  }
  setSubmitLoading(form, true);
  const loaderDone = TFMSLoader.show();

  try {
    const receiptDataUrl = await readFileAsDataUrl(file);
    const cedulaDataUrl = await readFileAsDataUrl(cedulaFile);
    const driversLicensePhotoDataUrl = await readFileAsDataUrl(driversLicensePhoto);
    const driversLicenseBackPhotoDataUrl = await readFileAsDataUrl(driversLicenseBackPhoto);
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        action: 'create-franchise',
        name,
        owner,
        address,
        receiptDataUrl,
        cedulaDataUrl,
        driversLicense,
        driversLicensePhotoDataUrl,
        driversLicenseBackPhotoDataUrl
      })
    });

    await loaderDone;
    localFranchiseSubmission = true;
    sessionStorage.setItem('franchiseRegistrationPending', 'true');
    state.franchise = { ...state.franchise, name, status: 'Pending', registered: false, hasApplication: true };
    renderFranchiseSummary();
    showToast('Franchise registration submitted for LGU review.');
    form.reset();
    document.querySelector('[data-close="screen-register-franchise"]').click();
    await refreshDashboard();
  } catch (error) {
    TFMSLoader.hide();
    showToast(error.message || 'Unable to submit franchise application.', 'error');
  } finally {
    setSubmitLoading(form, false);
  }
}

async function handleCreateDriver(evt) {
  evt.preventDefault();
  const name = document.getElementById('f-name').value.trim();
  const dob = document.getElementById('f-dob').value;
  const gender = document.getElementById('f-gender').value;
  const licenseNumber = document.getElementById('f-license-number').value.trim();
  const orCrNumber = document.getElementById('f-or-cr-number').value.trim();
  const contact = document.getElementById('f-contact').value.trim();
  const address = document.getElementById('f-address').value.trim();
  const tricycleId = document.getElementById('f-tricycle').value || null;
  const fileInputLicense = document.getElementById('f-license');
  const fileInputLicenseBack = document.getElementById('f-license-back');
  const fileInputOr = document.getElementById('f-or');
  const fileInputPresident = document.getElementById('f-president');

  if (!name || !dob || !gender || !licenseNumber || !orCrNumber || !contact) {
    showToast('Please complete the driver information, license number, and OR/CR number.', 'error');
    return;
  }
  const form = evt.currentTarget;
  setSubmitLoading(form, true);
  const loaderDone = TFMSLoader.show();

  try {
    const payload = {
      action: 'create',
      name,
      dob,
      gender,
      driver_license_number: licenseNumber,
      or_cr_number: orCrNumber,
      contact,
      address,
      tricycle_id: tricycleId,
      licenseData: await readFileAsDataUrl(fileInputLicense?.files?.[0] || null),
      licenseBackData: await readFileAsDataUrl(fileInputLicenseBack?.files?.[0] || null),
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

    await loaderDone;
    showToast(editingDriverId ? 'Driver submission updated.' : 'Driver registered successfully.');
    editingDriverId = null;
    setElementText('driver-form-title', 'Add Driver');
    setElementText('driver-submit-label', 'Register Driver');
    setFieldRequired('f-license', true);
    setFieldRequired('f-license-back', true);
    setFieldRequired('f-dob', true);
    setFieldRequired('f-license-exp', true);
    form.reset();
    clickElement('[data-close="screen-add-driver"]');
    await refreshDashboard();
  } catch (error) {
    TFMSLoader.hide();
    showToast(error.message || 'Unable to save driver.', 'error');
  } finally {
    setSubmitLoading(form, false);
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

  const driverId = document.getElementById('t-driver').value || null;
  if (!brand || !sticker || !plate || !engine || !chassis || !color) {
    TFMSLoader.hide();
    showToast('Please complete all required tricycle details.', 'error');
    return;
  }
  const form = evt.currentTarget;
  setSubmitLoading(form, true);
  const loaderDone = TFMSLoader.show();

  try {
    const orDocument = await readFileAsDataUrl(document.getElementById('t-or')?.files?.[0] || null);
    const vehicleImage = await readOptionalVehicleImage('t-image');
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
        orNumber: document.getElementById('t-or-number').value.trim(),
        orDocumentData: orDocument,
        vehicleImageData: vehicleImage,
        driver_id: driverId,
        status: 'Pending'
      })
    });

    await loaderDone;
    showToast(
      editingTricycleId ? 'Tricycle submission updated.' : 'Tricycle added successfully.',
      'success',
      editingTricycleId ? 2200 : 8000,
      editingTricycleId ? '' : 'tricycle-registration-success'
    );
    editingTricycleId = null;
    setElementText('tricycle-form-title', 'Add Tricycle');
    setElementText('tricycle-submit-label', 'Register Tricycle');
    form.reset();
    clickElement('[data-close="screen-add-tricycle"]');
    await refreshDashboard();
  } catch (error) {
    TFMSLoader.hide();
    if (error.status === 401) {
      setAppVisible(false);
      setLoginError('Your session expired. Please sign in again.');
    } else {
      showToast(error.message || 'Unable to add tricycle.', 'error');
    }
  } finally {
    setSubmitLoading(form, false);
  }
}

async function handleCreateFranchiseOwnerTricycle(evt) {
  evt.preventDefault();
  const form = evt.currentTarget;
  const orCrImage = document.getElementById('ot-orcr-image')?.files?.[0];
  if (!orCrImage) {
    showToast('Please upload an OR/CR image as proof of vehicle documentation.', 'error');
    return;
  }
  if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(orCrImage.type)) {
    showToast('Upload the OR/CR proof as a JPEG, PNG, GIF, or WebP image.', 'error');
    return;
  }
  if (orCrImage.size > 5 * 1024 * 1024) {
    showToast('The OR/CR image must be 5 MB or smaller.', 'error');
    return;
  }

  const payload = {
    action: 'create-franchise-owner-tricycle',
    brand: document.getElementById('ot-brand').value.trim(),
    sticker: document.getElementById('ot-unit').value.trim(),
    plate: document.getElementById('ot-plate').value.trim(),
    engine: document.getElementById('ot-engine').value.trim(),
    chassis: document.getElementById('ot-chassis').value.trim(),
    color: document.getElementById('ot-color').value.trim(),
    orNumber: document.getElementById('ot-or-number').value.trim(),
    orCrDocumentData: '',
    vehicleImageData: ''
  };
  if (!payload.brand || !payload.sticker || !payload.plate || !payload.engine || !payload.chassis || !payload.color) {
    showToast('Please complete all required owner tricycle details.', 'error');
    return;
  }

  setSubmitLoading(form, true);
  try {
    payload.orCrDocumentData = await readFileAsDataUrl(orCrImage);
    payload.vehicleImageData = await readOptionalVehicleImage('ot-image');
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    form.reset();
    document.querySelector('[data-close="screen-add-owner-tricycle"]')?.click();
    showToast('Owner tricycle saved separately from the registered fleet.');
    await refreshDashboard(false);
  } catch (error) {
    if (error.status === 401) {
      setAppVisible(false);
      setLoginError('Your session expired. Please sign in again.');
    } else {
      showToast(error.message || 'Unable to save owner tricycle.', 'error');
    }
  } finally {
    setSubmitLoading(form, false);
  }
}

function openProfilePhotoMenu() {
  const menu = document.getElementById('profile-photo-menu');
  if (!menu || menu.open) return;
  const hasPhoto = Boolean(state.profile?.photoUrl);
  document.getElementById('profile-photo-view').disabled = !hasPhoto;
  const change = document.getElementById('profile-photo-change');
  change.hidden = false;
  change.textContent = hasPhoto ? 'Change profile photo' : 'Upload profile photo';
  document.getElementById('profile-photo-menu-description').textContent = hasPhoto
    ? 'View your photo or choose a new one.'
    : 'You have not uploaded a profile photo yet.';
  menu.showModal();
}

async function handleProfilePhotoChange(event) {
  const input = event.currentTarget;
  const file = input.files?.[0];
  if (!file) return;
  const button = document.getElementById('profile-avatar-button');
  const uploadButton = document.getElementById('profile-photo-upload');
  const status = document.getElementById('profile-photo-status');
  try {
    if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) throw new Error('Choose a JPG, PNG, WebP or GIF image.');
    if (file.size > 5 * 1024 * 1024) throw new Error('Your profile picture must be 5 MB or smaller.');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    uploadButton.disabled = true;
    uploadButton.textContent = 'Uploading…';
    status.textContent = 'Uploading your profile picture.';
    const dataUrl = await readFileAsDataUrl(file);
    const result = await requestJson(riderApi, { method: 'POST', body: JSON.stringify({ action: 'update-profile-photo', photoDataUrl: dataUrl }) });
    state.profile.photoUrl = result.photoUrl;
    hydrateProfile();
    status.textContent = 'Profile picture updated.';
    showToast('Profile picture updated.');
  } catch (error) {
    status.textContent = error.message || 'Unable to upload profile picture.';
    showToast(status.textContent, 'error');
  } finally {
    button.disabled = false;
    button.removeAttribute('aria-busy');
    uploadButton.disabled = false;
    uploadButton.textContent = state.profile?.photoUrl ? 'Change profile photo' : 'Upload profile photo';
    input.value = '';
  }
}

async function handleUpdateProfile(evt) {
  evt.preventDefault();
  const name = document.getElementById('as-name').value.trim();
  const middleName = document.getElementById('as-middle-name').value.trim();
  const email = document.getElementById('as-email').value.trim();
  const contact = document.getElementById('as-contact').value.trim();

  if (!name || !email) {
    showToast('Name and email are required.', 'error');
    return;
  }
  const form = evt.currentTarget;
  setSubmitLoading(form, true);
  const loaderDone = TFMSLoader.show();

  try {
    await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        action: 'update-profile',
        id: state.profile?.id,
        name,
        middleName,
        email,
        contact
      })
    });

    await loaderDone;
    showToast('Profile updated successfully.');
    document.getElementById('screen-account-personal')?.classList.remove('active');
    document.getElementById('screen-account-settings')?.classList.add('active');
    await refreshDashboard();
  } catch (error) {
    TFMSLoader.hide();
    showToast(error.message || 'Unable to save profile.', 'error');
  } finally {
    setSubmitLoading(form, false);
  }
}

async function handleChangeAccountPassword(evt) {
  evt.preventDefault();
  const form = evt.currentTarget;
  const currentPassword = document.getElementById('account-current-password').value;
  const newPassword = document.getElementById('account-new-password').value;
  const confirmPassword = document.getElementById('account-confirm-password').value;

  if (newPassword.length < 8) {
    showToast('New password must be at least 8 characters.', 'error');
    return;
  }
  if (newPassword !== confirmPassword) {
    showToast('New passwords do not match.', 'error');
    return;
  }

  setSubmitLoading(form, true);
  try {
    const result = await requestJson(riderApi, {
      method: 'POST',
      body: JSON.stringify({
        action: 'change-password',
        currentPassword,
        newPassword,
        confirmPassword
      })
    });
    form.reset();
    showToast(result.notificationWarning || result.message || 'Password changed successfully.', result.notificationWarning ? 'error' : 'success');
  } catch (error) {
    showToast(error.message || 'Unable to change your password.', 'error');
  } finally {
    setSubmitLoading(form, false);
  }
}

function ensureUploadGroups() {
  const driverGroup = document.getElementById('upload-groups');
  if (driverGroup && driverGroup.children.length === 0) {
    const documents = [
      { id: 'f-license', label: "Driver's License (Front)", required: true, accept: 'image/*,.pdf' },
      { id: 'f-license-back', label: "Driver's License (Back Image)", required: true, accept: 'image/jpeg,image/png,image/gif,image/webp' },
      { id: 'f-or', label: 'OR/CR', required: false, accept: 'image/*,.pdf' },
      { id: 'f-president', label: "President's Certificate", required: false, accept: 'image/*,.pdf' }
    ];

    documents.forEach((doc) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'field';
      wrapper.innerHTML = `
        <label for="${doc.id}">${doc.label}</label>
        <input id="${doc.id}" type="file" accept="${doc.accept}" ${doc.required ? 'required' : ''}>
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

  document.querySelectorAll('[data-account-settings-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = document.getElementById(button.dataset.accountSettingsOpen);
      if (!target) return;
      document.getElementById('screen-account-settings')?.classList.remove('active');
      target.classList.add('active');
    });
  });
  document.querySelectorAll('[data-account-settings-back]').forEach((button) => {
    button.addEventListener('click', () => {
      button.closest('.detail-screen')?.classList.remove('active');
      document.getElementById('screen-account-settings')?.classList.add('active');
    });
  });

  document.getElementById('login-toggle-pw')?.addEventListener('click', () => {
    const input = document.getElementById('login-password');
    const isVisible = input.type === 'text';
    input.type = isVisible ? 'password' : 'text';
    document.getElementById('login-toggle-pw').setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
    document.getElementById('login-toggle-pw').setAttribute('aria-pressed', String(!isVisible));
  });

  document.getElementById('signup-toggle-pw')?.addEventListener('click', () => {
    const input = document.getElementById('signup-password');
    const isVisible = input.type === 'text';
    input.type = isVisible ? 'password' : 'text';
    document.getElementById('signup-toggle-pw').setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
    document.getElementById('signup-toggle-pw').setAttribute('aria-pressed', String(!isVisible));
  });

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    const icons = button.querySelectorAll('svg');
    button.addEventListener('click', () => {
      const isVisible = input.type === 'text';
      input.type = isVisible ? 'password' : 'text';
      button.setAttribute('aria-label', `${isVisible ? 'Show' : 'Hide'} ${input.labels?.[0]?.textContent.toLowerCase() || 'password'}`);
      button.setAttribute('aria-pressed', String(!isVisible));
      icons[0].hidden = !isVisible;
      icons[1].hidden = isVisible;
    });
  });

  document.getElementById('auth-switch-btn')?.addEventListener('click', () => {
    const signupForm = document.getElementById('signup-form');
    const isSignup = !signupForm?.classList.contains('rider-hidden');
    setAuthMode(isSignup ? 'login' : 'signup');
  });

  document.getElementById('login-forgot')?.addEventListener('click', () => {
    const loginIdentifier = document.getElementById('login-username').value.trim();
    const resetEmail = document.getElementById('reset-email');
    resetEmail.value = loginIdentifier.includes('@') ? loginIdentifier : '';
    document.getElementById('login-form')?.classList.add('rider-hidden');
    document.getElementById('signup-form')?.classList.add('rider-hidden');
    document.getElementById('password-reset-request-form')?.classList.remove('rider-hidden');
    document.getElementById('auth-switch-row')?.classList.add('rider-hidden');
    document.getElementById('login-welcome-heading')?.classList.add('rider-hidden');
    setLoginError('');
    setPasswordResetStatus('');
    resetEmail.focus();
  });
  document.getElementById('reset-request-back')?.addEventListener('click', () => setAuthMode('login'));
  document.getElementById('password-reset-request-form')?.addEventListener('submit', handlePasswordResetRequest);

  document.getElementById('login-form')?.addEventListener('submit', handleLogin);
  document.getElementById('signup-form')?.addEventListener('submit', handleSignup);
  ['login-username', 'login-password'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', () => {
      if (document.getElementById('login-error')?.style.display !== 'none') setLoginError('');
    });
  });
  ['signup-name', 'signup-email', 'signup-password'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', () => {
      if (document.getElementById('signup-error')?.style.display !== 'none') setSignupError('');
    });
  });
  document.getElementById('register-franchise-form')?.addEventListener('submit', handleCreateFranchise);
  document.getElementById('renewal-form')?.addEventListener('submit', handleApplyRenewal);
  document.getElementById('add-driver-form')?.addEventListener('submit', handleCreateDriver);
  document.getElementById('td-status-btn')?.addEventListener('click', handleStatusButtonClick);
  document.getElementById('dd-status-btn')?.addEventListener('click', () => {
    closeDriverActionsMenu();
    handleStatusButtonClick();
  });
  document.getElementById('dd-actions-toggle')?.addEventListener('click', (event) => {
    event.stopPropagation();
    const menu = document.getElementById('dd-actions-menu');
    const isOpen = menu?.classList.toggle('is-open') || false;
    document.getElementById('dd-actions-toggle')?.setAttribute('aria-expanded', String(isOpen));
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.driver-actions')) closeDriverActionsMenu();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeDriverActionsMenu();
  });
  document.getElementById('td-remove-btn')?.addEventListener('click', () => {
    const button = document.getElementById('td-remove-btn');
    const id = button.dataset.tricycleId;
    if (id) openRemoveConfirmation(button.dataset.recordType || 'tricycle', Number(id));
  });
  document.getElementById('dd-remove-btn')?.addEventListener('click', () => {
    closeDriverActionsMenu();
    const id = document.getElementById('dd-remove-btn').dataset.driverId;
    if (id) openRemoveConfirmation('driver', Number(id));
  });
  document.getElementById('confirm-cancel')?.addEventListener('click', closeStatusConfirmation);
  document.getElementById('confirm-ok')?.addEventListener('click', handleStatusConfirmation);
  document.getElementById('assign-tricycle-cancel')?.addEventListener('click', () => {
    document.getElementById('assign-tricycle-overlay')?.classList.remove('active');
  });
  document.getElementById('assign-tricycle-confirm')?.addEventListener('click', () => {
    const assignButton = document.getElementById('dd-assign-btn');
    const tricycleSelect = document.getElementById('dd-tricycle-select');
    if (assignButton?.dataset.driverId && tricycleSelect) {
      assignDriver(assignButton.dataset.driverId, tricycleSelect.value);
    }
  });
  document.getElementById('dd-edit-btn')?.addEventListener('click', () => {
    closeDriverActionsMenu();
    const driver = state.drivers.find((item) => String(item.id) === String(document.getElementById('dd-edit-btn').dataset.driverId));
    if (!driver) return;
    editingDriverId = driver.id;
    document.getElementById('driver-form-title').textContent = 'Edit Driver Submission';
    document.getElementById('driver-submit-label').textContent = 'Save Changes';
    document.getElementById('add-driver-form').reset();
    setFieldRequired('f-license', false);
    setFieldRequired('f-license-back', false);
    setFieldRequired('f-dob', false);
    setFieldRequired('f-license-exp', false);
    document.getElementById('f-name').value = driver.name || '';
    document.getElementById('f-contact').value = driver.contact || '';
    document.getElementById('f-gender').value = driver.gender || '';
    document.getElementById('f-license-number').value = driver.licenseNumber || '';
    document.getElementById('f-or-cr-number').value = driver.orCrNumber || '';
    document.getElementById('f-address').value = driver.address || '';
    document.getElementById('f-tricycle').value = driver.tricycleId || '';
    document.querySelector('[data-close="detail-driver"]').click();
    document.getElementById('screen-add-driver').classList.add('active');
  });
  document.getElementById('add-tricycle-form')?.addEventListener('submit', handleCreateTricycle);
  document.getElementById('add-owner-tricycle-form')?.addEventListener('submit', handleCreateFranchiseOwnerTricycle);
  document.getElementById('td-photo-view')?.addEventListener('click', () => {
    const collection = currentVehiclePhotoTarget?.type === 'owner_tricycle' ? state.franchiseOwnerTricycles : state.tricycles;
    const vehicle = collection.find((item) => String(item.id) === String(currentVehiclePhotoTarget?.id));
    if (vehicle?.image) {
      openDocumentViewer(vehicle.image, `${vehicle.unit || 'Tricycle'} image`);
    } else {
      document.getElementById('td-photo-file')?.click();
    }
  });
  document.getElementById('td-photo-upload-trigger')?.addEventListener('click', () => {
    document.getElementById('td-photo-file')?.click();
  });
  document.getElementById('td-photo-file')?.addEventListener('change', async (event) => {
    const file = event.target.files?.[0];
    if (file) await uploadVehicleImage(file);
    event.target.value = '';
  });
  document.getElementById('td-photo-reset')?.addEventListener('click', resetVehicleImage);
  document.getElementById('btn-add-owner-tricycle')?.addEventListener('click', () => {
    if (!state.franchise?.registered) {
      showToast('Register and get your franchise approved before adding owner tricycles.', 'error');
      return;
    }
    const screen = document.getElementById('screen-add-owner-tricycle');
    if (screen) screen.classList.add('active');
  });
  document.getElementById('td-more')?.addEventListener('click', () => {
    const tricycle = state.tricycles.find((item) => String(item.id) === String(document.getElementById('td-more').dataset.tricycleId));
    if (!tricycle) return;
    editingTricycleId = tricycle.id;
    document.getElementById('tricycle-form-title').textContent = 'Edit Tricycle Submission';
    document.getElementById('tricycle-submit-label').textContent = 'Save Changes';
    document.getElementById('add-tricycle-form').reset();
    setFieldRequired('t-or', false);
    document.getElementById('t-brand').value = tricycle.brand || '';
    document.getElementById('t-unit').value = tricycle.unit || '';
    document.getElementById('t-plate').value = tricycle.plate || '';
    document.getElementById('t-engine').value = tricycle.engine || '';
    document.getElementById('t-chassis').value = tricycle.chassis || '';
    document.getElementById('t-color').value = tricycle.color || '';
    document.getElementById('t-or-number').value = tricycle.orNumber || '';
    const driver = state.drivers.find((item) => item.name === tricycle.driver);
    document.getElementById('t-driver').value = driver?.id || '';
    document.querySelector('[data-close="detail-tricycle"]').click();
    document.getElementById('screen-add-tricycle').classList.add('active');
  });
  document.getElementById('profile-photo-button')?.addEventListener('click', openProfilePhotoMenu);
  document.getElementById('profile-photo-upload')?.addEventListener('click', openProfilePhotoMenu);
  document.getElementById('profile-avatar-button')?.addEventListener('click', openProfilePhotoMenu);
  document.getElementById('profile-photo-view')?.addEventListener('click', () => {
    document.getElementById('profile-photo-menu').close();
    if (state.profile?.photoUrl) openDocumentViewer(state.profile.photoUrl, 'Profile photo');
  });
  document.getElementById('profile-photo-change')?.addEventListener('click', () => {
    document.getElementById('profile-photo-menu').close();
    document.getElementById('profile-photo-input').click();
  });
  document.getElementById('profile-photo-menu')?.addEventListener('click', (event) => {
    if (event.target !== event.currentTarget) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) event.currentTarget.close();
  });
  document.getElementById('profile-photo-input')?.addEventListener('change', handleProfilePhotoChange);
  document.getElementById('account-settings-form')?.addEventListener('submit', handleUpdateProfile);
  document.getElementById('account-password-form')?.addEventListener('submit', handleChangeAccountPassword);

  document.getElementById('btn-open-register-franchise')?.addEventListener('click', () => {
    const screen = document.getElementById('screen-register-franchise');
    if (screen) screen.classList.add('active');
  });

  document.getElementById('fab-add-driver')?.addEventListener('click', () => {
    editingDriverId = null;
    document.getElementById('driver-form-title').textContent = 'Add Driver';
    document.getElementById('driver-submit-label').textContent = 'Register Driver';
    setFieldRequired('f-license', true);
    setFieldRequired('f-license-back', true);
    setFieldRequired('f-dob', true);
    setFieldRequired('f-license-exp', true);
    const screen = document.getElementById('screen-add-driver');
    if (screen) screen.classList.add('active');
  });

  document.getElementById('fab-add-tricycle')?.addEventListener('click', () => {
    editingTricycleId = null;
    document.getElementById('tricycle-form-title').textContent = 'Add Tricycle';
    document.getElementById('tricycle-submit-label').textContent = 'Register Tricycle';
    setFieldRequired('t-or', true);
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

  const legacyResetToken = new URLSearchParams(window.location.search).get('reset_token');
  if (legacyResetToken) {
    const resetUrl = new URL('reset_password.html', window.location.href);
    resetUrl.searchParams.set('token', legacyResetToken);
    window.location.replace(resetUrl);
    return;
  }

  const hasSession = await refreshDashboard();
  if (!hasSession) {
    document.getElementById('login-username')?.focus();
    return;
  }

  state.refreshTimer = window.setInterval(() => refreshDashboard(false), 30000);
}

document.addEventListener('DOMContentLoaded', initializeApp);
