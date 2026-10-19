<link href="{{ asset('css/home.css') }}" rel="stylesheet">
<link href="{{ asset('css/events.css') }}" rel="stylesheet">

<section id="home" class="main-events">
    <div class="main-content">
        <h1>Villa Natividad’s Resort &amp; Events Place</h1>
        <div class="about-divider"></div>
        <h1>Events</h1>
    </div>
</section>


<section id="venues">

    <h2>Our Signature Venues</h2>

    <div class="venues-grid">


        <div class="venue-card">
            <div class="venue-image">
                <img src="{{ asset('images/pool2.jpg') }}" alt="Swimming Pool">
            </div>

            <div class="venue-info">
                <div class="venue-title">
                    <h3>Swimming Pool</h3>
                    <span class="venue-badge">50+ CAPACITY</span>
                </div>

                <p>
                    Enjoy a refreshing swim in our adult and kiddie pools, designed for
                    fun, relaxation, and family bonding. With crystal-clear water and a
                    clean, well-maintained environment.
                </p>

                <a href="#" class="venue-link">VIEW DETAILS &rarr;</a>
            </div>
        </div>

        <div class="venue-card">
            <div class="venue-image">
                <img src="{{ asset('images/deb2.jpg') }}" alt="Event Pavilion">
            </div>

            <div class="venue-info">
                <div class="venue-title">
                    <h3>Event Pavilion</h3>
                    <span class="venue-badge">200+ CAPACITY</span>
                </div>

                <p>
                    Host weddings, birthdays, and special occasions in our spacious
                    venue, designed to create unforgettable celebrations.
                </p>

                <a href="#" class="venue-link">VIEW DETAILS &rarr;</a>
            </div>
        </div>

    </div>

</section>


<section id="events">

    <h2>Remarkable Events</h2>
    <p class="events-subtitle">
        Celebrate weddings, birthdays, and special occasions in a spacious
        venue designed for unforgettable moments.
    </p>

    <div class="events-grid">

        <div class="event-card">
            <img src="{{ asset('images/wed.jpg') }}" alt="Weddings">
            <div class="event-text">
                <h3>Weddings</h3>
                <p>Celebrate your love in a beautiful setting made for unforgettable "I do" moments.</p>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('images/fam.jpg') }}" alt="Family Gathering">
            <div class="event-text">
                <h3>Family Gathering</h3>
                <p>Bring everyone together in a roomy space made for reunions and quality time.</p>
            </div>
        </div>

        <div class="event-card event-card-wide">
            <img src="{{ asset('images/birthday.jpg') }}" alt="Birthday Celebrations">
            <div class="event-text">
                <h3>Birthday Celebrations</h3>
                <p>Make every birthday extra special with a beautiful space designed for fun, laughter, and lasting memories.</p>
            </div>
        </div>

    </div>

</section>


<section id="packages">

    <h2>Event Packages</h2>
    <p class="packages-subtitle">
        Choose a package that fits your needs, or contact us to create a
        fully bespoke experience.
    </p>

    {{-- backend --}}

    <div class="packages-grid">

        {{-- Silver --}}
        <div class="package-card">
            <h3>Silver</h3>
            <p class="package-desc">Essential elegance for gatherings.</p>

            <p class="package-price">₱30k <span>/ Starting</span></p>

            <ul class="package-features">
                <li>5 Hours Venue Access</li>
                <li>Tables &amp; Chairs Arrangement</li>
                <li>Basic Floral Styling</li>
            </ul>

            <a href="#" class="package-btn">SELECT SILVER</a>
        </div>

  
        <div class="package-card package-popular">
            <span class="package-badge">MOST POPULAR</span>

            <h3>Gold</h3>
            <p class="package-desc">The gold standard for refined celebrations.</p>

            <p class="package-price">₱50k <span>/ Starting</span></p>

            <ul class="package-features">
                <li>8 Hours Venue Access</li>
                <li>Tables &amp; Chairs Setup</li>
                <li>Premium Floral Design &amp; Styling</li>
                <li>Basic Light &amp; Sound Setup</li>
            </ul>

            <a href="#" class="package-btn">SELECT GOLD</a>
        </div>

        {{-- Bronze --}}
        <div class="package-card">
            <h3>Bronze</h3>
            <p class="package-desc">Unrivaled luxury and complete customizations.</p>

            <p class="package-price">₱15k <span>/ Starting</span></p>

            <ul class="package-features">
                <li>3 Hours Venue Access</li>
                <li>Basic Venue Decoration</li>
                <li>Tables &amp; Chairs Access</li>
            </ul>

            <a href="#" class="package-btn">SELECT BRONZE</a>
        </div>

    </div>

</section>