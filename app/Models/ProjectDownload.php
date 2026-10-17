<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDownload extends Model
{
    use HasTranslations;

    public const PLATFORMS = ['windows', 'macos', 'linux', 'android', 'ios', 'ipados', 'other'];

    public const DISTRIBUTION_TYPES = ['direct', 'store', 'test'];

    public const CHANNELS = ['stable', 'beta'];

    protected $fillable = [
        'project_id', 'title', 'platform', 'architecture', 'distribution_type', 'channel',
        'version', 'download_url', 'file_size', 'released_at', 'notes', 'requirements',
        'license_url', 'sha256', 'sort_order', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array', 'notes' => 'array', 'requirements' => 'array',
            'file_size' => 'integer', 'released_at' => 'date',
            'sort_order' => 'integer', 'is_published' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('download_url')->where('download_url', '!=', '');
    }

    public function getPlatformLabelAttribute(): string
    {
        $key = 'downloads.platforms.'.$this->platform;

        return in_array($this->platform, self::PLATFORMS, true) ? __($key) : (string) $this->platform;
    }

    public function getDistributionLabelAttribute(): string
    {
        return __('downloads.types.'.$this->distribution_type);
    }

    public function getChannelLabelAttribute(): string
    {
        return __('downloads.channels.'.$this->channel);
    }

    public function getActionLabelAttribute(): string
    {
        $host = strtolower((string) parse_url((string) $this->download_url, PHP_URL_HOST));
        $action = match (true) {
            $this->distribution_type === 'store' && $host === 'play.google.com' => 'google_play',
            $this->distribution_type === 'store' && $host === 'apps.apple.com' => 'app_store',
            $this->distribution_type === 'store' && $host === 'apps.microsoft.com' => 'microsoft_store',
            $this->distribution_type === 'test' && $host === 'testflight.apple.com' => 'testflight',
            default => $this->distribution_type,
        };

        return __('downloads.actions.'.$action);
    }

    public function getFileSizeLabelAttribute(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }

        $size = $this->file_size;
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1, app()->getLocale() === 'tr' ? ',' : '.', '').' '.$units[$unit];
    }
}
