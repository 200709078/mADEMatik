@props(['label' => null])
<div class="table-wrap" tabindex="0" role="region" aria-label="{{ $label ?? __('ui.data_table') }}"><table {{ $attributes->class(['data-table']) }}>{{ $slot }}</table></div>
