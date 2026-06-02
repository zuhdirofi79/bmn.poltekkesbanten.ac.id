@php
  $cls = 'badge-bmn-warning';
  if ($row->payment_status === 'confirmed') { $cls = 'badge-bmn-success'; }
  if ($row->payment_status === 'rejected')  { $cls = 'badge-bmn-danger'; }
@endphp
<span class="badge rounded-pill {{ $cls }}">{{ $row->paymentStatusLabel() }}</span>
