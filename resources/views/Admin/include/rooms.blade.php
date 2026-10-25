<section class="admin-rooms">

    <div class="rooms-header">
        <h1>Room & Cottage Management</h1>
    </div>


    <!-- Search and Filters -->
    <div class="rooms-filters">

        <div class="rooms-search">
            <span class="search-icon">⌕</span>

            <input
                type="text"
                placeholder="Search room or cottages..."
            >
        </div>


        <select class="rooms-filter">
            <option value="">Cottages</option>
            <option value="cottage">Cottages</option>
            <option value="room">Rooms</option>
        </select>


        <select class="rooms-filter">
            <option value="">All Status</option>
            <option value="available">Available</option>
            <option value="occupied">Occupied</option>
        </select>

    </div>


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
                            ₱{{ number_format($cottage->price_day, 0) }}
                        </td>


                        <td>
                            {{ ucfirst($cottage->status) }}
                        </td>


                        <td class="room-actions">

                            <!-- Edit -->
                            <button
                                type="button"
                                class="edit-btn"
                                title="Edit"
                            >
                                <img
                                    src="{{ asset('images/bookings-edit.svg') }}"
                                    alt="Edit"
                                >
                            </button>


                            <!-- Delete -->
                            <button
                                type="button"
                                class="delete-btn"
                                title="Delete"
                            >
                                <img
                                    src="{{ asset('images/bookings-delete.svg') }}"
                                    alt="Delete"
                                >
                            </button>

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

                @for ($page = 1; $page <= $cottages->lastPage(); $page++)

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