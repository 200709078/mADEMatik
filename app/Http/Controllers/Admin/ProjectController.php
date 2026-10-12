<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Services\MediaService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('category')->orderBy('sort_order')->latest();
        if ($request->filled('q')) { $term = mb_substr($request->string('q'), 0, 100); $query->where(fn ($q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('slug', 'like', '%'.$term.'%')); }
        return view('admin.projects.index', ['projects' => $query->paginate(20)->withQueryString()]);
    }
    public function create() { return view('admin.projects.form', ['project' => new Project(['status' => 'developing', 'sort_order' => 0]), 'categories' => ProjectCategory::orderBy('sort_order')->get()]); }
    public function edit(Project $project) { return view('admin.projects.form', ['project' => $project->load('media'), 'categories' => ProjectCategory::orderBy('sort_order')->get()]); }
    public function store(ProjectRequest $request, MediaService $media, NotificationService $notifications)
    {
        $project = new Project();
        $this->save($request, $project, $media, $notifications);
        ActivityLog::record('project.created', $project);
        return redirect()->route('admin.projects.edit', $project)->with('status', 'Proje oluşturuldu.');
    }
    public function update(ProjectRequest $request, Project $project, MediaService $media, NotificationService $notifications)
    {
        $this->save($request, $project, $media, $notifications);
        ActivityLog::record('project.updated', $project);
        return back()->with('status', 'Proje güncellendi.');
    }
    private function save(ProjectRequest $request, Project $project, MediaService $media, NotificationService $notifications): void
    {
        $data = $request->validated();
        foreach (['title', 'summary', 'description', 'seo_title', 'meta_description'] as $field) { $project->{$field} = array_replace((array) ($project->{$field} ?? []), ['tr' => $data[$field] ?? '']); }
        $project->features = array_replace((array) ($project->features ?? []), ['tr' => array_values(array_filter(array_map('trim', preg_split('/\R/u', $data['features'] ?? '') ?: [])))]);
        $project->fill(collect($data)->only(['slug', 'project_category_id', 'status', 'sort_order', 'external_url'])->all());
        $project->is_published = $request->boolean('is_published');
        $project->is_featured = $request->boolean('is_featured');
        $project->technologies = array_values(array_filter(array_map('trim', explode(',', $data['technologies'] ?? ''))));
        $oldCover = $project->cover_path;
        if ($request->hasFile('cover')) { $project->cover_path = $media->store($request->file('cover')); }
        elseif ($request->boolean('remove_cover')) { $project->cover_path = null; }
        $project->save();
        if ($oldCover && $oldCover !== $project->cover_path && str_starts_with($oldCover, 'media/')) { Storage::disk('public')->delete($oldCover); }
        if ($request->boolean('announce') && $project->is_published) {
            User::where('is_blocked', false)->whereNotNull('email_verified_at')->whereHas('notificationPreference', fn ($q) => $q->where('project_mail', true))->chunkById(100, function ($users) use ($project, $notifications) {
                foreach ($users as $user) { $notifications->send($user, 'project.announced', 'Yeni proje duyurusu', $project->translated('title'), route('projects.show', $project->slug), 'project_mail'); }
            });
            ActivityLog::record('project.announced', $project);
        }
    }
    public function destroy(Project $project)
    {
        $paths = $project->media()->pluck('path')->push($project->cover_path)->filter(fn ($path) => $path && str_starts_with($path, 'media/'))->all();
        ActivityLog::record('project.deleted', $project);
        $project->delete();
        Storage::disk('public')->delete($paths);
        return redirect()->route('admin.projects.index')->with('status', 'Proje silindi.');
    }
}
