  fetch('../controllers/notification.php', { credentials: 'same-origin', cache: 'no-store' }).then(response => response.json()).then(result => {
    const count = (result.notifications || []).filter(notification => !notification.isRead).length;
    document.querySelectorAll('.notification-count').forEach(badge => { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; });
  }).catch(() => {});
  fetch('../controllers/pending.php', { credentials: 'same-origin', cache: 'no-store' }).then(response => response.json()).then(result => {
    const count = (result.groups || []).flatMap(group => group.requests || []).filter(request => request.status === 'Pending').length;
    const badge = document.getElementById('navBadge');
    if (badge) { badge.textContent = count; badge.style.display = count ? 'flex' : 'none'; }
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
  }

  function updateReportInput() {
    const today = new Date();
    const currentMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    const currentDay = today.toISOString().split('T')[0];

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

    const pendingApplications = document.getElementById('pendingApplicationList');
    pendingApplications.innerHTML = result.pendingApplications.length ? result.pendingApplications.map(application => `
      <div class="dashboard-list-item">
        <div><div class="dashboard-list-name">${application.title}</div><div class="dashboard-list-sub">${application.type} · ${application.subtitle}</div></div>
        <div class="dashboard-list-date">${application.submitted || 'Recently submitted'}</div>
      </div>
    `).join('') : '<div class="dashboard-list-sub">No pending applications</div>';
    document.getElementById('pendingApplicationCount').textContent = stats.pendingApplications;
    const pendingBadge = document.getElementById('navBadge');
    if (pendingBadge) {
      pendingBadge.textContent = stats.pendingApplications;
      pendingBadge.style.display = stats.pendingApplications ? 'flex' : 'none';
    }

    const franchiseStatuses = [
      ['Active', result.renewalOverview.active, 'var(--green)'],
      ['Expired', result.renewalOverview.expired, 'var(--red)'],
      ['Pending Renewal', result.renewalOverview.pending, 'var(--yellow)']
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
    document.getElementById('pendingApplicationList').innerHTML = `<div class="dashboard-list-sub">${error.message}</div>`;
  });
  setInterval(() => {
    if (!document.hidden) loadDashboard().catch(() => {});
  }, 15000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) loadDashboard().catch(() => {});
  });
