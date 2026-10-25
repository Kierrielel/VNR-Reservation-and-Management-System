<section class="admin-reports">

    <div class="reports-header">
        <h1>Revenue Analytics</h1>
    </div>

   <div class="reports-layout">

    <!-- LEFT SIDE -->
    <div class="reports-main">

        <!-- Summary Cards -->
        <div class="report-summary">

            <div class="report-card customers-card">
                <div class="report-card-content">
                    <p>Total Customers</p>
                    <h2>105</h2>
                    <span>Overview of<br>Monthly Customers</span>

                    <div class="progress-line"></div>
                </div>
            </div>

            <div class="report-card revenue-card">
                <div class="report-card-content">
                    <p>Net Revenue</p>
                    <h2>₱203.4K</h2>
                    <span>Overview of<br>Monthly Income</span>
                </div>

                <div class="revenue-circle"></div>
            </div>

        </div>

        <!-- GRAPH + CALENDAR -->
        <div class="reports-bottom">

            <!-- Revenue Chart -->
            <div class="revenue-chart-container">

                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>

            </div>

            <!-- Calendar -->
            <div class="reports-side">

                <div class="monthly-calendar">

                    <h2>Monthly Calendar</h2>

                    <div class="calendar-line"></div>

                    <div class="calendar-days">
                        <span>Mon</span>
                        <span>Tue</span>
                        <span>Wed</span>
                        <span>Thu</span>
                        <span>Fri</span>
                        <span>Sat</span>
                        <span>Sun</span>
                    </div>

                    <div class="calendar-dates">

                        <span>1</span>
                        <span class="selected-date">2</span>
                        <span>3</span>
                        <span>4</span>
                        <span>5</span>
                        <span>6</span>
                        <span>7</span>

                        <span>8</span>
                        <span>9</span>
                        <span>10</span>
                        <span>11</span>
                        <span>12</span>
                        <span>13</span>
                        <span>14</span>

                        <span>15</span>
                        <span>16</span>
                        <span>17</span>
                        <span>18</span>
                        <span>19</span>
                        <span>20</span>
                        <span>21</span>

                        <span>22</span>
                        <span>23</span>
                        <span>24</span>
                        <span>25</span>
                        <span>26</span>
                        <span>27</span>
                        <span>28</span>

                        <span>29</span>
                        <span>30</span>
                        <span>31</span>

                    </div>

                </div>

                <!-- Date Range -->
                <div class="report-date-range">

                    <div class="date-selector">
                        <span>▣</span>
                        <span>Jan 01, 2026 - Dec 31, 2026</span>
                        <span>⌄</span>
                    </div>

                    <div class="report-buttons">
                        <button type="button">PDF</button>
                        <button type="button">Excel</button>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</section>

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