<div class="modal fade pa-delete-modal" id="paDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" id="paDeleteForm" class="modal-content">
      @csrf
      <input type="hidden" name="_method" id="paDeleteMethod" value="POST">
      <div id="paDeleteIds"></div>
      <div class="modal-header" id="paDeleteHeader">
        <h5 class="modal-title" id="paDeleteTitle">Konfirmasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p id="paDeleteBody" class="mb-0" style="white-space:pre-line;"></p>
      </div>
      <div class="modal-footer d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger" id="paDeleteSubmit">Hapus</button>
      </div>
    </form>
  </div>
</div>

@once
@push('scripts')
<script>
(function () {
  var modalEl = document.getElementById('paDeleteModal');
  var form = document.getElementById('paDeleteForm');
  if (!modalEl || !form || typeof bootstrap === 'undefined') return;

  var titleEl = document.getElementById('paDeleteTitle');
  var bodyEl = document.getElementById('paDeleteBody');
  var methodEl = document.getElementById('paDeleteMethod');
  var idsEl = document.getElementById('paDeleteIds');
  var submitEl = document.getElementById('paDeleteSubmit');
  var headerEl = document.getElementById('paDeleteHeader');
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  function selectedIds(selector) {
    var ids = [];
    document.querySelectorAll(selector).forEach(function (el) {
      if (el.checked) ids.push(el.value);
    });
    return ids;
  }

  function fillIds(ids) {
    idsEl.innerHTML = '';
    ids.forEach(function (id) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'ids[]';
      input.value = id;
      idsEl.appendChild(input);
    });
  }

  function openFrom(btn, ids) {
    var tone = btn.getAttribute('data-tone') || 'danger';
    var subject = btn.getAttribute('data-subject') || '';
    var message = btn.getAttribute('data-message') || '';
    var count = ids ? ids.length : 0;
    titleEl.textContent = (btn.getAttribute('data-title') || 'Konfirmasi').replace('{n}', String(count));
    bodyEl.textContent = subject !== '' ? ('Anda akan menghapus:\n\n' + subject + '\n\n' + message) : message;
    form.action = btn.getAttribute('data-url') || form.action;
    methodEl.value = (btn.getAttribute('data-method') || 'POST').toUpperCase();
    fillIds(ids || []);
    submitEl.textContent = btn.getAttribute('data-confirm') || 'Ya';
    submitEl.className = 'btn ' + (tone === 'warn' ? 'btn-warning' : 'btn-danger');
    submitEl.disabled = false;
    submitEl.dataset.busy = '0';
    submitEl.dataset.busyLabel = btn.getAttribute('data-busy-label') || 'Menghapus...';
    headerEl.className = 'modal-header ' + (tone === 'warn' ? 'warn' : 'danger');
    modal.show();
  }

  document.addEventListener('click', function (event) {
    var single = event.target.closest('.js-pa-action');
    if (single) {
      event.preventDefault();
      openFrom(single, []);
      return;
    }
    var bulk = event.target.closest('.js-pa-bulk');
    if (!bulk) return;
    event.preventDefault();
    var ids = selectedIds(bulk.getAttribute('data-checkbox') || '');
    if (!ids.length) return;
    openFrom(bulk, ids);
  });

  form.addEventListener('submit', function (event) {
    if (submitEl.dataset.busy === '1') {
      event.preventDefault();
      return;
    }
    submitEl.dataset.busy = '1';
    submitEl.disabled = true;
    submitEl.textContent = submitEl.dataset.busyLabel || 'Menghapus...';
  });
})();
</script>
@endpush
@endonce
