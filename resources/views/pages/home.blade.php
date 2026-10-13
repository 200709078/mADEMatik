@extends('layouts.app')
@section('content')
<section class="container-site pt-6 sm:pt-8" aria-label="{{ __('ui.slider') }}">
    <div class="hero-panel" x-data="heroSlider({{ count($slides) }})" @keydown.arrow-left.prevent="previous()" @keydown.arrow-right.prevent="next()" aria-roledescription="{{ __('ui.slider') }}">
        <div aria-live="polite" aria-atomic="true">
            @foreach($slides as $slide)
                <div class="hero-slide" x-show="active === {{ $loop->index }}" @if(!$loop->first) x-cloak @endif>
                    <picture class="absolute inset-0 h-full w-full">
                        <source media="(max-width: 639px)" srcset="{{ asset('assets/brand/'.$slide['mobile_image']) }}">
                        <img src="{{ asset('assets/brand/'.$slide['image']) }}" width="1600" height="900" alt="" class="h-full w-full object-cover" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                    </picture>
                    <div class="hero-shade" aria-hidden="true"></div>
                    <div class="relative z-10 min-w-0 max-w-2xl p-7 pb-28 sm:p-12 sm:pb-28 lg:p-16 lg:pb-28">
                        <p class="mb-5 text-xs font-semibold uppercase tracking-[.16em] hero-description">{{ __('ui.portfolio') }}</p>
                        <h1 class="heading-hero">{{ $slide['title'] }}</h1>
                        <p class="hero-description mt-6 max-w-lg text-base leading-8 sm:text-lg">{{ $slide['description'] }}</p>
                        @if($slide['cta_url'] && $slide['cta_label'])<x-ui.button :href="$slide['cta_url']" class="hero-cta mt-8" size="lg">{{ $slide['cta_label'] }}<x-icon name="arrow-right" /></x-ui.button>@endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="absolute bottom-6 left-7 right-7 z-20 flex items-center justify-between sm:bottom-8 sm:left-12 sm:right-12 lg:left-16">
            <div class="flex items-center gap-2" role="group" aria-label="{{ __('ui.slider') }}">@foreach($slides as $slide)<button type="button" class="flex h-10 w-10 items-center justify-center" @click="active={{ $loop->index }}" :aria-pressed="active === {{ $loop->index }}" aria-label="{{ __('ui.slide', ['number' => $loop->iteration]) }}"><span class="h-2.5 rounded-full transition-all" :class="active === {{ $loop->index }} ? 'w-8 bg-white' : 'w-2.5 bg-white/50'"></span></button>@endforeach</div>
            <div class="flex gap-2"><x-ui.button variant="outline" class="button-icon hero-control" @click="previous()" :aria-label="__('ui.previous')"><x-icon name="chevron-left" /></x-ui.button><x-ui.button variant="outline" class="button-icon hero-control" @click="next()" :aria-label="__('ui.next')"><x-icon name="chevron-right" /></x-ui.button></div>
        </div>
        <span class="sr-only">{{ __('ui.slider_manual') }}</span>
    </div>
</section>

<section class="container-site section-space">
    <div class="grid gap-8 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
        <div><p class="eyebrow mb-4">mADEMatik Solutions</p><h2 class="heading-section">{{ data_get($siteSettings, 'home.intro_title') }}</h2><p class="text-muted mt-5 text-base leading-8">{{ data_get($siteSettings, 'home.intro_body') }}</p><x-ui.button :href="route('about')" variant="outline" class="mt-7">{{ __('ui.about') }}<x-icon name="arrow-right" class="h-4 w-4" /></x-ui.button></div>
        <div class="card p-6 sm:p-8"><h3 class="text-xl font-semibold">{{ data_get($siteSettings, 'home.expertise_title') }}</h3><p class="text-muted mt-3 text-sm leading-7">{{ data_get($siteSettings, 'home.expertise_body') }}</p><div class="mt-6 space-y-6">@foreach(data_get($siteSettings, 'home.expertise_items', []) as $expertise)<div class="flex gap-4"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-soft)] text-[var(--brand-text)]"><x-icon :name="['graduation-cap','code-2','layers'][$loop->index % 3]" /></span><div><h4 class="text-sm font-semibold">{{ $expertise['title'] }}</h4><p class="text-muted mt-1 text-sm leading-6">{{ $expertise['body'] }}</p></div></div>@endforeach</div></div>
    </div>
</section>

<section class="surface-muted section-space">
    <div class="container-site">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-5"><div><p class="eyebrow mb-3">{{ __('ui.selected_projects') }}</p><h2 class="heading-section">{{ data_get($siteSettings, 'home.projects_title', __('ui.projects')) }}</h2></div><x-ui.button :href="route('projects.index')" variant="outline">{{ __('ui.all_projects') }}<x-icon name="arrow-right" class="h-4 w-4" /></x-ui.button></div>
        @if($featuredProjects->isNotEmpty())<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($featuredProjects as $project)<x-project-card :project="$project" />@endforeach</div>@else<x-ui.empty-state :title="__('ui.project_empty')" :description="__('ui.project_empty_body')" icon="folder" />@endif
    </div>
</section>

<section class="container-site section-space">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-5"><h2 class="heading-section">{{ data_get($siteSettings, 'home.testimonials_title', __('ui.comments')) }}</h2><x-ui.button :href="route('comments.index')" variant="ghost">{{ __('ui.comments') }}<x-icon name="arrow-right" class="h-4 w-4" /></x-ui.button></div>
    @if($testimonials->isNotEmpty())<div class="grid gap-6 md:grid-cols-3">@foreach($testimonials as $comment)<figure class="card p-6"><x-icon name="message-square" class="mb-5 text-[var(--brand-text)]" /><blockquote class="text-sm leading-7 break-words">{{ $comment->body }}</blockquote><figcaption class="mt-6 flex items-center gap-3"><x-ui.avatar :name="$comment->user->name" :src="$comment->user->avatar_url" size="sm" /><div><p class="text-sm font-semibold">{{ $comment->user->name }}</p><time datetime="{{ $comment->created_at->toIso8601String() }}" class="text-muted text-xs">{{ $comment->created_at->translatedFormat('d F Y') }}</time></div></figcaption></figure>@endforeach</div>@else<x-ui.empty-state :title="__('ui.comments_empty')" :description="__('ui.comments_empty_body')" icon="message-square"><x-ui.button :href="route('comments.index')" variant="secondary">{{ __('ui.comments') }}</x-ui.button></x-ui.empty-state>@endif
</section>

<section class="container-site pb-16">
    <div class="card bg-[var(--brand-soft)] border-[var(--brand-soft)] p-7 sm:p-10 flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between"><div class="max-w-2xl"><h2 class="heading-section">{{ data_get($siteSettings, 'home.cta_title') }}</h2><p class="text-muted mt-4 leading-7">{{ data_get($siteSettings, 'home.cta_body') }}</p></div><x-ui.button :href="route('contact')" size="lg" class="shrink-0">{{ data_get($siteSettings, 'home.cta_label', __('ui.get_in_touch')) }}<x-icon name="arrow-right" /></x-ui.button></div>
</section>
@endsection
