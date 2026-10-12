@props(['name', 'label' => null, 'value', 'checked' => false])
@php($id = $attributes->get('id', $name.'-'.$value))
@php($fieldName = rtrim(preg_replace('/\[([^\]]*)\]/', '.$1', $name), '.'))
<label for="{{ $id }}" class="flex items-center gap-3 text-sm"><input type="radio" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @checked(session()->hasOldInput() ? (string) old($fieldName) === (string) $value : $checked) {{ $attributes->except('id')->class(['check-field']) }}><span>{{ $label ?? $slot }}</span></label>
