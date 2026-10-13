<?php
namespace App\Services;
use App\Models\User;
use App\Notifications\{SiteNotification,SiteMail};
class NotificationService
{
    public function send(User $user, string $type, string $title, string $message, string $url, ?string $mailPreference = null, array $context = []): void
    {
        if ($user->is_blocked) return;
        $preference=$user->notificationPreference;
        if ($user->isSuperUser() || $preference->site_notifications) $user->notify(new SiteNotification($type,$title,$message,$url,$context));
        if ($mailPreference && $user->hasVerifiedEmail() && $preference->getAttribute($mailPreference)) {
            $user->notify((new SiteMail($title,$message,$url))->locale($user->preferredLocale()));
        }
    }
    public function superUsers(string $type, string $title, string $message, string $url, array $context = []): void
    {
        User::where('role','super_user')->where('is_blocked',false)->each(function(User $user) use($type,$title,$message,$url,$context) {
            $this->send($user,$type,$title,$message,$url,context: $context);
            if ($user->hasVerifiedEmail()) $user->notify((new SiteMail($title,$message,$url))->locale($user->preferredLocale()));
        });
    }
}
