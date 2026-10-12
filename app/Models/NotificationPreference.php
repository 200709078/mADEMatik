<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NotificationPreference extends Model
{
    protected $fillable=['user_id','comment_mail','reply_mail','project_mail','site_notifications'];
    protected function casts(): array { return ['comment_mail'=>'boolean','reply_mail'=>'boolean','project_mail'=>'boolean','site_notifications'=>'boolean']; }
}
