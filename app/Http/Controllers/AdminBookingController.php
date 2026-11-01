<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\AdminBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,confirmed,completed,cancelled,expired',
            'date' => 'nullable|date_format:Y-m-d',
            'type' => 'nullable|in:public,event',
        ]);

        $recentBooking = Booking::with('user')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('reference_code', 'like', '%'.$search.'%')
                        ->orWhere('guest_name', 'like', '%'.$search.'%')
                        ->orWhere('guest_email', 'like', '%'.$search.'%')
                        ->orWhere('guest_contact', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn (Builder $user): Builder => $user
                            ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('check_in', $date))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('reservation_type', $type))
            ->latest()->orderByDesc('id')->paginate(5)->withQueryString();

        return view('Admin.dashboard', compact(
            'recentBooking'
        ));
    }

    public function show(Booking $booking): View
    {
        return view('Admin.dashboard', [
            'booking' => $booking->load('user', 'cottages', 'eventPackage', 'payments', 'checkedInBy', 'bookedBy'),
        ]);
    }

    public function update(Request $request, Booking $booking, AdminBookingService $service): RedirectResponse
    {
        $validated = $request->validate(['status' => 'required|in:confirmed,completed,cancelled']);
        $service->updateStatus($booking, $validated['status']);

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Booking status updated.');
    }

    public function settleBalance(Request $request, Booking $booking, AdminBookingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'method' => 'required|in:cash,gcash,paymaya,qrph',
            'reference_no' => 'nullable|required_unless:method,cash|string|max:100',
        ]);
        $service->settleBalance($booking, $validated['method'], $validated['reference_no'] ?? null);

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Remaining balance recorded as paid.');
    }
}
