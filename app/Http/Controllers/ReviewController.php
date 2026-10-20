<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// CUSTOMER side: saves the review from the "Write a Review" modal
class ReviewController extends Controller
{
    public function store(Request $request)
    {
        // 1. check the form
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'name'   => 'required|string|max:100',
            'email'  => 'required|email|max:150',
            'review' => 'required|string|max:1000',
        ]);

        // 2. save it
        Review::create([
            'user_id' => Auth::id(),          // null if not logged in
            'name'    => $data['name'],
            'email'   => $data['email'],
            'rating'  => $data['rating'],
            'comment' => $data['review'],
        ]);

        // 3. go back with a message
        return redirect()->to(route('home').'#reviews')->with('success', 'Thank you for your review!');
    }
}