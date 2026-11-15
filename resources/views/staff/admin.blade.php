@extends('layouts.app')
@section('title', 'Admin dashboard')
@section('content')
<div class="container py-5">

  <span class="eyebrow text-primary">STAYGO CONTROL CENTRE</span>
  <h1>Administrator dashboard</h1>

  {{-- Totals --}}
  <div class="row g-3 mt-2">
    @foreach($stats as $label => $value)
      <div class="col-6 col-lg">
        <div class="stat-card">
          <strong>{{ number_format($value) }}</strong>
          <span>{{ ucfirst(str_replace('_', ' ', $label)) }}</span>
        </div>
      </div>
    @endforeach
  </div>

  <div class="d-flex gap-2 mt-4">
    <a class="btn btn-outline-primary" href="{{ route('admin.users') }}">Manage users</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.reviews') }}">Moderate reviews</a>
  </div>

  {{-- Hotel bookings --}}
  <h3 class="mt-5">Recent hotel bookings</h3>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Customer</th>
          <th>Hotel</th>
          <th>Dates</th>
          <th>Total</th>
          <th>Status</th>
          <th>Reference</th>
        </tr>
      </thead>
      <tbody>
        @forelse($recentBookings as $booking)
          <tr>
            <td>
              {{ $booking->user?->name ?? 'Deleted user' }}
              <small class="d-block text-muted">{{ $booking->guest_email }}</small>
            </td>
            <td>
              {{ $booking->hotelDisplayName() }}
              @if($booking->room_name)
                <small class="d-block text-muted">{{ $booking->room_name }}</small>
              @endif
            </td>
            <td>{{ $booking->check_in->format('d M Y') }} – {{ $booking->check_out->format('d M Y') }}</td>
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
                <span class="badge bg-secondary">Test</span>
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
            <td colspan="6" class="text-muted">No hotel bookings yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Flight bookings --}}
  <h3 class="mt-5">Recent flight bookings</h3>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Customer</th>
          <th>Route</th>
          <th>Departs</th>
          <th>Total</th>
          <th>Status</th>
          <th>Reference</th>
        </tr>
      </thead>
      <tbody>
        @forelse($recentFlights as $flight)
          <tr>
            <td>
              {{ $flight->user?->name ?? 'Deleted user' }}
              <small class="d-block text-muted">{{ $flight->contact_email }}</small>
            </td>
            <td>
              {{ $flight->origin }} → {{ $flight->destination }}
              <small class="d-block text-muted">
                {{ $flight->trip === 'return' ? 'Return' : 'One way' }} · {{ $flight->travellerCount() }} {{ Str::plural('traveller', $flight->travellerCount()) }}
              </small>
            </td>
            <td>{{ $flight->depart_at->format('d M Y, H:i') }}</td>
            <td>{{ $flight->currency }} {{ number_format((float) $flight->total_amount, 2) }}</td>
            <td>
              <span class="badge {{ $flight->isConfirmed() ? 'bg-success' : ($flight->isCancelled() ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $flight->statusLabel() }}</span>
              @if($flight->is_sandbox)
                <span class="badge bg-secondary">Test</span>
              @endif
            </td>
            <td>
              <code>{{ $flight->booking_ref ?: '—' }}</code>
              @if($flight->pnr)
                <small class="d-block text-muted">PNR {{ $flight->pnr }}</small>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-muted">No flight bookings yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

</div>
@endsection
