@props(['href' => null, 'variant' => 'primary', 'size' => 'md', 'type' => 'button', 'disabled' => false, 'loading' => false])
@php($classes = 'button button-'.(in_array($variant, ['primary','secondary','outline','ghost','danger']) ? $variant : 'primary').' '.($size === 'md' ? '' : 'button-'.$size))
@if($href)
<a @unless($disabled || $loading) href="{{ $href }}" @endunless {{ $attributes->class([$classes]) }} @if($disabled || $loading) role="link" aria-disabled="true" tabindex="-1" @endif>
    @if($loading)<x-icon name="loader-circle" class="animate-spin h-4 w-4" />@endif{{ $slot }}
</a>
@else
<button type="{{ $type }}" {{ $attributes->class([$classes]) }} @disabled($disabled || $loading) @if($loading) aria-busy="true" @endif>
    @if($loading)<x-icon name="loader-circle" class="animate-spin h-4 w-4" />@endif{{ $slot }}
</button>
@endif
