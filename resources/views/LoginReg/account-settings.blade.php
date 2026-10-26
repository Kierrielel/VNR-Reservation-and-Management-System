@extends('LoginReg.layout')

@section('title', 'Account Settings | Villa Natividad')

@push('styles')
    <link href="{{ asset('css/customer-settings.css') }}?v={{ filemtime(public_path('css/customer-settings.css')) }}" rel="stylesheet">
@endpush

@section('content')
<main class="customer-settings-page">
    <div class="customer-settings-heading">
        <p class="customer-settings-eyebrow">Customer Account</p>
        <h1>Account Settings</h1>
        <p>Manage your personal information and account security.</p>
    </div>

    @if (session('success'))
        <p class="customer-settings-alert customer-settings-alert-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <div class="customer-settings-alert customer-settings-alert-error">
            <strong>Please check the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('account.settings.update') }}" method="POST" class="customer-settings-card">
        @csrf
        @method('PUT')

        <div class="customer-settings-card-header">
            <div class="customer-settings-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm7 8a7 7 0 0 0-14 0h14z"/></svg>
            </div>
            <div>
                <h2>Profile Information</h2>
                <p>Update the name and email used on your account.</p>
            </div>
        </div>

        <div class="customer-settings-card-body customer-settings-fields">
            <div class="customer-settings-field">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="customer-settings-field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>
        </div>

        <div class="customer-settings-card-header">
            <div class="customer-settings-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-7-2a2 2 0 0 1 4 0v2h-4V6zm5 9h-2v2h-2v-2H9v-2h2v-2h2v2h2v2z"/></svg>
            </div>
            <div>
                <h2>Change Password</h2>
                <p>Use a strong password to keep your account secure.</p>
            </div>
        </div>

        <div class="customer-settings-card-body customer-settings-fields customer-settings-password-fields">
            <div class="customer-settings-field">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password">
            </div>

            <div class="customer-settings-field">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password">
            </div>

            <div class="customer-settings-field">
                <label for="new_password_confirmation">Confirm New Password</label>
                <input type="password" id="new_password_confirmation" name="new_password_confirmation" autocomplete="new-password">
            </div>
        </div>

        <div class="customer-settings-card-footer">
            <button type="submit" class="customer-settings-save">Save Changes</button>
        </div>
    </form>
</main>
@endsection
