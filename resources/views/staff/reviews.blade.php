@extends('layouts.app')
@section('title', 'Review moderation')
@section('content')
<div class="container py-5">
  <h1>Review moderation</h1>
  <p class="text-muted">
    Reviews of real bookings are published straight away. Reject any that break the rules; rejected reviews are hidden from the site.
  </p>

  @forelse($reviews as $review)
    <div class="form-card mb-3">
      <div class="d-flex justify-content-between flex-wrap gap-2">
        <strong>
          {{ $review->isFlight() ? '✈' : '🏨' }} {{ $review->subjectName() }}
          · {{ $review->user?->name ?? 'Deleted user' }}
        </strong>
        <span>
          {{ $review->rating }}/5 ·
          @if($review->status === 'published')
            <span class="badge bg-success">Published</span>
          @elseif($review->status === 'rejected')
            <span class="badge bg-danger">Rejected</span>
          @else
            <span class="badge bg-warning text-dark">{{ ucfirst($review->status) }}</span>
          @endif
          @if($review->verified_stay)
            <span class="badge bg-secondary">Verified booking</span>
          @endif
        </span>
      </div>

      @if($review->title)
        <strong class="d-block mt-2">{{ $review->title }}</strong>
      @endif
      <p class="mt-2" style="white-space:pre-line">{{ $review->body }}</p>

      @if(count($review->photoUrls()) > 0)
        <div class="d-flex flex-wrap gap-2 mb-3">
          @foreach($review->photoUrls() as $url)
            <a href="{{ $url }}" target="_blank" rel="noopener">
              <img src="{{ $url }}" alt="Review photo" loading="lazy" width="120" height="120" style="object-fit:cover;border-radius:10px">
            </a>
          @endforeach
        </div>
      @endif

      <div class="d-flex gap-2">
        @foreach(['published' => 'Publish', 'rejected' => 'Reject'] as $status => $label)
          @if($review->status !== $status)
            <form method="POST" action="{{ route('admin.reviews.moderate', $review) }}">
              @csrf
              @method('PATCH')
              <input type="hidden" name="status" value="{{ $status }}">
              <button class="btn btn-sm {{ $status === 'published' ? 'btn-success' : 'btn-outline-danger' }}">{{ $label }}</button>
            </form>
          @endif
        @endforeach
      </div>
    </div>
  @empty
    <div class="empty-state">No reviews yet.</div>
  @endforelse

  {{ $reviews->links() }}
</div>
@endsection
