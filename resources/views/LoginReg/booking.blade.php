@extends('LoginReg.layout')

@section('title', 'Book Your Stay | Villa Natividad')

@push('styles')
    <link href="{{ asset('css/books.css') }}" rel="stylesheet">
@endpush

@section('content')
<main class="booking">
    @include('LoginReg.include.home.book')

    <form action="{{ route($walkIn ? 'walk-ins.store' : 'reservations.store') }}" method="post">
        @csrf
        <div class="booking__grid">

            {{-- LEFT: reservation options --}}
            <section class="panel">
                @if ($walkIn)
                    <h1>Walk-in booking</h1>
                    <p class="walkin-note">
                        <a href="{{ route('check-in.index') }}">Back to staff check-in</a><br>
                        Enter the guest's details. No customer account is required. Payment is recorded after creating the booking.
                    </p>
                    <div class="field">
                        <label for="guest_name">Guest name</label>
                        <input class="input" id="guest_name" name="guest_name" value="{{ old('guest_name') }}" maxlength="255" required>
                    </div>
                    <div class="field">
                        <label for="guest_contact">Contact number</label>
                        <input class="input" id="guest_contact" name="guest_contact" type="tel" value="{{ old('guest_contact') }}" maxlength="50" required>
                    </div>
                    <div class="field">
                        <label for="guest_email">Email (optional)</label>
                        <input class="input" id="guest_email" name="guest_email" type="email" value="{{ old('guest_email') }}" maxlength="255">
                    </div>
                @else
                    <h2>Reservation details</h2>
                @endif

                @if ($errors->any())
                    <div class="errors" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="field">
                    <label for="reservation_type">Reservation type</label>
                    <select class="select" name="reservation_type" id="reservation_type" required>
                        <option value="" disabled selected>Select a reservation type</option>
                        <option value="public" @selected(old('reservation_type') === 'public')>Public Pool</option>
                        <option value="event" @selected(old('reservation_type') === 'event')>Event</option>
                    </select>
                </div>

                {{-- Only shown for public pool reservations --}}
                <div class="field" id="stay-type-group" style="display:none;">
                    <label for="stay_type">Stay type</label>
                    <select class="select" name="stay_type" id="stay_type">
                        <option value="" disabled selected>Select stay type</option>
                        <option value="day_tour" @selected(old('stay_type') === 'day_tour')>Day Tour</option>
                        <option value="overnight" @selected(old('stay_type') === 'overnight')>Overnight</option>
                    </select>
                </div>

                <div class="field" id="event-package-group" hidden>
                    <label for="event_package_id">Event package</label>
                    <select class="select" name="event_package_id" id="event_package_id" disabled>
                        <option value="">Select an event package</option>
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" data-price="{{ $package->price }}"
                                @selected((string) old('event_package_id') === (string) $package->id)>
                                {{ $package->name }} — ₱{{ number_format($package->price, 2) }} ({{ $package->hours }} hours)
                            </option>
                        @endforeach
                    </select>
                    @if ($packages->isEmpty())
                        <p class="status">No event packages are currently available.</p>
                    @endif
                    <dl class="price-card" id="event-price-summary" aria-live="polite" hidden>
                        <dt>Package price</dt><dd id="event-total"></dd>
                        <dt>Reservation fee ({{ $reservationFeePercent }}%)</dt><dd id="event-fee"></dd>
                        <dt>Remaining balance</dt><dd id="event-balance"></dd>
                    </dl>
                </div>

                <div class="field">
                    <label for="guest_count">Number of guests</label>
                    <input class="input" type="number" name="guest_count" id="guest_count" value="{{ old('guest_count') }}" min="1" placeholder="Enter number of guests" required>
                </div>

                <div class="field" id="availability-results" style="display:none;">
                    <div class="availability-heading">
                        <div>
                            <span class="label">Choose your accommodation</span>
                            <p class="status">Select up to 2 accommodations that fit your group.</p>
                        </div>
                        <span class="availability-count" id="cottage-count" aria-live="polite"></span>
                    </div>
                    <div class="cottage-filters" aria-label="Filter accommodations">
                        <label class="filter-control filter-control--search"><span class="sr-only">Search accommodations</span><input class="input" type="search" id="cottage-search" placeholder="Search by name..." autocomplete="off"></label>
                        <label class="filter-control"><span class="sr-only">Filter by capacity</span><select class="select" id="cottage-capacity-filter"><option value="all">Any capacity</option><option value="4">4+ guests</option><option value="6">6+ guests</option><option value="9">9+ guests</option><option value="16">16+ guests</option></select></label>
                        <label class="filter-control"><span class="sr-only">Sort accommodations</span><select class="select" id="cottage-sort"><option value="recommended">Recommended</option><option value="price-low">Price: low to high</option><option value="price-high">Price: high to low</option><option value="capacity">Capacity: high to low</option></select></label>
                    </div>
                    <div class="cottages" id="cottage-list"></div>
                    <p class="status cottage-selection-status" id="cottage-selection-status" role="status" aria-live="polite">Choose up to 2 accommodations.</p>
                </div>

                <div class="field">
                    <label for="additional_message">Additional message <span style="color:var(--muted);font-weight:400">(optional)</span></label>
                    <textarea class="textarea" name="additional_message" id="additional_message" rows="3" placeholder="Any special requests...">{{ old('additional_message') }}</textarea>
                </div>

                <button class="btn" type="submit" id="book-btn" disabled>{{ $walkIn ? 'Create walk-in booking' : 'Book now' }}</button>
            </section>

            {{-- RIGHT: always-visible calendar --}}
            <div class="side">
                <section class="panel cal" aria-label="Availability calendar">
                    <h2><label for="check_in">Select date</label></h2>
                    {{-- flatpickr (inline) draws the calendar right after this input; the input stays in the form and holds the Y-m-d value --}}
                    <input class="cal__input" type="text" name="check_in" id="check_in" value="{{ old('check_in') }}" placeholder="Select Date..." required>
                    <p class="status" id="calendar-status" role="status">Pick a reservation type to see available dates.</p>
                    <div class="legend">
                        <span><i class="l-sel"></i>Your date</span>
                        <span><i class="l-booked"></i>Unavailable</span>
                    </div>
                </section>
            </div>

        </div>
    </form>
</main>
@endsection

@push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const reservationType = document.getElementById('reservation_type');
            const stayTypeGroup  = document.getElementById('stay-type-group');
            const stayType       = document.getElementById('stay_type');
            const checkInInput   = document.getElementById('check_in');
            const resultsDiv     = document.getElementById('availability-results');
            const cottageList    = document.getElementById('cottage-list');
            const guestCount = document.getElementById('guest_count');
            const selectionStatus = document.getElementById('cottage-selection-status');
            const cottageCount = document.getElementById('cottage-count');
            const cottageSearch = document.getElementById('cottage-search');
            const cottageCapacity = document.getElementById('cottage-capacity-filter');
            const cottageSort = document.getElementById('cottage-sort');
            const bookBtn        = document.getElementById('book-btn');
            const packageGroup = document.getElementById('event-package-group');
            const packageSelect = document.getElementById('event_package_id');
            const priceSummary = document.getElementById('event-price-summary');
            const calendarStatus = document.getElementById('calendar-status');
            const feePercent = @json($reservationFeePercent);
            const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
            const IDLE_MSG = 'Pick a reservation type to see available dates.';
            let calendarReady = false;
            let calendarRequest = 0;
            let availableCottages = [];

            function updatePackageSummary() {
                const option = packageSelect.selectedOptions[0];
                const total = Number(option?.dataset.price ?? 0);
                const fee = Math.round(total * feePercent) / 100;
                priceSummary.hidden = !packageSelect.value;
                document.getElementById('event-total').textContent = currency.format(total);
                document.getElementById('event-fee').textContent = currency.format(fee);
                document.getElementById('event-balance').textContent = currency.format(total - fee);
                if (reservationType.value === 'event') {
                    bookBtn.disabled = !calendarReady || !checkInInput.value || !packageSelect.value;
                }
            }

            function updateReservationFields() {
                const isEvent = reservationType.value === 'event';
                packageGroup.hidden = !isEvent;
                packageSelect.disabled = !isEvent;
                packageSelect.required = isEvent;
                stayTypeGroup.style.display = reservationType.value === 'public' ? '' : 'none';
                stayType.disabled = isEvent;
                stayType.required = reservationType.value === 'public';
                updatePackageSummary();
                updateCottageSelection();
            }

            function updateCottageSelection() {
                guestCount.setCustomValidity('');
                guestCount.removeAttribute('max');
                if (reservationType.value !== 'public') return;

                const choices = [...cottageList.querySelectorAll('input[type="checkbox"]')];
                const selected = choices.filter(choice => choice.checked);
                const capacity = selected.reduce((total, choice) => total + Number(choice.dataset.capacity), 0);
                choices.forEach(choice => { choice.disabled = !choice.checked && selected.length >= 2; });
                selectionStatus.textContent = `${selected.length} of 2 accommodations selected. Combined capacity: ${capacity} guests.`;
                if (selected.length > 0) {
                    guestCount.max = String(capacity);
                    if (Number(guestCount.value) > capacity) {
                        guestCount.setCustomValidity(`The selected accommodations can hold a maximum of ${capacity} guests.`);
                        selectionStatus.textContent += ' Select another accommodation or reduce the number of guests.';
                    }
                }
                bookBtn.disabled = !calendarReady || !checkInInput.value || selected.length === 0
                    || selected.length > 2 || !guestCount.checkValidity();
            }

            function renderCottages() {
                const search = cottageSearch.value.trim().toLowerCase();
                const minimumCapacity = cottageCapacity.value === 'all' ? 0 : Number(cottageCapacity.value);
                const sort = cottageSort.value;
                const selectedIds = new Set([...cottageList.querySelectorAll('input:checked')].map(input => String(input.value)));
                const filtered = availableCottages.filter(cottage => selectedIds.has(String(cottage.id)) || (cottage.name.toLowerCase().includes(search) && Number(cottage.capacity) >= minimumCapacity));
                filtered.sort((a, b) => {
                    if (sort === 'price-low') return Number(a.display_price) - Number(b.display_price);
                    if (sort === 'price-high') return Number(b.display_price) - Number(a.display_price);
                    if (sort === 'capacity') return Number(b.capacity) - Number(a.capacity);
                    return Number(a.id) - Number(b.id);
                });
                cottageCount.textContent = `${filtered.length} of ${availableCottages.length} available`;
                cottageList.replaceChildren();
                if (!filtered.length) {
                    cottageList.innerHTML = '<p class="status cottage-empty">No accommodations match your filters. Try a different search or capacity.</p>';
                    return;
                }
                filtered.forEach(cottage => {
                    const label = document.createElement('label');
                    label.className = 'cottage';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox'; checkbox.name = 'cottage_ids[]'; checkbox.value = cottage.id; checkbox.dataset.capacity = cottage.capacity; checkbox.checked = selectedIds.has(String(cottage.id));
                    const info = document.createElement('span'); info.className = 'cottage__info';
                    const name = document.createElement('span'); name.className = 'cottage__name'; name.textContent = cottage.name;
                    const meta = document.createElement('span'); meta.className = 'cottage__meta'; meta.textContent = `Up to ${cottage.capacity} guests`;
                    info.append(name, meta);
                    const cost = document.createElement('span'); cost.className = 'cottage__price'; cost.textContent = currency.format(cottage.display_price);
                    label.append(checkbox, info, cost); cottageList.appendChild(label);
                });
                updateCottageSelection();
            }

            cottageList.addEventListener('change', updateCottageSelection);
            guestCount.addEventListener('input', updateCottageSelection);
            [cottageSearch, cottageCapacity, cottageSort].forEach(control => control.addEventListener('input', renderCottages));

            packageSelect.addEventListener('change', updatePackageSummary);

            // CHANGED: inline calendar is always visible (no altInput needed)
            const picker = flatpickr("#check_in", {
                inline: true,
                dateFormat: "Y-m-d",
                minDate: @json($earliestBookingDate),
                onChange: function() {
                    checkAvailability();
                }
            });

            // Show/hide the stay-type dropdown based on reservation type
            reservationType.addEventListener('change', function() {
                // Clear the selected date whenever the type changes
                picker.clear();
                resultsDiv.style.display = 'none';
                cottageList.innerHTML = '';
                bookBtn.disabled = true;
                calendarReady = false;
                calendarRequest++;
                picker.set('disable', []);
                stayType.value = '';
                calendarStatus.textContent = IDLE_MSG;
                updateReservationFields();

                if (this.value === 'event') {
                    refreshDisabledDates();
                }
            });

            stayType.addEventListener('change', function() {
                // Clear the selected date when stay type changes (disabled dates shift)
                picker.clear();
                resultsDiv.style.display = 'none';
                cottageList.innerHTML = '';
                bookBtn.disabled = true;

                refreshDisabledDates();
            });

            /**
             * Fetch dates that should be greyed-out in the calendar, then
             * update the calendar without discarding the selected date.
             */
            function refreshDisabledDates() {
                const type = reservationType.value;
                const stay = stayType.value;

                if (!type) return;
                if (type === 'public' && !stay) return;

                let url = `{{ route('reservations.disabled-dates') }}?reservation_type=${type}`;
                if (type === 'public') {
                    url += `&stay_type=${stay}`;
                }

                calendarReady = false;
                bookBtn.disabled = true;
                calendarStatus.textContent = 'Loading available dates…';
                const request = ++calendarRequest;
                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Could not load available dates.');
                    return response.json();
                })
                .then(disabledDates => {
                    if (request !== calendarRequest) return;
                    picker.set('disable', disabledDates);
                    calendarReady = true;
                    calendarStatus.textContent = '';
                    checkAvailability();
                })
                .catch(err => {
                    if (request !== calendarRequest) return;
                    calendarStatus.textContent = 'Could not load available dates. Please refresh and try again.';
                    console.error('Failed to load disabled dates:', err);
                });
            }

            function checkAvailability() {
                const date = checkInInput.value;
                const type = reservationType.value;
                const stay = stayType.value;

                // For public reservations we need date + stay_type before we can check
                if (type === 'public' && date && stay) {
                    fetchAvailableCottages(date, stay);
                } else if (type === 'event' && date) {
                    resultsDiv.style.display = 'none';
                    cottageList.innerHTML = '';
                    updatePackageSummary();
                } else {
                    resultsDiv.style.display = 'none';
                    cottageList.innerHTML = '';
                    bookBtn.disabled = true;
                }
            }

            function fetchAvailableCottages(date, stay) {
                cottageList.replaceChildren();
                availableCottages = [];
                updateCottageSelection();
                const url = `{{ route('reservations.availability') }}?check_in=${date}&stay_type=${stay}`;

                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Could not check availability.');
                    return response.json();
                })
                .then(cottages => {
                    if (reservationType.value !== 'public' || checkInInput.value !== date || stayType.value !== stay) return;
                    cottageList.innerHTML = '';

                    if (cottages.length === 0) {
                        cottageList.innerHTML = '<p class="status">No cottages available for the selected date.</p>';
                        bookBtn.disabled = true;
                        resultsDiv.style.display = '';
                        return;
                    }

                    availableCottages = cottages.map(cottage => ({
                        ...cottage,
                        display_price: stay === 'overnight' ? cottage.price_overnight : cottage.price_day,
                    }));
                    cottages.forEach(cottage => {
                        const price = stay === 'overnight' ? cottage.price_overnight : cottage.price_day;
                        // CHANGED: each cottage is a selectable card (same checkbox name/value/capacity as before)
                        const label = document.createElement('label');
                        label.className = 'cottage';
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.name = 'cottage_ids[]';
                        checkbox.value = cottage.id;
                        checkbox.dataset.capacity = cottage.capacity;
                        const info = document.createElement('span');
                        const name = document.createElement('span');
                        name.className = 'cottage__name';
                        name.textContent = cottage.name;
                        const meta = document.createElement('span');
                        meta.className = 'cottage__meta';
                        meta.textContent = `Capacity: ${cottage.capacity} guests`;
                        info.append(name, meta);
                        const cost = document.createElement('span');
                        cost.className = 'cottage__price';
                        cost.textContent = `₱${parseFloat(price).toLocaleString()}`;
                        label.append(checkbox, info, cost);
                        cottageList.appendChild(label);
                    });
                    resultsDiv.style.display = '';
                    renderCottages();
                })
                .catch(err => {
                    if (reservationType.value !== 'public' || checkInInput.value !== date || stayType.value !== stay) return;
                    bookBtn.disabled = true;
                    console.error('Availability check failed:', err);
                    cottageList.innerHTML = '<p class="status">Could not check availability. Please try again.</p>';
                });
            }

            updateReservationFields();
            if (reservationType.value) refreshDisabledDates();
        });
    </script>
@endpush
