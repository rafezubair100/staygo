@extends('layouts.app')
@section('title', 'My bookings')
@section('content')
<div class="container py-5">
  <h1>My bookings</h1>

  @if($bookings->contains(fn ($booking) => $booking->status !== 'confirmed'))
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
              @else
                <span class="badge bg-warning text-dark">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
              @endif
              @if($booking->is_sandbox)
                <span class="badge bg-secondary">Test booking</span>
              @endif
              @if($booking->isLive())
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
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-muted">No bookings yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $bookings->links() }}
</div>
@endsection