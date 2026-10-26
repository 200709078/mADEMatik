<?php

namespace Database\Seeders;

use App\Models\{Setting, Project, ProjectCategory};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (config('content') as $key=>$value) Setting::firstOrCreate(['key'=>$key],['value'=>$value]);
        $tools=ProjectCategory::firstOrCreate(['slug'=>'dijital-araclar'],['name'=>['tr'=>'Dijital Araçlar'],'sort_order'=>1]);
        $education=ProjectCategory::firstOrCreate(['slug'=>'egitim-matematik'],['name'=>['tr'=>'Eğitim & Matematik'],'sort_order'=>2]);
        $management=ProjectCategory::firstOrCreate(['slug'=>'yonetim'],['name'=>['tr'=>'Yönetim'],'sort_order'=>3]);
        $items=[
            ['draw','drAW',$tools->id,'Etkileşimli beyaz tahta ve çizim uygulaması.','Çizim ve eğitim amaçlı kullanılabilen bağımsız beyaz tahta uygulaması.',['Etkileşimli çizim alanı'],['TypeScript','Vite','Electron'],'https://madematik.com/drAW'],
            ['draw2d','drAW2D',$education->id,'Fonksiyon grafikleri ve iki boyutlu geometri araçları.','Matematik ve geometri çalışmaları için grafik ve çizim araçları sunan bağımsız uygulama.',['Fonksiyon grafikleri','İki boyutlu geometri'],['JavaScript','Canvas','math.js'],'https://madematik.com/drAW2D'],
            ['draw3d','drAW3D',$education->id,'Üç boyutlu geometriyi etkileşimli keşfedin.','Nokta, doğru, düzlem ve temel geometrik cisimler üzerinde çalışmayı sağlayan bağımsız uygulama.',['Üç boyutlu geometri','Prizma, piramit, küre, silindir ve koni'],['TypeScript','Vite','Three.js'],'https://madematik.com/drAW3D'],
            ['awcell','awCELL',$tools->id,'Hücre tabanlı çalışma kitapları ve hesaplama araçları.','Hücreler, formüller ve grafikler üzerinden veri ile çalışmayı sağlayan bağımsız çalışma kitabı uygulaması.',['Formüller','Biçimlendirme ve sıralama','Grafikler'],['JavaScript'],'https://madematik.com/awCELL'],
            ['awa','aWa',$management->id,'Okul, öğrenci ve personel yönetimi.','Öğrenci ve personel bilgileri, sınav ve oturma planları için geliştirilen bağımsız uygulama. aWa ayrı hesap, ayrı veritabanı ve ayrı yönetim paneli kullanır.',['Öğrenci ve personel yönetimi','Sınav ve oturma planları'],['Laravel','Vue','Inertia'],'https://awa.madematik.com'],
        ];
        foreach ($items as [$slug,$title,$category,$summary,$description,$features,$technologies,$url]) {
            Project::firstOrCreate(['slug'=>$slug],['title'=>['tr'=>$title],'project_category_id'=>$category,'summary'=>['tr'=>$summary],'description'=>['tr'=>$description],'features'=>['tr'=>$features],'technologies'=>$technologies,'status'=>'live','is_published'=>true,'is_featured'=>true,'sort_order'=>array_search($title,['drAW','drAW2D','drAW3D','awCELL','aWa'],true)+1,'external_url'=>$url]);
        }
        $this->call(ClockProjectSeeder::class);
        $this->call(DrawDownloadsSeeder::class);
    }
}
