<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    // Show all reviews (newest first)
    public function index(Request $request)
    {
        $query = Review::latest();

        if ($request->status === 'responded') {
            $query->whereNotNull('response');
        } elseif ($request->status === 'pending') {
            $query->whereNull('response');
        }

        $reviews = $query->paginate(10)->withQueryString();

        $reviewCount = Review::count();
        $reviewAverage = Review::avg('rating') ?? 0;

        return view('Admin.dashboard', compact('reviews', 'reviewCount', 'reviewAverage'));
    }

    // Save the resort's reply to a review
    public function respond(Request $request, Review $review)
    {
        $request->validate([
            'response' => 'required|string|max:1000',
        ]);

        $review->update(['response' => $request->response]);

        return back()->with('success', 'Response saved.');
    }

    // Delete a review
    public function destroy(Review $review)
    {
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}