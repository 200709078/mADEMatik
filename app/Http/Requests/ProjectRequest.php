<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isSuperUser() && ! $this->user()->is_blocked; }
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('projects')->ignore($this->route('project')?->id)],
            'summary' => ['nullable', 'string', 'max:700'], 'description' => ['nullable', 'string', 'max:30000'], 'features' => ['nullable', 'string', 'max:8000'],
            'project_category_id' => ['nullable', 'exists:project_categories,id'], 'status' => ['required', Rule::in(['developing', 'live', 'completed'])],
            'is_published' => ['nullable', 'boolean'], 'is_featured' => ['nullable', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'technologies' => ['nullable', 'string', 'max:2000'],
            'external_url' => ['nullable', 'string', 'max:2000', function ($attribute, $value, $fail) {
                $hasUnsafeCharacters = preg_match('/[\s\x00-\x1F\x7F\\\\]/', $value) !== 0;
                $isInternal = str_starts_with($value, '/') && ! str_starts_with($value, '//');
                $isAbsolute = filter_var($value, FILTER_VALIDATE_URL) !== false
                    && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
                    && ! empty(parse_url($value, PHP_URL_HOST));
                if ($hasUnsafeCharacters || (! $isInternal && ! $isAbsolute)) { $fail('Bağlantı http/https adresi veya / ile başlayan güvenli site yolu olmalıdır.'); }
            }],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'], 'remove_cover' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:160'], 'meta_description' => ['nullable', 'string', 'max:320'], 'announce' => ['nullable', 'boolean'],
        ];
    }
}
