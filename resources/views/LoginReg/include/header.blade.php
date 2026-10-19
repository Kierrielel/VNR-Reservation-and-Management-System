<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mrs+Saint+Delafield&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="{{ asset('css/header.css') }}" rel="stylesheet">

<nav class="navbar navbar-expand-lg vnr-navbar">
    <div class="container-fluid">
        <a class="vnr-brand" href="{{ url('/') }}">
            <span class="vnr-brand-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9v1.4c0 .7.6 1.3 1.3 1.3h17c.7 0 1.3-.6 1.3-1.3v-1.4c0-3.3-6.5-4.9-9.8-4.9z"/>
                </svg>
            </span>
            <span class="vnr-brand-text">Villa Natividad</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText"
                aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarText">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 vnr-nav">
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#amenities">Amenities</a></li>
                <li class="nav-item"><a class="nav-link" href="#accommodations">Accommodations</a></li>
                <li class="nav-item"><a class="nav-link" href="#events">Events</a></li>
                <li class="nav-item"><a class="nav-link" href="#gallery">Gallery</a></li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                @auth
                    <span class="vnr-welcome">Welcome, {{ auth()->user()->name }}</span>
                    <a href="{{ route('logout') }}" class="btn btn-vnr-outline">Logout</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-vnr-outline">Login</a>
                @endauth
               <a href="{{ route('book') }}" class="btn btn-vnr-fill">Book Now</a>
            </div>
        </div>
    </div>
</nav>