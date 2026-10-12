@props(['name', 'label' => null])
<i data-lucide="{{ $name }}" {{ $attributes->class(['h-5 w-5 shrink-0']) }} @if($label) aria-label="{{ $label }}" role="img" @else aria-hidden="true" @endif></i>
