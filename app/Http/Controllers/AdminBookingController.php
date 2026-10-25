<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function index()
    {
        return view('Admin.dashboard');
    }
}