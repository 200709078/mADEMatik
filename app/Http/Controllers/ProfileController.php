<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user(), 'preference' => $request->user()->notificationPreference()->firstOrCreate([], ['comment_mail' => false, 'reply_mail' => false, 'project_mail' => false, 'site_notifications' => true]), 'comments' => Comment::where('user_id', $request->user()->id)->with('project')->latest()->paginate(10)]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100'], 'email' => ['bail', 'required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)], 'current_password' => ['bail', 'required', 'string', 'current_password']]);
        $previousEmail = $user->email;
        $emailChanged = Str::lower($data['email']) !== $previousEmail;
        $user->fill(['name' => $data['name'], 'email' => Str::lower($data['email'])]);
        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->forceFill(['remember_token' => Str::random(60)]);
            DB::table('password_reset_tokens')->where('email', $previousEmail)->delete();
        }
        $user->save();
        ActivityLog::record('user.profile_updated', $user, ['email_changed' => $emailChanged]);
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            return redirect()->route('verification.notice')->with('status', 'Yeni e-posta adresinizi doğrulayın.');
        }
        return back()->with('status', 'Profiliniz güncellendi.');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => ['bail', 'required', 'string', 'current_password'], 'password' => ['required', 'confirmed', Password::defaults()]]);
        $request->user()->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->regenerate();
        ActivityLog::record('user.password_changed', $request->user());
        return back()->with('status', 'Parolanız güncellendi. Diğer cihazlardaki oturumlarınız kapatıldı.');
    }

    public function preferences(Request $request)
    {
        $request->validate(['comment_mail' => ['nullable', 'boolean'], 'reply_mail' => ['nullable', 'boolean'], 'project_mail' => ['nullable', 'boolean'], 'site_notifications' => ['nullable', 'boolean']]);
        $request->user()->notificationPreference()->updateOrCreate([], collect(['comment_mail', 'reply_mail', 'project_mail', 'site_notifications'])->mapWithKeys(fn ($key) => [$key => $request->boolean($key)])->all());
        return back()->with('status', 'Bildirim tercihleriniz kaydedildi.');
    }

    public function requestDeletion(Request $request, NotificationService $notifications)
    {
        $request->validate(['current_password' => ['bail', 'required', 'string', 'current_password'], 'confirm_deletion' => ['accepted']]);
        $user = $request->user();
        if ($user->deletion_requested_at) { return back()->with('status', 'Silme talebiniz zaten değerlendirme sırasında.'); }
        $message = DB::transaction(function () use ($user) {
            $user->forceFill(['deletion_requested_at' => now()])->save();
            $message = ContactMessage::create(['name' => $user->name, 'email' => $user->email, 'subject' => 'Hesap ve kişisel veri silme talebi', 'body' => 'Üye #'.$user->id.' hesabının ve kişisel verilerinin silinmesini talep etti. Yorumların ve bağlı yanıtların silinmesi ve saklama yükümlülükleri değerlendirilmelidir.', 'status' => 'new']);
            ActivityLog::record('account.deletion_requested', $user);
            return $message;
        });
        $notifications->superUsers('account.deletion_requested', 'Hesap silme talebi', 'Bir üye hesap ve kişisel veri silme talebinde bulundu.', route('admin.contact-messages.show', $message));
        return back()->with('status', 'Silme talebiniz yöneticiye iletildi. İşlem sonucu sizinle paylaşılacak.');
    }
}
