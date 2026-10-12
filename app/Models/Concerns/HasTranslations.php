<?php
namespace App\Models\Concerns;
trait HasTranslations
{
    public function translated(string $field, ?string $locale = null): mixed
    {
        $value = $this->getAttribute($field);
        if (!is_array($value)) return $value ?? '';
        return $value[$locale ?? app()->getLocale()] ?? $value['tr'] ?? '';
    }
}
