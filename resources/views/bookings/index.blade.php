@extends('layouts.app')
@section('title','My booking requests')
@section('content')
<div class="container py-5"><h1>My booking requests</h1><p class="text-muted">A request is not a confirmed reservation until the supplier confirms it.</p><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Hotel</th><th>Dates</th><th>Status</th><th>Payment</th></tr></thead><tbody>@forelse($bookings as $booking)<tr><td>{{ $booking->hotel->name }}</td><td>{{ $booking->check_in->format('d M Y') }} – {{ $booking->check_out->format('d M Y') }}</td><td><span class="badge bg-warning text-dark">{{ str_replace('_',' ',$booking->status) }}</span></td><td>{{ $booking->payment_status }}</td></tr>@empty<tr><td colspan="4" class="text-muted">No booking requests yet.</td></tr>@endforelse</tbody></table></div>{{ $bookings->links() }}</div>
@endsection
