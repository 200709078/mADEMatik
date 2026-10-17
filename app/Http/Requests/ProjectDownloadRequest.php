<?php

namespace App\Http\Requests;

use App\Models\ProjectDownload;
use App\Rules\SafeDownloadUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperUser();
    }

    public function attributes(): array
    {
        return __('downloads.fields');
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:160'],
            'platform' => ['bail', 'required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]{0,39}$/'],
            'architecture' => ['nullable', 'string', 'max:80'],
            'distribution_type' => ['required', Rule::in(ProjectDownload::DISTRIBUTION_TYPES)],
            'channel' => ['required', Rule::in(ProjectDownload::CHANNELS)],
            'version' => ['nullable', 'string', 'max:80'],
            'download_url' => ['bail', Rule::requiredIf(fn () => $this->boolean('is_published')), 'nullable', 'string', 'max:2048', new SafeDownloadUrl()],
            'file_size' => ['nullable', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
            'released_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:8000'],
            'requirements' => ['nullable', 'string', 'max:4000'],
            'license_url' => ['bail', 'nullable', 'string', 'max:2048', new SafeDownloadUrl()],
            'sha256' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}
