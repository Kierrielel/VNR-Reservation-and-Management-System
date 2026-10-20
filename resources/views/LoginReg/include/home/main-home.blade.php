<link href="{{ asset('css/home.css') }}?v={{ filemtime(public_path('css/home.css')) }}" rel="stylesheet">



<section id="home" class="main">

    <div class="main-content">
        <h1>Villa Natividad’s Resort &amp; Events Place</h1>
          <div class="about-divider"></div>
        <h1>Home</h1>
    </div>
</section>

<section class="welcome" id="welcome">
    <h2 class="welcome-title">Welcome to Villa Natividad</h2>
    <p class="welcome-tagline">A relaxing destination for family gatherings, celebrations, and unforgettable memories.</p>
    <p class="welcome-text">Villa Natividad Resort & Events Place offers a peaceful environment with swimming pools, comfortable accommodations, and spacious event venues for birthdays, weddings, reunions, corporate events, and other special occasions. Whether you're planning a day of relaxation or a memorable celebration, we provide the perfect setting for every guest.</p>
    <a href="#" class="btn-learn">Learn More</a>
</section>

<section class="highlights" id="amenities">
    <h2 class="highlights-title">Everything You Need for a Perfect Stay</h2>

    <div class="highlights-grid">
        <div class="highlight-card">
            <img src="{{ asset('images/pool.jpg') }}" alt="Swimming pool">
        </div>
        <div class="highlight-card">
            <img src="{{ asset('images/bed.jpg') }}" alt="Bed">
        </div>
        <div class="highlight-card">
            <img src="{{ asset('images/deb.jpg') }}" alt="Event">
        </div>
    </div>
</section>

<section class="reviews" id="reviews">
    @include('LoginReg.include.home.reviews')
</section>

<section class="about" id="about">
    <h2 class="about-title">About Us</h2>
    <p class="about-text">
        Welcome to Villa Natividad, where nature, comfort, and unforgettable celebrations come together. Our resort and events place offers a peaceful escape and a beautiful setting for weddings, birthdays, corporate events, family gatherings, and relaxing getaways. With quality facilities, warm hospitality, and a commitment to exceptional service, we strive to make every visit memorable and every celebration truly special.
    </p>
</section>