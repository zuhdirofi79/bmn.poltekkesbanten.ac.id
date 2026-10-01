@if(!empty($path) && is_file($path))
  <div class="shot">
    <img src="{{ $path }}" alt="{{ $caption }}">
    <div class="cap">{{ $caption }}</div>
  </div>
@endif
