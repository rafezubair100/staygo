@extends('layouts.app')
@section('title', 'Traveller details')
@section('content')
@php
  $criteria = $checkout['criteria'];
  $journey = $checkout['journey'];
  $user = auth()->user();
  $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);
  $travellerTotal = count($travellers);
@endphp
<div class="container py-5">

  <a href="{{ route('flights.index', array_filter($criteria, fn ($value) => $value !== null && $value !== false)) }}" class="text-decoration-none">← Back to flights</a>

  <h1 class="h2 mt-3 mb-4">Traveller details</h1>

  @if($sandbox)
    <div class="alert alert-warning">
      <strong>Test mode.</strong> No payment is taken and no real seat is reserved. This booking is simulated.
    </div>
  @endif

  @if($checkout['price_changed'])
    <div class="alert alert-info">
      <strong>The price has changed since your search.</strong>
      It was {{ $checkout['currency'] }} {{ number_format((float) $checkout['searched_price'], 2) }}
      and is now {{ $checkout['currency'] }} {{ number_format((float) $checkout['price'], 2) }}.
    </div>
  @endif

  <form method="POST" action="{{ route('flights.checkout.confirm', $token) }}">
    @csrf

    <div class="row g-4">

      <div class="col-lg-7">

        {{-- Contact --}}
        <div class="form-card mb-4">
          <h4>Contact details</h4>
          <p class="text-muted small">The airline uses these to send updates about the booking.</p>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="contact-first-name">First name</label>
              <input class="form-control" id="contact-first-name" name="contact[first_name]" maxlength="60" autocomplete="given-name" value="{{ old('contact.first_name', $nameParts[0] ?? '') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="contact-last-name">Last name</label>
              <input class="form-control" id="contact-last-name" name="contact[last_name]" maxlength="60" autocomplete="family-name" value="{{ old('contact.last_name', $nameParts[1] ?? '') }}" required>
            </div>
            <div class="col-md-12">
              <label class="form-label" for="contact-email">Email</label>
              <input class="form-control" id="contact-email" name="contact[email]" type="email" maxlength="190" autocomplete="email" value="{{ old('contact.email', $user->email) }}" required>
            </div>
            <div class="col-4 col-md-3">
              <label class="form-label" for="contact-phone-code">Country code</label>
              <div class="input-group">
                <span class="input-group-text">+</span>
                <input class="form-control" id="contact-phone-code" name="contact[phone_country_code]" inputmode="numeric" maxlength="4" pattern="[0-9]{1,4}" value="{{ old('contact.phone_country_code', '44') }}" required>
              </div>
            </div>
            <div class="col-8 col-md-9">
              <label class="form-label" for="contact-phone">Phone number</label>
              <input class="form-control" id="contact-phone" name="contact[phone_number]" type="tel" inputmode="numeric" maxlength="20" autocomplete="tel-national" placeholder="7911 123456" value="{{ old('contact.phone_number') }}" required>
            </div>
          </div>
        </div>

        {{-- Travellers --}}
        @foreach($travellers as $index => $traveller)
          <div class="form-card mb-4">
            <h4 class="mb-1">Traveller {{ $index + 1 }}: {{ $traveller['label'] }}</h4>
            <p class="text-muted small">{{ $traveller['hint'] }} on the day of travel. Names must match the passport exactly.</p>

            <div class="row g-3">
              <div class="col-md-2 col-4">
                <label class="form-label" for="passenger-{{ $index }}-title">Title</label>
                <select class="form-select" id="passenger-{{ $index }}-title" name="passengers[{{ $index }}][title]" required>
                  @foreach(['MR' => 'Mr', 'MRS' => 'Mrs', 'MS' => 'Ms', 'MISS' => 'Miss'] as $code => $label)
                    <option value="{{ $code }}" @selected(old('passengers.'.$index.'.title') === $code)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-5 col-8">
                <label class="form-label" for="passenger-{{ $index }}-first-name">First name</label>
                <input class="form-control" id="passenger-{{ $index }}-first-name" name="passengers[{{ $index }}][first_name]" maxlength="60" value="{{ old('passengers.'.$index.'.first_name') }}" required>
              </div>
              <div class="col-md-5">
                <label class="form-label" for="passenger-{{ $index }}-last-name">Last name</label>
                <input class="form-control" id="passenger-{{ $index }}-last-name" name="passengers[{{ $index }}][last_name]" maxlength="60" value="{{ old('passengers.'.$index.'.last_name') }}" required>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="passenger-{{ $index }}-birthday">Date of birth</label>
                <input class="form-control" id="passenger-{{ $index }}-birthday" name="passengers[{{ $index }}][birthday]" type="date" max="{{ date('Y-m-d', strtotime('-1 day')) }}" value="{{ old('passengers.'.$index.'.birthday') }}" required>
              </div>
              <div class="col-md-3">
                <label class="form-label" for="passenger-{{ $index }}-gender">Gender</label>
                <select class="form-select" id="passenger-{{ $index }}-gender" name="passengers[{{ $index }}][gender]" required>
                  <option value="" disabled @selected(!old('passengers.'.$index.'.gender'))>Choose</option>
                  @foreach(['M' => 'Male', 'F' => 'Female', 'X' => 'Unspecified'] as $code => $label)
                    <option value="{{ $code }}" @selected(old('passengers.'.$index.'.gender') === $code)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label" for="passenger-{{ $index }}-nationality">Nationality</label>
                <select class="form-select" id="passenger-{{ $index }}-nationality" name="passengers[{{ $index }}][nationality]" required>
                  @foreach($countries as $code => $name)
                    <option value="{{ $code }}" @selected(old('passengers.'.$index.'.nationality', 'GB') === $code)>{{ $name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        @endforeach

        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" value="1" @checked(old('accept_terms')) required>
          <label class="form-check-label" for="accept_terms">
            I have checked the flights, dates, traveller names and price, and I accept the fare conditions.
          </label>
        </div>

        <button class="btn btn-primary btn-lg w-100 mt-4" type="submit">
          {{ $sandbox ? 'Confirm test booking' : 'Confirm booking' }}
        </button>

        <p class="text-muted small mt-3">
          This fare is held for about {{ $minutesLeft }} more {{ Str::plural('minute', $minutesLeft) }}.
          Booking can take up to a minute. Please do not close or refresh the page.
        </p>
      </div>

      {{-- Flight summary --}}
      <div class="col-lg-5">
        <div class="booking-box">
          <h5 class="mb-1">{{ $criteria['origin'] }} → {{ $criteria['destination'] }}</h5>
          <p class="text-muted small">
            {{ $criteria['trip'] === 'return' ? 'Return' : 'One way' }}
            · {{ $travellerTotal }} {{ Str::plural('traveller', $travellerTotal) }}
            @if(!empty($journey['cabin'])) · {{ $journey['cabin'] }} @endif
          </p>

          <hr>

          @include('flights.partials.itinerary', ['legs' => $journey['legs']])

          <hr>

          <div class="d-flex flex-wrap gap-2">
            <span class="amenity">{{ $journey['checked_bag'] ? 'Checked bag included' : 'Cabin bag only' }}</span>
            <span class="amenity">{{ $journey['refundable'] ? 'Refundable' : 'Non-refundable' }}</span>
            <span class="amenity">{{ $journey['changeable'] ? 'Changes allowed' : 'No changes' }}</span>
          </div>

          <hr>

          <div class="d-flex justify-content-between align-items-end">
            <span>Total for {{ $travellerTotal }} {{ Str::plural('traveller', $travellerTotal) }}</span>
            <strong class="fs-3">{{ $checkout['currency'] }} {{ number_format((float) $checkout['price'], 2) }}</strong>
          </div>
        </div>
      </div>

    </div>
  </form>
</div>
@endsection
