<h2 class="reviews-title">What Our Guests Say</h2>


@if (session('success'))
    <p class="review-alert review-alert-success">{{ session('success') }}</p>
@endif
@if ($errors->any())
    <p class="review-alert review-alert-error">{{ $errors->first() }}</p>
@endif

<div class="reviews-summary">
    <div class="reviews-rating">
        @if ($reviewCount > 0)
  
            <span class="rating-number">{{ number_format($reviewAverage, 1) }}</span>
            <span class="rating-stars">
                @for ($i = 1; $i <= 5; $i++)
                    {{ $i <= round($reviewAverage) ? '★' : '☆' }}
                @endfor
            </span>
            <span class="rating-count">Based on {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</span>
        @else
            <span class="rating-count">No reviews yet. Be the first!</span>
        @endif
    </div>

    @auth
        <button type="button" class="btn-blue" data-bs-toggle="modal" data-bs-target="#reviewModal">
            Write a Review
        </button>
    @else
        <a href="{{ route('login') }}" class="btn-blue">Log in to write a review</a>
    @endauth
</div>

<div class="reviews-grid">
    @foreach ($reviews as $review)
        <div class="review-card">
            <div class="review-card-top">
                <div class="review-card-avatar">{{ strtoupper(substr($review->name, 0, 1)) }}</div>
                <div>
                    <strong>{{ $review->name }}</strong>
                    <small>{{ $review->created_at->format('M d, Y') }}</small>
                </div>
            </div>

            <div class="review-card-stars">
                @for ($i = 1; $i <= 5; $i++)
                    {{ $i <= $review->rating ? '★' : '☆' }}
                @endfor
            </div>

            <p>{{ Str::limit($review->comment, 150) }}</p>
        </div>
    @endforeach
</div>

<a href="{{ route('reviews.index') }}" class="btn-blue">See All Reviews</a>

@auth
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content review-modal">

            <div class="modal-header">
                <h3 class="modal-title">Add a Review</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('reviews.store') }}" method="POST">
                @csrf
                <div class="modal-body">

                    <label>Add Your Rating</label>
                    <div class="star-rating">
                        <input type="radio" name="rating" id="star5" value="5" required><label for="star5">★</label>
                        <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
                        <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
                        <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
                        <input type="radio" name="rating" id="star1" value="1"><label for="star1">★</label>
                    </div>

                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="{{ old('name', auth()->check() ? auth()->user()->name : '') }}" required>

                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="{{ old('email', auth()->check() ? auth()->user()->email : '') }}" required>

                    <label for="review">Write Your Review:</label>
                    <textarea id="review" name="review" rows="5" class="form-control" required>{{ old('review') }}</textarea>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn-blue">Submit</button>
                </div>
            </form>

        </div>
    </div>
</div>
@endauth
