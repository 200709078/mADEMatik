<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{HasMany,HasOne};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    use HasFactory, Notifiable;
    protected $fillable = ['name','email','password','locale'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_blocked'=>'boolean','deletion_requested_at'=>'datetime']; }
    public function isSuperUser(): bool { return $this->role === 'super_user' && !$this->is_blocked; }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }
    public function notificationPreference(): HasOne { return $this->hasOne(NotificationPreference::class)->withDefault(['comment_mail'=>false,'reply_mail'=>false,'project_mail'=>false,'site_notifications'=>true]); }
    public function preferredLocale(): string { return in_array($this->locale,['tr','en'],true) ? $this->locale : 'tr'; }
}
