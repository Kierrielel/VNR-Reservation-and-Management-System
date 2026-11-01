@extends('LoginReg.layout')

@section('title', 'Book Your Stay | Villa Natividad')

@push('styles')
    <link href="{{ asset('css/book.css') }}" rel="stylesheet">
@endpush

@section('content')
    @include('LoginReg.include.home.book')
    <section>
        <form action="{{ route($walkIn ? 'walk-ins.store' : 'reservations.store') }}" method="post">
        @csrf
            @if ($walkIn)
                <h1>Walk-in booking</h1>
                <p><a href="{{ route('check-in.index') }}">Back to staff check-in</a></p>
                <p>Enter the guest's details. No customer account is required. Payment is recorded after creating the booking.</p>
                <div>
                    <label for="guest_name">Guest name</label>
                    <input id="guest_name" name="guest_name" value="{{ old('guest_name') }}" maxlength="255" required>
                </div>
                <div>
                    <label for="guest_contact">Contact number</label>
                    <input id="guest_contact" name="guest_contact" type="tel" value="{{ old('guest_contact') }}" maxlength="50" required>
                </div>
                <div>
                    <label for="guest_email">Email (optional)</label>
                    <input id="guest_email" name="guest_email" type="email" value="{{ old('guest_email') }}" maxlength="255">
                </div>
            @endif
            @if ($errors->any())
                <div role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label for="reservation_type">Select Reservation type</label>
                <select name="reservation_type" id="reservation_type" required>
                    <option value="" disabled selected>Select a reservation type</option>
                    <option value="public" @selected(old('reservation_type') === 'public')>Public Pool</option>
                    <option value="event" @selected(old('reservation_type') === 'event')>Event</option>
                </select>
            </div>

            {{-- Only shown for public pool reservations --}}
            <div id="stay-type-group" style="display:none;">
                <label for="stay_type">Stay Type</label>
                <select name="stay_type" id="stay_type">
                    <option value="" disabled selected>Select stay type</option>
                    <option value="day_tour" @selected(old('stay_type') === 'day_tour')>Day Tour</option>
                    <option value="overnight" @selected(old('stay_type') === 'overnight')>Overnight</option>
                </select>
            </div>

            <div id="event-package-group" hidden>
                <label for="event_package_id">Event Package</label>
                <select name="event_package_id" id="event_package_id" disabled>
                    <option value="">Select an event package</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" data-price="{{ $package->price }}"
                            @selected((string) old('event_package_id') === (string) $package->id)>
                            {{ $package->name }} — ₱{{ number_format($package->price, 2) }} ({{ $package->hours }} hours)
                        </option>
                    @endforeach
                </select>
                @if ($packages->isEmpty())
                    <p>No event packages are currently available.</p>
                @endif
                <dl id="event-price-summary" aria-live="polite" hidden>
                    <dt>Package price</dt><dd id="event-total"></dd>
                    <dt>Reservation fee ({{ $reservationFeePercent }}%)</dt><dd id="event-fee"></dd>
                    <dt>Remaining balance</dt><dd id="event-balance"></dd>
                </dl>
            </div>

            <div>
                <label for="check_in">Select Date</label>
                <input type="text" name="check_in" id="check_in" value="{{ old('check_in') }}" placeholder="Select Date..." required>
                <p id="calendar-status" role="status"></p>
            </div>

            <div>
                <label for="guest_count">Number of Guests</label>
                <input type="number" name="guest_count" id="guest_count" value="{{ old('guest_count') }}" min="1" placeholder="Enter number of guests" required>
            </div>

            <div id="availability-results" style="display:none;">
                <label>Available Cottages</label>
                <div id="cottage-list"></div>
                <p id="cottage-selection-status" role="status" aria-live="polite">Choose up to 2 accommodations.</p>
            </div>

            <div>
                <label for="additional_message">Additional Message (optional)</label>
                <textarea name="additional_message" id="additional_message" rows="3" placeholder="Any special requests...">{{ old('additional_message') }}</textarea>
            </div>

            <button type="submit" id="book-btn" disabled>{{ $walkIn ? 'Create walk-in booking' : 'Book Now' }}</button>
        </form>
    </section>
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
            const bookBtn        = document.getElementById('book-btn');
            const packageGroup = document.getElementById('event-package-group');
            const packageSelect = document.getElementById('event_package_id');
            const priceSummary = document.getElementById('event-price-summary');
            const calendarStatus = document.getElementById('calendar-status');
            const feePercent = @json($reservationFeePercent);
            const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
            let calendarReady = false;
            let calendarRequest = 0;

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

            cottageList.addEventListener('change', updateCottageSelection);
            guestCount.addEventListener('input', updateCottageSelection);

            packageSelect.addEventListener('change', updatePackageSummary);

            const picker = flatpickr("#check_in", {
                altInput: true,
                altFormat: "F j, Y",
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
                calendarStatus.textContent = '';
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
                        cottageList.innerHTML = '<p>No cottages available for the selected date.</p>';
                        bookBtn.disabled = true;
                        resultsDiv.style.display = '';
                        return;
                    }

                    cottages.forEach(cottage => {
                        const price = stay === 'overnight' ? cottage.price_overnight : cottage.price_day;
                        const label = document.createElement('label');
                        label.style.display = 'block';
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.name = 'cottage_ids[]';
                        checkbox.value = cottage.id;
                        checkbox.dataset.capacity = cottage.capacity;
                        label.append(checkbox, document.createTextNode(
                            `${cottage.name} — ₱${parseFloat(price).toLocaleString()} (capacity: ${cottage.capacity} guests)`
                        ));
                        cottageList.appendChild(label);
                    });

                    resultsDiv.style.display = '';
                    updateCottageSelection();
                })
                .catch(err => {
                    if (reservationType.value !== 'public' || checkInInput.value !== date || stayType.value !== stay) return;
                    bookBtn.disabled = true;
                    console.error('Availability check failed:', err);
                    cottageList.innerHTML = '<p>Could not check availability. Please try again.</p>';
                });
            }

            updateReservationFields();
            if (reservationType.value) refreshDisabledDates();
        });
    </script>
@endpush
