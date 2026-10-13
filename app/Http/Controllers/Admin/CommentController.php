<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $filters=$request->validate(['q'=>['nullable','string','max:100'],'status'=>['nullable','string',Rule::in(['pending','approved','rejected','hidden','all'])]]);
        $status = $filters['status'] ?? 'all';
        $query = Comment::with('user', 'project', 'parent')->latest();
        if (in_array($status, ['pending', 'approved', 'rejected', 'hidden'], true)) { $query->where('status', $status); }
        if (($filters['q']??'')!=='') $query->where('body','like','%'.addcslashes($filters['q'],'%_\\').'%');
        return view('admin.comments.index', ['comments' => $query->paginate(20)->withQueryString(), 'status' => $status, 'reports' => CommentReport::with('comment', 'user')->where('status', 'open')->latest()->limit(30)->get()]);
    }
    public function update(Request $request, Comment $comment, ModerationService $moderation)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'hidden'])], 'moderation_note' => ['nullable', 'string', 'max:1000']]);
        $moderation->moderate($comment, $data['status'], $data['moderation_note'] ?? null);
        return back()->with('status', 'Yorumun durumu güncellendi.');
    }
    public function destroy(Comment $comment)
    {
        ActivityLog::record('comment.deleted', $comment); $comment->delete();
        return back()->with('status', 'Yorum silindi.');
    }
    public function resolveReport(CommentReport $report)
    {
        $report->update(['status' => 'resolved']); ActivityLog::record('comment.report_resolved', $report);
        return back()->with('status', 'Yorum şikâyeti değerlendirildi olarak işaretlendi.');
    }
}
