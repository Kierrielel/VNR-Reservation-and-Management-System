<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function markAllRead()
    {
        foreach (Announcement::active()->pluck('id') as $id) {
            DB::table('announcement_reads')->updateOrInsert([
                'user_id'         => Auth::id(),
                'announcement_id' => $id,
            ]);
        }

        return back();
    }
}