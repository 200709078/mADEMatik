@props(['name' => '', 'size' => 'md', 'src' => null])
@php
    $words = preg_split('/[\s\p{Z}]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = mb_substr($words[0] ?? '', 0, 1, 'UTF-8');
    if (count($words) > 1) $initials .= mb_substr($words[array_key_last($words)], 0, 1, 'UTF-8');
    if (app()->getLocale() === 'tr') $initials = str_replace('i', 'İ', $initials);
    $initials = mb_strtoupper($initials, 'UTF-8');
@endphp
<span {{ $attributes->class(['relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--brand-soft)] text-[var(--brand-text)] font-semibold', 'h-8 w-8 text-xs' => $size === 'sm', 'h-10 w-10 text-sm' => $size === 'md', 'h-14 w-14 text-xl' => $size === 'lg', 'h-24 w-24 text-2xl' => $size === 'xl']) }} aria-hidden="true" @if($src) x-data="{photoFailed:false}" x-init="$nextTick(() => { if ($refs.photo.complete && !$refs.photo.naturalWidth) photoFailed = true })" @endif>
    @if($src)
        <img src="{{ $src }}" alt="" width="512" height="512" class="absolute inset-0 h-full w-full object-cover" x-ref="photo" x-show="!photoFailed" x-on:error="photoFailed = true" decoding="async">
        <span x-cloak x-show="photoFailed">{{ $initials }}</span>
    @else
        {{ $initials }}
    @endif
</span>
