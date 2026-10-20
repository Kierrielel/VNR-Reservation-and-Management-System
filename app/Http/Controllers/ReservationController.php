<?php

namespace App\Http\Controllers;

use App\Models\Cottage;
use App\Models\Setting;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function checkAvailability(Request $request) {}
    private function calculateTotal(array $cottageIds, string $stayType, int $guestCount)
    {

        $cottages = Cottage::whereIn('id', $cottageIds)->get();

        $cottage_price = $cottages->sum(function ($cottage) use ($stayType) {
            return $stayType === 'overnight' ? $cottage->price_overnight : $cottage->price_day;
        });

        $guestRates = Setting::whereIn('key', ['guest_rate_day', 'guest_rate_overnight'])->pluck('value', 'key');

        $rateDay = $guestRates['guest_rate_day'] ?? 150;
        $rateOvernight = $guestRates['guest_rate_overnight'] ?? 200;

        $guest_price = $stayType === 'overnight'
            ? $guestCount * $rateOvernight
            : $guestCount * $rateDay;

        $total = $cottage_price + $guest_price;

        return $total;
    }
    public function store(Request $request) {}
}
