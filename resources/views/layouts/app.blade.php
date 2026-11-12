<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Staygo') | Find your stay. Love your journey.</title>
    <meta name="description" content="@yield('meta_description', 'Discover hotels and destinations worldwide with Staygo.')">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/staygo.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
  <div class="container py-2">
    <a class="navbar-brand brand" href="{{ route('home') }}"><span class="brand-mark">S</span> staygo</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
        <li><a class="nav-link" href="{{ route('home') }}#destinations">Destinations</a></li>
        <li><a class="nav-link" href="{{ route('home') }}#why">Why Staygo</a></li>
        @auth
          <li><a class="nav-link" href="{{ route('account') }}">My account</a></li>
          <li><a class="nav-link" href="{{ route('bookings.index') }}">Bookings</a></li>
          @if(auth()->user()->role === 'admin')<li><a class="nav-link" href="{{ route('admin.dashboard') }}">Admin</a></li>@endif
          @if(auth()->user()->role === 'manager')<li><a class="nav-link" href="{{ route('manager.dashboard') }}">Manager</a></li>@endif
          <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-dark btn-sm">Log out</button></form></li>
        @else
          <li><a class="nav-link" href="{{ route('login') }}">Log in</a></li>
          <li><a class="btn btn-primary btn-sm px-3" href="{{ route('register') }}">Create account</a></li>
        @endauth
      </ul>
    </div>
  </div>
</nav>
@if(session('success'))<div class="container mt-3"><div class="alert alert-success">{{ session('success') }}</div></div>@endif
@if($errors->any())<div class="container mt-3"><div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
<main>@yield('content')</main>
<footer class="footer mt-5"><div class="container py-5"><div class="row g-4"><div class="col-md-5"><a class="brand text-white" href="{{ route('home') }}">staygo</a><p class="text-white-50 mt-3">Find your stay. Love your journey.<br>Discover destinations and compare hotel offers worldwide.</p></div><div class="col-md-3"><h6>Explore</h6><a href="{{ route('home') }}#destinations">Destinations</a><a href="{{ route('home') }}">Find hotels</a></div><div class="col-md-4"><h6>Staygo</h6><p class="text-white-50 small">Hotel availability and prices are supplied by connected providers and may change until confirmed.</p></div></div><hr class="border-secondary"><small class="text-white-50">© {{ date('Y') }} Staygo. All rights reserved.</small></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/staygo.js') }}"></script>
@stack('scripts')
</body></html>