@extends('layouts.app')
@section('title','Review moderation')
@section('content')
<div class="container py-5"><h1>Review moderation</h1>@foreach($reviews as $review)<div class="form-card mb-3"><div class="d-flex justify-content-between"><strong>{{ $review->hotel->name }} · {{ $review->user->name }}</strong><span>{{ $review->rating }}/5 · {{ $review->status }}</span></div><p class="mt-2">{{ $review->body }}</p><div class="d-flex gap-2">@foreach(['published'=>'Publish','rejected'=>'Reject'] as $status=>$label)<form method="POST" action="{{ route('admin.reviews.moderate',$review) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $status }}"><button class="btn btn-sm {{ $status==='published'?'btn-success':'btn-outline-danger' }}">{{ $label }}</button></form>@endforeach</div></div>@endforeach{{ $reviews->links() }}</div>
@endsection
