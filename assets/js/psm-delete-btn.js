document.addEventListener('click', function (e) {
  const deleteBtn = e.target.closest('.psm-delete-btn');
  if (!deleteBtn) return;

  const postId = deleteBtn.dataset.id;
  const nonce = deleteBtn.dataset.nonce;
  const actionName = deleteBtn.dataset.action;
  // Haal de selector op en zorg ervoor dat er altijd een punt voor staat als het een class is
  let rawSelector = deleteBtn.dataset.selector;
  const selector =
    rawSelector.startsWith('.') || rawSelector.startsWith('#')
      ? rawSelector
      : '.' + rawSelector;

  if (!confirm('Weet je zeker dat je dit item wilt verwijderen?')) {
    return;
  }

  const formData = new FormData();
  formData.append('action', actionName);
  formData.append('post_id', postId);
  formData.append('security_nonce', nonce);

  fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        console.log(data);
        refreshDom(selector);
      } else {
        alert('Fout: ' + (data.data.message || 'Verwijderen mislukt.'));
      }
    })
    .catch((error) => {
      console.error('Error:', error);
      alert('Er is een netwerkfout opgetreden.');
    });
});
