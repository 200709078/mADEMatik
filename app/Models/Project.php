<?php
namespace App\Models;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\{Model,Builder};
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class Project extends Model
{
    use HasTranslations;
    protected $fillable = ['project_category_id','title','slug','summary','description','features','technologies','status','is_published','is_featured','sort_order','external_url','cover_path','seo_title','meta_description'];
    protected function casts(): array { return ['title'=>'array','summary'=>'array','description'=>'array','features'=>'array','technologies'=>'array','seo_title'=>'array','meta_description'=>'array','is_published'=>'boolean','is_featured'=>'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(ProjectCategory::class,'project_category_id'); }
    public function media(): HasMany { return $this->hasMany(ProjectMedia::class)->orderBy('sort_order'); }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }
    public function scopePublished(Builder $query): void { $query->where('is_published',true); }
    public function getCoverUrlAttribute(): string { return $this->cover_path ? asset('storage/'.$this->cover_path) : asset('assets/brand/placeholders/proje-varsayilan-1200x675.webp'); }
    public function getStatusLabelAttribute(): string { return ['developing'=>'Geliştiriliyor','live'=>'Yayında','completed'=>'Tamamlandı'][$this->status] ?? $this->status; }
}
