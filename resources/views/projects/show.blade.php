@extends('layouts.app')
@section('title', $project->translated('title'))
@section('content')
<section class="container-site section-space">
    <a href="{{ route('projects.index') }}" class="mb-7 inline-flex items-center gap-2 text-sm text-muted"><x-icon name="arrow-left" class="h-4 w-4" />{{ __('ui.back_projects') }}</a>
    <div class="mb-8 grid items-end gap-6 lg:grid-cols-[1fr_auto]"><div><div class="mb-4 flex flex-wrap gap-2"><x-ui.badge :variant="$project->status === 'live' ? 'success' : ($project->status === 'developing' ? 'warning' : 'brand')">{{ $project->status_label }}</x-ui.badge>@if($project->category)<x-ui.badge>{{ $project->category->translated('name') }}</x-ui.badge>@endif</div><h1 class="heading-page">{{ $project->translated('title') }}</h1><p class="text-muted mt-5 max-w-3xl text-lg leading-8">{{ $project->translated('summary') }}</p></div>@if($project->external_url)<x-ui.button :href="$project->external_url" target="_blank" rel="noopener noreferrer" size="lg">{{ __('ui.open_app') }}<x-icon name="external-link" /></x-ui.button>@endif</div>
    <img src="{{ $project->cover_url }}" width="1200" height="675" alt="{{ __('ui.project_image', ['title' => $project->translated('title')]) }}" class="card project-cover mb-10" fetchpriority="high" decoding="async">
    <div class="grid gap-8 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-8"><x-ui.card><h2 class="text-xl font-semibold">{{ __('ui.project_overview') }}</h2><p class="prose-site mt-5 whitespace-pre-line">{{ $project->translated('description') ?: __('ui.project_description_missing') }}</p></x-ui.card>
            @if($project->translated('features'))<x-ui.card><h2 class="text-xl font-semibold">{{ __('ui.project_features') }}</h2><ul class="mt-5 space-y-4">@foreach($project->translated('features') as $feature)<li class="flex gap-3 text-sm leading-7"><x-icon name="check-circle" class="mt-1 h-5 w-5 text-[var(--brand-text)]" /><span>{{ $feature }}</span></li>@endforeach</ul></x-ui.card>@endif
            @if($project->media->isNotEmpty())<div><h2 class="mb-5 text-xl font-semibold">{{ __('ui.screenshots') }}</h2><div class="grid gap-5 sm:grid-cols-2">@foreach($project->media as $media)<a href="{{ $media->url }}" target="_blank" rel="noopener noreferrer"><img src="{{ $media->url }}" alt="{{ $media->translated('alt') ?: __('ui.screenshot', ['number' => $loop->iteration]) }}" width="1200" height="675" class="card project-cover" loading="lazy" decoding="async"></a>@endforeach</div></div>@endif
        </div>
        <aside><x-ui.card><h2 class="text-lg font-semibold">{{ __('ui.technologies') }}</h2>@if($project->technologies)<div class="mt-5 flex flex-wrap gap-2">@foreach($project->technologies as $technology)<x-ui.badge variant="brand">{{ $technology }}</x-ui.badge>@endforeach</div>@else<p class="text-muted mt-4 text-sm leading-7">{{ __('ui.no_technology') }}</p>@endif</x-ui.card></aside>
    </div>
    <div class="mt-16 max-w-3xl"><h2 class="heading-section mb-4">{{ __('ui.project_comments') }}</h2><p class="text-muted mb-8 text-sm leading-7">{{ __('ui.comments_intro') }}</p><x-comments-section :comments="$comments" :project="$project" /></div>
</section>
@endsection
