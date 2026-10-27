<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;  

class AdminAnnouncementController extends Controller
{
    // Show the list
    public function index(Request $request)
    {
        $query = Announcement::latest('publish_at');

        if ($request->status === 'active') {
            $query->active();
        } elseif ($request->status === 'scheduled') {
            $query->scheduled();
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $announcements = $query->paginate(3)->withQueryString();

        $allCount       = Announcement::count();
        $activeCount    = Announcement::active()->count();
        $scheduledCount = Announcement::scheduled()->count();
        $highPriority   = Announcement::active()->where('is_high_priority', true)->count();
        $guestsNotified = User::count();

        $reads = DB::table('announcement_reads')
            ->whereIn('announcement_id', Announcement::active()->pluck('id'))
            ->count();
        $possible = $activeCount * $guestsNotified;
        $openRate = $possible > 0 ? round($reads / $possible * 100) : 0;

        return view('Admin.dashboard', compact(
            'announcements', 'allCount', 'activeCount',
            'scheduledCount', 'highPriority', 'guestsNotified',
            'openRate'
        ));
    }

    // Save a new announcement
    public function store(Request $request)
    {
        $request->validate([
            'title'     => 'required|string|max:100',
            'message'   => 'required|string|max:1000',
            'category'  => 'required|in:Maintenance,Event,Promotion,General',
            'publish_at' => 'nullable|date',
        ]);

        Announcement::create([
            'title'            => $request->title,
            'message'          => $request->message,
            'category'         => $request->category,
            'is_high_priority' => $request->boolean('is_high_priority'),
            'publish_at'       => $request->publish_at ?? now(),
        ]);

        return back()->with('success', 'Announcement Edited.');
    }

    // Update an existing announcement
    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:100',
            'message'    => 'required|string|max:1000',
            'category'   => 'required|in:Maintenance,Event,Promotion,General',
            'publish_at' => 'nullable|date',
        ]);

        $announcement->update([
            'title'            => $data['title'],
            'message'          => $data['message'],
            'category'         => $data['category'],
            'is_high_priority' => $request->boolean('is_high_priority'),
            'publish_at'       => $data['publish_at'] ?? now(),
        ]);

        DB::table('announcement_reads')
            ->where('announcement_id', $announcement->id)
            ->delete();

        return back()->with('success', 'Announcement updated.');
    }

    // Delete an announcement
    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }
}
