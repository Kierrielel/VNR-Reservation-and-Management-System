<link href="{{ asset('css/home.css') }}" rel="stylesheet">
<link href="{{ asset('css/accommodation.css') }}" rel="stylesheet">

<section id="home" class="main-accommodations">
    <div class="main-content">
        <h1>Villa Natividad’s Resort &amp; Events Place</h1>
        <div class="about-divider"></div>
        <h1>Accommodations</h1>
    </div>
</section>


<section id="accommodations" class="acc-section">
    <p class="acc-eyebrow">REST &amp; REJUVENATE</p>
    <h2 class="acc-title">Our Accommodations</h2>
    <p class="acc-subtitle">
        Experience tropical serenity in our thoughtfully designed rooms and cottages.
        Whether you seek a romantic getaway or a family retreat, find your perfect
        sanctuary at Villa Natividad.
    </p>

    <div class="acc-grid">


        <article class="acc-card">
            <div class="acc-img">
                <img src="{{ asset('images/cottage.jpg') }}" alt="Deluxe Room">
            </div>
            <div class="acc-body">
                <div class="acc-head">
                    <h3>Deluxe Room</h3>
                    <span class="acc-price">₱4,500<small>/n</small></span>
                </div>
                <p class="acc-desc">
                    A spacious, elegantly appointed room perfect for couples, featuring a
                    private balcony overlooking the gardens.
                </p>
                <ul class="acc-features">
                    <li><i class="fa-solid fa-user-group"></i> 5 Guests</li>
                    <li><i class="fa-solid fa-bed"></i> 1 King Bed</li>
                    <li><i class="fa-regular fa-snowflake"></i> Air Conditioned</li>
                    <li><i class="fa-solid fa-wifi"></i> Free Wifi</li>
                </ul>
                <a href="#" class="acc-btn">Book This Room</a>
            </div>
        </article>


        <article class="acc-card">
            <div class="acc-img">
                <img src="{{ asset('images/bed2.jpg') }}" alt="Family Cottage">
            </div>
            <div class="acc-body">
                <div class="acc-head">
                    <h3>Family Cottage</h3>
                    <span class="acc-price">₱8,500<small>/n</small></span>
                </div>
                <p class="acc-desc">
                    Ideal for family getaways, offering ample space, a comfortable living
                    area, and direct access to the main pool.
                </p>
                <ul class="acc-features">
                    <li><i class="fa-solid fa-user-group"></i> 10 Guests</li>
                    <li><i class="fa-solid fa-bed"></i> 3 Queen Beds</li>
                    <li><i class="fa-solid fa-snowflake"></i> Mini Fridge</li>
                    <li><i class="fa-solid fa-tv"></i> Smart TV</li>
                </ul>
                <a href="#" class="acc-btn">Book This Room</a>
            </div>
        </article>


        <article class="acc-card">
            <div class="acc-img">
                <img src="{{ asset('images/vipbed.jpg') }}" alt="VIP Suite">
            </div>
            <div class="acc-body">
                <div class="acc-head">
                    <h3>VIP Suite</h3>
                    <span class="acc-price">₱12,000<small>/n</small></span>
                </div>
                <p class="acc-desc">
                    The pinnacle of luxury. Featuring a private plunge pool, expansive
                    living quarters, and exclusive premium amenities.
                </p>
                <ul class="acc-features">
                    <li><i class="fa-solid fa-user-group"></i> 2 Guests</li>
                    <li><i class="fa-solid fa-bed"></i> 1 King Bed</li>
                    <li><i class="fa-solid fa-bell-concierge"></i> Room Services</li>
                    <li><i class="fa-solid fa-tv"></i> Smart TV</li>
                </ul>
                <a href="#" class="acc-btn">Book This Room</a>
            </div>
        </article>

    </div>
</section>



<section id="gallery" class="gal-section">
    <h2 class="gal-title">The Resort Experience</h2>
    <p class="gal-subtitle">
        Explore the beauty of Villa Natividad. From our expansive pools and lush
        gardens to our elegant event spaces.
    </p>

    <div class="gal-grid">
        <div class="gal-item gal-tall"><img src="{{ asset('images/villa-bg.jpg') }}" alt="Pool"></div>
        <div class="gal-item gal-tall"><img src="{{ asset('images/prayer.jpg') }}" alt="Grotto"></div>
        <div class="gal-item"><img src="{{ asset('images/cottage.jpg') }}" alt="Cottage"></div>
        <div class="gal-item"><img src="{{ asset('images/pool2.jpg') }}" alt="Garden"></div>
        <div class="gal-item gal-tall"><img src="{{ asset('images/bed2.jpg') }}" alt="Room"></div>
    </div>
</section>