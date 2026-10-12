<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', ['stats' => ['projects' => Project::count(), 'pending_comments' => Comment::where('status', 'pending')->count(), 'new_messages' => ContactMessage::where('status', 'new')->count(), 'users' => User::count()], 'comments' => Comment::with('user', 'project')->where('status', 'pending')->latest()->limit(5)->get(), 'activities' => ActivityLog::with('user')->latest()->limit(8)->get()]);
    }
}
