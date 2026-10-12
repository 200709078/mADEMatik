<?php
namespace App\Models;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProjectMedia extends Model
{
    use HasTranslations;
    protected $table='project_media';
    protected $fillable=['project_id','path','alt','sort_order'];
    protected function casts(): array { return ['alt'=>'array']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function getUrlAttribute(): string { return asset('storage/'.$this->path); }
}
