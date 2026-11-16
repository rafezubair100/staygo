@extends('layouts.app')
@section('title', 'Guest reviews')
@section('content')
<div class="container py-5" style="max-width:860px">

  <span class="eyebrow text-primary">GUEST REVIEWS</span>
  <h1 class="h2">What travellers say</h1>
  <p class="text-muted">Reviews are written by people who booked their hotel or flight through Staygo.</p>

  @forelse($reviews as $review)
    <div class="form-card mb-3">
      @include('reviews.partials.card', ['review' => $review, 'showSubject' => true])
    </div>
  @empty
    <div class="empty-state">No reviews yet. After you book, you can write the first one from My bookings.</div>
  @endforelse

  {{ $reviews->links() }}

</div>
@endsection
