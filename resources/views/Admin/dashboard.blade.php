@include('Admin.include.admin-header')

<link href="{{ asset('css/admin-dashboard.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin-reviews.css') }}">

<div class="admin-container">

    <aside class="admin-sidebar">
        <div class="sidebar_content">
            <div class="sidebar-options">
                <span class="sidebar-title">Management Tools</span>
                <div class="dashboard-logo-cont">
                    <img src="../images/dashboard.svg" class="dashboard-logo" alt="Dashboard Logo" width="18px" height="18px">
                    <span class="sidebar-title-dashboard">Dashboard</span>
                </div>
                <div class="rooms-logo-cont">
                    <img src="../images/rooms.svg" class="rooms-logo" alt="Rooms Logo" width="20px" height="14px">
                    <span class="sidebar-title-rooms">Rooms/Cottages</span>
                </div>
                <div class="bookings-logo-cont">
                    <img src="../images/booking.svg" class="booking-logo" alt="Booking Logo" width="20px" height="19.5px">
                    <span class="sidebar-title-bookings">Bookings</span>
                </div>
                <div class="announce-logo-cont">
                    <img src="../images/announce.svg" class="announce-logo" alt="Announcement Logo" width="24px" height="24px">
                    <span class="sidebar-title-announcements">Announcements</span>
                </div>

                <a href="{{ route('admin.reviews.index') }}" 
                     class="reviews-logo-cont {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}">
                    <img src="../images/reviews.svg" class="reviews-logo" alt="Reviews Logo" width="24px" height="24px">
                    <span class="sidebar-title-reviews">Reviews</span>
                </a>
                
                <div class="reports-logo-cont">
                    <img src="../images/reports.svg" class="reports-logo" alt="Reports Logo" width="18.75px" height="18.75px">
                    <span class="sidebar-title-reports">Reports</span>
                </div>
                <div class="sett-logo-cont">
                    <img src="../images/settings.svg" class="settings-logo" alt="Settings Logo" width="19.04px" height="20px">
                    <span class="sidebar-title-settings">Settings</span>
                </div>
            </div>
            <div class="sidebar-logout">
                <div class="logout-logo-cont">
                    <img src="../images/Logout.svg" class="logout-logo" alt="Logout Logo" width="33px" height="35px">
                    <button type="logout" class="auth-logout-btn">Logout</button>
                </div>
            </div>
        </div>
    </aside>

    <main class="admin-dashboard">
        
        @if (!request()->routeIs('admin.reviews.*'))
            <div class="dashboard-content">
                <div class="dashboard-info">
                    <span class="dashboard-title">ADMIN DASHBOARD</span>
                    <span class="dashboard-greet">Welcome back, Admin!</span>
                    <span class="dashboard-desc">Welcome back, Admin. Here's what's happening today</span>
                </div>
                <div class="totals-cont">
                    <div class="info-cont">
                        <span class="total-bookings-title">TOTAL BOOKINGS</span>

                    </div>
                    <div class="info-cont">
                        <span class="total-rev-title">TOTAL REVENUE</span>

                    </div>
                    <div class="info-cont">
                        <span class="pending-title">PENDING</span>

                    </div>
                    <div class="info-cont">
                        <span class="avail-cottage-title">AVAILABLE COTTAGE/ROOM</span>

                    </div>
                </div>
                <div class="analytics-cont">
                    <div class="rev-analytics-cont">
                        <span class="analytics-title"> Revenue Analytics </span>
                    </div>
                    <div class="occupancy-alert-cont">
                        <span class="occupancy-alert-title"> Occupancy Alert </span>
                        <br>
                        <span class="cottage-occupancy"> Cottage Occupancy</span>
                    </div>
                </div>
                <div class="recent-book-cont">
                    <div class="recent-book-info">
                        <span class="recent-bookings"> Recent Bookings</span>
                    </div>
                </div>
            </div>
        @endif

        @if (request()->routeIs('admin.reviews.*'))
            @include('Admin.include.review')
        @endif
        
    </main>

</div>