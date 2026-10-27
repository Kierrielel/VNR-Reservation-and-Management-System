<link href="{{ asset('css/all-review.css') }}?v={{ filemtime(public_path('css/all-review.css')) }}" rel="stylesheet">

<div class="all-reviews-wrap">

    <a href="{{ route('home') }}" class="all-reviews-back">← Back to Home</a>

    <h1 class="all-reviews-title">All Guest Reviews</h1>

    <div class="all-reviews-summary">
        @if ($reviewCount > 0)
            <span class="all-reviews-number">{{ number_format($reviewAverage, 1) }}</span>
            <span class="all-reviews-stars">
                @for ($i = 1; $i <= 5; $i++)
                    {{ $i <= round($reviewAverage) ? '★' : '☆' }}
                @endfor
            </span>
            <span class="all-reviews-count">Based on {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</span>
        @else
            <span class="all-reviews-count">No reviews yet.</span>
        @endif
    </div>

    <div class="all-reviews-card">
        <div class="all-reviews-card-header all-reviews-card-header-filter">
            <span>Guest Reviews</span>

            <form action="{{ route('reviews.index') }}" method="GET" class="all-reviews-filter-form">
                <label for="rating">Filter by rating</label>
                <select id="rating" name="rating" onchange="this.form.submit()">
                    <option value="">All ratings</option>
                    @for ($rating = 5; $rating >= 1; $rating--)
                        <option value="{{ $rating }}" @selected($selectedRating === $rating)>
                            {{ $rating }} {{ Str::plural('star', $rating) }}
                        </option>
                    @endfor
                </select>
                <noscript>
                    <button type="submit">Apply Filter</button>
                </noscript>
            </form>
        </div>

        <div class="all-reviews-grid">
            @forelse ($reviews as $review)
                <div class="all-review-item">

                    <div class="all-review-top">
                        <div class="all-review-avatar">{{ strtoupper(substr($review->name, 0, 1)) }}</div>
                        <div>
                            <strong>{{ $review->name }}</strong>
                            <small>{{ $review->created_at->format('M d, Y') }}</small>
                        </div>
                    </div>

                    <div class="all-review-stars">
                        @for ($i = 1; $i <= 5; $i++)
                            {{ $i <= $review->rating ? '★' : '☆' }}
                        @endfor
                    </div>

                    <p class="all-review-text">{{ $review->comment }}</p>

                    @if ($review->response)
                        <div class="all-review-response">
                            <strong>Response from Villa Natividad Resort</strong>
                            <p>{{ $review->response }}</p>
                        </div>
                    @endif

                </div>
            @empty
                <p class="all-reviews-empty">No reviews yet.</p>
            @endforelse
        </div>
    </div>

    <div class="all-reviews-pages">
        {{ $reviews->links('pagination::bootstrap-5') }}
    </div>

</div>
