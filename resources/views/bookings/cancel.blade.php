@extends('layouts.app')
@section('title', 'Cancel booking')
@section('content')
<div class="container py-5" style="max-width:760px">

  <a href="{{ route('bookings.index') }}" class="text-decoration-none">← Back to my bookings</a>

  <h1 class="h2 mt-3 mb-4">Cancel this booking?</h1>

  @if($booking->refundable)
    <div class="alert alert-info">
      <strong>This booking has free cancellation.</strong>
      The exact refund is confirmed by the supplier when you cancel, and depends on the cancellation deadline.
    </div>
  @else
    <div class="alert alert-danger">
      <strong>This booking is non-refundable.</strong>
      If you cancel, the room is released and you will not get your money back.
    </div>
  @endif

  @if($booking->is_sandbox)
    <div class="alert alert-warning">
      <strong>Test booking.</strong> This reservation is simulated, so no money is involved either way.
    </div>
  @endif

  <div class="form-card">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <div>
        <h5 class="mb-1">{{ $booking->hotelDisplayName() }}</h5>
        <p class="text-muted mb-0">
          {{ collect([$booking->room_name, $booking->board])->filter()->implode(' · ') }}
        </p>
      </div>
      <div class="text-end">
        <small class="text-muted d-block">Booking reference</small>
        <code>{{ $booking->supplier_reference }}</code>
      </div>
    </div>

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
        <small class="text-muted d-block">Total</small>
        <strong>{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</strong>
      </div>
    </div>

    <hr>

    <form method="POST" action="{{ route('bookings.cancel', $booking) }}">
      @csrf

      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="confirm_cancel" name="confirm_cancel" value="1" required>
        <label class="form-check-label" for="confirm_cancel">
          I understand that cancelling cannot be undone{{ $booking->refundable ? '' : ' and that this booking is non-refundable' }}.
        </label>
      </div>

      <div class="d-flex flex-wrap gap-3 mt-4">
        <button class="btn btn-danger" type="submit">Cancel this booking</button>
        <a class="btn btn-outline-primary" href="{{ route('bookings.index') }}">Keep my booking</a>
      </div>
    </form>
  </div>

</div>
@endsection
