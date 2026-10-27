<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// CUSTOMER side: saves the review from the "Write a Review" modal
class ReviewController extends Controller
{
    // CUSTOMER: submit a review
    public function store(Request $request)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'name'   => 'required|string|max:100',
            'email'  => 'required|email|max:150',
            'review' => 'required|string|max:1000',
        ]);

        Review::create([
            'user_id' => Auth::id(),
            'name'    => $data['name'],
            'email'   => $data['email'],
            'rating'  => $data['rating'],
            'comment' => $data['review'],
        ]);

        return redirect()->to(route('home') . '#reviews')->with('success', 'Thank you for your review!');
    }

    // CUSTOMER: see all reviews (read only)
    public function index(Request $request)
    {
        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ]);

        $reviewsQuery = Review::query();

        if (isset($validated['rating'])) {
            $reviewsQuery->where('rating', $validated['rating']);
        }

        $reviews       = $reviewsQuery->latest()->paginate(12)->withQueryString();
        $reviewCount   = $reviewsQuery->count();
        $reviewAverage = $reviewsQuery->avg('rating') ?? 0;
        $selectedRating = isset($validated['rating']) ? (int) $validated['rating'] : null;

        return view('LoginReg.include.home.all-reviews', compact(
            'reviews',
            'reviewCount',
            'reviewAverage',
            'selectedRating'
        ));
    }
}
