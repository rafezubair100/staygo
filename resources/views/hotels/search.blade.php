@extends('layouts.app')
@section('title', 'Hotel search results')
@section('content')
<div class="container py-5">
 <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4"><div><span class="eyebrow text-primary">SEARCH RESULTS</span><h1 class="h2 mb-1">Stays in {{ $criteria['destination'] }}</h1><p class="text-muted mb-0">{{ $criteria['check_in'] }} – {{ $criteria['check_out'] }} · {{ $criteria['adults'] }} adults · {{ $criteria['rooms'] }} room(s)</p></div><a href="{{ route('destinations.show', Str::slug($criteria['destination'])) }}" class="btn btn-outline-primary">Destination guide</a></div>
 @if($liveAvailable)
 <div class="alert alert-success">Live supplier offers were returned. Final availability and price must be rechecked before booking.</div>
 <div class="row g-3 mb-4">@foreach($offers as $offer)<div class="col-lg-6"><div class="hotel-card p-3"><div class="d-flex justify-content-between"><div><span class="badge bg-success">Live Amadeus offer</span><h5 class="mt-2">{{ data_get($offer,'hotel.name','Hotel offer') }}</h5><small class="text-muted">Supplier hotel ID: {{ data_get($offer,'hotel.hotelId','—') }}</small></div><div class="text-end"><strong class="fs-4">{{ data_get($offer,'offers.0.price.currency','') }} {{ data_get($offer,'offers.0.price.total','Price unavailable') }}</strong><small class="d-block text-muted">Supplier returned total</small></div></div><p class="small text-muted mt-3">Offer terms and cancellation details are supplier-controlled. Recheck before purchase.</p></div></div>@endforeach</div>
 @elseif(!$supplierConfigured)
 <div class="alert alert-warning"><strong>Live hotel search is not connected yet.</strong> Add Amadeus API credentials to your .env file and provide a 3-letter IATA city code to request live offers. The hotel records below, if any, are local development records and are not live inventory.</div>
 @else
 <div class="alert alert-info">No live supplier offers were returned for this search. Try another destination or check your API account and city code.</div>
 @endif
 <div class="row g-4"><aside class="col-lg-3"><div class="filter-box"><h5>Filter local listings</h5><form method="GET" action="{{ route('hotels.search') }}">@foreach($criteria as $k=>$v) @if(!in_array($k,['min_price','max_price','stars','sort']))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
 <label class="form-label mt-3">Minimum price (GBP)</label><input class="form-control" name="min_price" type="number" min="0" value="{{ request('min_price') }}">
 <label class="form-label mt-3">Maximum price (GBP)</label><input class="form-control" name="max_price" type="number" min="0" value="{{ request('max_price') }}">
 <label class="form-label mt-3">Star rating</label><select class="form-select" name="stars"><option value="">Any</option>@for($i=1;$i<=5;$i++)<option value="{{ $i }}" @selected(request('stars')==$i)>{{ $i }} stars</option>@endfor</select>
 <label class="form-label mt-3">Sort</label><select class="form-select" name="sort"><option value="recommended">Recommended</option><option value="price_asc">Lowest price</option><option value="price_desc">Highest price</option><option value="rating">Guest rating</option></select>
 <button class="btn btn-primary w-100 mt-3">Apply filters</button></form></div></aside>
 <div class="col-lg-9"><div class="d-flex justify-content-between mb-3"><h4>Staygo local listings</h4><small class="text-muted">{{ $hotels->total() }} records</small></div><div class="row g-3">
 @forelse($hotels as $hotel)<div class="col-12"><div class="hotel-card horizontal"><div class="hotel-placeholder">✦</div><div class="p-3 flex-grow-1"><div class="d-flex justify-content-between"><div><span class="stars">{{ str_repeat('★',(int)$hotel->stars) }}</span><h5 class="mb-1">{{ $hotel->name }}</h5><p class="text-muted mb-2">{{ $hotel->city }}, {{ $hotel->country }}</p></div><span class="badge bg-secondary align-self-start">Development record</span></div><p>{{ Str::limit($hotel->description, 130) }}</p><div class="d-flex justify-content-between align-items-center"><small class="text-muted">Not a live supplier offer</small><a class="btn btn-outline-primary" href="{{ route('hotels.show',$hotel) }}">View hotel</a></div></div></div></div>@empty<div class="col-12"><div class="empty-state">No local records match these filters. Try another search or connect live supplier inventory.</div></div>@endforelse
 </div>{{ $hotels->links() }}</div></div>
</div>
@endsection
