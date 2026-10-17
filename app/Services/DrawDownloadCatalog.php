<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DrawDownloadCatalog
{
    public function import(): int
    {
        return DB::transaction(function () {
            $project = Project::where('slug', 'draw')->lockForUpdate()->first();
            if (! $project) {
                throw new RuntimeException('drAW proje kaydı bulunamadı. Önce projeyi oluşturun.');
            }

            $count = 0;
            foreach (config('project-downloads.draw', []) as $attributes) {
                // Identify a verified binary by its digest. Preserve all later admin edits.
                $download = $project->downloads()->firstOrCreate(
                    ['sha256' => $attributes['sha256']],
                    array_replace($attributes, ['is_published' => false]),
                );
                if ($download->wasRecentlyCreated) {
                    $count++;
                }
            }

            return $count;
        });
    }
}
