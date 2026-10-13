<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClockProjectSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function () {
            // Preserve the project's content and category after an administrator edits it.
            if (Project::where('slug', 'saat-sayac')->exists()) {
                return;
            }

            $category = ProjectCategory::firstOrCreate(['slug' => 'dijital-araclar'], [
                'name' => ['tr' => 'Dijital Araçlar'],
                'sort_order' => (int) ProjectCategory::max('sort_order') + 1,
            ]);

            Project::firstOrCreate(['slug' => 'saat-sayac'], [
                'project_category_id' => $category->id,
                'title' => ['tr' => 'Saat & Sayaç'],
                'summary' => ['tr' => 'Yerel saat ve kalıcı geri sayım aracını ders, çalışma ve mola sürelerinizi yönetmek için kullanın.'],
                'description' => ['tr' => 'mADEMatik içindeki Saat & Sayaç, cihazınızın yerel saatini gösterir ve belirlediğiniz süre için geri sayım yapar. Sayacı başlatabilir, duraklatabilir, kaldığı yerden sürdürebilir ve sıfırlayabilirsiniz. Sayaç bilgileri bu cihazın tarayıcısında saklanır; sayfayı yeniden açtığınızda çalışan sayaç bitiş zamanı üzerinden hesaplanır. Büyütme görünümü ve açık/koyu tema desteği sunar.'],
                'features' => ['tr' => [
                    'Yerel saat ve tarih gösterimi',
                    'Saat, dakika ve saniye ile geri sayım',
                    'Başlatma, duraklatma, sürdürme ve sıfırlama',
                    'Tarayıcıda saklanan sayaç durumu',
                    'Büyütme ve tam ekran görünümü',
                    'Açık/koyu tema ve mobil uyum',
                ]],
                'technologies' => ['Laravel 13', 'Blade', 'Tailwind CSS 4', 'Alpine.js', 'JavaScript'],
                'status' => 'live',
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => (int) Project::max('sort_order') + 1,
                'external_url' => '/saat-sayac',
            ]);
        });
    }
}
