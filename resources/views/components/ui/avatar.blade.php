@props(['name' => '', 'size' => 'md'])
@php
    $words = preg_split('/[\s\p{Z}]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = mb_substr($words[0] ?? '', 0, 1, 'UTF-8');
    if (count($words) > 1) $initials .= mb_substr($words[array_key_last($words)], 0, 1, 'UTF-8');
    if (app()->getLocale() === 'tr') $initials = str_replace('i', 'İ', $initials);
    $initials = mb_strtoupper($initials, 'UTF-8');
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand-text)] font-semibold', 'h-8 w-8 text-xs' => $size === 'sm', 'h-10 w-10 text-sm' => $size === 'md', 'h-14 w-14 text-xl' => $size === 'lg']) }} aria-hidden="true">{{ $initials }}</span>
