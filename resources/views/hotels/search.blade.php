@extends('layouts.app')
@section('title', 'Hotel search results')
@section('content')
<div class="container py-5">

  {{-- Heading --}}
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
    <div>
      <span class="eyebrow text-primary">SEARCH RESULTS</span>
      <h1 class="h2 mb-1">Stays in {{ $criteria['destination'] }}</h1>
      <p class="text-muted mb-0">
        {{ \Illuminate\Support\Carbon::parse($criteria['check_in'])->format('d M Y') }}
        – {{ \Illuminate\Support\Carbon::parse($criteria['check_out'])->format('d M Y') }}
        · {{ $criteria['adults'] }} {{ Str::plural('adult', (int) $criteria['adults']) }}
        · {{ $criteria['rooms'] }} {{ Str::plural('room', (int) $criteria['rooms']) }}
      </p>
    </div>
    <a href="{{ route('destinations.show', Str::slug($criteria['destination'])) }}" class="btn btn-outline-primary">Destination guide</a>
  </div>

  {{-- Status message --}}
  @if(count($liveHotels) > 0)
    <div class="alert alert-success">
      <strong>{{ count($liveHotels) }} {{ Str::plural('hotel', count($liveHotels)) }} with live prices.</strong>
      Prices are for the whole stay and can change until the booking is confirmed.
    </div>
  @elseif(!$supplierConfigured)
    <div class="alert alert-warning">
      <strong>Live hotel search is not connected.</strong>
      Add a LITEAPI_API_KEY to the .env file to show live hotels and prices.
    </div>
  @else
    <div class="alert alert-info">
      No live offers were found for this destination and these dates. Try different dates or a nearby city.
    </div>
  @endif

  <div class="row g-4">

    {{-- Filters --}}
    <aside class="col-lg-3">
      <div class="filter-box">
        <h5>Filter results</h5>
        <form method="GET" action="{{ route('hotels.search') }}">
          @foreach($criteria as $key => $value)
            @if(!in_array($key, ['min_price', 'max_price', 'stars', 'sort']) && $value !== null && $value !== '')
              <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
          @endforeach

          <label class="form-label mt-3">Minimum price per night (GBP)</label>
          <input class="form-control" name="min_price" type="number" min="0" value="{{ request('min_price') }}">

          <label class="form-label mt-3">Maximum price per night (GBP)</label>
          <input class="form-control" name="max_price" type="number" min="0" value="{{ request('max_price') }}">

          <label class="form-label mt-3">Star rating</label>
          <select class="form-select" name="stars">
            <option value="">Any</option>
            @for($i = 1; $i <= 5; $i++)
              <option value="{{ $i }}" @selected(request('stars') == $i)>{{ $i }} {{ Str::plural('star', $i) }}</option>
            @endfor
          </select>

          <label class="form-label mt-3">Sort</label>
          <select class="form-select" name="sort">
            <option value="recommended" @selected(request('sort', 'recommended') === 'recommended')>Recommended</option>
            <option value="price_asc" @selected(request('sort') === 'price_asc')>Lowest price</option>
            <option value="price_desc" @selected(request('sort') === 'price_desc')>Highest price</option>
            <option value="rating" @selected(request('sort') === 'rating')>Guest rating</option>
          </select>

          <button class="btn btn-primary w-100 mt-3">Apply filters</button>
        </form>
      </div>
    </aside>

    <div class="col-lg-9">

      {{-- Live hotels --}}
      @if(count($liveHotels) > 0)
        <div class="row g-3 mb-5">
          @foreach($liveHotels as $hotel)
            <div class="col-12">
              <div class="hotel-card">
                <div class="row g-0">
                  <div class="col-md-4">
                    @if(!empty($hotel['image']))
                      <img src="{{ $hotel['image'] }}" alt="{{ $hotel['name'] }}" loading="lazy" class="w-100 h-100" style="object-fit:cover;min-height:200px;max-height:260px">
                    @else
                      <div class="hotel-placeholder h-100">✦</div>
                    @endif
                  </div>
                  <div class="col-md-8">
                    <div class="p-3 h-100 d-flex flex-column">
                      <div class="d-flex justify-content-between gap-3">
                        <div>
                          @if((int) round($hotel['stars'] ?? 0) > 0)
                            <span class="stars">{{ str_repeat('★', (int) round($hotel['stars'])) }}</span>
                          @endif
                          <h5 class="mb-1">{{ $hotel['name'] }}</h5>
                          <p class="text-muted mb-2">
                            {{ collect([$hotel['address'] ?? null, $hotel['city'] ?? null])->filter()->implode(', ') }}
                          </p>
                        </div>
                        @if(!empty($hotel['rating']))
                          <div class="text-end flex-shrink-0">
                            <span class="badge bg-primary fs-6">{{ number_format((float) $hotel['rating'], 1) }}</span>
                            @if(!empty($hotel['review_count']))
                              <small class="d-block text-muted mt-1">{{ number_format((int) $hotel['review_count']) }} reviews</small>
                            @endif
                          </div>
                        @endif
                      </div>

                      <p class="mb-2 small">
                        {{ collect([$hotel['room_name'] ?? null, $hotel['board'] ?? null])->filter()->implode(' · ') }}
                      </p>

                      <div>
                        @if($hotel['refundable'] ?? false)
                          <span class="badge bg-success">Free cancellation available</span>
                        @else
                          <span class="badge bg-secondary">Non-refundable</span>
                        @endif
                      </div>

                      <div class="d-flex justify-content-between align-items-end mt-auto pt-3">
                        <small class="text-muted">Live price</small>
                        <div class="text-end">
                          <strong class="fs-4">{{ $hotel['currency'] }} {{ number_format((float) $hotel['price'], 2) }}</strong>
                          <small class="d-block text-muted">
                            total for {{ $hotel['nights'] }} {{ Str::plural('night', (int) $hotel['nights']) }}
                            @if(!empty($hotel['price_per_night']))
                              · {{ $hotel['currency'] }} {{ number_format((float) $hotel['price_per_night'], 2) }} per night
                            @endif
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif

      {{-- Local sample records --}}
      @if($hotels->total() > 0)
        <div class="d-flex justify-content-between mb-3">
          <h4>Sample listings</h4>
          <small class="text-muted">{{ $hotels->total() }} development {{ Str::plural('record', $hotels->total()) }}</small>
        </div>
        <div class="row g-3">
          @foreach($hotels as $hotel)
            <div class="col-12">
              <div class="hotel-card horizontal">
                <div class="hotel-placeholder">✦</div>
                <div class="p-3 flex-grow-1">
                  <div class="d-flex justify-content-between">
                    <div>
                      <span class="stars">{{ str_repeat('★', (int) $hotel->stars) }}</span>
                      <h5 class="mb-1">{{ $hotel->name }}</h5>
                      <p class="text-muted mb-2">{{ $hotel->city }}, {{ $hotel->country }}</p>
                    </div>
                    <span class="badge bg-secondary align-self-start">Development record</span>
                  </div>
                  <p>{{ Str::limit($hotel->description, 130) }}</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Not a live supplier offer</small>
                    <a class="btn btn-outline-primary" href="{{ route('hotels.show', $hotel) }}">View hotel</a>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
        {{ $hotels->links() }}
      @elseif(count($liveHotels) === 0)
        <div class="empty-state">No hotels match this search. Try another destination, different dates or fewer filters.</div>
      @endif

    </div>
  </div>
</div>
@endsection