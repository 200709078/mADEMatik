<?php

namespace App\Http\Controllers;

use App\Services\ContactMessageNotifications;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, ContactMessageNotifications $notifications)
    {
        $notifications->syncLegacy($request->user());
        return view('notifications.index', ['notifications' => $request->user()->notifications()->latest()->paginate(20)]);
    }
    public function read(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();
        return back()->with('status', 'Bildirim okundu olarak işaretlendi.');
    }
    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return back()->with('status', 'Tüm bildirimler okundu olarak işaretlendi.');
    }
}
