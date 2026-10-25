<?php

namespace App\Http\Controllers;

use App\Models\Cottage;

class AdminRoomsController extends Controller
{
    public function index()
    {
        $cottages = Cottage::paginate(8);

        return view('Admin.dashboard', compact('cottages'));
    }
}