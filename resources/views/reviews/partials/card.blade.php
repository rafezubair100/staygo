{{-- One review. Expects $review; set $showSubject to show what it is about. --}}
<div class="review mb-3">
  <div class="d-flex justify-content-between flex-wrap gap-2">
    <div>
      <span class="stars" aria-label="{{ $review->rating }} out of 5">{{ str_repeat('★', (int) $review->rating) }}<span class="text-muted">{{ str_repeat('★', 5 - (int) $review->rating) }}</span></span>
      @if(!empty($showSubject))
        <strong class="d-block">
          {{ $review->isFlight() ? '✈' : '🏨' }} {{ $review->subjectName() }}
        </strong>
      @endif
      @if($review->title)
        <strong class="d-block">{{ $review->title }}</strong>
      @endif
    </div>
    <small class="text-muted text-end">
      {{ $review->reviewerName() }} · {{ $review->created_at->format('d M Y') }}
      @if($review->verified_stay)
        <span class="badge bg-success d-block mt-1">Verified booking</span>
      @endif
    </small>
  </div>

  <p class="mt-2 mb-2" style="white-space:pre-line">{{ $review->body }}</p>

  @if(count($review->photoUrls()) > 0)
    <div class="d-flex flex-wrap gap-2">
      @foreach($review->photoUrls() as $url)
        <a href="{{ $url }}" target="_blank" rel="noopener">
          <img src="{{ $url }}" alt="Photo from {{ $review->reviewerName() }}'s review" loading="lazy" width="120" height="120" style="object-fit:cover;border-radius:10px">
        </a>
      @endforeach
    </div>
  @endif
</div>
