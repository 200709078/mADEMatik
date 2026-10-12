<?php
namespace App\Services;
use App\Models\{Comment,ActivityLog};
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Validation\ValidationException;
class ModerationService
{
    public function __construct(private NotificationService $notifications) {}
    public function moderate(Comment $comment, string $status, ?string $note = null): void
    {
        Gate::authorize('admin');
        if (!in_array($status,['approved','rejected','hidden'],true)) throw ValidationException::withMessages(['status'=>'Geçersiz yorum durumu.']);
        DB::transaction(function() use($comment,$status,$note) {
            $comment=Comment::lockForUpdate()->findOrFail($comment->id);
            if ($status==='approved' && $comment->parent_id && !Comment::publiclyVisible()->whereKey($comment->parent_id)->exists()) {
                throw ValidationException::withMessages(['status'=>'Yanıtı onaylamadan önce ana yorumun onaylı ve görünür olması gerekir.']);
            }
            if ($status==='approved' && $comment->user->is_blocked) throw ValidationException::withMessages(['status'=>'Engellenmiş üyenin yorumu onaylanamaz.']);
            $previous=$comment->status;
            $comment->forceFill(['status'=>$status,'moderated_by'=>auth()->id(),'moderated_at'=>now(),'approved_at'=>$status==='approved'?now():null,'moderation_note'=>$note])->save();
            ActivityLog::record('comment.'.$status,$comment,['previous_status'=>$previous,'note'=>$note]);
            $url=route('profile.edit');
            $this->notifications->send($comment->user,'comment_status','Yorumunuzun durumu güncellendi','Yorumunuz: '.$comment->status_label.'.',$url,'comment_mail');
            if ($status==='approved' && $previous!=='approved' && $comment->parent_id && $comment->parent->user_id!==$comment->user_id) {
                $target=$comment->project ? route('projects.show',$comment->project->slug) : route('comments.index');
                $this->notifications->send($comment->parent->user,'comment_reply','Yorumunuza yanıt geldi','Yorumunuza verilen yanıt yönetici tarafından onaylandı.',$target,'reply_mail');
            }
        });
    }
}
