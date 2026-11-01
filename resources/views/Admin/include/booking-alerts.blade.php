@if (session('success'))
    <p class="booking-alert booking-alert-success" role="status">{{ session('success') }}</p>
@endif
@if ($errors->any())
    <div class="booking-alert booking-alert-error" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
