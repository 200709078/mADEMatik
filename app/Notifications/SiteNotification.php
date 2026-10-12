<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
class SiteNotification extends Notification
{
    public function __construct(public string $type, public string $title, public string $message, public string $url) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array { return ['type'=>$this->type,'title'=>$this->title,'message'=>$this->message,'url'=>$this->url]; }
}
