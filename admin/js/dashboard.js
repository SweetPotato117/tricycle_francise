  fetch('../controllers/notification.php', { credentials: 'same-origin', cache: 'no-store' }).then(response => response.json()).then(result => {
    const count = (result.notifications || []).filter(notification => !notification.isRead).length;
    document.querySelectorAll('.notification-count').forEach(badge => { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; });
  }).catch(() => {});
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('drawerOverlay');
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  const reportModal = document.getElementById('reportModal');
  const openReportModalBtn = document.getElementById('openReportModalBtn');
  const closeReportModalBtn = document.getElementById('closeReportModalBtn');
  const reportType = document.getElementById('reportType');
  const reportValue = document.getElementById('reportValue');
  const reportPassword = document.getElementById('reportPassword');
  const toggleReportPassword = document.getElementById('toggleReportPassword');

  function openDrawer() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
  }
  function closeDrawer() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  }

  function openReportModal() {
    reportModal.classList.add('open');
    updateReportInput();
  }

  function closeReportModal() {
    reportModal.classList.remove('open');
    reportPassword.value = '';
    reportPassword.type = 'password';
    toggleReportPassword.setAttribute('aria-label', 'Show Excel password');
    toggleReportPassword.setAttribute('aria-pressed', 'false');
  }

  function updateReportInput() {
    const today = new Date();
    const currentMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    const currentDay = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    if (reportType.value === 'day') {
      reportValue.type = 'date';
      reportValue.value = currentDay;
    } else if (reportType.value === 'month') {
      reportValue.type = 'month';
      reportValue.value = currentMonth;
    } else {
      reportValue.type = 'number';
      reportValue.min = '2000';
      reportValue.max = '2100';
      reportValue.value = today.getFullYear();
    }
  }

  hamburgerBtn.addEventListener('click', openDrawer);
  overlay.addEventListener('click', closeDrawer);
  openReportModalBtn.addEventListener('click', openReportModal);
  closeReportModalBtn.addEventListener('click', closeReportModal);
  reportType.addEventListener('change', updateReportInput);
  toggleReportPassword.addEventListener('click', () => {
    const isVisible = reportPassword.type === 'text';
    reportPassword.type = isVisible ? 'password' : 'text';
    toggleReportPassword.setAttribute('aria-label', `${isVisible ? 'Show' : 'Hide'} Excel password`);
    toggleReportPassword.setAttribute('aria-pressed', String(!isVisible));
  });
  reportModal.addEventListener('click', (event) => {
    if (event.target === reportModal) closeReportModal();
  });

  document.getElementById('generateReportBtn').addEventListener('click', () => {
    if (!reportPassword.reportValidity()) return;

    const type = reportType.value;
    const value = reportValue.value;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../controllers/report_export.php';
    form.target = '_blank';
    form.hidden = true;

    [['type', type], ['value', value], ['sheet_password', reportPassword.value]].forEach(([name, fieldValue]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = fieldValue;
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();
    closeReportModal();
  });

  const dashboardApi = '../controllers/dashboard.php';
  const pendingApi = '../controllers/pending.php';

  async function loadPendingApplications() {
    const pendingList = document.getElementById('pendingApplicationList');
    const pendingCount = document.getElementById('pendingApplicationCount');
    const pendingBadge = document.getElementById('navBadge');

    try {
      const response = await fetch(`${pendingApi}?_=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load pending applications.');

      const requests = (result.groups || []).flatMap(group => (group.requests || [])
        .filter(request => request.status === 'Pending')
        .map(request => ({ group, request })))
        .sort((first, second) => new Date(second.request.submittedAt) - new Date(first.request.submittedAt));
      pendingCount.textContent = requests.length;
      pendingBadge.textContent = requests.length;
      pendingBadge.style.display = requests.length ? 'flex' : 'none';
      pendingList.replaceChildren();

      if (!requests.length) {
        const emptyMessage = document.createElement('div');
        emptyMessage.className = 'dashboard-list-sub';
        emptyMessage.textContent = 'No pending applications';
        pendingList.appendChild(emptyMessage);
        return;
      }

      requests.forEach(({ group, request }) => {
        const link = document.createElement('a');
        const query = new URLSearchParams({
          group: group.id,
          request: `${request.actionType}-${request.id}`
        });
        link.className = 'dashboard-list-item dashboard-list-link';
        link.href = `pending.html?${query}`;

        const details = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'dashboard-list-name';
        title.textContent = request.title;
        const subtitle = document.createElement('div');
        subtitle.className = 'dashboard-list-sub';
        subtitle.textContent = `${request.type} · ${group.franchiseName}`;
        details.append(title, subtitle);

        const submitted = document.createElement('div');
        submitted.className = 'dashboard-list-date';
        submitted.textContent = request.submittedAt || 'Recently submitted';
        link.append(details, submitted);
        pendingList.appendChild(link);
      });
    } catch (error) {
      pendingCount.textContent = '—';
      pendingBadge.style.display = 'none';
      pendingList.textContent = error.message;
    }
  }

  async function loadDashboard() {
    const response = await fetch(`${dashboardApi}?_=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load dashboard.');

    const stats = result.stats;
    document.getElementById('statTotalFranchises').textContent = stats.totalFranchises;
    document.getElementById('statActiveTricycles').textContent = stats.activeTricycles;
    document.getElementById('statActiveDrivers').textContent = stats.activeDrivers;

    const activeFranchises = document.getElementById('activeFranchiseList');
    activeFranchises.innerHTML = result.activeFranchises.length ? result.activeFranchises.map(franchise => `
      <div class="dashboard-list-item">
        <div><div class="dashboard-list-name">${franchise.name}</div><div class="dashboard-list-sub">${franchise.owner}</div></div>
        <div class="dashboard-list-date">${franchise.expiry || 'No expiry date'}</div>
      </div>
    `).join('') : '<div class="dashboard-list-sub">No active franchises</div>';
    document.getElementById('activeFranchiseCount').textContent = stats.activeFranchises;

    const franchiseStatuses = [
      ['Active', result.renewalOverview.active, 'var(--green)'],
      ['Expired', result.renewalOverview.expired, 'var(--red)']
    ];
    const totalFranchises = franchiseStatuses.reduce((total, [, count]) => total + count, 0);
    let currentPercent = 0;
    const segments = franchiseStatuses.filter(([, count]) => count > 0).map(([, count, color]) => {
      const nextPercent = currentPercent + (count / totalFranchises * 100);
      const segment = `${color} ${currentPercent}% ${nextPercent}%`;
      currentPercent = nextPercent;
      return segment;
    });
    document.getElementById('franchiseStatusChart').style.background = segments.length
      ? `conic-gradient(${segments.join(', ')})`
      : 'conic-gradient(var(--border-gray) 0 100%)';
    document.getElementById('franchiseStatusLegend').innerHTML = franchiseStatuses.map(([status, count, color]) => `
      <div class="pie-legend-row"><span class="pie-legend-label"><span class="pie-swatch" style="background:${color}"></span>${status}</span><strong>${count}</strong></div>
    `).join('');
  }

  loadDashboard().catch(error => {
    document.getElementById('activeFranchiseList').innerHTML = `<div class="dashboard-list-sub">${error.message}</div>`;
  });
  loadPendingApplications();
  setInterval(() => {
    if (!document.hidden) {
      loadDashboard().catch(() => {});
      loadPendingApplications();
    }
  }, 15000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      loadDashboard().catch(() => {});
      loadPendingApplications();
    }
  });
