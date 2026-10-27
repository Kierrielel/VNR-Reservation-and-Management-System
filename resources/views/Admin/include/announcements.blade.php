<div class="ann-page">

    <div class="ann-top">
        <h1 class="ann-title">Announcements Management</h1>
        <button type="button" class="ann-create-btn"
                onclick="openCreateAnnouncementModal()">
            + Create Announcement
        </button>
    </div>

    @if (session('success'))
        <div class="flash flash-success">{{ session('success') }}</div>
    @endif

    <div class="ann-layout">

        <div class="ann-main">

            <div class="ann-filters">
                <a href="{{ route('admin.announcements.index') }}"
                   class="ann-pill {{ !request('status') ? 'active' : '' }}">All ({{ $allCount }})</a>
                <a href="{{ route('admin.announcements.index', ['status' => 'active']) }}"
                   class="ann-pill {{ request('status') === 'active' ? 'active' : '' }}">Active ({{ $activeCount }})</a>
                <a href="{{ route('admin.announcements.index', ['status' => 'scheduled']) }}"
                   class="ann-pill {{ request('status') === 'scheduled' ? 'active' : '' }}">Scheduled ({{ $scheduledCount }})</a>

                <form method="GET" action="{{ route('admin.announcements.index') }}" class="ann-category-form">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <select name="category" onchange="this.form.submit()">
                        <option value="">Filter by Category</option>
                        @foreach (['Maintenance', 'Event', 'Promotion', 'General'] as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="ann-card">
                <div class="ann-row ann-head">
                    <span>ANNOUNCEMENT</span>
                    <span>DATE POSTED</span>
                    <span>STATUS</span>
                    <span></span>
                </div>

                @forelse ($announcements as $announcement)
                    <div class="ann-row">
                        <div class="ann-info">
                            <span class="ann-icon">{{ $announcement->icon }}</span>
                            <div>
                                <h3>{{ $announcement->title }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit($announcement->message, 50) }}</p>
                            </div>
                        </div>

                        <span class="ann-date">{{ $announcement->publish_at->format('F j, Y') }}</span>

                        <span><span class="ann-badge ann-{{ $announcement->status }}">{{ ucfirst($announcement->status) }}</span></span>

                        <div class="ann-actions">
                            <button type="button" class="ann-btn ann-edit-btn"
                                    data-update-url="{{ route('admin.announcements.update', $announcement) }}"
                                    data-title="{{ $announcement->title }}"
                                    data-message="{{ $announcement->message }}"
                                    data-category="{{ $announcement->category }}"
                                    data-publish-at="{{ $announcement->publish_at?->format('Y-m-d\\TH:i') }}"
                                    data-high-priority="{{ $announcement->is_high_priority ? '1' : '0' }}">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}"
                                  data-confirm data-confirm-title="Delete announcement?"
                                  data-confirm-message="This announcement will be permanently deleted. This action cannot be undone."
                                  data-confirm-label="Yes, delete">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="ann-empty">No announcements found.</p>
                @endforelse

                <div class="ann-footer">
                    <span>Showing {{ $announcements->firstItem() ?? 0 }} to {{ $announcements->lastItem() ?? 0 }}
                        of {{ $announcements->total() }} results</span>
                    {{ $announcements->links('vendor.pagination.admin') }}
                </div>
            </div>
        </div>

        <div class="ann-side">

            <div class="ann-side-card">
                <h2>Announcement Reach</h2>
                <p class="ann-reach-label">Guests Notified</p>
                <span class="ann-reach-number">{{ $guestsNotified }}</span>
                <div class="ann-bar"><div class="ann-bar-fill"></div></div>
                <div class="ann-stats">
                    <div><small>High Priority</small><strong>{{ $highPriority }}</strong></div>
                    <div class="ann-bar"><div class="ann-bar-fill" style="width: {{ $openRate }}%"></div></div>
                </div>
            </div>

            <div class="ann-side-card">
                <h2>Quick Templates</h2>

                <button type="button" class="ann-template"
                        data-category="Maintenance"
                        data-priority="1"
                        data-title="Scheduled Maintenance Notice"
                        data-message="Please be informed that the [area, e.g. main pool] will be closed for maintenance on [date]. We apologize for the inconvenience and thank you for your understanding.">
                    🧹 Maintenance Notice <span>→</span>
                </button>

                <button type="button" class="ann-template"
                        data-category="Event"
                        data-priority="0"
                        data-title="You're Invited: [Event Name]"
                        data-message="Join us on [date] at [time] here at Villa Natividad for [event name]. Reserve your spot now and enjoy a memorable day with family and friends.">
                    🎟️ Event Invitation <span>→</span>
                </button>

                <button type="button" class="ann-template"
                        data-category="Promotion"
                        data-priority="0"
                        data-title="Special Promo: [discount]% Off"
                        data-message="Enjoy special rates on [rooms/cottages] from [start date] to [end date]. Book now and save! Terms and conditions apply.">
                    🏷️ Special Promotion <span>→</span>
                </button>
            </div>

        </div>
    </div>

    <div class="ann-modal {{ $errors->any() ? 'open' : '' }}" id="annModal" aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
        <div class="ann-modal-box" role="dialog" aria-modal="true" aria-labelledby="annModalTitle">
            <h2 id="annModalTitle">Create Announcement</h2>

            @if ($errors->any())
                <div class="flash flash-error">{{ $errors->first() }}</div>
            @endif

            <form id="announcementForm" method="POST" action="{{ route('admin.announcements.store') }}">
                @csrf
                <input type="hidden" name="_method" id="annMethod" value="">

                <label for="annTitle">Title</label>
                <input class="form-control" type="text" id="annTitle" name="title" value="{{ old('title') }}" required>

                <label for="annMessage">Message</label>
                <textarea class="form-control" id="annMessage" name="message" rows="5" required>{{ old('message') }}</textarea>

                <label for="annCategory">Category</label>
                <select class="form-control" id="annCategory" name="category">
                    @foreach (['Maintenance', 'Event', 'Promotion', 'General'] as $cat)
                        <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>

                <label for="annPublishAt">Post date <small>(leave empty to post now)</small></label>
                <input class="form-control" type="datetime-local" id="annPublishAt" name="publish_at" value="{{ old('publish_at') }}">

                <label class="ann-check">
                    <input type="checkbox" name="is_high_priority" value="1"> High priority
                </label>

                <div class="ann-modal-buttons">
                    <button type="button" class="ann-btn-secondary"
                            onclick="document.getElementById('annModal').classList.remove('open'); document.getElementById('annModal').setAttribute('aria-hidden', 'true')">Cancel</button>
                    <button type="submit" class="btn-save" id="annSubmitButton">Post Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Keep the fixed modal outside the dashboard layout so its backdrop covers the sticky header.
        const announcementModal = document.getElementById('annModal');
        if (announcementModal && announcementModal.parentElement !== document.body) {
            document.body.appendChild(announcementModal);
        }

        const announcementForm = document.getElementById('announcementForm');
        const announcementMethod = document.getElementById('annMethod');
        const announcementTitle = document.getElementById('annModalTitle');
        const announcementSubmitButton = document.getElementById('annSubmitButton');

        // admin can create announcemnt
        function openCreateAnnouncementModal() {
            announcementForm.reset();
            announcementForm.action = "{{ route('admin.announcements.store') }}";
            announcementMethod.value = '';
            announcementTitle.textContent = 'Create Announcement';
            announcementSubmitButton.textContent = 'Post Announcement';
            announcementModal.classList.add('open');
            announcementModal.setAttribute('aria-hidden', 'false');
        }
        // Admin can edit announcemnt
        document.querySelectorAll('.ann-edit-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                announcementForm.action = button.dataset.updateUrl;
                announcementMethod.value = 'PUT';
                announcementTitle.textContent = 'Edit Announcement';
                announcementSubmitButton.textContent = 'Save Changes';
                announcementForm.querySelector('[name="title"]').value = button.dataset.title;
                announcementForm.querySelector('[name="message"]').value = button.dataset.message;
                announcementForm.querySelector('[name="category"]').value = button.dataset.category;
                announcementForm.querySelector('[name="publish_at"]').value = button.dataset.publishAt;
                announcementForm.querySelector('[name="is_high_priority"]').checked = button.dataset.highPriority === '1';
                announcementModal.classList.add('open');
                announcementModal.setAttribute('aria-hidden', 'false');
            });
        });

        // Quick templates: fill the template, then open it
        document.querySelectorAll('.ann-template').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openCreateAnnouncementModal();
            var modal = announcementModal;
            modal.querySelector('[name="title"]').value = btn.dataset.title;
            modal.querySelector('[name="message"]').value = btn.dataset.message;
            modal.querySelector('[name="category"]').value = btn.dataset.category;
            modal.querySelector('[name="is_high_priority"]').checked = btn.dataset.priority === '1';
            modal.classList.add('open');
        });
    });

        
    </script>

</div>
