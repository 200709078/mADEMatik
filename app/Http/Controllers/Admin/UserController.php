<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::latest();
        if ($request->filled('q')) { $term = mb_substr($request->string('q'), 0, 100); $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')); }
        return view('admin.users.index', ['users' => $query->paginate(25)->withQueryString()]);
    }
    public function edit(User $user) { return view('admin.users.edit', compact('user')); }
    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100'], 'is_blocked' => ['nullable', 'boolean']]);
        if ($user->id === $request->user()->id && $request->boolean('is_blocked')) { return back()->withErrors(['is_blocked' => 'Kendi hesabınızı engelleyemezsiniz.']); }
        if (($user->role === 'super_user') && $request->boolean('is_blocked') && User::where('role', 'super_user')->where('is_blocked', false)->where('id', '!=', $user->id)->count() === 0) { return back()->withErrors(['is_blocked' => 'Son etkin Super User engellenemez.']); }
        $user->forceFill(['name' => $data['name'], 'is_blocked' => $request->boolean('is_blocked')])->save();
        if ($user->is_blocked) { DB::table('sessions')->where('user_id', $user->id)->delete(); }
        ActivityLog::record('user.updated', $user, ['is_blocked' => $user->is_blocked]);
        return back()->with('status', 'Üye bilgileri güncellendi.');
    }
    public function destroy(Request $request, User $user)
    {
        $request->validate(['confirm_delete' => ['accepted']]);
        if ($user->id === $request->user()->id || ($user->role === 'super_user')) { return back()->withErrors(['user' => 'Super User hesapları bu ekrandan silinemez.']); }
        DB::transaction(function () use ($user) {
            ActivityLog::record('user.deleted', $user, ['comments_deleted' => $user->comments()->count()]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->notifications()->delete();
            $user->delete();
        });
        return redirect()->route('admin.users.index')->with('status', 'Üye hesabı, yorumları ve bağlı yanıtlar silindi. İletişim ve işlem kayıtlarındaki kişisel verileri ayrıca değerlendirin.');
    }
}
