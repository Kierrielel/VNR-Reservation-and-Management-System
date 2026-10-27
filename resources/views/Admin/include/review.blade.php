<div class="reviews-page">

    <h1 class="reviews-title">Reviews</h1>

    @if (session('success'))
        <p class="flash flash-success">{{ session('success') }}</p>
    @endif


    <div class="reviews-card">
        <div class="reviews-card-header">Overall Rating</div>

        <div class="admin-rating-summary">
            @if ($reviewCount > 0)
                <span class="admin-rating-number">{{ number_format($reviewAverage, 1) }}</span>

                <span class="review-stars admin-rating-stars">
                    @for ($i = 1; $i <= 5; $i++)
                        {{ $i <= round($reviewAverage) ? '★' : '☆' }}
                    @endfor
                </span>

                <span class="admin-rating-count">
                    Based on {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}
                </span>
            @else
                <span class="admin-rating-count">No reviews yet</span>
            @endif
        </div>
    </div>

    <div class="reviews-card">

        <div class="reviews-card-header reviews-top">
            <span>Recent Reviews</span>

            <form action="{{ route('admin.reviews.index') }}" method="GET" class="reviews-filters">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="responded" {{ request('status') == 'responded' ? 'selected' : '' }}>Responded</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Not Responded</option>
                </select>
                <select name="rating">
                    <option value="">All Ratings</option>
                    @for ($rating = 5; $rating >= 1; $rating--)
                        <option value="{{ $rating }}" @selected((int) request('rating') === $rating)>
                            {{ $rating }} {{ Str::plural('star', $rating) }}
                        </option>
                    @endfor
                </select>
                <button type="submit"><i class="bi bi-funnel"></i> Filter</button>
            </form>
        </div>

        <div class="reviews-list">

            @forelse ($reviews as $review)
                <div class="review-item">

                    <div class="review-guest">
                        <div class="guest-avatar">{{ strtoupper(substr($review->name, 0, 1)) }}</div>

                        <div>
                            <h3>{{ $review->name }}</h3>

                            @if ($review->stayed)
                                <small>Stayed {{ $review->stayed }}</small>
                            @else
                                <small>Reviewed {{ $review->created_at->format('M d, Y') }}</small>
                            @endif

                            <div class="review-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    {{ $i <= $review->rating ? '★' : '☆' }}
                                @endfor
                            </div>
                        </div>
                    </div>

                    @if ($review->title)
                        <h4 class="review-title">{{ $review->title }}</h4>
                    @endif
                    <p class="review-text">{{ $review->comment }}</p>

                    @if ($review->response)
                        <div class="review-response">
                            <strong><i class="bi bi-reply"></i> Response from Villa Natividad Resort</strong>
                            <p>{{ $review->response }}</p>
                        </div>
                    @else
                        <details class="draft-box">
                            <summary class="btn-draft">Draft Response</summary>

                            <form action="{{ route('admin.reviews.respond', $review) }}" method="POST">
                                @csrf
                                <textarea name="response" rows="3" placeholder="Write your reply..." required></textarea>
                                <button type="submit" class="btn-save">Save Response</button>
                            </form>
                        </details>
                    @endif

                    <div class="review-bottom">
                        <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST"
                              data-confirm data-confirm-title="Delete review?"
                              data-confirm-message="This review will be permanently deleted. This action cannot be undone."
                              data-confirm-label="Yes, delete">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-delete"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </div>

                </div>
            @empty
                <p class="no-reviews">No reviews found.</p>
            @endforelse

        </div>

        <div class="reviews-pages">
            {{ $reviews->links('pagination::bootstrap-5') }}
        </div>

    </div>

</div>
