@props(['name', 'label' => null, 'value' => null, 'options' => [], 'placeholder' => null, 'help' => null, 'error' => null])
@php($id = $attributes->get('id', str_replace(['[',']','.'], ['-','','-'], $name)))
@php($fieldName = rtrim(preg_replace('/\[([^\]]*)\]/', '.$1', $name), '.'))
@php($message = $error ?? $errors->first($fieldName))
<div>
    @if($label)<x-ui.label :for="$id" :required="$attributes->has('required')">{{ $label }}</x-ui.label>@endif
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->except('id')->class(['field', 'field-error' => (bool) $message]) }} @if($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
        @if($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $optionValue => $optionLabel)<option value="{{ $optionValue }}" @selected((string) old($fieldName, $value) === (string) $optionValue)>{{ $optionLabel }}</option>@endforeach
        {{ $slot }}
    </select>
    @if($help)<p class="field-help">{{ $help }}</p>@endif
    @if($message)<p id="{{ $id }}-error" class="field-message">{{ $message }}</p>@endif
</div>
