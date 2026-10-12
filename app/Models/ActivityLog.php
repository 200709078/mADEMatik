<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ActivityLog extends Model
{
    protected $fillable=['user_id','action','subject_type','subject_id','metadata'];
    protected function casts(): array { return ['metadata'=>'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function getActionLabelAttribute(): string { return __('activity.'.$this->action); }
    public static function record(string $action,?Model $subject=null,array $metadata=[]): self
    {
        return static::create(['user_id'=>auth()->id(),'action'=>$action,'subject_type'=>$subject?$subject::class:null,'subject_id'=>$subject?->getKey(),'metadata'=>$metadata]);
    }
}
