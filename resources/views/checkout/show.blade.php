@extends('layouts.app')
@section('title', 'Complete your booking')
@section('content')
@php
  $hotel = $checkout['hotel'];
  $stay = $checkout['stay'];
  $offer = $checkout['offer'];
  $user = auth()->user();
  $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);
@endphp
<div class="container py-5">

  <a href="{{ route('stays.show', ['hotelId' => $hotel['id'], 'check_in' => $stay['check_in'], 'check_out' => $stay['check_out'], 'adults' => $stay['adults'], 'rooms' => $stay['rooms']]) }}" class="text-decoration-none">← Back to hotel</a>

  <h1 class="h2 mt-3 mb-4">Complete your booking</h1>

  @if($sandbox)
    <div class="alert alert-warning">
      <strong>Test mode.</strong> No payment is taken and no real room is reserved. This booking is simulated.
    </div>
  @endif

  @if(abs($offer['price_change_percent']) > 0)
    <div class="alert alert-info">
      <strong>The price has changed since your search.</strong>
      The total below is the current price for this room.
    </div>
  @endif

  @if($offer['cancellation_changed'] || $offer['board_changed'])
    <div class="alert alert-info">
      <strong>Some details have changed since your search.</strong>
      Please check the {{ $offer['board_changed'] ? 'meal plan' : '' }}{{ $offer['board_changed'] && $offer['cancellation_changed'] ? ' and ' : '' }}{{ $offer['cancellation_changed'] ? 'cancellation terms' : '' }} below before you confirm.
    </div>
  @endif

  <div class="row g-4">

    {{-- Guest details --}}
    <div class="col-lg-7">
      <div class="form-card">
        <h4>Guest details</h4>
        <p class="text-muted small">The name must match the ID of the guest checking in.</p>

        <form method="POST" action="{{ route('checkout.confirm', $token) }}">
          @csrf

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="first_name">First name</label>
              <input class="form-control" id="first_name" name="first_name" type="text" maxlength="60" autocomplete="given-name" value="{{ old('first_name', $nameParts[0] ?? '') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="last_name">Last name</label>
              <input class="form-control" id="last_name" name="last_name" type="text" maxlength="60" autocomplete="family-name" value="{{ old('last_name', $nameParts[1] ?? '') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input class="form-control" id="email" name="email" type="email" maxlength="190" autocomplete="email" value="{{ old('email', $user->email) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="phone">Phone</label>
              <input class="form-control" id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" placeholder="+44 7000 000000" value="{{ old('phone', $user->phone) }}" required>
            </div>
          </div>

          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" value="1" @checked(old('accept_terms')) required>
            <label class="form-check-label" for="accept_terms">
              I have checked the dates, room, price and cancellation terms, and I accept the booking conditions.
            </label>
          </div>

          <button class="btn btn-primary btn-lg w-100 mt-4" type="submit">
            {{ $sandbox ? 'Confirm test booking' : 'Confirm booking' }}
          </button>

          <p class="text-muted small mt-3 mb-0">
            This price is held for about {{ $minutesLeft }} more {{ Str::plural('minute', $minutesLeft) }}.
          </p>
        </form>
      </div>

      @if(!empty($offer['terms']))
        <details class="mt-4">
          <summary>Booking conditions</summary>
          <p class="small text-muted mt-2">{{ $offer['terms'] }}</p>
        </details>
      @endif
    </div>

    {{-- Stay summary --}}
    <div class="col-lg-5">
      <div class="booking-box">
        @if(!empty($hotel['image']))
          <img src="{{ $hotel['image'] }}" alt="{{ $hotel['name'] }}" class="w-100 mb-3" style="height:180px;object-fit:cover;border-radius:12px">
        @endif

        <h5 class="mb-1">{{ $hotel['name'] }}</h5>
        <p class="text-muted small">{{ $hotel['address'] }}</p>

        <hr>

        <div class="d-flex justify-content-between">
          <span class="text-muted">Check-in</span>
          <strong>{{ \Illuminate\Support\Carbon::parse($stay['check_in'])->format('D d M Y') }}</strong>
        </div>
        <div class="d-flex justify-content-between mt-2">
          <span class="text-muted">Check-out</span>
          <strong>{{ \Illuminate\Support\Carbon::parse($stay['check_out'])->format('D d M Y') }}</strong>
        </div>
        <div class="d-flex justify-content-between mt-2">
          <span class="text-muted">Stay</span>
          <span>
            {{ $nights }} {{ Str::plural('night', $nights) }},
            {{ $stay['adults'] }} {{ Str::plural('adult', $stay['adults']) }},
            {{ $stay['rooms'] }} {{ Str::plural('room', $stay['rooms']) }}
          </span>
        </div>

        <hr>

        <strong>{{ $offer['room_name'] ?: 'Room' }}</strong>
        <div class="text-muted small">{{ $offer['board'] ?: 'Room only' }}</div>

        <div class="mt-2">
          @if($offer['refundable'])
            <span class="badge bg-success">Free cancellation</span>
            @if(!empty($offer['cancel_by']))
              <small class="text-muted d-block mt-1">until {{ rescue(fn () => \Illuminate\Support\Carbon::parse($offer['cancel_by'])->format('d M Y, H:i'), $offer['cancel_by'], false) }}</small>
            @endif
          @else
            <span class="badge bg-secondary">Non-refundable</span>
            <small class="text-muted d-block mt-1">This booking cannot be cancelled or changed for a refund.</small>
          @endif
        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-end">
          <span>Total</span>
          <strong class="fs-3">{{ $offer['currency'] }} {{ number_format((float) $offer['price'], 2) }}</strong>
        </div>

        @foreach($offer['pay_at_hotel'] as $fee)
          <div class="d-flex justify-content-between small text-muted mt-2">
            <span>{{ $fee['description'] ?: 'Fee' }} (paid at the hotel)</span>
            <span>{{ $fee['currency'] }} {{ number_format((float) $fee['amount'], 2) }}</span>
          </div>
        @endforeach
      </div>
    </div>

  </div>
</div>
@endsection
