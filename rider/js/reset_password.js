const resetToken = new URLSearchParams(window.location.search).get('token') || '';
const resetForm = document.getElementById('reset-form');
const resetStatus = document.getElementById('reset-status');
const resetSubmit = document.getElementById('reset-submit');

function showResetStatus(message, success = false) {
  resetStatus.textContent = message;
  resetStatus.classList.toggle('show', Boolean(message));
  resetStatus.classList.toggle('success', success);
}

if (!/^[a-f0-9]{64}$/i.test(resetToken)) {
  resetForm.hidden = true;
  showResetStatus('This password reset link is missing or invalid. Request a new link.');
}

resetForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const password = document.getElementById('new-password').value;
  const confirmation = document.getElementById('confirm-password').value;

  if (!resetToken) {
    showResetStatus('This password reset link is missing or invalid. Request a new link.');
    return;
  }
  if (password.length < 8) {
    showResetStatus('Your password must be at least 8 characters.');
    return;
  }
  if (password !== confirmation) {
    showResetStatus('The passwords do not match.');
    return;
  }

  resetSubmit.disabled = true;
  resetSubmit.textContent = 'Updating...';
  try {
    const response = await fetch('../controllers/rider_login.php', {
      method: 'POST',
      credentials: 'include',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reset-password', token: resetToken, password })
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Unable to update your password. Request a new link and try again.');
    }

    resetForm.reset();
    resetForm.hidden = true;
    showResetStatus(
      result.notificationWarning || result.message || 'Your password has been updated. You can now sign in.',
      !result.notificationWarning
    );
    const cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete('token');
    history.replaceState(null, document.title, `${cleanUrl.pathname}${cleanUrl.search}${cleanUrl.hash}`);
  } catch (error) {
    showResetStatus(error.message || 'Unable to update your password. Request a new link and try again.');
  } finally {
    resetSubmit.disabled = false;
    resetSubmit.textContent = 'Update Password';
  }
});
