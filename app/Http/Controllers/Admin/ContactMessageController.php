<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Services\ContactMessageNotifications;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    public function index(Request $request, ContactMessageNotifications $notifications)
    {
        $data = $request->validate(['status' => ['bail', 'nullable', 'string', Rule::in(['new', 'read', 'archived'])]]);
        $notifications->syncLegacy($request->user());
        $query = ContactMessage::latest();
        if (isset($data['status'])) { $query->where('status', $data['status']); }
        return view('admin.contact-messages.index', ['messages' => $query->paginate(25)->withQueryString()]);
    }
    public function show(Request $request, ContactMessage $contactMessage, ContactMessageNotifications $notifications)
    {
        if ($contactMessage->status === 'new') { $contactMessage->update(['status' => 'read', 'read_at' => now()]); }
        $notifications->markAsRead($request->user(), $contactMessage);
        return view('admin.contact-messages.show', ['message' => $contactMessage]);
    }
    public function update(Request $request, ContactMessage $contactMessage, ContactMessageNotifications $notifications)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'read', 'archived'])]]);
        $contactMessage->update(['status' => $data['status'], 'read_at' => $data['status'] === 'new' ? null : now()]);
        if ($data['status'] !== 'new') { $notifications->markAsRead($request->user(), $contactMessage); }
        ActivityLog::record('contact.status_updated', $contactMessage, ['status' => $data['status']]);
        return back()->with('status', 'Mesaj durumu güncellendi.');
    }
    public function destroy(Request $request, ContactMessage $contactMessage, ContactMessageNotifications $notifications)
    {
        ActivityLog::record('contact.deleted', $contactMessage); $contactMessage->delete();
        $notifications->markAsRead($request->user(), $contactMessage);
        return redirect()->route('admin.contact-messages.index')->with('status', 'İletişim mesajı silindi.');
    }
}
