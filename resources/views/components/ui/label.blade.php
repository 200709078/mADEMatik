@props(['for' => null, 'required' => false])
<label @if($for) for="{{ $for }}" @endif {{ $attributes->class(['field-label']) }}>{{ $slot }}@if($required)<span aria-hidden="true" class="text-muted"> *</span>@endif</label>
