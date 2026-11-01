<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Cottage;
use App\Services\AvailabilityService;
use App\Services\ReportingService;
use Illuminate\Contracts\View\View;

class AdminDashboardController extends Controller
{
    public function index(AvailabilityService $availability, ReportingService $reporting): View
    {
        $recentBooking = Booking::with('user')->latest()->take(5)->get();

        $totalBookings = Booking::count();
        $totalRevenue = (float) $reporting->collectedPayments()->sum('amount');
        $pendingBookings = Booking::where('status', 'pending')->count();

        $totalCottages = Cottage::count();
        $today = today()->toDateString();
        $availableCottages = $availability->availableCottages($today, $today)->count();
        $occupiedCottages = $availability->occupiedCottages($today, $today)->count();
        $occupancyChart = [
            'labels' => ['Booked', 'Available', 'Unavailable'],
            'values' => [$occupiedCottages, $availableCottages, max(0, $totalCottages - $occupiedCottages - $availableCottages)],
        ];
        $chart = $reporting->dailyRevenue(today()->subDays(6)->toDateString(), $today);
        $occupancyRate = $totalCottages > 0
            ? round(($occupiedCottages / $totalCottages) * 100, 2)
            : 0;

        return view('Admin.dashboard', compact(
            'recentBooking',
            'totalBookings',
            'totalRevenue',
            'pendingBookings',
            'totalCottages',
            'availableCottages',
            'occupancyRate',
            'occupiedCottages',
            'occupancyChart',
            'chart'
        ));
    }
}
