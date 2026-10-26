<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mrs+Saint+Delafield&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="{{ asset('css/header.css') }}?v={{ filemtime(public_path('css/header.css')) }}" rel="stylesheet">
<link href="{{ asset('css/notification.css') }}" rel="stylesheet">
<link rel="icon" type="image/png" href="{{ asset('images/VNR-favicon.png') }}">

<nav class="navbar navbar-expand-lg vnr-navbar">
    <div class="container-fluid">
        <a class="vnr-brand" href="{{ url('/') }}">
            <img class="vnr-brand-logo" src="{{ asset('images/logo.png') }}"
                 alt="Villa Natividad Resort and Events Place">
            <span class="vnr-brand-copy">
                <span class="vnr-brand-text">Villa Natividad</span>
                <small>RESORT &amp; EVENTS PLACE</small>
            </span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText"
                aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarText">
            @php
                $isHomePage = request()->routeIs('home');
            @endphp
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 vnr-nav">
                <li class="nav-item"><a class="nav-link {{ $isHomePage ? 'active' : '' }}" aria-current="{{ $isHomePage ? 'page' : 'false' }}" href="{{ $isHomePage ? '#home' : route('home') . '#home' }}">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $isHomePage ? '#about' : route('home') . '#about' }}">About</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $isHomePage ? '#amenities' : route('home') . '#amenities' }}">Amenities</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $isHomePage ? '#accommodations' : route('home') . '#accommodations' }}">Accommodations</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $isHomePage ? '#events' : route('home') . '#events' }}">Events</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $isHomePage ? '#gallery' : route('home') . '#gallery' }}">Gallery</a></li>
            </ul>

            <div class="d-flex align-items-center gap-3 vnr-nav-actions">
                    @auth
                        <div class="dropdown vnr-bell">
                            <button class="vnr-bell-btn" type="button" data-bs-toggle="dropdown"
                                  data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
                                <svg viewBox="0 0 16 16" width="20" height="20" fill="currentColor">
                                    <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zM8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z"/>
                                </svg>
                                @if ($unreadCount > 0)
                                    <span class="vnr-bell-badge">{{ $unreadCount }}</span>
                                @endif
                            </button>

                            <div class="dropdown-menu dropdown-menu-end vnr-notif-menu">
                                <div class="vnr-notif-head">
                                    <span class="vnr-notif-heading">Notifications</span>
                                    @if ($unreadCount > 0)
                                        <form method="POST" action="{{ route('announcements.read') }}">
                                            @csrf
                                            <button type="submit" class="vnr-notif-markall">Mark all as read</button>
                                        </form>
                                    @endif
                                </div>

                                <div class="vnr-notif-list">
                                    @if ($navAnnouncements->isNotEmpty())
                                        @foreach ($navAnnouncements as $item)
                                        @php $isUnread = !$readIds->contains($item->id); @endphp
                                        <div class="vnr-notif-item {{ $isUnread ? 'unread' : '' }}">
                                            <span class="vnr-notif-icon">{{ $item->icon }}</span>

                                            <div class="vnr-notif-body">
                                                <div class="vnr-notif-title">
                                                    <strong>{{ $item->title }}</strong>
                                                    @if ($item->is_high_priority)
                                                        <span class="vnr-notif-priority">Important</span>
                                                    @endif
                                                </div>
                                                <p>{{ $item->message }}</p>
                                                <small>{{ $item->category }} · {{ $item->publish_at->diffForHumans() }}</small>
                                            </div>

                                            @if ($isUnread)
                                                <span class="vnr-notif-dot"></span>
                                            @endif
                                        </div>
                                        @endforeach
                                    @else
                                        <p class="vnr-notif-empty">No announcements yet.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="dropdown vnr-account">
                            <button class="vnr-account-trigger" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false" aria-label="Open account menu">
                                <span class="vnr-avatar" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9v1.4c0 .7.6 1.3 1.3 1.3h17c.7 0 1.3-.6 1.3-1.3v-1.4c0-3.3-6.5-4.9-9.8-4.9z"/>
                                    </svg>
                                </span>
                                <span class="vnr-account-summary">
                                    <strong>{{ auth()->user()->name }}</strong>
                                    <small>Customer Account</small>
                                </span>
                                <svg class="vnr-account-chevron" viewBox="0 0 16 16" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M1.646 5.646a.5.5 0 0 1 .708 0L8 11.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                                </svg>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end vnr-account-menu">
                                <div class="vnr-account-head">
                                    <span class="vnr-avatar vnr-avatar-large" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9v1.4c0 .7.6 1.3 1.3 1.3h17c.7 0 1.3-.6 1.3-1.3v-1.4c0-3.3-6.5-4.9-9.8-4.9z"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <strong>{{ auth()->user()->name }}</strong>
                                        <small>{{ auth()->user()->email }}</small>
                                    </div>
                                </div>

                                <div class="vnr-account-links">
                                    <a href="#" class="vnr-account-item vnr-account-placeholder" aria-disabled="true">
                                        <span class="vnr-account-item-icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm7 8a7 7 0 0 0-14 0h14z"/></svg>
                                        </span>
                                        <span><strong>My Profile</strong><small>View your personal information</small></span>
                                    </a>
                                    <a href="#" class="vnr-account-item vnr-account-placeholder" aria-disabled="true">
                                        <span class="vnr-account-item-icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm-2 7h14v10H5V9zm2 2v2h2v-2H7zm4 0v2h2v-2h-2zm4 0v2h2v-2h-2z"/></svg>
                                        </span>
                                        <span><strong>My Reservations</strong><small>View your bookings and status</small></span>
                                    </a>
                                    <a href="{{ route('account.settings') }}" class="vnr-account-item">
                                        <span class="vnr-account-item-icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m19.4 13 .1-1-.1-1 2-1.6-2-3.4-2.4 1a7.3 7.3 0 0 0-1.7-1L15 3h-4l-.3 3a7.3 7.3 0 0 0-1.7 1l-2.4-1-2 3L6.6 11l-.1 1 .1 1-2 1.6 2 3.4 2.4-1a7.3 7.3 0 0 0 1.7 1l.3 3h4l.3-3a7.3 7.3 0 0 0 1.7-1l2.4 1 2-3-2-1.6zM13 15.5A3.5 3.5 0 1 1 13 8a3.5 3.5 0 0 1 0 7.5z"/></svg>
                                        </span>
                                        <span><strong>Account Settings</strong><small>Update your account details</small></span>
                                    </a>
                                </div>

                                <div class="vnr-account-footer">
                                    <a href="{{ route('logout') }}" class="vnr-account-item vnr-account-logout" data-confirm
                                        data-confirm-title="Log out?"
                                        data-confirm-message="Are you sure you want to log out of your account?"
                                        data-confirm-label="Yes, log out">
                                        <span class="vnr-account-item-icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5v-2H5V5h5V3zm5 4-1.4 1.4 2.6 2.6H9v2h7.2l-2.6 2.6L15 17l5-5-5-5z"/></svg>
                                        </span>
                                        <span><strong>Log Out</strong><small>Sign out of your account</small></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-vnr-outline">Login</a>
                    @endauth
                    <a href="{{ route('book') }}" class="btn btn-vnr-fill">Book Now</a>
                </div>
        </div>
    </div>
</nav>

<script>
    // Click a notification to show or hide its full message
    document.addEventListener('click', function (e) {
        var item = e.target.closest('.vnr-notif-item');
        if (item) {
            item.classList.toggle('expanded');
        }
    });

    // On phones, position the notification panel right below the bell
    document.addEventListener('shown.bs.dropdown', function (e) {
        var btn  = e.target;
        var menu = btn.parentElement.querySelector('.vnr-notif-menu');
        if (!menu) return;

        if (window.innerWidth <= 576) {
            var bottom = btn.getBoundingClientRect().bottom;
            menu.style.setProperty('--notif-top', (bottom + 8) + 'px');
        }
    });
</script>

@include('shared.confirmation-modal')
