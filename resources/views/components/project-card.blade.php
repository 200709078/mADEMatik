@props(['project'])
<article {{ $attributes->class(['card overflow-hidden flex flex-col']) }}>
    <a href="{{ route('projects.show', $project->slug) }}" tabindex="-1" aria-hidden="true"><img src="{{ $project->cover_url }}" alt="" width="1200" height="675" class="project-cover" loading="lazy" decoding="async"></a>
    <div class="flex flex-1 flex-col p-6">
        <div class="mb-4 flex flex-wrap items-center gap-2"><x-ui.badge :variant="$project->status === 'live' ? 'success' : ($project->status === 'developing' ? 'warning' : 'brand')">{{ $project->status_label }}</x-ui.badge>@if($project->category)<span class="text-muted text-xs">{{ $project->category->translated('name') }}</span>@endif</div>
        <h3 class="text-xl font-semibold tracking-tight"><a href="{{ route('projects.show', $project->slug) }}">{{ $project->translated('title') }}</a></h3>
        @if($project->translated('summary'))<p class="text-muted mt-3 text-sm leading-7">{{ $project->translated('summary') }}</p>@endif
        @if($project->technologies)<div class="mt-5 flex flex-wrap gap-2">@foreach(array_slice($project->technologies, 0, 4) as $technology)<x-ui.badge>{{ $technology }}</x-ui.badge>@endforeach</div>@endif
        <div class="mt-auto flex flex-wrap gap-2 pt-6"><x-ui.button :href="route('projects.show', $project->slug)" variant="secondary" size="sm">{{ __('ui.view_project') }}<x-icon name="arrow-right" class="h-4 w-4" /></x-ui.button>@if($project->external_url)<x-ui.button :href="$project->external_url" variant="ghost" size="sm" target="_blank" rel="noopener noreferrer">{{ __('ui.open_app') }}<x-icon name="external-link" class="h-4 w-4" /></x-ui.button>@endif</div>
    </div>
</article>
