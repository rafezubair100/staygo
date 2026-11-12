@extends('layouts.app')
@section('title', 'Worldwide hotel search')
@section('content')
<section class="hero"><div class="container hero-inner"><div class="row align-items-center"><div class="col-lg-7">
  <span class="eyebrow">YOUR NEXT JOURNEY STARTS HERE</span><h1>Find a place<br>to <em>belong.</em></h1>
  <p class="lead text-white-50 mb-4">Explore stays around the world. Compare options, discover destinations and plan with confidence.</p>
</div><div class="col-lg-5 d-none d-lg-block"><div class="hero-note"><span>✦</span><div><strong>One search. More possibilities.</strong><small>Discover your next favourite place.</small></div></div></div></div>
<form class="search-panel row g-2 align-items-end" action="{{ route('hotels.search') }}" method="GET">
 <div class="col-lg-3 col-md-6"><label>Destination</label><input class="form-control" id="destination-input" name="destination" placeholder="Where are you going?" autocomplete="off" required><input type="hidden" id="city-code" name="city_code"><div id="destination-suggestions" class="suggestions"></div></div>
 <div class="col-lg-2 col-md-6"><label>Check-in</label><input class="form-control" type="date" name="check_in" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d', strtotime('+7 days')) }}" required></div>
 <div class="col-lg-2 col-md-6"><label>Check-out</label><input class="form-control" type="date" name="check_out" min="{{ date('Y-m-d', strtotime('+1 day')) }}" value="{{ date('Y-m-d', strtotime('+9 days')) }}" required></div>
 <div class="col-lg-2 col-md-6"><label>Guests</label><select class="form-select" name="adults"><option value="1">1 adult</option><option value="2" selected>2 adults</option><option value="3">3 adults</option><option value="4">4 adults</option></select></div>
 <div class="col-lg-1 col-md-6"><label>Rooms</label><select class="form-select" name="rooms"><option>1</option><option>2</option><option>3</option><option>4</option></select></div>
 <div class="col-lg-2 col-md-6"><button class="btn btn-primary w-100 py-2">Search stays <span>→</span></button></div>
</form></div></section>
<section class="container py-5" id="destinations"><div class="section-head"><div><span class="eyebrow text-primary">GET INSPIRED</span><h2>Popular destinations</h2><p>Ideas for your next escape.</p></div></div>
<div class="row g-3">
@foreach([['London','United Kingdom','photo-1513635269975-59663e0ac1ad'],['Paris','France','photo-1502602898657-3e91760cbb34'],['Dubai','United Arab Emirates','photo-1512453979798-5ea266f8880c'],['Kathmandu','Nepal','photo-1544735716-392fe2489ffa']] as $d)
<div class="col-6 col-lg-3"><a class="destination-card" href="{{ route('destinations.show', Str::slug($d[0])) }}" style="background-image:url('https://images.unsplash.com/{{ $d[2] }}?auto=format&fit=crop&w=700&q=80')"><span><b>{{ $d[0] }}</b><small>{{ $d[1] }}</small></span></a></div>
@endforeach
</div></section>
<section class="container pb-5"><div class="section-head"><div><span class="eyebrow text-primary">STAYS TO EXPLORE</span><h2>Discover places to stay</h2><p>Seed records are clearly marked as development examples.</p></div></div><div class="row g-4">
@forelse($featured as $hotel)<div class="col-md-6 col-lg-4"><div class="hotel-card"><div class="hotel-placeholder">✦</div><div class="p-3"><div class="d-flex justify-content-between"><span class="stars">{{ str_repeat('★', (int)($hotel->stars ?? 0)) }}</span><small class="text-muted">{{ $hotel->supplier === 'seed_sample' ? 'Sample listing' : 'Hotel' }}</small></div><h5 class="mt-2">{{ $hotel->name }}</h5><p class="text-muted">{{ $hotel->city }}, {{ $hotel->country }}</p><a class="btn btn-outline-primary w-100" href="{{ route('hotels.show', $hotel) }}">View details</a></div></div></div>@empty<div class="col-12"><p>No hotel records yet. Connect a supplier or seed sample data.</p></div>@endforelse
</div></section>
<section class="why-section" id="why"><div class="container py-5"><div class="row g-4"><div class="col-md-4"><span>01</span><h4>Explore worldwide</h4><p>Discover destinations and hotel options across the globe.</p></div><div class="col-md-4"><span>02</span><h4>Know what is real</h4><p>Supplier offers are separated from sample and editorial content.</p></div><div class="col-md-4"><span>03</span><h4>Travel with context</h4><p>Destination guides, maps and weather help you plan better.</p></div></div></div></section>
@endsection
