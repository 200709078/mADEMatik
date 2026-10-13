<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = User::latest();
        if (($data['q'] ?? '') !== '') { $term = addcslashes($data['q'], '%_\\'); $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')); }
        return view('admin.users.index', ['users' => $query->paginate(25)->withQueryString()]);
    }
    public function edit(User $user) { return view('admin.users.edit', compact('user')); }
    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100'], 'is_blocked' => ['nullable', 'boolean']]);
        DB::transaction(function () use ($request,$user,$data) {
            // Lock the complete role set in a stable order to prevent mutual blocking write skew.
            $superUsers=User::where('role','super_user')->orderBy('id')->lockForUpdate()->get();
            $actor=$superUsers->firstWhere('id',$request->user()->id);
            abort_unless($actor?->isSuperUser(),403);
            $target=$superUsers->firstWhere('id',$user->id) ?? User::lockForUpdate()->findOrFail($user->id);
            $block=$request->boolean('is_blocked');
            if ($target->id===$actor->id && $block) throw ValidationException::withMessages(['is_blocked'=>'Kendi hesabınızı engelleyemezsiniz.']);
            $activeCount=$superUsers->filter(fn($candidate)=>!$candidate->is_blocked)->count();
            if ($target->role==='super_user' && $block && $activeCount-($target->is_blocked?0:1)<1) throw ValidationException::withMessages(['is_blocked'=>'Son etkin Super User engellenemez.']);
            $target->forceFill(['name'=>$data['name'],'is_blocked'=>$block])->save();
            if ($block) DB::table('sessions')->where('user_id',$target->id)->delete();
            ActivityLog::record('user.updated',$target,['is_blocked'=>$block]);
        },3);
        return back()->with('status', 'Üye bilgileri güncellendi.');
    }
    public function destroy(Request $request, User $user, AvatarService $avatars)
    {
        $request->validate(['confirm_delete' => ['accepted']]);
        if ($user->id === $request->user()->id || ($user->role === 'super_user')) { return back()->withErrors(['user' => 'Super User hesapları bu ekrandan silinemez.']); }
        $avatarPath = DB::transaction(function () use ($request, $user) {
            // Read the final photo while holding the same row lock used during uploads.
            $target = User::lockForUpdate()->findOrFail($user->getKey());
            abort_if($target->id === $request->user()->id || $target->role === 'super_user', 403);
            $avatarPath = $target->avatar_path;
            ActivityLog::record('user.deleted', $target, ['comments_deleted' => $target->comments()->count()]);
            DB::table('sessions')->where('user_id', $target->id)->delete();
            DB::table('password_reset_tokens')->where('email', $target->email)->delete();
            $target->notifications()->delete();
            $target->delete();

            return $avatarPath;
        });
        DB::afterCommit(fn () => $avatars->deleteStored($avatarPath));
        return redirect()->route('admin.users.index')->with('status', 'Üye hesabı, yorumları ve bağlı yanıtlar silindi. İletişim ve işlem kayıtlarındaki kişisel verileri ayrıca değerlendirin.');
    }
}
