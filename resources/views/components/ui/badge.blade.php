@props(['variant' => 'default'])
<span {{ $attributes->class(['badge', 'badge-'.$variant => $variant !== 'default']) }}>{{ $slot }}</span>
