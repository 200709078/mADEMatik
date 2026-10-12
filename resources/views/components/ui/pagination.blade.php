@props(['paginator'])
@if($paginator->hasPages())
<nav {{ $attributes->class(['flex flex-wrap items-center justify-between gap-4 mt-8']) }} aria-label="{{ __('ui.pagination') }}">
    <p class="text-muted text-sm">{{ __('ui.results', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>
    <div class="flex gap-2">
        @if($paginator->onFirstPage())<x-ui.button variant="outline" size="sm" disabled>{{ __('ui.previous') }}</x-ui.button>@else<x-ui.button :href="$paginator->previousPageUrl()" variant="outline" size="sm" rel="prev"><x-icon name="chevron-left" class="h-4 w-4" />{{ __('ui.previous') }}</x-ui.button>@endif
        <span class="button button-sm button-ghost" aria-current="page">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages())<x-ui.button :href="$paginator->nextPageUrl()" variant="outline" size="sm" rel="next">{{ __('ui.next') }}<x-icon name="chevron-right" class="h-4 w-4" /></x-ui.button>@else<x-ui.button variant="outline" size="sm" disabled>{{ __('ui.next') }}</x-ui.button>@endif
    </div>
</nav>
@endif
