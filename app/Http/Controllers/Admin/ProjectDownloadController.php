<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectDownloadRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectDownloadController extends Controller
{
    public function create(Project $project): View
    {
        $download = new ProjectDownload([
            'distribution_type' => 'direct',
            'channel' => 'stable',
            'sort_order' => 0,
            'is_published' => false,
        ]);

        return view('admin.projects.download-form', compact('project', 'download'));
    }

    public function store(ProjectDownloadRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            $download = $project->downloads()->make();
            $this->save($request, $download);
            ActivityLog::record('download.created', $download);
        });

        return $this->redirectToProject($project)->with('status', __('downloads.messages.created'));
    }

    public function edit(Project $project, ProjectDownload $download): View
    {
        $this->ensureProject($project, $download);

        return view('admin.projects.download-form', compact('project', 'download'));
    }

    public function update(ProjectDownloadRequest $request, Project $project, ProjectDownload $download): RedirectResponse
    {
        $this->ensureProject($project, $download);
        DB::transaction(function () use ($request, $download) {
            $this->save($request, $download);
            ActivityLog::record('download.updated', $download);
        });

        return $this->redirectToProject($project)->with('status', __('downloads.messages.updated'));
    }

    public function destroy(Request $request, Project $project, ProjectDownload $download): RedirectResponse
    {
        $this->ensureProject($project, $download);
        $request->validate(['confirm_delete' => ['required', 'accepted']]);
        DB::transaction(function () use ($download) {
            ActivityLog::record('download.deleted', $download);
            $download->delete();
        });

        return $this->redirectToProject($project)->with('status', __('downloads.messages.deleted'));
    }

    private function save(ProjectDownloadRequest $request, ProjectDownload $download): void
    {
        $data = $request->validated();
        foreach (['title', 'notes', 'requirements'] as $field) {
            $download->{$field} = array_replace((array) ($download->{$field} ?? []), ['tr' => $data[$field] ?? '']);
        }

        $download->fill(collect($data)->except(['title', 'notes', 'requirements', 'is_published'])->all());
        $download->sha256 = isset($data['sha256']) ? strtolower($data['sha256']) : null;
        $download->is_published = $request->boolean('is_published');
        $download->save();
    }

    private function ensureProject(Project $project, ProjectDownload $download): void
    {
        abort_unless((int) $download->project_id === (int) $project->id, 404);
    }

    private function redirectToProject(Project $project): RedirectResponse
    {
        return redirect()->to(route('admin.projects.edit', $project).'#downloads');
    }
}
