window.openDonateModal = function() {
  const modal = document.getElementById('donateModal');
  if (!modal) return;
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
};
