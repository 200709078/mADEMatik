@props(['items' => []])
<nav {{ $attributes->class(['flex flex-wrap gap-1 border-b border-token pb-2']) }} aria-label="{{ __('ui.tabs') }}">@foreach($items as $item)<a href="{{ $item['url'] }}" class="nav-link" @if($item['active'] ?? false) aria-current="page" @endif>{{ $item['label'] }}</a>@endforeach</nav>
