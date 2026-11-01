<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCottageRequest;
use App\Models\Cottage;
use App\Services\CottageManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminRoomsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'type' => 'nullable|in:cottage,room',
            'status' => 'nullable|in:available,occupied,unavailable',
        ]);

        $cottages = Cottage::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->where('name', 'like', '%'.$search.'%'))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->withExists('bookings')->orderBy('id')->paginate(8)->withQueryString();

        return view('Admin.dashboard', compact('cottages'));
    }

    public function create(): View
    {
        return view('Admin.dashboard', ['cottage' => new Cottage]);
    }

    public function store(SaveCottageRequest $request): RedirectResponse
    {
        $cottage = Cottage::create($request->validated());

        return redirect()->route('admin.rooms.edit', $cottage)->with('success', 'Room or cottage created.');
    }

    public function edit(Cottage $cottage): View
    {
        return view('Admin.dashboard', ['cottage' => $cottage]);
    }

    public function update(SaveCottageRequest $request, Cottage $cottage, CottageManagementService $service): RedirectResponse
    {
        $service->update($cottage, $request->validated());

        return redirect()->route('admin.rooms.edit', $cottage)->with('success', 'Room or cottage updated.');
    }

    public function destroy(Cottage $cottage, CottageManagementService $service): RedirectResponse
    {
        $service->delete($cottage);

        return redirect()->route('admin.rooms.index')->with('success', 'Room or cottage deleted.');
    }
}
