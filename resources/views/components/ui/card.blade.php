@props(['padding' => true])
<div {{ $attributes->class(['card', 'card-padding' => $padding]) }}>{{ $slot }}</div>
