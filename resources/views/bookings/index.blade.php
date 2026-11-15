@extends('layouts.app')
@section('title', 'My bookings')
@section('content')
<div class="container py-5">
  <h1>My bookings</h1>

  {{-- Flights --}}
  @if(isset($flightBookings) && $flightBookings->count() > 0)
    <h2 class="h4 mt-4">Flights</h2>
    <div class="table-responsive mb-5">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Route</th>
            <th>Departs</th>
            <th>Travellers</th>
            <th>Total</th>
            <th>Status</th>
            <th>Reference</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($flightBookings as $flight)
            <tr>
              <td>
                <strong>{{ $flight->origin }} → {{ $flight->destination }}</strong>
                <small class="d-block text-muted">
                  {{ collect([$flight->trip === 'return' ? 'Return' : 'One way', $flight->airlines])->filter()->implode(' · ') }}
                </small>
              </td>
              <td>
                {{ $flight->depart_at->format('d M Y, H:i') }}
                @if($flight->return_at)
                  <small class="d-block text-muted">returns {{ $flight->return_at->format('d M Y, H:i') }}</small>
                @endif
              </td>
              <td>{{ $flight->travellerCount() }}</td>
              <td>{{ $flight->currency }} {{ number_format((float) $flight->total_amount, 2) }}</td>
              <td>
                <span class="badge {{ $flight->isConfirmed() ? 'bg-success' : ($flight->isCancelled() ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $flight->statusLabel() }}</span>
                @if($flight->is_sandbox)
                  <span class="badge bg-secondary">Test booking</span>
                @endif
              </td>
              <td>
                <code>{{ $flight->booking_ref ?: '—' }}</code>
                @if($flight->pnr)
                  <small class="d-block text-muted">PNR {{ $flight->pnr }}</small>
                @endif
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('flights.confirmation', $flight) }}">View</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <h2 class="h4">Hotels</h2>
  @endif

  @if($bookings->contains(fn ($booking) => !in_array($booking->status, ['confirmed', 'cancelled'])))
    <p class="text-muted">A request is not a confirmed reservation until the supplier confirms it.</p>
  @endif

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Hotel</th>
          <th>Dates</th>
          <th>Guests</th>
          <th>Total</th>
          <th>Status</th>
          <th>Reference</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($bookings as $booking)
          <tr>
            <td>
              <strong>{{ $booking->hotelDisplayName() }}</strong>
              @if($booking->room_name || $booking->board)
                <small class="d-block text-muted">
                  {{ collect([$booking->room_name, $booking->board])->filter()->implode(' · ') }}
                </small>
              @endif
            </td>
            <td>
              {{ $booking->check_in->format('d M Y') }} – {{ $booking->check_out->format('d M Y') }}
            </td>
            <td>
              {{ $booking->adults }} {{ Str::plural('adult', (int) $booking->adults) }},
              {{ $booking->rooms }} {{ Str::plural('room', (int) $booking->rooms) }}
            </td>
            <td>
              @if((float) $booking->total_amount > 0)
                {{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td>
              @if($booking->status === 'confirmed')
                <span class="badge bg-success">Confirmed</span>
              @elseif($booking->isCancelled())
                <span class="badge bg-danger">Cancelled</span>
              @else
                <span class="badge bg-warning text-dark">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
              @endif
              @if($booking->is_sandbox)
                <span class="badge bg-secondary">Test booking</span>
              @endif
              @if($booking->isCancelled())
                <small class="d-block text-muted mt-1">
                  @if($booking->cancelled_at)
                    on {{ $booking->cancelled_at->format('d M Y') }} ·
                  @endif
                  @if($booking->refund_amount !== null && (float) $booking->refund_amount > 0)
                    refund {{ $booking->currency }} {{ number_format((float) $booking->refund_amount, 2) }}
                  @else
                    no refund
                  @endif
                </small>
              @elseif($booking->isLive())
                <small class="d-block text-muted mt-1">
                  {{ $booking->refundable ? 'Free cancellation' : 'Non-refundable' }}
                </small>
              @endif
            </td>
            <td>
              @if($booking->supplier_reference)
                <code>{{ $booking->supplier_reference }}</code>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td class="text-end text-nowrap">
              @if($booking->isLive())
                <a class="btn btn-sm btn-outline-primary" href="{{ route('bookings.confirmation', $booking) }}">View</a>
              @endif
              @if($booking->canBeCancelled())
                <a class="btn btn-sm btn-outline-danger" href="{{ route('bookings.cancel.form', $booking) }}">Cancel</a>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-muted">No bookings yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $bookings->links() }}
</div>
@endsection
