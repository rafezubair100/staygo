@extends('layouts.app')
@section('title', 'Flight booking')
@section('content')
<div class="container py-5" style="max-width:860px">

  <div class="text-center mb-4">
    <div class="display-4 {{ $booking->isCancelled() ? 'text-danger' : 'text-success' }}">{{ $booking->isCancelled() ? '✕' : '✓' }}</div>
    <h1 class="h2">
      @if($booking->isCancelled())
        This flight booking is cancelled
      @elseif($booking->isConfirmed())
        Your flight is confirmed
      @else
        Your flight is booked
      @endif
    </h1>
    <p class="text-muted mb-0">A summary is below. You can find it again under My bookings.</p>
  </div>

  @if($booking->is_sandbox)
    <div class="alert alert-warning">
      <strong>Test booking.</strong> This reservation is simulated. No payment was taken and no real seat is reserved.
    </div>
  @endif

  @if(!$booking->isConfirmed() && !$booking->isCancelled())
    <div class="alert alert-info">
      <strong>Awaiting airline confirmation.</strong>
      The airline issues tickets after a booking is placed, which can take a little time.
    </div>
  @endif

  <div class="form-card">
    <div class="row g-3">
      <div class="col-sm-4">
        <small class="text-muted d-block">Booking reference</small>
        <strong class="fs-5"><code>{{ $booking->booking_ref ?: '—' }}</code></strong>
      </div>
      <div class="col-sm-4">
        <small class="text-muted d-block">Airline booking code (PNR)</small>
        <strong class="fs-5"><code>{{ $booking->pnr ?: 'Pending' }}</code></strong>
      </div>
      <div class="col-sm-4 text-sm-end">
        <small class="text-muted d-block">Status</small>
        <span class="badge fs-6 {{ $booking->isConfirmed() ? 'bg-success' : ($booking->isCancelled() ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $booking->statusLabel() }}</span>
      </div>
    </div>

    <hr>

    <h5 class="mb-1">{{ $booking->origin }} → {{ $booking->destination }}</h5>
    <p class="text-muted">
      {{ $booking->trip === 'return' ? 'Return' : 'One way' }}
      · {{ collect([$booking->airlines, $booking->cabin])->filter()->implode(' · ') }}
    </p>

    @include('flights.partials.itinerary', ['legs' => $booking->itinerary ?? []])

    <hr>

    <div class="row g-3">
      <div class="col-sm-6">
        <small class="text-muted d-block">Travellers</small>
        @foreach(($booking->passengers ?? []) as $passenger)
          <div>{{ $passenger['name'] ?? '' }} <small class="text-muted">{{ $passenger['type'] ?? '' }}</small></div>
        @endforeach
      </div>
      <div class="col-sm-6">
        <small class="text-muted d-block">Contact</small>
        {{ $booking->contact_name }}
        <small class="text-muted d-block">{{ $booking->contact_email }}</small>
        <small class="text-muted d-block">{{ $booking->contact_phone }}</small>
      </div>
      <div class="col-sm-6">
        <small class="text-muted d-block">Fare</small>
        {{ $booking->refundable ? 'Refundable' : 'Non-refundable' }}
      </div>
      <div class="col-sm-6">
        <small class="text-muted d-block">Total</small>
        <strong class="fs-5">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</strong>
        <small class="text-muted d-block">
          {{ $booking->is_sandbox ? 'Not charged (test mode)' : ucfirst(str_replace('_', ' ', (string) $booking->payment_status)) }}
        </small>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-center gap-3 mt-4">
    <a href="{{ route('bookings.index') }}" class="btn btn-primary">My bookings</a>
    <a href="{{ route('flights.index') }}" class="btn btn-outline-primary">Search more flights</a>
  </div>

</div>
@endsection
