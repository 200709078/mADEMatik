<?php
namespace App\Http\Controllers;
use App\Models\{Project,ProjectCategory,Comment};
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $data=$request->validate(['q'=>['nullable','string','max:100'],'category'=>['nullable','string','max:100'],'status'=>['nullable',Rule::in(['developing','live','completed'])]]);
        $query=Project::published()->with('category')->orderBy('sort_order')->latest();
        if (!empty($data['q'])) $query->where('title->tr','like','%'.addcslashes($data['q'],'%_\\').'%');
        if (!empty($data['category'])) $query->whereHas('category',fn($q)=>$q->where('slug',$data['category']));
        if (!empty($data['status'])) $query->where('status',$data['status']);
        return view('projects.index',['siteSettings'=>Settings::allLocalized(),'projects'=>$query->paginate(9)->withQueryString(),'categories'=>ProjectCategory::whereHas('projects',fn($q)=>$q->published())->orderBy('sort_order')->get(),'seo'=>['title'=>'Projelerim | mADEMatik Solutions','description'=>'mADEMatik Solutions bağımsız uygulamaları ve dijital projeleri.','canonical'=>route('projects.index')]]);
    }
    public function show(string $slug)
    {
        $project=Project::published()->with(['category','media'])->where('slug',$slug)->firstOrFail();
        $comments=$project->comments()->publiclyVisible()->whereNull('parent_id')->with(['user','replies'=>fn($q)=>$q->publiclyVisible()->with('user')])->latest('approved_at')->paginate(10);
        return view('projects.show',['siteSettings'=>Settings::allLocalized(),'project'=>$project,'comments'=>$comments,'seo'=>['title'=>($project->translated('seo_title')?:$project->translated('title')).' | mADEMatik Solutions','description'=>$project->translated('meta_description')?:$project->translated('summary'),'image'=>$project->cover_url,'canonical'=>route('projects.show',$project->slug)]]);
    }
}
