@if(!empty($canManageMaster))
<div class="modal fade" id="bhpImportFotoModal" tabindex="-1" aria-labelledby="bhpImportFotoTitle" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-xl modal-dialog-scrollable bhp-import-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="bhpImportFotoTitle">Import Barang BHP dari Foto</h5>
          <div class="text-muted small">Upload foto tabel daftar barang. Sistem membaca data otomatis, lalu Anda periksa sebelum disimpan.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="bhpImportAlert" class="alert d-none" role="alert"></div>
        <div id="bhpImportOcrHint" class="alert alert-warning d-none"></div>

        <div id="bhpImportStepUpload">
          <div class="bhp-import-drop" id="bhpImportDrop">
            <input type="file" id="bhpImportFiles" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple hidden>
            <i class="ti ti-camera" style="font-size:2rem;"></i>
            <div class="fw-semibold mt-2">Upload Foto</div>
            <div class="text-muted small">JPG, JPEG, PNG, atau WEBP. Bisa beberapa halaman sekaligus (satu batch).</div>
            <button type="button" class="btn btn-prs-blue btn-sm mt-2" id="bhpImportPickBtn">Pilih foto</button>
          </div>
          <div id="bhpImportThumbs" class="bhp-import-thumbs mt-3"></div>
          <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="bhpImportAddMore">Tambah Foto</button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="bhpImportClear">Clear Semua</button>
            <button type="button" class="btn btn-prs-green btn-sm" id="bhpImportOcrBtn" disabled>Proses OCR</button>
          </div>
          <div id="bhpImportProgress" class="d-none mt-3">
            <div class="small text-muted mb-1">Membaca tabel dari foto… ini bisa 15–60 detik untuk beberapa halaman.</div>
            <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>
          </div>
        </div>

        <div id="bhpImportStepPreview" class="d-none">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <strong>Preview Hasil Pembacaan</strong>
            <div class="small text-muted" id="bhpImportSummary"></div>
          </div>
          <div class="table-responsive bhp-import-table-wrap">
            <table class="table table-sm align-middle bhp-import-table">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Nama Barang</th>
                  <th>Merk / Spesifikasi</th>
                  <th>Jumlah</th>
                  <th>Satuan</th>
                  <th>Kode Barang</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="bhpImportTbody"></tbody>
            </table>
          </div>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="bhpImportAddRow">+ Tambah Baris</button>
        </div>

        <div id="bhpImportStepDone" class="d-none">
          <div class="alert alert-success mb-0" id="bhpImportDoneMsg"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-outline-secondary d-none" id="bhpImportBack">Kembali ke Upload</button>
        <button type="button" class="btn btn-prs-green d-none" id="bhpImportSave">Simpan Semua Barang</button>
        <button type="button" class="btn btn-prs-blue d-none" id="bhpImportReload">Muat ulang Master Barang</button>
      </div>
    </div>
  </div>
</div>
@endif
