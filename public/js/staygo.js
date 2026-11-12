document.addEventListener('DOMContentLoaded', () => {
  const checkIn = document.querySelector('input[name="check_in"]');
  const checkOut = document.querySelector('input[name="check_out"]');
  if (checkIn && checkOut) {
    checkIn.addEventListener('change', () => {
      if (checkIn.value) {
        const next = new Date(checkIn.value + 'T00:00:00');
        next.setDate(next.getDate() + 1);
        const min = next.toISOString().slice(0, 10);
        checkOut.min = min;
        if (!checkOut.value || checkOut.value <= checkIn.value) checkOut.value = min;
      }
    });
  }

  const destinationInput = document.getElementById('destination-input');
  const cityCode = document.getElementById('city-code');
  const suggestionBox = document.getElementById('destination-suggestions');
  let suggestionTimer;
  if (destinationInput && suggestionBox) {
    destinationInput.addEventListener('input', () => {
      clearTimeout(suggestionTimer);
      if (cityCode) cityCode.value = '';
      const query = destinationInput.value.trim();
      if (query.length < 2) { suggestionBox.innerHTML = ''; return; }
      suggestionTimer = setTimeout(async () => {
        try {
          const response = await fetch(`/destination-suggestions?q=${encodeURIComponent(query)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          if (!response.ok) return;
          const places = await response.json();
          suggestionBox.innerHTML = '';
          places.forEach(place => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'suggestion-item';
            button.textContent = place.label;
            button.addEventListener('click', () => {
              destinationInput.value = place.label;
              if (cityCode) cityCode.value = place.city_code;
              suggestionBox.innerHTML = '';
            });
            suggestionBox.appendChild(button);
          });
        } catch (_) { /* Optional API: manual destination entry remains available. */ }
      }, 250);
    });
    document.addEventListener('click', (event) => {
      if (!suggestionBox.contains(event.target) && event.target !== destinationInput) suggestionBox.innerHTML = '';
    });
  }
});
