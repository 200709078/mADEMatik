<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index() { return view('admin.activity.index', ['activities' => ActivityLog::with('user')->latest()->paginate(40)]); }
}
