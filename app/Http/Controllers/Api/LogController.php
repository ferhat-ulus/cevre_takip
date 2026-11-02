<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Inertia\Inertia;

class LogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz yok.');
        }

        // Son 1000 log kaydını çekelim ki sistem yavaşlamasın
        $logs = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get();

        return Inertia::render('IslemGecmisi', [
            'logs' => $logs
        ]);
    }
}