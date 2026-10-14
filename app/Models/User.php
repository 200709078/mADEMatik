<?php
namespace App\Models;
use App\Services\AvatarService;
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
    // Only the public identity fields may leave the model as an array or JSON.
    protected $visible = ['id','name','locale'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_blocked'=>'boolean','deletion_requested_at'=>'datetime']; }
    public function meetsEmailVerificationRequirement(): bool
    {
        return ! config('auth.email_verification_enabled', true) || $this->hasVerifiedEmail();
    }
    public function sendEmailVerificationNotification(): void
    {
        if (config('auth.email_verification_enabled', true)) {
            parent::sendEmailVerificationNotification();
        }
    }
    public function isSuperUser(): bool { return $this->role === 'super_user' && !$this->is_blocked; }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }
    public function notificationPreference(): HasOne { return $this->hasOne(NotificationPreference::class)->withDefault(['comment_mail'=>false,'reply_mail'=>false,'project_mail'=>false,'site_notifications'=>true]); }
    public function getAvatarUrlAttribute(): ?string { return app(AvatarService::class)->urlFor($this->avatar_path); }
    public function preferredLocale(): string { return in_array($this->locale,['tr','en'],true) ? $this->locale : 'tr'; }
}
