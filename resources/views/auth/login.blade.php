@extends('layouts.app')
@section('title','Log in')
@section('content')
<div class="auth-wrap"><form class="form-card auth-card" method="POST" action="{{ route('login') }}">@csrf<span class="eyebrow text-primary">WELCOME BACK</span><h1>Log in to Staygo</h1><label class="form-label">Email</label><input class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" required autofocus><label class="form-label">Password</label><input class="form-control mb-3" type="password" name="password" required><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label for="remember">Remember me</label></div><button class="btn btn-primary w-100">Log in</button><p class="mt-3 mb-0 text-center">New to Staygo? <a href="{{ route('register') }}">Create an account</a></p></form></div>
@endsection
