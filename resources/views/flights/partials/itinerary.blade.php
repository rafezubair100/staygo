{{-- Shows the flights of a journey. Expects $legs in the standard format. --}}
@php
  $itineraryDuration = fn ($minutes) => is_numeric($minutes)
      ? intdiv((int) $minutes, 60).'h '.str_pad((string) ((int) $minutes % 60), 2, '0', STR_PAD_LEFT).'m'
      : '';
  $itineraryTime = fn ($stamp, $format) => $stamp
      ? rescue(fn () => \Illuminate\Support\Carbon::parse($stamp)->format($format), '', false)
      : '';
@endphp
@foreach($legs as $leg)
  <div class="@if(!$loop->last) pb-3 mb-3 border-bottom @endif">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
      <strong>
        {{ ($leg['direction'] ?? 'OUTBOUND') === 'INBOUND' ? 'Return' : 'Outbound' }}
        · {{ $itineraryTime($leg['departure'] ?? null, 'D d M Y') }}
      </strong>
      <small class="text-muted">
        {{ $itineraryDuration($leg['duration_minutes'] ?? null) }}
        ·
        @if(($leg['stops'] ?? 0) === 0)
          Direct
        @else
          {{ $leg['stops'] }} {{ Str::plural('stop', (int) $leg['stops']) }}
        @endif
      </small>
    </div>

    @foreach(($leg['segments'] ?? []) as $segment)
      <div class="d-flex align-items-center gap-3 @if(!$loop->last) mb-2 @endif">
        @if(!empty($segment['airline_logo']))
          <img src="{{ $segment['airline_logo'] }}" alt="" width="28" height="28" style="object-fit:contain">
        @endif
        <div class="flex-grow-1">
          <div>
            <strong>{{ $itineraryTime($segment['departure'] ?? null, 'H:i') }}</strong>
            {{ $segment['origin_code'] ?? '' }}
            →
            <strong>{{ $itineraryTime($segment['arrival'] ?? null, 'H:i') }}</strong>
            {{ $segment['destination_code'] ?? '' }}
            @if(($segment['departure'] ?? null) && ($segment['arrival'] ?? null)
                && $itineraryTime($segment['departure'], 'Y-m-d') !== $itineraryTime($segment['arrival'], 'Y-m-d'))
              <small class="text-danger">arrives {{ $itineraryTime($segment['arrival'], 'D d M') }}</small>
            @endif
          </div>
          <small class="text-muted">
            {{ collect([$segment['airline_name'] ?? null, $segment['flight_number'] ?? null, $itineraryDuration($segment['duration_minutes'] ?? null)])->filter()->implode(' · ') }}
          </small>
          <small class="text-muted d-block">
            {{ $segment['origin_name'] ?? '' }} to {{ $segment['destination_name'] ?? '' }}
          </small>
        </div>
      </div>
    @endforeach
  </div>
@endforeach
