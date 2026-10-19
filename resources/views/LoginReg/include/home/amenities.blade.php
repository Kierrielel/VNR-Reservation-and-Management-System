  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link href="{{ asset('css/amenities.css') }}" rel="stylesheet">
  <link href="{{ asset('css/about.css') }}" rel="stylesheet">
  <link href="{{ asset('css/home.css') }}" rel="stylesheet">

@php

    $amenities = [
        [
            'title' => 'Crystal Clear Pools',
            'text'  => 'Enjoy a refreshing swim in our adult and kiddie pools, designed for fun, relaxation, and family bonding. With crystal-clear water and a clean, well-maintained environment.',
            'tags'  => ['Safe for adults', 'Safe for Kids'],
            'color' => 'blue',
            'image' => 'images/villa-bg.jpg',
        ],
        [
            'title' => 'Cozy Accommodations',
            'text'  => 'Enjoy a comfortable stay in our charming cottages and rooms, where a peaceful setting and modern amenities create the perfect place to rest and recharge.',
            'tags'  => ['Luxury Suites', 'Smart Tech'],
            'color' => 'pink',
            'image' => 'images/bed.jpg',
        ],
        [
            'title' => 'Event Pavilions',
            'text'  => 'Celebrate life\'s special moments in our spacious event venues. Whether it\'s a wedding, birthday, corporate event, or family gathering, we have the perfect space to make your occasion memorable.',
            'tags'  => ['AC Available', '200+ Capacity'],
            'color' => 'pink',
            'image' => 'images/deb.jpg',
        ],
        [
            'title' => 'Green Garden',
            'text'  => 'Explore our beautiful gardens filled with vibrant plants and well-kept landscapes. It\'s a great place to relax, enjoy nature, and capture memorable photos.',
            'tags'  => ['Photoshoot Ready', 'Garden Walking'],
            'color' => 'blue',
            'image' => 'images/green-garden.jpg',
        ],
        [
            'title' => 'Traditional Nipa Hut',
            'text'  => 'Experience the charm of our traditional nipa huts, where you can relax, enjoy the fresh air, and spend quality time with family and friends. It\'s the perfect spot for picnics, gatherings, or simply unwinding in nature.',
            'tags'  => ['Native Comfort', 'Family Cottage'],
            'color' => 'blue',
            'image' => 'images/nippa.jpg',
        ],
        [
            'title' => 'Event Stage',
            'text'  => 'Host your special occasions on our spacious stage, ideal for ceremonies, performances, and community gatherings. Whether it\'s a grand celebration or an intimate event.',
            'tags'  => ['Stage Spotlight', 'Music Stage'],
            'color' => 'pink',
            'image' => 'images/stage.jpg',
        ],
        [
            'title' => 'Spacious Parking Area',
            'text'  => 'Our spacious parking area provides a safe and convenient place for guests to park their vehicles. With plenty of parking spaces available, you can enjoy your visit without worrying about finding a spot.',
            'tags'  => ['Easy Parking', 'Secure Parking'],
            'color' => 'blue',
            'image' => 'images/park1.jpg',
        ],
        [
            'title' => 'Prayer Garden',
            'text'  => 'Escape the noise and enjoy the peaceful surroundings of our Prayer Garden. With beautiful plants and a quiet environment, it\'s an ideal place for prayer, reflection, or simply taking a moment to unwind.',
            'tags'  => ['Sacred Space', 'Divine Peace'],
            'color' => 'pink',
            'image' => 'images/prayer1.jpg',
        ],
    ];
@endphp

<section id="home" class="main-amenities">
    <div class="main-content">
        <h1>Villa Natividad’s Resort &amp; Events Place</h1>
        <div class="about-divider"></div>
        <h1>Amenities</h1>
    </div>
</section>

<section id="amenities">

    <h2 class="amenities-title">Discover Our Spaces</h2>

    <div class="amenities-grid">

        @foreach ($amenities as $amenity)
            <article class="amenity-card">

                <div class="amenity-image">
                    <img src="{{ asset($amenity['image']) }}" alt="{{ $amenity['title'] }}">
                </div>

              
                <div class="amenity-body">
                    <h3>{{ $amenity['title'] }}</h3>
                    <p>{{ $amenity['text'] }}</p>

                    <div class="amenity-tags">
                        @foreach ($amenity['tags'] as $tag)
                            <span class="amenity-tag {{ $amenity['color'] }}">{{ $tag }}</span>
                        @endforeach
                    </div>
                </div>

            </article>
        @endforeach

    </div>

</section>

@php
   
    $services = [
        [
            'title' => 'High-Speed Wi-Fi',
            'text'  => 'Seamless connectivity across the entire resort property for work or play.',
            'icon'  => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
        ],
        [
            'title' => 'Hassle-Free Parking',
            'text'  => 'Secure, well-lit parking areas that can accommodate large event guest lists.',
            'icon'  => '<path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>',
        ],
        [
            'title' => '24/7 Security',
            'text'  => 'Round-the-clock professional security and CCTV for your peace of mind.',
            'icon'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        ],
        [
            'title' => 'Event Venue',
            'text'  => 'Create unforgettable memories in the perfect venue for every special occasion.',
            'icon'  => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        ],
        [
            'title' => 'Videoke & Entertainment',
            'text'  => 'Modern entertainment systems and private karaoke rooms for groups.',
            'icon'  => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/>',
        ],
        [
            'title' => 'Kids Play Area',
            'text'  => 'A safe, fun, and monitored environment for our younger guests to play.',
            'icon'  => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',
        ],
    ];
@endphp

<section id="services">

    <div class="services-header">
        <div>
            <h2 class="services-title">Essential Services</h2>
            <p class="services-subtitle">Everything you need for a seamless and comfortable stay at our resort.</p>
        </div>
        <span class="services-line"></span>
    </div>


    <div class="services-grid">

        @foreach ($services as $service)
            <div class="service-card">

                <div class="service-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        {!! $service['icon'] !!}
                    </svg>
                </div>

                <div class="service-text">
                    <h4>{{ $service['title'] }}</h4>
                    <p>{{ $service['text'] }}</p>
                </div>

            </div>
        @endforeach

    </div>

</section>