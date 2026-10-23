<div class="ann-page">

    <div class="ann-top">
        <h1 class="ann-title">Announcements Management</h1>
        <button type="button" class="ann-create-btn"
                onclick="document.getElementById('annModal').classList.add('open')">
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
                            @if ($announcement->status !== 'archived')
                                <form method="POST" action="{{ route('admin.announcements.archive', $announcement) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ann-btn">Archive</button>
                                </form>
                            @else
                                <span class="ann-btn ann-btn-archived" aria-label="Announcement archived">Archived</span>
                            @endif
                            <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}"
                                  onsubmit="return confirm('Delete this announcement?')">
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
                <button type="button" class="ann-template">🧹 Maintenance Notice <span>→</span></button>
                <button type="button" class="ann-template">🎟️ Event Invitation <span>→</span></button>
                <button type="button" class="ann-template">🏷️ Special Promotion <span>→</span></button>
            </div>

        </div>
    </div>

    <div class="ann-modal {{ $errors->any() ? 'open' : '' }}" id="annModal">
        <div class="ann-modal-box">
            <h2>Create Announcement</h2>

            @if ($errors->any())
                <div class="flash flash-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.announcements.store') }}">
                @csrf

                <label>Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required>

                <label>Message</label>
                <textarea name="message" rows="4" required>{{ old('message') }}</textarea>

                <label>Category</label>
                <select name="category">
                    @foreach (['Maintenance', 'Event', 'Promotion', 'General'] as $cat)
                        <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>

                <label>Post date <small>(leave empty to post now)</small></label>
                <input type="datetime-local" name="publish_at" value="{{ old('publish_at') }}">

                <label class="ann-check">
                    <input type="checkbox" name="is_high_priority" value="1"> High priority
                </label>

                <div class="ann-modal-buttons">
                    <button type="button" class="ann-btn-secondary"
                            onclick="document.getElementById('annModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-save">Post Announcement</button>
                </div>
            </form>
        </div>
    </div>

</div>
