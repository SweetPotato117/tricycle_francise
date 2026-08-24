  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('drawerOverlay');
  const hamburgerBtn = document.getElementById('hamburgerBtn');

  function openDrawer() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
  }
  function closeDrawer() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  }

  hamburgerBtn.addEventListener('click', openDrawer);
  overlay.addEventListener('click', closeDrawer);

  /* ===== Add Franchise Modal ===== */
  const addFranchiseBtn = document.getElementById('addFranchiseBtn');
  const addFranchiseOverlay = document.getElementById('addFranchiseOverlay');
  const franchiseModalCloseBtn = document.getElementById('franchiseModalCloseBtn');
  const franchiseCancelBtn = document.getElementById('franchiseCancelBtn');
  const franchiseSaveBtn = document.getElementById('franchiseSaveBtn');
  const statusOptions = document.querySelectorAll('.status-toggle-option');

  function openFranchiseModal() { addFranchiseOverlay.classList.add('open'); }
  function closeFranchiseModal() { addFranchiseOverlay.classList.remove('open'); }

  addFranchiseBtn.addEventListener('click', openFranchiseModal);
  franchiseModalCloseBtn.addEventListener('click', closeFranchiseModal);
  franchiseCancelBtn.addEventListener('click', closeFranchiseModal);
  addFranchiseOverlay.addEventListener('click', (e) => {
    if (e.target === addFranchiseOverlay) closeFranchiseModal();
  });

  statusOptions.forEach(opt => {
    opt.addEventListener('click', () => {
      statusOptions.forEach(o => o.classList.remove('selected', 'active-choice', 'expired-choice'));
      opt.classList.add('selected', opt.dataset.status === 'active' ? 'active-choice' : 'expired-choice');
    });
  });

  franchiseSaveBtn.addEventListener('click', () => {
    closeFranchiseModal();
  });