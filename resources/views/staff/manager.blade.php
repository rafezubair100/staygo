@extends('layouts.app')
@section('title','Hotel manager dashboard')
@section('content')
<div class="container py-5"><span class="eyebrow text-primary">PROPERTY WORKSPACE</span><h1>Hotel manager dashboard</h1><p class="text-muted">Property records currently available in the platform. Manager ownership and supplier inventory permissions must be assigned before editing or selling rooms.</p><div class="row g-3">@foreach($properties as $hotel)<div class="col-md-6"><div class="form-card"><h5>{{ $hotel->name }}</h5><p>{{ $hotel->city }}, {{ $hotel->country }}</p><small class="text-muted">Read-only starter view · no supplier rate changes are exposed.</small></div></div>@endforeach</div>{{ $properties->links() }}</div>
@endsection
