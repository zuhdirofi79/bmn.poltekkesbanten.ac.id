<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('persediaan.admin.pembelian.import') }}" enctype="multipart/form-data" class="modal-content" id="importExcelForm">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Import Excel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">Satu file dapat berisi banyak transaksi. Baris dengan No. Faktur yang sama menjadi satu transaksi. Data belum masuk ke database sampai preview dikonfirmasi.</p>
        <label class="form-label">File Excel</label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" class="form-control" required>
        <div class="mt-2">
          <a href="{{ route('persediaan.admin.pembelian.import.template') }}" class="small">Download Template Excel</a>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-blue" id="importSubmitBtn">Upload &amp; Preview</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('importExcelForm').addEventListener('submit', function () {
  var btn = document.getElementById('importSubmitBtn');
  btn.disabled = true;
  btn.textContent = 'Memeriksa...';
});
</script>
