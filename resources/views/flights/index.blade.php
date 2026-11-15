@extends('layouts.app')
@section('title', 'Search flights')
@section('content')
@php
  $value = fn (string $key, $default = '') => old($key, $criteria[$key] ?? $default);
  $trip = $value('trip', 'return');
  $duration = fn ($minutes) => is_numeric($minutes)
      ? intdiv((int) $minutes, 60).'h '.str_pad((string) ((int) $minutes % 60), 2, '0', STR_PAD_LEFT).'m'
      : '';
  $time = fn ($stamp) => $stamp ? rescue(fn () => \Illuminate\Support\Carbon::parse($stamp)->format('H:i'), '', false) : '';
  $day = fn ($stamp) => $stamp ? rescue(fn () => \Illuminate\Support\Carbon::parse($stamp)->format('D d M'), '', false) : '';
@endphp
<div class="container py-5">

  <span class="eyebrow text-primary">FLIGHTS</span>
  <h1 class="h2 mb-4">Search flights</h1>

  @if(!$configured)
    <div class="alert alert-warning">
      <strong>Flight search is not connected.</strong>
      Add a LITEAPI_API_KEY to the .env file to search flights.
    </div>
  @elseif($sandbox)
    <div class="alert alert-warning">
      <strong>Test mode.</strong> These flights and prices are sample data from the supplier's sandbox, not real fares.
    </div>
  @endif

  {{-- Search form --}}
  <form class="form-card mb-4" method="GET" action="{{ route('flights.index') }}" autocomplete="off">
    <div class="d-flex gap-4 mb-3">
      <div class="form-check">
        <input class="form-check-input" type="radio" name="trip" id="trip-return" value="return" @checked($trip === 'return')>
        <label class="form-check-label" for="trip-return">Return</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="trip" id="trip-one-way" value="one_way" @checked($trip === 'one_way')>
        <label class="form-check-label" for="trip-one-way">One way</label>
      </div>
    </div>

    <div class="row g-3 align-items-end">
      <div class="col-lg-3 col-md-6 position-relative">
        <label class="form-label" for="origin-input">From</label>
        <input class="form-control" id="origin-input" name="origin_label" placeholder="City or airport" value="{{ $value('origin_label') }}" required>
        <input type="hidden" id="origin-code" name="origin" value="{{ $value('origin') }}">
        <div id="origin-suggestions" class="suggestions" style="left:12px;right:12px"></div>
      </div>
      <div class="col-lg-3 col-md-6 position-relative">
        <label class="form-label" for="destination-airport-input">To</label>
        <input class="form-control" id="destination-airport-input" name="destination_label" placeholder="City or airport" value="{{ $value('destination_label') }}" required>
        <input type="hidden" id="destination-code" name="destination" value="{{ $value('destination') }}">
        <div id="destination-airport-suggestions" class="suggestions" style="left:12px;right:12px"></div>
      </div>
      <div class="col-lg-3 col-md-6">
        <label class="form-label" for="depart-date">Depart</label>
        <input class="form-control" type="date" id="depart-date" name="depart_date" min="{{ date('Y-m-d') }}" value="{{ $value('depart_date', date('Y-m-d', strtotime('+14 days'))) }}" required>
      </div>
      <div class="col-lg-3 col-md-6">
        <label class="form-label" for="return-date">Return</label>
        <input class="form-control" type="date" id="return-date" name="return_date" min="{{ date('Y-m-d') }}" value="{{ $value('return_date', date('Y-m-d', strtotime('+21 days'))) }}">
      </div>

      <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="adults">Adults <small class="text-muted">12+</small></label>
        <select class="form-select" id="adults" name="adults">
          @for($i = 1; $i <= 9; $i++)
            <option value="{{ $i }}" @selected((int) $value('adults', 1) === $i)>{{ $i }}</option>
          @endfor
        </select>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="children">Children <small class="text-muted">2–11</small></label>
        <select class="form-select" id="children" name="children">
          @for($i = 0; $i <= 8; $i++)
            <option value="{{ $i }}" @selected((int) $value('children', 0) === $i)>{{ $i }}</option>
          @endfor
        </select>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="infants">Infants <small class="text-muted">under 2</small></label>
        <select class="form-select" id="infants" name="infants">
          @for($i = 0; $i <= 4; $i++)
            <option value="{{ $i }}" @selected((int) $value('infants', 0) === $i)>{{ $i }}</option>
          @endfor
        </select>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="cabin-class">Cabin</label>
        <select class="form-select" id="cabin-class" name="cabin_class">
          @foreach(['ECONOMY' => 'Economy', 'PREMIUM_ECONOMY' => 'Premium economy', 'BUSINESS' => 'Business', 'FIRST' => 'First'] as $code => $label)
            <option value="{{ $code }}" @selected($value('cabin_class', 'ECONOMY') === $code)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="sort">Sort by</label>
        <select class="form-select" id="sort" name="sort">
          @foreach(['price' => 'Lowest price', 'duration' => 'Shortest', 'departure' => 'Earliest'] as $code => $label)
            <option value="{{ $code }}" @selected($value('sort', 'price') === $code)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="direct" name="direct" value="1" @checked($value('direct', false))>
          <label class="form-check-label" for="direct">Direct only</label>
        </div>
      </div>

      <div class="col-12">
        <button class="btn btn-primary px-4 py-2" type="submit">Search flights →</button>
      </div>
    </div>
  </form>

  {{-- Results --}}
  @if($searched)
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
      <div>
        <h2 class="h4 mb-1">{{ $criteria['origin'] }} → {{ $criteria['destination'] }}</h2>
        <p class="text-muted mb-0">
          {{ \Illuminate\Support\Carbon::parse($criteria['depart_date'])->format('d M Y') }}
          @if($criteria['return_date'])
            – {{ \Illuminate\Support\Carbon::parse($criteria['return_date'])->format('d M Y') }}
          @endif
          · {{ $criteria['adults'] + $criteria['children'] + $criteria['infants'] }}
          {{ Str::plural('traveller', $criteria['adults'] + $criteria['children'] + $criteria['infants']) }}
        </p>
      </div>
      @if($total > 0)
        <small class="text-muted">
          Showing {{ count($journeys) }} of {{ $total }} {{ Str::plural('flight', $total) }}
        </small>
      @endif
    </div>

    @forelse($journeys as $journey)
      <div class="hotel-card mb-3">
        <div class="row g-0 align-items-center">

          <div class="col-lg-9 p-3">
            @foreach($journey['legs'] as $leg)
              <div class="row align-items-center g-2 @if(!$loop->last) pb-3 mb-3 border-bottom @endif">
                <div class="col-md-3 d-flex align-items-center gap-2">
                  @if(!empty($leg['segments'][0]['airline_logo']))
                    <img src="{{ $leg['segments'][0]['airline_logo'] }}" alt="" width="32" height="32" style="object-fit:contain">
                  @endif
                  <div>
                    <strong class="d-block">{{ collect($leg['segments'])->pluck('airline_name')->filter()->unique()->implode(', ') }}</strong>
                    <small class="text-muted">{{ collect($leg['segments'])->pluck('flight_number')->filter()->implode(' · ') }}</small>
                  </div>
                </div>
                <div class="col-md-3 col-4">
                  <strong class="fs-5">{{ $time($leg['departure']) }}</strong>
                  <small class="d-block text-muted">{{ $leg['origin_code'] }} · {{ $day($leg['departure']) }}</small>
                </div>
                <div class="col-md-3 col-4 text-center">
                  <small class="text-muted d-block">{{ $duration($leg['duration_minutes']) }}</small>
                  <small class="@if($leg['stops'] === 0) text-success @else text-muted @endif">
                    @if($leg['stops'] === 0)
                      Direct
                    @else
                      {{ $leg['stops'] }} {{ Str::plural('stop', $leg['stops']) }}@if(count($leg['via']) > 0) via {{ implode(', ', $leg['via']) }}@endif
                    @endif
                  </small>
                </div>
                <div class="col-md-3 col-4 text-md-start text-end">
                  <strong class="fs-5">{{ $time($leg['arrival']) }}</strong>@if($leg['day_change'] > 0)<sup class="text-danger">+{{ $leg['day_change'] }}</sup>@endif
                  <small class="d-block text-muted">{{ $leg['destination_code'] }} · {{ $day($leg['arrival']) }}</small>
                </div>
              </div>
            @endforeach

            <div class="d-flex flex-wrap gap-2 mt-3">
              @if(!empty($journey['cabin']))
                <span class="amenity">{{ $journey['cabin'] }}</span>
              @endif
              <span class="amenity">{{ $journey['checked_bag'] ? 'Checked bag included' : 'Cabin bag only' }}</span>
              <span class="amenity">{{ $journey['refundable'] ? 'Refundable' : 'Non-refundable' }}</span>
              @if(is_numeric($journey['seats_remaining']) && (int) $journey['seats_remaining'] <= 4)
                <span class="amenity">{{ $journey['seats_remaining'] }} {{ Str::plural('seat', (int) $journey['seats_remaining']) }} left at this price</span>
              @endif
            </div>
          </div>

          <div class="col-lg-3 p-3 text-lg-end border-start">
            <strong class="fs-4 d-block">{{ $journey['currency'] }} {{ number_format((float) $journey['price'], 2) }}</strong>
            <small class="text-muted d-block">
              total for {{ $criteria['adults'] + $criteria['children'] + $criteria['infants'] }}
              {{ Str::plural('traveller', $criteria['adults'] + $criteria['children'] + $criteria['infants']) }}
            </small>
            @if($journey['price_per_adult'] && ($criteria['adults'] + $criteria['children'] + $criteria['infants']) > 1)
              <small class="text-muted d-block">{{ $journey['currency'] }} {{ number_format((float) $journey['price_per_adult'], 2) }} per adult</small>
            @endif
            <small class="text-muted d-block mt-2">Total trip {{ $duration($journey['duration_minutes']) }}</small>

            @auth
              <form method="POST" action="{{ route('flights.select') }}" class="mt-3">
                @csrf
                <input type="hidden" name="journey_key" value="{{ $journey['key'] }}">
                @foreach(['origin', 'origin_label', 'destination', 'destination_label', 'depart_date', 'return_date', 'adults', 'children', 'infants', 'cabin_class', 'sort'] as $field)
                  @if($criteria[$field] !== null && $criteria[$field] !== '')
                    <input type="hidden" name="{{ $field }}" value="{{ $criteria[$field] }}">
                  @endif
                @endforeach
                @if($criteria['direct'])
                  <input type="hidden" name="direct" value="1">
                @endif
                <button class="btn btn-primary w-100" type="submit">Select</button>
              </form>
            @else
              <a class="btn btn-outline-primary w-100 mt-3" href="{{ route('login') }}">Log in to book</a>
            @endauth
          </div>

        </div>
      </div>
    @empty
      <div class="empty-state">
        No flights were found for this route and these dates. Try different dates, a nearby airport, or untick "Direct only".
      </div>
    @endforelse
  @endif

</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Airport autocomplete for one text box, hidden code field and list.
    function airportSearch(inputId, codeId, boxId) {
      var input = document.getElementById(inputId);
      var code = document.getElementById(codeId);
      var box = document.getElementById(boxId);
      var timer;

      if (!input || !code || !box) { return; }

      input.addEventListener('input', function () {
        clearTimeout(timer);
        code.value = '';
        var query = input.value.trim();

        if (query.length < 2) { box.innerHTML = ''; return; }

        timer = setTimeout(function () {
          fetch('{{ route('flights.airports') }}?q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          })
            .then(function (response) { return response.ok ? response.json() : []; })
            .then(function (airports) {
              box.innerHTML = '';
              airports.forEach(function (airport) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'suggestion-item';
                button.textContent = airport.label;
                button.addEventListener('click', function () {
                  input.value = airport.label;
                  code.value = airport.iata;
                  box.innerHTML = '';
                });
                box.appendChild(button);
              });
            })
            .catch(function () { /* Typing an airport by hand still works. */ });
        }, 250);
      });

      document.addEventListener('click', function (event) {
        if (!box.contains(event.target) && event.target !== input) { box.innerHTML = ''; }
      });
    }

    airportSearch('origin-input', 'origin-code', 'origin-suggestions');
    airportSearch('destination-airport-input', 'destination-code', 'destination-airport-suggestions');

    // The return date only applies to return trips.
    var returnDate = document.getElementById('return-date');
    var departDate = document.getElementById('depart-date');

    function syncTrip() {
      var oneWay = document.getElementById('trip-one-way').checked;
      returnDate.disabled = oneWay;
      returnDate.required = !oneWay;
    }

    document.getElementById('trip-return').addEventListener('change', syncTrip);
    document.getElementById('trip-one-way').addEventListener('change', syncTrip);
    syncTrip();

    departDate.addEventListener('change', function () {
      returnDate.min = departDate.value;
      if (returnDate.value && returnDate.value < departDate.value) { returnDate.value = departDate.value; }
    });
  });
</script>
@endpush
