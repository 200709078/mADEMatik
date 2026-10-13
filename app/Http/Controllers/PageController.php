<?php
namespace App\Http\Controllers;
use App\Models\{Comment,Project};
use App\Services\Settings;
class PageController extends Controller
{
    private function page(string $view,string $title,array $data=[])
    {
        $settings=Settings::allLocalized();
        $pageTitle=str_contains($title,'mADEMatik') ? $title : $title.' | mADEMatik Solutions';
        return view($view,array_merge(['siteSettings'=>$settings,'seo'=>['title'=>$pageTitle,'description'=>$settings['seo']['description']??'','canonical'=>url()->current()]],$data));
    }
    public function home()
    {
        $settings=Settings::allLocalized();
        return $this->page('pages.home',$settings['seo']['title']??'mADEMatik Solutions',[
            'slides'=>$settings['sliders'],
            'featuredProjects'=>Project::published()->with('category')->where('is_featured',true)->orderBy('sort_order')->limit(6)->get(),
            'testimonials'=>Comment::publiclyVisible()->whereNull('project_id')->whereNull('parent_id')->with('user')->latest('approved_at')->limit(3)->get(),
        ]);
    }
    public function about() { return $this->page('pages.about','Hakkımda'); }
    public function clock() { return $this->page('pages.clock','Saat & Sayaç'); }
    public function contact() { return $this->page('pages.contact','İletişim'); }
    public function legal(string $document)
    {
        $titles=['privacy'=>'Gizlilik ve KVKK','terms'=>'Kullanım koşulları','membership'=>'Üyelik Aydınlatma Metni'];
        abort_unless(isset($titles[$document]),404);
        return $this->page('pages.legal',$titles[$document],['title'=>$titles[$document],'body'=>Settings::localized('legal')[$document]??'']);
    }
}
