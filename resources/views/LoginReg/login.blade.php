
@extends('LoginReg.layout-auth')
@section('title', 'Login')
@section('content')

<div class="auth-page">

    <div class="auth-image-panel">
        <div class="auth-image-text">
            <h1>Your Getaway Starts Here.</h1>
            <p>Relax, unwind, and make memories.</p>
        </div>
    </div>

    <div class="auth-form-panel">
        <div class="auth-form-inner">

            <div class="auth-top-row">
                <a href="{{ route('home') }}" class="auth-brand">
                    Villa Natividad
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
                </a>
            </div>

            <h1 class="auth-heading">Welcome back</h1>
            <p class="auth-subheading">Log in to your account</p>
            <p class="auth-description">
                Take a little break from the busy days and make time for something special.
                Log in to view your bookings and get ready for your next relaxing getaway.
            </p>

            {{-- validation error --}}
            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    <div class="auth-alert auth-alert-error">{{ $error }}</div>
                @endforeach
            @endif

            @if (session()->has('error'))
                <div class="auth-alert auth-alert-error">{{ session('error') }}</div>
            @endif

            @if (session()->has('success'))
                <div class="auth-alert auth-alert-success">{{ session('success') }}</div>
            @endif


            <form action="{{ route('login.post') }}" method="POST">
                @csrf

                <div class="auth-field">
                    <label for="email">Email</label>
                    <div class="auth-input-wrap">
                        <input type="email" id="email" name="email" placeholder="Email" value="{{ old('email') }}">
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="auth-input-wrap">
                        <input type="password" id="password" name="password" placeholder="Password">
                        <button type="button" class="auth-toggle-password" onclick="togglePassword('password', this)">&#128065;</button>
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn">Log in</button>
            </form>

            <div class="auth-social-row">
                <span>or Log in using</span>
                <a href="#" class="auth-social-facebook" title="Log in with Facebook">f</a>
                <a href="#" class="auth-social-google" title="Log in with Google">G</a>
            </div>

            <div class="auth-switch-link">
                Don't have an account?
                <a href="{{ route('registration') }}">Sign up here!</a>
            </div>

        </div>
    </div>
</div>

<script>
    // Shows/hides a password field when the eye icon is clicked
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        input.type = input.type === 'password' ? 'text' : 'password';
    }
    function selectRole(role) {
        document.getElementById('btn-customer').classList.toggle('active', role === 'customer');
        document.getElementById('btn-admin').classList.toggle('active', role === 'admin');
    }
</script>

@endsection