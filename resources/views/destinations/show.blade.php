@extends('layouts.app')
@section('title', $name.' destination guide')
@section('content')
<div class="container py-5">

  <span class="eyebrow text-primary">DESTINATION GUIDE</span>
  <h1 class="display-5 fw-bold">{{ $name }}</h1>

  <div class="row g-4 mt-2">

    {{-- About --}}
    <div class="col-lg-8">
      <div class="form-card">
        @if($summary)
          <h3>{{ $summary['title'] }}</h3>
          <p>{{ $summary['extract'] }}</p>
          @if($summary['thumbnail'])
            <img class="wiki-image" src="{{ $summary['thumbnail'] }}" alt="{{ $summary['title'] }}">
          @endif
          <a href="{{ $summary['url'] }}" target="_blank" rel="noopener">Read more on Wikipedia ↗</a>
          <small class="d-block text-muted mt-2">Destination information sourced from Wikipedia. Content may be incomplete.</small>
        @else
          <p class="mb-0">No Wikipedia summary was found for this destination.</p>
        @endif
      </div>
    </div>

    {{-- Weather --}}
    <div class="col-lg-4">
      <div class="form-card">
        <h4>Current weather</h4>
        @if($conditions)
          <div class="display-5 fw-bold">{{ round($conditions['temperature']) }}°C</div>
          <p class="text-capitalize">{{ $conditions['condition'] }}</p>
          <p class="text-muted small mb-2">
            @if(is_numeric($conditions['humidity'] ?? null)) Humidity {{ round($conditions['humidity']) }}% @endif
            @if(is_numeric($conditions['wind'] ?? null)) · Wind {{ round($conditions['wind'], 1) }} m/s @endif
          </p>
          @if(($conditions['source'] ?? '') === 'Open-Meteo')
            <small class="text-muted">Weather data by <a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo.com</a></small>
          @elseif(!empty($conditions['source']))
            <small class="text-muted">Weather data by {{ $conditions['source'] }}</small>
          @endif
        @else
          <p class="text-muted mb-0">Weather is not available for this destination right now.</p>
        @endif
      </div>

      <a class="btn btn-primary w-100 mt-3" href="{{ route('hotels.search', ['destination' => $name, 'check_in' => date('Y-m-d', strtotime('+7 days')), 'check_out' => date('Y-m-d', strtotime('+9 days')), 'adults' => 2, 'rooms' => 1]) }}">
        Search hotels in {{ $name }}
      </a>
    </div>

  </div>

  {{-- Things to see --}}
  <h2 class="h3 mt-5 mb-1">Things to see in {{ $name }}</h2>

  @if(count($attractions) > 0)
    <p class="text-muted">Well-known places near the centre, from Wikipedia.</p>

    <div class="row g-3">
      @foreach($attractions as $place)
        <div class="col-md-6 col-lg-4">
          <div class="hotel-card h-100 d-flex flex-column">
            @if(!empty($place['image']))
              <img src="{{ $place['image'] }}" alt="{{ $place['name'] }}" loading="lazy" class="w-100" style="height:180px;object-fit:cover">
            @else
              <div class="hotel-placeholder">✦</div>
            @endif
            <div class="p-3 d-flex flex-column flex-grow-1">
              <h5 class="mb-1">{{ $place['name'] }}</h5>
              @if(!empty($place['description']))
                <p class="text-muted small mb-2">{{ $place['description'] }}</p>
              @endif
              <div class="d-flex justify-content-between align-items-center mt-auto">
                <small class="text-muted">
                  @if(is_numeric($place['distance'] ?? null))
                    {{ number_format((float) $place['distance'] / 1000, 1) }} km from centre
                  @endif
                </small>
                @if(!empty($place['url']))
                  <a href="{{ $place['url'] }}" target="_blank" rel="noopener" class="small">Wikipedia ↗</a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Map --}}
    @if(count($markers) > 0)
      <h2 class="h3 mt-5 mb-3">Map</h2>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
      <div id="destination-map" class="map-frame" style="height:420px"></div>
      <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
      <script>
        (function () {
          if (typeof L === 'undefined') { return; }

          var places = @json($markers);
          var map = L.map('destination-map', { scrollWheelZoom: false });

          L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
          }).addTo(map);

          var points = [];

          places.forEach(function (place) {
            // The name is added as text, never as HTML.
            var label = document.createElement('strong');
            label.textContent = place.name;

            L.marker([place.lat, place.lng]).addTo(map).bindPopup(label);
            points.push([place.lat, place.lng]);
          });

          if (points.length > 1) {
            map.fitBounds(points, { padding: [30, 30] });
          } else {
            map.setView(points[0], 14);
          }
        })();
      </script>
    @endif
  @else
    <div class="empty-state mt-3">No attractions were found for this destination.</div>
  @endif

</div>
@endsection
