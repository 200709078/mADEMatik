<?php
namespace App\Http\Controllers;
use App\Models\Project;
class SeoController extends Controller
{
    public function sitemap()
    {
        $urls=collect(['home','about','projects.index','comments.index','clock','contact'])->map(fn($name)=>['url'=>route($name),'modified'=>null]);
        Project::published()->orderBy('id')->each(fn($project)=>$urls->push(['url'=>route('projects.show',$project->slug),'modified'=>$project->updated_at->toAtomString()]));
        return response()->view('seo.sitemap',['urls'=>$urls])->header('Content-Type','application/xml; charset=UTF-8');
    }
    public function robots()
    {
        $body=app()->isProduction() ? "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /profilim\nDisallow: /bildirimler\nSitemap: ".route('sitemap')."\n" : "User-agent: *\nDisallow: /\n";
        return response($body,200,['Content-Type'=>'text/plain; charset=UTF-8']);
    }
}
