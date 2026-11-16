@extends('layouts.app')
@section('title', 'Write a review')
@section('content')
<div class="container py-5" style="max-width:760px">

  <a href="{{ route('bookings.index') }}" class="text-decoration-none">← Back to my bookings</a>

  <h1 class="h2 mt-3 mb-1">Write a review</h1>
  <p class="text-muted mb-4">
    {{ $kind === 'flight' ? '✈' : '🏨' }} <strong>{{ $heading }}</strong>
    @if($detail) · {{ $detail }} @endif
  </p>

  <form class="form-card" method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf

    <label class="form-label" for="rating">Your rating</label>
    <select class="form-select" id="rating" name="rating" required style="max-width:260px">
      <option value="" disabled @selected(!old('rating'))>Choose a rating</option>
      @foreach([5 => '★★★★★  Excellent', 4 => '★★★★  Good', 3 => '★★★  Average', 2 => '★★  Poor', 1 => '★  Terrible'] as $value => $label)
        <option value="{{ $value }}" @selected((int) old('rating') === $value)>{{ $label }}</option>
      @endforeach
    </select>

    <label class="form-label mt-3" for="title">Title <small class="text-muted">optional</small></label>
    <input class="form-control" id="title" name="title" maxlength="120" value="{{ old('title') }}" placeholder="Sum up your experience">

    <label class="form-label mt-3" for="body">Your review</label>
    <textarea class="form-control" id="body" name="body" rows="6" minlength="15" maxlength="3000" required placeholder="{{ $kind === 'flight' ? 'How was the flight, the service and the booking?' : 'How was the room, the location and the service?' }}">{{ old('body') }}</textarea>
    <small class="text-muted">At least 15 characters.</small>

    <label class="form-label mt-3 d-block" for="photos">Photos <small class="text-muted">optional, up to {{ $maxPhotos }}</small></label>
    <input class="form-control" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
    <small class="text-muted d-block" id="photo-note">JPG, PNG or WebP. Large photos are made smaller before they are sent.</small>
    <div class="d-flex flex-wrap gap-2 mt-2" id="photo-previews"></div>

    <div class="alert alert-info mt-4 mb-0">
      Your review is published straight away with your first name. Please only upload photos you took yourself,
      and avoid photos that show other people's faces.
    </div>

    <button class="btn btn-primary btn-lg mt-4" type="submit" id="review-submit">Publish review</button>
  </form>

</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('photos');
    var previews = document.getElementById('photo-previews');
    var note = document.getElementById('photo-note');
    var submit = document.getElementById('review-submit');
    var maxPhotos = {{ (int) $maxPhotos }};
    var maxSide = 1600;

    if (!input) { return; }

    // Shrink one image in the browser. Falls back to the original file.
    function shrink(file) {
      return new Promise(function (resolve) {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { resolve(file); return; }

        var url = URL.createObjectURL(file);
        var image = new Image();

        image.onload = function () {
          try {
            var scale = Math.min(1, maxSide / Math.max(image.width, image.height));
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(image.width * scale);
            canvas.height = Math.round(image.height * scale);
            canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);

            canvas.toBlob(function (blob) {
              URL.revokeObjectURL(url);
              if (!blob) { resolve(file); return; }
              var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
              resolve(new File([blob], name, { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.85);
          } catch (error) {
            URL.revokeObjectURL(url);
            resolve(file);
          }
        };

        image.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
        image.src = url;
      });
    }

    input.addEventListener('change', function () {
      var chosen = Array.prototype.slice.call(input.files || []);
      var tooMany = chosen.length > maxPhotos;
      var files = chosen.slice(0, maxPhotos);

      previews.innerHTML = '';
      note.textContent = tooMany
        ? 'Only the first ' + maxPhotos + ' photos were kept.'
        : 'JPG, PNG or WebP. Large photos are made smaller before they are sent.';

      if (files.length === 0) { return; }

      submit.disabled = true;

      Promise.all(files.map(shrink)).then(function (ready) {
        // Put the smaller files back into the form, where the browser allows it.
        try {
          var transfer = new DataTransfer();
          ready.forEach(function (file) { transfer.items.add(file); });
          input.files = transfer.files;
        } catch (error) { /* The original files are sent instead. */ }

        ready.forEach(function (file) {
          var img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          img.alt = 'Selected photo';
          img.width = 96;
          img.height = 96;
          img.style.objectFit = 'cover';
          img.style.borderRadius = '10px';
          previews.appendChild(img);
        });

        submit.disabled = false;
      }).catch(function () { submit.disabled = false; });
    });
  });
</script>
@endpush
