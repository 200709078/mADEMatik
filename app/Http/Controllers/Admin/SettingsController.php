<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    private const GROUPS = ['brand', 'navigation', 'home', 'about', 'contact', 'sliders', 'social', 'footer', 'seo', 'legal'];

    public function edit()
    {
        return view('admin.settings.edit', ['settings' => collect(self::GROUPS)->mapWithKeys(fn ($key) => [$key => Settings::localized($key, [])])->all()]);
    }
    public function update(Request $request)
    {
        $link = function ($attribute, $value, $fail) {
            $hasUnsafeCharacters = preg_match('/[\s\x00-\x1F\x7F\\\\]/', $value) !== 0;
            $isInternal = str_starts_with($value, '/') && ! str_starts_with($value, '//');
            $isAbsolute = filter_var($value, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
                && ! empty(parse_url($value, PHP_URL_HOST));
            if ($hasUnsafeCharacters || (! $isInternal && ! $isAbsolute)) { $fail('Bağlantı http/https adresi veya güvenli bir site yolu olmalıdır.'); }
        };
        $rules = [
            'brand' => ['required', 'array:name,tagline,description,email,location'], 'brand.name' => ['required', 'string', 'max:100'], 'brand.tagline' => ['nullable', 'string', 'max:200'], 'brand.description' => ['nullable', 'string', 'max:1000'], 'brand.email' => ['nullable', 'email', 'max:254'], 'brand.location' => ['nullable', 'string', 'max:200'],
            'navigation' => ['required', 'array:home,about,projects,clock,contact'], 'navigation.*' => ['required', 'string', 'max:50'],
            'home' => ['required', 'array:intro_title,intro_body,expertise_title,expertise_body,expertise_items,projects_title,testimonials_title,cta_title,cta_body,cta_label'],
            'home.expertise_items' => ['nullable', 'array', 'max:8'], 'home.expertise_items.*' => ['array:title,body'], 'home.expertise_items.*.title' => ['nullable', 'string', 'max:100'], 'home.expertise_items.*.body' => ['nullable', 'string', 'max:1000'],
            'about' => ['required', 'array:title,lead,body,approach_title,approach_body,tools'], 'about.tools' => ['nullable', 'string', 'max:2000'],
            'contact' => ['required', 'array:title,lead,email'], 'contact.title' => ['required', 'string', 'max:200'], 'contact.lead' => ['nullable', 'string', 'max:2000'], 'contact.email' => ['nullable', 'email', 'max:254'],
            'sliders' => ['required', 'array', 'list', 'size:3'], 'sliders.*' => ['array:title,description,cta_label,cta_url'], 'sliders.*.title' => ['required', 'string', 'max:200'], 'sliders.*.description' => ['nullable', 'string', 'max:1500'], 'sliders.*.cta_label' => ['required', 'string', 'max:100'], 'sliders.*.cta_url' => ['bail', 'required', 'string', 'max:2000', $link],
            'social' => ['nullable', 'array', 'max:8'], 'social.*' => ['array:label,url'], 'social.*.label' => ['nullable', 'string', 'max:60'], 'social.*.url' => ['bail', 'nullable', 'string', 'max:2000', $link],
            'footer' => ['required', 'array:description,copyright'], 'footer.description' => ['nullable', 'string', 'max:1000'], 'footer.copyright' => ['nullable', 'string', 'max:200'],
            'seo' => ['required', 'array:title,description'], 'seo.title' => ['required', 'string', 'max:160'], 'seo.description' => ['nullable', 'string', 'max:320'],
            'legal' => ['required', 'array:privacy,terms,membership'], 'legal.*' => ['nullable', 'string', 'max:30000'],
        ];
        foreach (['intro_title', 'expertise_title', 'projects_title', 'testimonials_title', 'cta_title', 'cta_label'] as $field) { $rules['home.'.$field] = ['nullable', 'string', 'max:200']; }
        foreach (['intro_body', 'expertise_body', 'cta_body'] as $field) { $rules['home.'.$field] = ['nullable', 'string', 'max:5000']; }
        foreach (['title', 'approach_title'] as $field) { $rules['about.'.$field] = ['nullable', 'string', 'max:200']; }
        foreach (['lead', 'body', 'approach_body'] as $field) { $rules['about.'.$field] = ['nullable', 'string', 'max:15000']; }
        $data = $request->validate($rules);
        $data['about']['tools'] = array_values(array_filter(array_map('trim', explode(',', $data['about']['tools'] ?? ''))));
        $data['social'] = array_values(array_filter($data['social'] ?? [], fn ($item) => ! empty($item['label']) && ! empty($item['url'])));
        $data['home']['expertise_items'] = array_values(array_filter($data['home']['expertise_items'] ?? [], fn ($item) => ! empty($item['title'])));
        $existingSlides = Settings::localized('sliders', []);
        foreach ($data['sliders'] as $index => &$slide) { $slide = array_replace($existingSlides[$index] ?? [], $slide); }
        unset($slide);
        DB::transaction(function () use ($data) {
            foreach (self::GROUPS as $key) { Settings::put($key, array_replace((array) Settings::get($key, []), ['tr' => $data[$key] ?? []])); }
            ActivityLog::record('settings.updated', null, ['groups' => self::GROUPS]);
        });
        return back()->with('status', 'Türkçe site içerikleri ve ayarları kaydedildi.');
    }
}
