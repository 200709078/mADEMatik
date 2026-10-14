<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class SiteMail extends Notification implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public function __construct(public string $title, public string $message, public string $url) { $this->afterCommit(); }
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->greeting(__('mail.greeting',['name'=>$notifiable->name ?? __('mail.admin_name')]))->line($this->message)->action(__('mail.site_action'), $this->url)->salutation('mADEMatik Solutions');
    }
}
