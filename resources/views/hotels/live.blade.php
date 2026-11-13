@extends('layouts.app')
@section('title', $hotel['name'])
@section('content')
@php
  $stars = (int) round($hotel['stars'] ?? 0);
  $gallery = array_slice($hotel['images'], 0, 15);
  $location = collect([$hotel['address'] ?? null, $hotel['city'] ?? null, $hotel['country'] ?? null])->filter()->implode(', ');
@endphp
<div class="container py-5">

  <a href="{{ url()->previous() }}" class="text-decoration-none">← Back to results</a>

  {{-- Heading --}}
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mt-3 mb-4">
    <div>
      @if($stars > 0)
        <span class="stars">{{ str_repeat('★', $stars) }}</span>
      @endif
      <h1 class="h2 mb-1">{{ $hotel['name'] }}</h1>
      <p class="text-muted mb-0">{{ $location }}</p>
    </div>
    @if(!empty($hotel['rating']))
      <div class="text-end">
        <span class="badge bg-primary fs-5">{{ number_format((float) $hotel['rating'], 1) }}</span>
        @if(!empty($hotel['review_count']))
          <small class="d-block text-muted mt-1">{{ number_format((int) $hotel['review_count']) }} reviews</small>
        @endif
      </div>
    @endif
  </div>

  {{-- Photo gallery --}}
  @if(count($gallery) > 0)
    <div id="hotelGallery" class="carousel slide mb-4" style="border-radius:18px;overflow:hidden">
      <div class="carousel-inner">
        @foreach($gallery as $index => $image)
          <div class="carousel-item @if($index === 0) active @endif">
            <img src="{{ $image['url'] }}" alt="{{ $image['caption'] ?: $hotel['name'] }}" class="d-block w-100" @if($index > 0) loading="lazy" @endif style="height:460px;object-fit:cover">
          </div>
        @endforeach
      </div>
      @if(count($gallery) > 1)
        <button class="carousel-control-prev" type="button" data-bs-target="#hotelGallery" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Previous photo</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#hotelGallery" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Next photo</span>
        </button>
      @endif
    </div>
  @elseif(!empty($hotel['main_photo']))
    <img src="{{ $hotel['main_photo'] }}" alt="{{ $hotel['name'] }}" class="w-100 mb-4" style="height:460px;object-fit:cover;border-radius:18px">
  @endif

  <div class="row g-4">

    {{-- Main column --}}
    <div class="col-lg-8">

      @if(!empty($hotel['description']))
        <h3>About this hotel</h3>
        {{-- The description is cleaned to basic formatting tags in LiteApiService. --}}
        <div class="mb-4">{!! $hotel['description'] !!}</div>
      @endif

      @if(count($hotel['facilities']) > 0)
        <h3>Facilities</h3>
        <div class="d-flex flex-wrap gap-2 mb-4">
          @foreach(array_slice($hotel['facilities'], 0, 24) as $facility)
            <span class="amenity">{{ $facility }}</span>
          @endforeach
        </div>
      @endif

      {{-- Rooms --}}
      <h3 id="rooms">Choose your room</h3>
      <p class="text-muted">
        {{ \Illuminate\Support\Carbon::parse($criteria['check_in'])->format('d M Y') }}
        – {{ \Illuminate\Support\Carbon::parse($criteria['check_out'])->format('d M Y') }}
        · {{ $nights }} {{ Str::plural('night', $nights) }}
        · {{ $criteria['adults'] }} {{ Str::plural('adult', $criteria['adults']) }}
        · {{ $criteria['rooms'] }} {{ Str::plural('room', $criteria['rooms']) }}
      </p>

      @forelse($rooms as $room)
        <div class="hotel-card mb-3">
          <div class="row g-0">
            <div class="col-md-4">
              @if(!empty($room['photos'][0]))
                <img src="{{ $room['photos'][0] }}" alt="{{ $room['name'] }}" loading="lazy" class="w-100 h-100" style="object-fit:cover;min-height:200px;max-height:280px">
              @else
                <div class="hotel-placeholder h-100">✦</div>
              @endif
            </div>
            <div class="col-md-8">
              <div class="p-3">
                <h5 class="mb-1">{{ $room['name'] }}</h5>
                <p class="text-muted small mb-2">
                  {{ collect([
                      !empty($room['size']) ? $room['size'].' m²' : null,
                      !empty($room['max_occupancy']) ? 'Sleeps '.$room['max_occupancy'] : null,
                      count($room['beds']) > 0 ? implode(' or ', $room['beds']) : null,
                  ])->filter()->implode(' · ') }}
                </p>
                @if(count($room['amenities']) > 0)
                  <p class="small mb-0">{{ implode(' · ', $room['amenities']) }}</p>
                @endif
              </div>
            </div>
          </div>

          <div class="border-top">
            @foreach($room['offers'] as $offer)
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 @if(!$loop->last) border-bottom @endif">
                <div>
                  <strong>{{ $offer['board'] ?: 'Room only' }}</strong>
                  <div class="mt-1">
                    @if($offer['refundable'])
                      <span class="badge bg-success">Free cancellation</span>
                      @if(!empty($offer['cancel_by']))
                        <small class="text-muted ms-1">until {{ rescue(fn () => \Illuminate\Support\Carbon::parse($offer['cancel_by'])->format('d M Y, H:i'), $offer['cancel_by'], false) }}</small>
                      @endif
                    @else
                      <span class="badge bg-secondary">Non-refundable</span>
                    @endif
                  </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <div class="text-end">
                    <strong class="fs-5">{{ $offer['currency'] }} {{ number_format((float) $offer['price'], 2) }}</strong>
                    <small class="d-block text-muted">{{ $offer['currency'] }} {{ number_format((float) $offer['price_per_night'], 2) }} per night</small>
                  </div>
                  <button class="btn btn-primary" type="button" disabled>Booking coming soon</button>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @empty
        <div class="empty-state">No rooms are available for these dates. Try different dates or fewer guests.</div>
      @endforelse

      @if(!empty($hotel['important_information']))
        <h3 class="mt-5">Important information</h3>
        <p>{{ $hotel['important_information'] }}</p>
      @endif

      @foreach($hotel['policies'] as $policy)
        <h5 class="mt-4">{{ $policy['name'] }}</h5>
        <p>{{ $policy['description'] }}</p>
      @endforeach

      @if(is_numeric($hotel['latitude']) && is_numeric($hotel['longitude']))
        <h3 class="mt-5">Location</h3>
        <p class="text-muted">{{ $location }}</p>
        <div class="map-frame">
          <iframe width="100%" height="320" frameborder="0" scrolling="no" title="Map showing {{ $hotel['name'] }}" src="https://www.openstreetmap.org/export/embed.html?bbox={{ $hotel['longitude'] - 0.01 }}%2C{{ $hotel['latitude'] - 0.01 }}%2C{{ $hotel['longitude'] + 0.01 }}%2C{{ $hotel['latitude'] + 0.01 }}&layer=mapnik&marker={{ $hotel['latitude'] }}%2C{{ $hotel['longitude'] }}"></iframe>
        </div>
      @endif

    </div>

    {{-- Side column: change dates --}}
    <div class="col-lg-4">
      <div class="booking-box sticky-lg-top">
        <h4>Your stay</h4>
        <form method="GET" action="{{ route('stays.show', $hotel['id']) }}">
          <label class="form-label mt-2">Check-in</label>
          <input class="form-control" type="date" name="check_in" min="{{ date('Y-m-d') }}" value="{{ $criteria['check_in'] }}" required>

          <label class="form-label mt-3">Check-out</label>
          <input class="form-control" type="date" name="check_out" min="{{ date('Y-m-d', strtotime('+1 day')) }}" value="{{ $criteria['check_out'] }}" required>

          <div class="row">
            <div class="col">
              <label class="form-label mt-3">Adults</label>
              <input class="form-control" type="number" name="adults" min="1" max="12" value="{{ $criteria['adults'] }}" required>
            </div>
            <div class="col">
              <label class="form-label mt-3">Rooms</label>
              <input class="form-control" type="number" name="rooms" min="1" max="8" value="{{ $criteria['rooms'] }}" required>
            </div>
          </div>

          <button class="btn btn-primary w-100 mt-3">Update prices</button>
        </form>

        @if(!empty($hotel['checkin_from']) || !empty($hotel['checkout_until']))
          <hr>
          <p class="small text-muted mb-0">
            @if(!empty($hotel['checkin_from'])) Check-in from {{ $hotel['checkin_from'] }}<br>@endif
            @if(!empty($hotel['checkout_until'])) Check-out until {{ $hotel['checkout_until'] }}@endif
          </p>
        @endif

        @if(count($rooms) > 0)
          <hr>
          <p class="mb-0">
            <small class="text-muted d-block">Rooms from</small>
            <strong class="fs-4">{{ $rooms[0]['offers'][0]['currency'] }} {{ number_format((float) $rooms[0]['from_price'], 2) }}</strong>
            <small class="text-muted">for {{ $nights }} {{ Str::plural('night', $nights) }}</small>
          </p>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection