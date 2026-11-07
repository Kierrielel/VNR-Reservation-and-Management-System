<section class="admin-rooms">
    <div class="rooms-header">
        <h1>{{ $cottage->exists ? 'Edit '.$cottage->name : 'Add Room or Cottage' }}</h1>
    </div>

    @if (session('success'))
        <p class="room-alert room-alert-success" role="status">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <div class="room-alert room-alert-error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $cottage->exists ? route('admin.rooms.update', $cottage) : route('admin.rooms.store') }}" class="room-form">
        @csrf
        @if ($cottage->exists)
            @method('PUT')
        @endif
        <div class="room-form-grid">
            <label for="room-name">Name
                <input id="room-name" name="name" value="{{ old('name', $cottage->name) }}" maxlength="100" required>
            </label>
            <label for="room-type">Type
                <select id="room-type" name="type" required>
                    <option value="cottage" @selected(old('type', $cottage->type) === 'cottage')>Cottage</option>
                    <option value="room" @selected(old('type', $cottage->type) === 'room')>Room</option>
                </select>
            </label>
            <label for="room-capacity">Guest capacity
                <input id="room-capacity" type="number" name="capacity" value="{{ old('capacity', $cottage->capacity) }}" min="1" max="65535" required>
            </label>
            <label for="room-status">Operational status
                <select id="room-status" name="status" required>
                    @foreach (['available' => 'Available', 'occupied' => 'Occupied', 'unavailable' => 'Unavailable'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $cottage->status ?? 'available') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label for="room-day-price">Day-tour price (₱)
                <input id="room-day-price" type="number" name="price_day" value="{{ old('price_day', $cottage->price_day) }}" min="0" max="99999999.99" step="0.01" required>
            </label>
            <label for="room-overnight-price">Overnight price (₱, optional)
                <input id="room-overnight-price" type="number" name="price_overnight" value="{{ old('price_overnight', $cottage->price_overnight) }}" min="0" max="99999999.99" step="0.01">
            </label>
        </div>
        <p>Leave the overnight price empty for day-tour-only accommodation.</p>
        <p>Unavailable and occupied units are excluded from new reservations. Existing bookings are kept.</p>
        <div class="room-form-actions">
            <button type="submit" class="room-button">{{ $cottage->exists ? 'Save changes' : 'Add accommodation' }}</button>
            <a href="{{ route('admin.rooms.index') }}" class="room-button room-button-secondary">Back to list</a>
        </div>
    </form>
</section>
