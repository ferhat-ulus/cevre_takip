<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public static function visible(Request $request): array
    {
        $announcements = array_filter(config('portal_announcements', []), fn ($announcement) => $announcement['published']);
        if (!$announcements) {
            return [];
        }

        $reads = DB::table('portal_announcement_reads')->where('user_id', $request->user()->id)
            ->pluck('acknowledged_at', 'announcement_key');

        return collect($announcements)->map(fn ($announcement, $key) => [
            ...$announcement, 'key' => $key, 'acknowledgedAt' => $reads->get($key),
        ])->values()->all();
    }

    public function index(Request $request)
    {
        $announcements = self::visible($request);
        if ($request->user()->role === 'admin' && $announcements) {
            $readers = DB::table('portal_announcement_reads')->join('users', 'users.id', '=', 'user_id')
                ->whereIn('announcement_key', array_column($announcements, 'key'))
                ->orderBy('acknowledged_at')->get(['announcement_key', 'users.name', 'users.username', 'acknowledged_at'])
                ->groupBy('announcement_key');
            foreach ($announcements as &$announcement) {
                $announcement['readers'] = $readers->get($announcement['key'], collect());
            }
            unset($announcement);
        }

        return Inertia::render('Duyurular', ['announcements' => $announcements]);
    }

    public function acknowledge(Request $request, string $announcement)
    {
        abort_unless(config("portal_announcements.{$announcement}.published"), 404);

        DB::table('portal_announcement_reads')->insertOrIgnore([
            'user_id' => $request->user()->id,
            'announcement_key' => $announcement,
            'acknowledged_at' => now(),
        ]);

        return back();
    }
}
