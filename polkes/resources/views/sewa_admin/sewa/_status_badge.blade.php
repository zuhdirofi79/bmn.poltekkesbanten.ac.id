@php
  $status = (string) ($row->status ?? '');
  $statusClassMap = [
    'pending' => 'badge-bmn-warning',
    'payment_review' => 'badge-bmn-info',
    'payment_confirmed' => 'badge-bmn-success',
    'coordinated' => 'badge-bmn-primary',
    'approved' => 'badge-bmn-success',
    'rejected' => 'badge-bmn-danger',
    'completed' => 'badge-bmn-dark',
  ];
  $statusLabelMap = [
    'pending' => 'Pending',
    'payment_review' => 'Diteruskan ke Bu Indah',
    'payment_confirmed' => 'Pembayaran Terkonfirmasi',
    'coordinated' => 'Koordinasi Berjalan',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak',
    'completed' => 'Selesai',
  ];
  $cls = $statusClassMap[$status] ?? 'badge-bmn-secondary';
  $lbl = $statusLabelMap[$status] ?? ucfirst(str_replace('_', ' ', $status));
@endphp
<span class="badge rounded-pill {{ $cls }}">{{ $lbl }}</span>
