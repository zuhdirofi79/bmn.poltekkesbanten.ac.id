(function () {
  var cfgEl = document.getElementById('bhpImportFotoConfig');
  var openBtn = document.getElementById('btnImportFoto');
  if (!cfgEl || !openBtn) return;

  var cfg = JSON.parse(cfgEl.textContent || '{}');
  var modalEl = document.getElementById('bhpImportFotoModal');
  if (!modalEl || typeof bootstrap === 'undefined') return;
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  var filesInput = document.getElementById('bhpImportFiles');
  var thumbs = document.getElementById('bhpImportThumbs');
  var drop = document.getElementById('bhpImportDrop');
  var alertBox = document.getElementById('bhpImportAlert');
  var hint = document.getElementById('bhpImportOcrHint');
  var stepUpload = document.getElementById('bhpImportStepUpload');
  var stepPreview = document.getElementById('bhpImportStepPreview');
  var stepDone = document.getElementById('bhpImportStepDone');
  var tbody = document.getElementById('bhpImportTbody');
  var summaryEl = document.getElementById('bhpImportSummary');
  var ocrBtn = document.getElementById('bhpImportOcrBtn');
  var saveBtn = document.getElementById('bhpImportSave');
  var backBtn = document.getElementById('bhpImportBack');
  var reloadBtn = document.getElementById('bhpImportReload');
  var progress = document.getElementById('bhpImportProgress');
  var doneMsg = document.getElementById('bhpImportDoneMsg');

  var files = [];
  var rows = [];
  var busy = false;

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function showAlert(type, text) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = text;
    alertBox.classList.remove('d-none');
  }
  function hideAlert() {
    alertBox.classList.add('d-none');
    alertBox.textContent = '';
  }

  function setStep(step) {
    stepUpload.classList.toggle('d-none', step !== 'upload');
    stepPreview.classList.toggle('d-none', step !== 'preview');
    stepDone.classList.toggle('d-none', step !== 'done');
    backBtn.classList.toggle('d-none', step !== 'preview');
    saveBtn.classList.toggle('d-none', step !== 'preview');
    reloadBtn.classList.toggle('d-none', step !== 'done');
  }

  function renderThumbs() {
    thumbs.innerHTML = '';
    files.forEach(function (file, i) {
      var wrap = document.createElement('div');
      wrap.className = 'bhp-import-thumb';
      var img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.alt = 'Foto ' + (i + 1);
      var cap = document.createElement('div');
      cap.className = 'small text-muted';
      cap.textContent = 'Foto ' + (i + 1);
      wrap.appendChild(img);
      wrap.appendChild(cap);
      thumbs.appendChild(wrap);
    });
    ocrBtn.disabled = files.length === 0 || busy;
  }

  function addFiles(list) {
    var max = cfg.max_files || 10;
    for (var i = 0; i < list.length; i++) {
      if (files.length >= max) break;
      var f = list[i];
      if (!/^image\/(jpeg|png|webp)$/i.test(f.type) && !/\.(jpe?g|png|webp)$/i.test(f.name || '')) continue;
      files.push(f);
    }
    renderThumbs();
  }

  function badge(status) {
    if (status === 'ok') return '<span class="badge bg-success">Siap</span>';
    if (status === 'sudah_ada') return '<span class="badge bg-secondary">Sudah ada</span>';
    if (status === 'duplikat_batch') return '<span class="badge bg-dark">Duplikat</span>';
    return '<span class="badge bg-warning text-dark">⚠ Perlu diperiksa</span>';
  }

  function rowClass(status) {
    if (status === 'perlu_diperiksa') return 'bhp-import-row--warn';
    if (status === 'sudah_ada' || status === 'duplikat_batch') return 'bhp-import-row--skip';
    return 'bhp-import-row--ok';
  }

  function refreshRowStatus(row) {
    var nama = (row.nama_barang || '').trim();
    var satuan = (row.satuan || '').trim();
    var jumlah = Number(row.jumlah);
    if (nama && satuan && Number.isFinite(jumlah) && jumlah >= 0) {
      row.status = 'ok';
      row.notes = [];
    } else {
      row.status = 'perlu_diperiksa';
    }
  }

  function renderRows() {
    tbody.innerHTML = '';
    var siap = 0, warn = 0, skip = 0;
    rows.forEach(function (row, i) {
      if (row.status === 'ok') siap++;
      else if (row.status === 'perlu_diperiksa') warn++;
      else skip++;
      var tr = document.createElement('tr');
      tr.className = rowClass(row.status);
      tr.innerHTML =
        '<td>' + (row.no || (i + 1)) + '</td>' +
        '<td><input class="form-control form-control-sm" data-k="nama_barang" value="' + esc(row.nama_barang) + '"></td>' +
        '<td><input class="form-control form-control-sm" data-k="merk" value="' + esc(row.merk) + '"></td>' +
        '<td><input class="form-control form-control-sm" type="number" min="0" data-k="jumlah" value="' + esc(row.jumlah) + '" style="max-width:90px"></td>' +
        '<td><input class="form-control form-control-sm" data-k="satuan" value="' + esc(row.satuan) + '" style="max-width:110px"></td>' +
        '<td><input class="form-control form-control-sm" data-k="kode_barang" value="' + esc(row.kode_barang) + '"></td>' +
        '<td class="bhp-import-status">' + badge(row.status) + (row.notes && row.notes.length ? '<div class="small text-muted mt-1">' + esc(row.notes.join(' ')) + '</div>' : '') + '</td>' +
        '<td><button type="button" class="btn btn-outline-danger btn-sm" data-del="' + i + '">Hapus</button></td>';
      tbody.appendChild(tr);
    });
    summaryEl.textContent = rows.length + ' baris · ' + siap + ' siap · ' + warn + ' perlu diperiksa · ' + skip + ' dilewati';
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  function payloadRows() {
    return rows.map(function (row) {
      return {
        nama_barang: row.nama_barang,
        merk: row.merk,
        satuan: row.satuan,
        jumlah: row.jumlah,
        kode_barang: row.kode_barang,
        status: row.status
      };
    });
  }

  openBtn.addEventListener('click', function () {
    hideAlert();
    setStep('upload');
    modal.show();
    fetch(cfg.status_url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.available) {
          hint.classList.remove('d-none');
          hint.textContent = data.message || 'Mesin OCR belum tersedia di server.';
          ocrBtn.disabled = true;
        } else {
          hint.classList.add('d-none');
          ocrBtn.disabled = files.length === 0;
        }
      })
      .catch(function () {});
  });

  document.getElementById('bhpImportPickBtn').addEventListener('click', function () { filesInput.click(); });
  document.getElementById('bhpImportAddMore').addEventListener('click', function () { filesInput.click(); });
  filesInput.addEventListener('change', function () {
    addFiles(filesInput.files || []);
    filesInput.value = '';
  });
  document.getElementById('bhpImportClear').addEventListener('click', function () {
    files = [];
    rows = [];
    renderThumbs();
    hideAlert();
    setStep('upload');
  });

  ['dragenter', 'dragover'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-drag'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); });
  });
  drop.addEventListener('drop', function (e) {
    addFiles(e.dataTransfer.files || []);
  });

  ocrBtn.addEventListener('click', function () {
    if (busy || files.length === 0) return;
    busy = true;
    ocrBtn.disabled = true;
    progress.classList.remove('d-none');
    hideAlert();
    var fd = new FormData();
    files.forEach(function (f) { fd.append('fotos[]', f); });
    fetch(cfg.ocr_url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: fd
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); })
      .then(function (res) {
        if (!res.body || !res.body.ok) {
          showAlert('danger', (res.body && res.body.message) || 'Gagal memproses OCR.');
          return;
        }
        rows = res.body.rows || [];
        renderRows();
        setStep('preview');
      })
      .catch(function () { showAlert('danger', 'Gagal menghubungi server OCR.'); })
      .finally(function () {
        busy = false;
        progress.classList.add('d-none');
        ocrBtn.disabled = files.length === 0;
      });
  });

  tbody.addEventListener('input', function (e) {
    var input = e.target.closest('input[data-k]');
    if (!input) return;
    var tr = input.closest('tr');
    var idx = Array.prototype.indexOf.call(tbody.children, tr);
    if (idx < 0 || !rows[idx]) return;
    var key = input.getAttribute('data-k');
    rows[idx][key] = key === 'jumlah' ? (input.value === '' ? '' : Number(input.value)) : input.value;
    refreshRowStatus(rows[idx]);
    tr.className = rowClass(rows[idx].status);
    var st = tr.querySelector('.bhp-import-status');
    if (st) st.innerHTML = badge(rows[idx].status);
  });

  tbody.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-del]');
    if (!btn) return;
    rows.splice(Number(btn.getAttribute('data-del')), 1);
    renderRows();
  });

  document.getElementById('bhpImportAddRow').addEventListener('click', function () {
    rows.push({
      no: rows.length + 1,
      nama_barang: '',
      merk: '',
      jumlah: 0,
      satuan: '',
      kode_barang: '',
      status: 'perlu_diperiksa',
      notes: ['Baris manual — lengkapi data.']
    });
    renderRows();
  });

  backBtn.addEventListener('click', function () { setStep('upload'); hideAlert(); });

  saveBtn.addEventListener('click', function () {
    if (busy || rows.length === 0) return;
    var warn = rows.filter(function (r) { return r.status === 'perlu_diperiksa'; }).length;
    if (warn > 0) {
      showAlert('warning', warn + ' baris perlu diperiksa. Koreksi atau hapus baris tersebut sebelum menyimpan. Tidak ada data yang akan disimpan sampai semua baris valid.');
      return;
    }
    busy = true;
    saveBtn.disabled = true;
    hideAlert();
    fetch(cfg.commit_url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify({ rows: payloadRows() })
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        if (!res.body || !res.body.ok) {
          var extra = '';
          if (res.body && res.body.errors && res.body.errors.length) {
            extra = ' ' + res.body.errors.slice(0, 8).join(' ');
          }
          showAlert('danger', ((res.body && res.body.message) || 'Import dibatalkan.') + extra);
          return;
        }
        doneMsg.textContent = res.body.message || 'Import berhasil.';
        setStep('done');
      })
      .catch(function () { showAlert('danger', 'Gagal menyimpan. Tidak ada perubahan yang diterapkan.'); })
      .finally(function () {
        busy = false;
        saveBtn.disabled = false;
      });
  });

  reloadBtn.addEventListener('click', function () { window.location.reload(); });
})();
