<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog,Comment,CommentReport,Project};
use App\Services\{Settings,NotificationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Validation\ValidationException;
class CommentController extends Controller
{
    public function index()
    {
        return view('comments.index',['siteSettings'=>Settings::allLocalized(),'comments'=>Comment::publiclyVisible()->whereNull('project_id')->whereNull('parent_id')->with(['user','replies'=>fn($q)=>$q->publiclyVisible()->with('user')])->latest('approved_at')->paginate(12),'seo'=>['title'=>'Ziyaretçi görüşleri | mADEMatik Solutions','description'=>'Onaylı ziyaretçi görüşleri ve yanıtları.']]);
    }
    public function store(Request $request,NotificationService $notifications)
    {
        Gate::authorize('create',Comment::class);
        $data=$request->validate(['body'=>['required','string','min:10','max:3000'],'project_id'=>['nullable','integer'],'parent_id'=>['nullable','integer'],'website'=>['nullable','max:0']]);
        if (!empty($data['project_id'])) Project::published()->findOrFail($data['project_id']);
        if (!empty($data['parent_id'])) {
            $parent=Comment::publiclyVisible()->whereNull('parent_id')->findOrFail($data['parent_id']);
            if ((int)$parent->project_id!==(int)($data['project_id']??null)) throw ValidationException::withMessages(['body'=>'Yanıtın proje kapsamı ana yorumla aynı olmalıdır.']);
        }
        DB::transaction(function() use($request,$data,$notifications) {
            $user=\App\Models\User::lockForUpdate()->findOrFail($request->user()->id);
            if ($user->comments()->where('status','pending')->count()>=5) throw ValidationException::withMessages(['body'=>'En fazla beş yorumunuz aynı anda onay bekleyebilir.']);
            $body=trim($data['body']);
            if ($user->comments()->where('body',$body)->where('created_at','>',now()->subHour())->exists()) throw ValidationException::withMessages(['body'=>'Bu yorumu kısa süre önce gönderdiniz.']);
            $comment=$user->comments()->create(['body'=>$body,'project_id'=>$data['project_id']??null,'parent_id'=>$data['parent_id']??null]);
            ActivityLog::record('comment.submitted',$comment);
            $notifications->superUsers('comment_pending','Yeni yorum onay bekliyor','Bir üye yeni bir yorum veya yanıt gönderdi.',route('admin.comments.index'));
        });
        return back()->with('status','Yorumunuz kaydedildi. Yayınlanmadan önce Super User onayından geçecektir.');
    }
    public function edit(Comment $comment)
    {
        Gate::authorize('update',$comment);
        return view('comments.edit',['comment'=>$comment,'siteSettings'=>Settings::allLocalized(),'seo'=>['title'=>'Yorumu düzenle | mADEMatik Solutions']]);
    }
    public function update(Request $request,Comment $comment,NotificationService $notifications)
    {
        Gate::authorize('update',$comment);
        $data=$request->validate(['body'=>['required','string','min:10','max:3000']]);
        DB::transaction(function() use($comment,$data,$notifications) {
            $comment=Comment::lockForUpdate()->findOrFail($comment->id);
            $comment->forceFill(['body'=>trim($data['body']),'status'=>'pending','moderated_by'=>null,'moderated_at'=>null,'approved_at'=>null,'moderation_note'=>null])->save();
            ActivityLog::record('comment.resubmitted',$comment);
            $notifications->superUsers('comment_pending','Düzenlenen yorum onay bekliyor','Bir üye yorumunu düzenledi. Yorum yeniden onay bekliyor.',route('admin.comments.index'));
        });
        return back()->with('status','Yorumunuz güncellendi ve yeniden onay bekliyor.');
    }
    public function destroy(Comment $comment)
    {
        Gate::authorize('delete',$comment); ActivityLog::record('comment.deleted',$comment); $comment->delete();
        return back()->with('status','Yorumunuz ve varsa yanıtları silindi.');
    }
    public function report(Request $request,Comment $comment,NotificationService $notifications)
    {
        abort_unless(Comment::publiclyVisible()->whereKey($comment->id)->exists(),404);
        $data=$request->validate(['reason'=>['required','string','min:10','max:1000']]);
        $report=CommentReport::firstOrCreate(['comment_id'=>$comment->id,'user_id'=>$request->user()->id],['reason'=>$data['reason'],'status'=>'open']);
        if ($report->wasRecentlyCreated) $notifications->superUsers('comment_report','Yorum şikâyeti','Bir yorum inceleme için bildirildi.',route('admin.comments.index'));
        return back()->with('status','Bildiriminiz inceleme için kaydedildi.');
    }
}
