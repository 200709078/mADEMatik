@props(['name' => '', 'size' => 'md'])
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand-text)] font-semibold', 'h-8 w-8 text-xs' => $size === 'sm', 'h-10 w-10 text-sm' => $size === 'md', 'h-14 w-14 text-xl' => $size === 'lg']) }} aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
