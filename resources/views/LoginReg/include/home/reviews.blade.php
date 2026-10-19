<h2 class="reviews-title">What Our Guests Say</h2>


<div class="reviews-summary">
    <div class="reviews-rating">
        <span class="rating-number">4.8</span>
        <span class="rating-stars">★★★★☆</span>
        <span class="rating-count">Based on 153 reviews</span>
    </div>

    <button type="button" class="btn-blue" data-bs-toggle="modal" data-bs-target="#reviewModal">
        Write a Review
    </button>
</div>

<div class="reviews-grid">
    <div class="review-card"></div>
    <div class="review-card"></div>
    <div class="review-card"></div>
</div>

<a href="#" class="btn-blue">See All Reviews</a>

<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content review-modal">

            <div class="modal-header">
                <h3 class="modal-title">Add a Review</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="#" method="POST">
                @csrf
                <div class="modal-body">

                    <label>Add Your Rating</label>
                    <div class="star-rating">
                        <input type="radio" name="rating" id="star5" value="5"><label for="star5">★</label>
                        <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
                        <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
                        <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
                        <input type="radio" name="rating" id="star1" value="1"><label for="star1">★</label>
                    </div>

                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" class="form-control" required>

                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" required>

                    <label for="review">Write Your Review:</label>
                    <textarea id="review" name="review" rows="5" class="form-control" required></textarea>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn-blue">Submit</button>
                </div>
            </form>

        </div>
    </div>
</div>