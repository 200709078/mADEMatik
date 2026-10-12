<?php
namespace App\Services;
use App\Models\Setting;
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return Setting::where('key',$key)->first()?->value ?? config('content.'.$key,$default);
    }
    public static function put(string $key, mixed $value): void { Setting::updateOrCreate(['key'=>$key],['value'=>$value]); }
    public static function localized(string $key, mixed $default = null): mixed
    {
        $value = static::get($key);
        return is_array($value) ? ($value[app()->getLocale()] ?? $value['tr'] ?? $default) : ($value ?? $default);
    }
    public static function allLocalized(): array
    {
        $rows=Setting::all()->pluck('value','key'); $data=[];
        foreach (config('content') as $key=>$defaults) {
            $value=$rows->get($key,$defaults); $data[$key]=$value[app()->getLocale()] ?? $value['tr'] ?? [];
        }
        return $data;
    }
}
