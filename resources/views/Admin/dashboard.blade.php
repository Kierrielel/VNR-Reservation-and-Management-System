@include('Admin.include.admin-header')

<link href="{{ asset('css/admin-dashboard.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin-reviews.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-settings.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-announcements.css') }}">

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
                <a href="{{ route('admin.announcements.index') }}"
                    class="announce-logo-cont {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                    <img src="../images/announce.svg" class="announce-logo" alt="Announcement Logo" width="24px" height="24px">
                     <span class="sidebar-title-announcements">Announcements</span>
                </a>

                <a href="{{ route('admin.reviews.index') }}" 
                     class="reviews-logo-cont {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}">
                    <img src="../images/reviews.svg" class="reviews-logo" alt="Reviews Logo" width="24px" height="24px">
                    <span class="sidebar-title-reviews">Reviews</span>
                </a>
                
                <div class="reports-logo-cont">
                    <img src="../images/reports.svg" class="reports-logo" alt="Reports Logo" width="18.75px" height="18.75px">
                    <span class="sidebar-title-reports">Reports</span>
                </div>
                <a href="{{ route('admin.settings.index') }}"
                    class="sett-logo-cont {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <img src="../images/settings.svg" class="settings-logo" alt="Settings Logo" width="19.04px" height="20px">
                    <span class="sidebar-title-settings">Settings</span>
                </a>
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
        
       @if (!request()->routeIs('admin.reviews.*', 'admin.settings.*', 'admin.announcements.*', 'admin.rooms.*'))

            <div class="dashboard-content">
                <div class="dashboard-info">
                    <span class="dashboard-title">ADMIN DASHBOARD</span>
                    <span class="dashboard-greet">Welcome back, Admin!</span>
                    <span class="dashboard-desc">Welcome back, Admin. Here's what's happening today</span>
                </div>
                <div class="totals-cont">
                    <div class="info-cont">
                        <img src="../images/total-bookings-logo.svg" class="total-bookings-logo" alt="Total Bookings Logo" width="59px" height="59px">
                        <br>
                        <span class="total-bookings-title">TOTAL BOOKINGS</span>
                        <br>
                        <span class="result"> 00 </span>

                    </div>
                    <div class="info-cont">
                        <img src="../images/total-revenue-logo.svg" class="total-revenue-logo" alt="Total Revenue Logo" width="59px" height="59px">
                        <br>
                        <span class="total-rev-title">TOTAL REVENUE</span>
                        <br>
                        <span class="result"> ₱00.00 </span>

                    </div>
                    <div class="info-cont">
                        <img src="../images/pending-logo.svg" class="pending-logo" alt="Pending Logo" width="59px" height="59px">
                        <br>
                        <span class="pending-title">PENDING</span>
                        <br>
                        <span class="result"> 00 </span>

                    </div>
                    <div class="info-cont">
                        <img src="../images/available-room-logo.svg" class="available-room-logo" alt="Available Cottage Logo" width="59px" height="59px">
                        <span class="avail-cottage-title">AVAILABLE <br>COTTAGE/ROOM</span>
                        <br>
                        <span class="result"> 0/8 </span>

                    </div>
                </div>
                <div class="analytics-cont">
                    <div class="rev-analytics-cont">
                        <span class="analytics-title"> Revenue Analytics </span>

                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                         </div>
                    </div>
                    <div class="occupancy-alert-cont">
                        <span class="occupancy-alert-title"> Occupancy Alert </span>
                        <br>
                        <span class="cottage-occupancy"> Cottage Occupancy</span>
                        <br>
                        <br>
                        <span class="cottage-occupancy-value"> 00.00%</span>
                        <br>
                        <br>
                        <span class="cottage-occupancy-review"> Review Allocations</span>
                    </div>
                </div>
                <div class="recent-book-cont">
                    <div class="recent-book-info">
                        <span class="recent-bookings"> Recent Bookings</span>
                        <hr>
                    </div>
                </div>
            </div>
        @endif

        @if (request()->routeIs('admin.reviews.*'))
            @include('Admin.include.review')
        @endif

        @if (request()->routeIs('admin.settings.*'))
            @include('Admin.include.settings')
        @endif
         @if (request()->routeIs('admin.rooms.*'))
            @include('Admin.include.rooms')
        @endif
        
        @if (request()->routeIs('admin.announcements.*'))
            @include('Admin.include.announcements')
        @endif
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const ctx = document.getElementById('revenueChart');

    const chart = new Chart(ctx, {
        type: 'line',

        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],

            datasets: [{
                label: 'Sales',
                data: [4000, 2800, 2000, 3123, 1900, 2200, 3500],

                borderColor: '#36A9E1',
                borderWidth: 2,

                backgroundColor: 'rgba(54, 169, 225, 0.25)',

                fill: true,

                tension: 0.4,

                pointRadius: 0,
                pointHoverRadius: 5,

                pointBackgroundColor: '#36A9E1',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            animation: {
                duration: 2000,
                easing: 'easeInOutQuart',

                x: {
                    duration: 2000,
                    easing: 'linear'
                },

                y: {
                    duration: 2000,
                    easing: 'easeOutQuart'
                }
            },

            interaction: {
                mode: 'index',
                intersect: false
            },

            plugins: {
                legend: {
                    display: false
                },

                tooltip: {
                    displayColors: false,

                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        },

                        label: function(context) {
                            return 'sales: ' + context.parsed.y;
                        }
                    }
                }
            },

            scales: {
                x: {
                    grid: {
                        display: false
                    },

                    border: {
                        display: false
                    }
                },

                y: {
                    min: 0,
                    max: 4000,

                    ticks: {
                        stepSize: 1000
                    },

                    grid: {
                        color: 'rgba(120, 120, 120, 0.4)',
                        borderDash: [3, 3]
                    },

                    border: {
                        display: false
                    }
                }
            }
        }
    });
</script>