@extends('layouts.app')
@section('title', 'Booking confirmed')
@section('content')
<div class="container py-5" style="max-width:820px">

  <div class="text-center mb-4">
    <div class="display-4 text-success">✓</div>
    <h1 class="h2">
      {{ $booking->status === 'confirmed' ? 'Your booking is confirmed' : 'Your booking has been received' }}
    </h1>
    <p class="text-muted mb-0">A summary is below. You can find it again under My bookings.</p>
  </div>

  @if($booking->is_sandbox)
    <div class="alert alert-warning">
      <strong>Test booking.</strong> This reservation is simulated. No payment was taken and no real room is reserved.
    </div>
  @endif

  <div class="form-card">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <div>
        <small class="text-muted d-block">Booking reference</small>
        <strong class="fs-4"><code>{{ $booking->supplier_reference ?: '—' }}</code></strong>
      </div>
      <div class="text-end">
        @if($booking->status === 'confirmed')
          <span class="badge bg-success fs-6">Confirmed</span>
        @else
          <span class="badge bg-warning text-dark fs-6">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
        @endif
      </div>
    </div>

    <hr>

    <h5 class="mb-1">{{ $booking->hotelDisplayName() }}</h5>
    <p class="text-muted mb-0">
      {{ collect([$booking->room_name, $booking->board])->filter()->implode(' · ') }}
    </p>

    <hr>

    <div class="row g-3">
      <div class="col-sm-6">
        <small class="text-muted d-block">Check-in</small>
        <strong>{{ $booking->check_in->format('D d M Y') }}</strong>
      </div>
      <div class="col-sm-6">
        <small class="text-muted d-block">Check-out</small>
        <strong>{{ $booking->check_out->format('D d M Y') }}</strong>
      </div>
      <div class="col-sm-6">
        <small class="text-muted d-block">Stay</small>
        {{ $nights }} {{ Str::plural('night', $nights) }},
        {{ $booking->adults }} {{ Str::plural('adult', (int) $booking->adults) }},
        {{ $booking->rooms }} {{ Str::plural('room', (int) $booking->rooms) }}
      </div>
      <div class="col-sm-6">
        <small class="text-muted d-block">Cancellation</small>
        {{ $booking->refundable ? 'Free cancellation available' : 'Non-refundable' }}
      </div>
      <div class="col-sm-6">
        <small class="text-muted d-block">Lead guest</small>
        {{ $booking->guest_name }}
        <small class="text-muted d-block">{{ $booking->guest_email }}</small>
      </div>
      <div class="col-sm-6">
        <small class="text-muted d-block">Total</small>
        <strong class="fs-5">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</strong>
        <small class="text-muted d-block">
          {{ $booking->is_sandbox ? 'Not charged (test mode)' : ucfirst(str_replace('_', ' ', $booking->payment_status)) }}
        </small>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-center gap-3 mt-4">
    <a href="{{ route('bookings.index') }}" class="btn btn-primary">My bookings</a>
    <a href="{{ route('home') }}" class="btn btn-outline-primary">Search more stays</a>
  </div>

</div>
@endsection
