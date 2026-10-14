<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SiteMail;
use App\Notifications\SiteNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotificationService
{
    public function send(User $user, string $type, string $title, string $message, string $url, ?string $mailPreference = null, array $context = [], bool $sendAdminMail = true): void
    {
        if ($user->is_blocked) return;

        $preference = $user->notificationPreference;
        if ($user->isSuperUser() || $preference->site_notifications) {
            $user->notify(new SiteNotification($type, $title, $message, $url, $context));
        }

        if ($user->isSuperUser()) {
            if ($sendAdminMail) $this->mailAdministrators([$user], $title, $message, $url);
            return;
        }

        if ($mailPreference && $user->meetsEmailVerificationRequirement() && $preference->getAttribute($mailPreference)) {
            $this->mailAfterCommit($user, (new SiteMail($title, $message, $url))->locale($user->preferredLocale()));
        }
    }

    public function superUsers(string $type, string $title, string $message, string $url, array $context = []): void
    {
        $users = User::where('role', 'super_user')->where('is_blocked', false)->get()->all();
        foreach ($users as $user) {
            $this->send($user, $type, $title, $message, $url, context: $context, sendAdminMail: false);
        }
        $this->mailAdministrators($users, $title, $message, $url);
    }

    private function mailAdministrators(array $users, string $title, string $message, string $url): void
    {
        if ($users === []) return;

        $address = trim((string) config('mail.admin_notification_address'));
        if ($address !== '') {
            if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                Log::error('Yönetici bildirim e-posta adresi geçersiz.');
                return;
            }
            // A shared inbox receives one email per event, even with multiple administrators.
            $this->mailAfterCommit(Notification::route('mail', $address), (new SiteMail($title, $message, $url))->locale($users[0]->preferredLocale()));
            return;
        }

        foreach ($users as $user) {
            if ($user->meetsEmailVerificationRequirement()) {
                $this->mailAfterCommit($user, (new SiteMail($title, $message, $url))->locale($user->preferredLocale()));
            }
        }
    }

    private function mailAfterCommit(object $notifiable, SiteMail $notification): void
    {
        DB::afterCommit(function () use ($notifiable, $notification) {
            try {
                $notifiable->notify($notification);
            } catch (Throwable $exception) {
                // SMTP errors must not undo saved messages or expose connection details.
                Log::error('E-posta bildirimi gönderilemedi.', ['exception_type' => get_class($exception)]);
            }
        });
    }
}
