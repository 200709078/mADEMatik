@props(['name', 'label' => null, 'value' => null, 'rows' => 5, 'help' => null, 'error' => null])
@php($id = $attributes->get('id', str_replace(['[',']','.'], ['-','','-'], $name)))
@php($fieldName = rtrim(preg_replace('/\[([^\]]*)\]/', '.$1', $name), '.'))
@php($message = $error ?? $errors->first($fieldName))
<div>
    @if($label)<x-ui.label :for="$id" :required="$attributes->has('required')">{{ $label }}</x-ui.label>@endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" {{ $attributes->except('id')->class(['field', 'field-error' => (bool) $message]) }} @if($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($help) aria-describedby="{{ $id }}-help" @endif>{{ old($fieldName, $value ?? (string) $slot) }}</textarea>
    @if($help)<p id="{{ $id }}-help" class="field-help">{{ $help }}</p>@endif
    @if($message)<p id="{{ $id }}-error" class="field-message">{{ $message }}</p>@endif
</div>
