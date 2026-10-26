<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Services\Settings;
class ProjectController extends Controller
{
    public function index()
    {
        $projects=Project::published()->with('category')->orderBy('sort_order')->latest()->paginate(9);
        return view('projects.index',['siteSettings'=>Settings::allLocalized(),'projects'=>$projects,'seo'=>['title'=>'Projelerim | mADEMatik Solutions','description'=>'mADEMatik Solutions bağımsız uygulamaları ve dijital projeleri.','canonical'=>route('projects.index')]]);
    }
    public function show(string $slug)
    {
        $project=Project::published()->with(['category','media','downloads'=>fn($q)=>$q->published()])->where('slug',$slug)->firstOrFail();
        $comments=$project->comments()->publiclyVisible()->whereNull('parent_id')->with(['user','replies'=>fn($q)=>$q->publiclyVisible()->with('user')])->latest('approved_at')->paginate(10);
        return view('projects.show',['siteSettings'=>Settings::allLocalized(),'project'=>$project,'comments'=>$comments,'seo'=>['title'=>($project->translated('seo_title')?:$project->translated('title')).' | mADEMatik Solutions','description'=>$project->translated('meta_description')?:$project->translated('summary'),'image'=>$project->cover_url,'canonical'=>route('projects.show',$project->slug)]]);
    }
}
