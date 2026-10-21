
@extends('LoginReg.layout-auth')
@section('title', 'Registration')
@section('content')

<div class="auth-page">


    <div class="auth-form-panel auth-form-panel-compact">
        <div class="auth-form-inner">

            <div class="auth-top-row">
                <a href="{{ route('home') }}" class="auth-brand">
                    Villa Natividad
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
                </a>
            </div>

            <h1 class="auth-heading">A Warm Welcome</h1>
            <p class="auth-subheading">Create an account</p>
            <p class="auth-description">
                Ready for a little getaway? Create your account to easily manage your
                bookings and start planning your next relaxing stay or special celebration.
            </p>

            {{-- Validation errors --}}
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

            <form action="{{ route('registration.post') }}" method="POST">
                @csrf

                <div class="auth-field">
                    <label for="email">Email</label>
                    <div class="auth-input-wrap">
                        <input type="email" id="email" name="email" placeholder="Email" value="{{ old('email') }}">
                    </div>
                </div>

                <div class="auth-field">
                    <label for="name">Username</label>
                    <div class="auth-input-wrap">
                        <input type="text" id="name" name="name" placeholder="Username" value="{{ old('name') }}">
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="auth-input-wrap">
                        <input type="password" id="password" name="password" placeholder="Password">
                        <button type="button" class="auth-toggle-password" onclick="togglePassword('password')">&#128065;</button>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password_confirmation">Confirm password</label>
                    <div class="auth-input-wrap">
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm password">
                        <button type="button" class="auth-toggle-password" onclick="togglePassword('password_confirmation')">&#128065;</button>
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn">Sign Up</button>
            </form>

            <div class="auth-social-row">
                <a href="#" class="auth-social-button" title="Sign up with Google">
                    <span class="auth-social-icon auth-social-google" aria-hidden="true">G</span>
                    <span>Google</span>
                </a>
                <a href="#" class="auth-social-button" title="Sign up with Facebook">
                    <span class="auth-social-icon auth-social-facebook" aria-hidden="true">f</span>
                    <span>Facebook</span>
                </a>
            </div>

            <div class="auth-switch-link">
                Already have an account?
                <a href="{{ route('login') }}">Login here!</a>
            </div>

        </div>
    </div>

    <div class="auth-image-panel">
        <div class="auth-image-text">
            <h1>Where New Memories Begin.</h1>
            <p>Make every moment a little more special.</p>
        </div>
    </div>

</div>

<script>
    // Shows/hides a password field when the eye icon is clicked
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        input.type = input.type === 'password' ? 'text' : 'password';
    }
</script>

@endsection