@php
  $cls = 'badge-bmn-secondary';
  if ($row->coordination_status === 'sent_to_team') { $cls = 'badge-bmn-info'; }
  if ($row->coordination_status === 'done')         { $cls = 'badge-bmn-success'; }
@endphp
<span class="badge rounded-pill {{ $cls }}">{{ $row->coordinationStatusLabel() }}</span>
