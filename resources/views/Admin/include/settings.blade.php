<div class="settings-page">
    <h1 class="settings-title">Settings</h1>

    @if (session('success'))
        <p class="settings-alert settings-alert-success">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <p class="settings-alert settings-alert-error">{{ $errors->first() }}</p>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="settings-card">
        @csrf
        @method('PUT')

        <div class="settings-card-header">Resort Profile</div>

        <div class="settings-card-body">

            <div class="settings-left">
                <label for="resort_name">Resort Name</label>
                <input type="text" id="resort_name" name="resort_name"
                       value="{{ old('resort_name', $setting->resort_name) }}" required>

                <div class="settings-row">
                    <div>
                        <label for="contact_number">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number"
                               value="{{ old('contact_number', $setting->contact_number) }}">
                    </div>
                    <div>
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $setting->email) }}">
                    </div>
                </div>

                <label for="address">Physical Address</label>
                <textarea id="address" name="address" rows="5">{{ old('address', $setting->address) }}</textarea>
            </div>

            <div class="settings-right">
                <label>Resort Logo</label>

                <label for="logo" class="logo-upload">
                    <img id="logoPreview"
                         src="{{ $setting->logo ? asset('storage/' . $setting->logo) : '' }}"
                         class="logo-preview" style="{{ $setting->logo ? '' : 'display:none;' }}" alt="Resort logo">

                    <div id="logoPlaceholder" style="{{ $setting->logo ? 'display:none;' : '' }}">
                        <i class="bi bi-cloud-upload"></i>
                        <p>Click to upload new logo</p>
                        <small>SVG, PNG, or JPG (max 2MB)</small>
                    </div>
                </label>
                <input type="file" id="logo" name="logo" accept=".svg,.png,.jpg,.jpeg" hidden>
            </div>

        </div>

        <div class="settings-card-footer">
            <button type="submit" class="btn-save-settings">Save Changes</button>
        </div>
    </form>

    <div class="settings-card notif-card">
        <div class="settings-card-header">
            <i class="bi bi-bell"></i> Notification
        </div>

        <div class="notif-body">

            <div class="notif-box">
                <h4 class="notif-group-title">Email Notification</h4>
                <div class="notif-row">
                    <div>
                        <span class="notif-label">New Booking Alerts</span>
                        <small>Receive instant emails for every new guest reservation</small>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <h4 class="notif-group-title notif-group-second">In-app Alert</h4>
                <div class="notif-row">
                    <span class="notif-label">New Booking Alerts</span>
                    <label class="toggle">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="notif-right">
                <label for="notif_email">Primary Notification Email</label>
                <input type="email" id="notif_email" value="admin@villanatividad.com">
            </div>

        </div>
    </div>
</div>

<script>
    // Show a preview of the chosen logo before saving
    document.getElementById('logo').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const preview = document.getElementById('logoPreview');
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
        document.getElementById('logoPlaceholder').style.display = 'none';
    });
</script>