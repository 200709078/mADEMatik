<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index() { return view('admin.categories.index', ['categories' => ProjectCategory::withCount('projects')->orderBy('sort_order')->paginate(30)]); }
    public function create() { return view('admin.categories.form', ['category' => new ProjectCategory(['sort_order' => 0])]); }
    public function edit(ProjectCategory $category) { return view('admin.categories.form', compact('category')); }
    public function store(Request $request)
    {
        $category = new ProjectCategory(); $this->save($request, $category);
        ActivityLog::record('category.created', $category);
        return redirect()->route('admin.categories.index')->with('status', 'Kategori oluşturuldu.');
    }
    public function update(Request $request, ProjectCategory $category)
    {
        $this->save($request, $category); ActivityLog::record('category.updated', $category);
        return redirect()->route('admin.categories.index')->with('status', 'Kategori güncellendi.');
    }
    private function save(Request $request, ProjectCategory $category): void
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['bail', 'required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('project_categories')->ignore($category->id)], 'sort_order' => ['bail', 'required', 'integer', 'min:0', 'max:10000']]);
        $category->fill(['name' => array_replace((array) ($category->name ?? []), ['tr' => $data['name']]), 'slug' => $data['slug'], 'sort_order' => $data['sort_order']])->save();
    }
    public function destroy(ProjectCategory $category)
    {
        if (Project::where('project_category_id', $category->id)->exists()) { return back()->withErrors(['category' => 'Önce bu kategorideki projeleri başka kategoriye taşıyın.']); }
        ActivityLog::record('category.deleted', $category); $category->delete();
        return back()->with('status', 'Kategori silindi.');
    }
}
