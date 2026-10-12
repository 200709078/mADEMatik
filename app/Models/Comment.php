<?php
namespace App\Models;
use Illuminate\Database\Eloquent\{Builder,Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class Comment extends Model
{
    protected $fillable=['body','project_id','parent_id'];
    protected function casts(): array { return ['moderated_at'=>'datetime','approved_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class,'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class,'parent_id')->oldest(); }
    public function scopeApproved(Builder $query): void { $query->where('status','approved'); }
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->approved()->whereHas('user',fn(Builder $q)=>$q->where('is_blocked',false))
            ->where(fn(Builder $q)=>$q->whereNull('project_id')->orWhereHas('project',fn(Builder $p)=>$p->published()))
            ->where(fn(Builder $q)=>$q->whereNull('parent_id')->orWhereHas('parent',fn(Builder $p)=>$p->approved()->whereHas('user',fn(Builder $u)=>$u->where('is_blocked',false))));
    }
    public function getStatusLabelAttribute(): string { return ['pending'=>'Onay bekliyor','approved'=>'Onaylandı','rejected'=>'Reddedildi','hidden'=>'Gizlendi'][$this->status]??$this->status; }
}
