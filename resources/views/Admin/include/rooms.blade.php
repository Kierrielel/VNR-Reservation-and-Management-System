<section class="admin-rooms">

    <div class="rooms-header">
        <h1>Room & Cottage Management</h1>
        <a href="{{ route('admin.rooms.create') }}" class="room-button">Add room or cottage</a>
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

    <form method="GET" action="{{ route('admin.rooms.index') }}" class="rooms-filters">

        <div class="rooms-search">
            <span class="search-icon">⌕</span>

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                aria-label="Search rooms or cottages"
                maxlength="100"
                placeholder="Search room or cottages..."
            >
        </div>


        <select name="type" class="rooms-filter" aria-label="Accommodation type">
            <option value="">All types</option>
            <option value="cottage" @selected(request('type') === 'cottage')>Cottages</option>
            <option value="room" @selected(request('type') === 'room')>Rooms</option>
        </select>


        <select name="status" class="rooms-filter" aria-label="Operational status">
            <option value="">All Status</option>
            <option value="available" @selected(request('status') === 'available')>Available</option>
            <option value="occupied" @selected(request('status') === 'occupied')>Occupied</option>
            <option value="unavailable" @selected(request('status') === 'unavailable')>Unavailable</option>
        </select>

        <button type="submit" class="room-button">Filter</button>
        <a href="{{ route('admin.rooms.index') }}" class="room-button room-button-secondary">Reset</a>
    </form>


    <!-- Cottage Table -->
    <div class="rooms-table-container">

        <table class="rooms-table">

            <thead>
                <tr>
                    <th>ROOM/COTTAGE</th>
                    <th>TYPE</th>
                    <th>CAPACITY</th>
                    <th>PRICE</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>


            <tbody>

                @forelse ($cottages as $cottage)

                    <tr>

                        <td>
                            {{ $cottage->name }}
                        </td>


                        <td>
                            {{ ucfirst($cottage->type) }}
                        </td>


                        <td>
                            {{ $cottage->capacity }} Guest
                        </td>


                        <td>
                            Day: ₱{{ number_format($cottage->price_day, 2) }}
                            <small class="room-overnight-price">
                                {{ $cottage->price_overnight === null ? 'Day tour only' : 'Overnight: ₱'.number_format($cottage->price_overnight, 2) }}
                            </small>
                        </td>


                        <td>
                            {{ ucfirst($cottage->status) }}
                        </td>


                        <td class="room-actions">

                            <!-- Edit -->
                            <a
                                href="{{ route('admin.rooms.edit', $cottage) }}"
                                class="edit-btn"
                                aria-label="Edit {{ $cottage->name }}"
                            >
                                <img
                                    src="{{ asset('images/bookings-edit.svg') }}"
                                    alt="Edit"
                                >
                            </a>


                            <!-- Delete -->
                            @if (! $cottage->bookings_exists)
                                <form method="POST" action="{{ route('admin.rooms.destroy', $cottage) }}" data-confirm
                                    data-confirm-title="Delete accommodation?"
                                    data-confirm-message="This unused room or cottage will be permanently deleted."
                                    data-confirm-label="Yes, delete">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="delete-btn" aria-label="Delete {{ $cottage->name }}">
                                        <img src="{{ asset('images/bookings-delete.svg') }}" alt="Delete">
                                    </button>
                                </form>
                            @else
                                <small>Booking history kept</small>
                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="6" class="no-results">
                            No rooms or cottages found.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>


        <!-- Pagination -->
        <div class="rooms-pagination">

            <span>
                Showing
                {{ $cottages->firstItem() ?? 0 }}
                to
                {{ $cottages->lastItem() ?? 0 }}
                of
                {{ $cottages->total() }}
                results
            </span>


            <div class="pagination-buttons">

                {{-- Previous --}}

                @if ($cottages->onFirstPage())

                    <button type="button" disabled>
                        ‹
                    </button>

                @else

                    <a href="{{ $cottages->previousPageUrl() }}">
                        ‹
                    </a>

                @endif


                {{-- Page Numbers --}}

                @for ($page = max(1, $cottages->currentPage() - 2); $page <= min($cottages->lastPage(), $cottages->currentPage() + 2); $page++)

                    @if ($page == $cottages->currentPage())

                        <a
                            href="{{ $cottages->url($page) }}"
                            class="active"
                        >
                            {{ $page }}
                        </a>

                    @else

                        <a href="{{ $cottages->url($page) }}">
                            {{ $page }}
                        </a>

                    @endif

                @endfor


                {{-- Next --}}

                @if ($cottages->hasMorePages())

                    <a href="{{ $cottages->nextPageUrl() }}">
                        ›
                    </a>

                @else

                    <button type="button" disabled>
                        ›
                    </button>

                @endif

            </div>

        </div>

    </div>

</section>
