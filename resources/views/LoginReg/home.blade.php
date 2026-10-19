@include('LoginReg.include.header')

<main>
    <div class="page-section" data-page="home">
        @include('LoginReg.include.home.main-home')
            
    </div>

    <div class="page-section" data-page="about">
        @include('LoginReg.include.home.about')
    </div>

    <div class="page-section" data-page="amenities">
        @include('LoginReg.include.home.amenities')
    </div>

    <div class="page-section" data-page="accommodations">
        @include('LoginReg.include.home.accommodations')
    </div>

    <div class="page-section" data-page="events">
        @include('LoginReg.include.home.events')
    </div>

    <div class="page-section" data-page="gallery">
        @include('LoginReg.include.home.gallery')
    </div>
    <div class="page-section" data-page="book">
        @include('LoginReg.include.home.book')
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const pages = document.querySelectorAll('.page-section');
    const links = document.querySelectorAll('.vnr-nav .nav-link');

    function showPage(name) {
        if (![...pages].some(p => p.dataset.page === name)) name = 'home';
        pages.forEach(p => p.classList.toggle('d-none', p.dataset.page !== name));
        links.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + name));
        window.scrollTo(0, 0);
    }

    links.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const name = link.getAttribute('href').slice(1);
            history.pushState(null, '', '#' + name);
            showPage(name);
        });
    });

    window.addEventListener('popstate', () => showPage(location.hash.slice(1) || 'home'));
    showPage(location.hash.slice(1) || 'home');
</script>

@include('LoginReg.include.footer')