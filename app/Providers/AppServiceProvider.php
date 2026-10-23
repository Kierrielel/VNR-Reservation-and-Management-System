<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider

{
    public function register(): void
    {
        //
    }

    public function boot(): void
{
    View::composer('LoginReg.include.header', function ($view) {
        if (!Auth::check()) {
            return;
        }

        $navAnnouncements = Announcement::active()->latest('publish_at')->take(10)->get();
        $readIds = DB::table('announcement_reads')
            ->where('user_id', Auth::id())
            ->pluck('announcement_id');

        $view->with([
            'navAnnouncements' => $navAnnouncements,
            'readIds'          => $readIds,
            'unreadCount'      => $navAnnouncements->whereNotIn('id', $readIds->all())->count(),
        ]);
    });
}
}