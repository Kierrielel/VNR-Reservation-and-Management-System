<section class="admin-bookings">

    <div class="bookings-header">
        <h1>Booking Management</h1>
    </div>

    <div class="bookings-table-container">

        <table class="bookings-table">

            <thead>
                <tr>
                    <th>GUEST NAME</th>
                    <th>DATE</th>
                    <th>TYPE</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>

            <tbody>

                <tr>
                    <td>Juan S. Makasalanan</td>
                    <td>September 3, 2026</td>
                    <td>Public</td>
                    <td>Confirmed</td>
                    <td>
                        <div class="booking-actions">
                            <button type="button" class="edit-booking">
                                <img src="{{ asset('images/bookings-edit.svg') }}" alt="Edit">
                            </button>

                            <button type="button" class="delete-booking">
                                <img src="{{ asset('images/bookings-delete.svg') }}" alt="Delete">
                            </button>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td>Aaron Caño</td>
                    <td>September 18, 2026</td>
                    <td>Public</td>
                    <td>Confirmed</td>
                    <td>
                        <div class="booking-actions">
                            <button type="button" class="edit-booking">
                                <img src="{{ asset('images/bookings-edit.svg') }}" alt="Edit">
                            </button>

                            <button type="button" class="delete-booking">
                                <img src="{{ asset('images/bookings-delete.svg') }}" alt="Delete">
                            </button>
                        </div>
                    </td>
                </tr>

            </tbody>

        </table>

        <div class="bookings-footer">

            <span class="booking-results">
                Showing 1 to 2 of 2 results
            </span>

            <div class="booking-pagination">

                <button type="button" class="page-arrow">
                    ‹
                </button>

                <button type="button" class="page-number active">
                    1
                </button>

                <button type="button" class="page-number">
                    2
                </button>

                <button type="button" class="page-number">
                    3
                </button>

                <button type="button" class="page-arrow">
                    ›
                </button>

            </div>

        </div>

    </div>

</section>