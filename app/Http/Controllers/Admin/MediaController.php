<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function store(Request $request, Project $project, MediaService $media)
    {
        $data = $request->validate(['image' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'], 'alt' => ['required', 'string', 'max:200'], 'sort_order' => ['bail', 'required', 'integer', 'min:0', 'max:10000']]);
        $item = $project->media()->create(['path' => $media->store($request->file('image')), 'alt' => ['tr' => $data['alt']], 'sort_order' => $data['sort_order']]);
        ActivityLog::record('media.created', $item); return back()->with('status', 'Proje görseli eklendi.');
    }
    public function update(Request $request, Project $project, ProjectMedia $media)
    {
        abort_unless((int) $media->project_id === (int) $project->id, 404);
        $data = $request->validate(['alt' => ['required', 'string', 'max:200'], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000']]);
        $media->update(['alt' => array_replace((array) $media->alt, ['tr' => $data['alt']]), 'sort_order' => $data['sort_order']]);
        ActivityLog::record('media.updated', $media); return back()->with('status', 'Görsel bilgileri güncellendi.');
    }
    public function destroy(Project $project, ProjectMedia $media)
    {
        abort_unless((int) $media->project_id === (int) $project->id, 404);
        $path = $media->path; ActivityLog::record('media.deleted', $media); $media->delete();
        if (str_starts_with($path, 'media/')) { Storage::disk('public')->delete($path); }
        return back()->with('status', 'Görsel silindi.');
    }
}
