@props(['name', 'label' => null, 'checked' => false, 'value' => '1'])
@php($id = $attributes->get('id', str_replace(['[',']','.'], ['-','','-'], $name)))
@php($fieldName = rtrim(preg_replace('/\[([^\]]*)\]/', '.$1', $name), '.'))
<div>
    <label for="{{ $id }}" class="flex items-start gap-3 text-sm leading-6">
        <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @checked(session()->hasOldInput() ? (bool) old($fieldName, false) : $checked) {{ $attributes->except('id')->class(['check-field mt-1']) }}>
        <span>{{ $label ?? $slot }}</span>
    </label>
    @error($fieldName)<p class="field-message">{{ $message }}</p>@enderror
</div>
