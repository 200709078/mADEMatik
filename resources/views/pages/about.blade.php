@extends('layouts.app')
@section('title', __('ui.about'))
@section('content')
<section class="container-site section-space">
    <div class="section-heading"><p class="eyebrow mb-4 normal-case">mADEMatik Solutions</p><h1 class="heading-page">{{ data_get($siteSettings, 'about.title', __('ui.about')) }}</h1><p class="text-muted mt-6 text-lg leading-8">{{ data_get($siteSettings, 'about.lead') }}</p></div>
    <div class="grid gap-8 lg:grid-cols-[1.3fr_1fr] lg:gap-12">
        <div class="card p-7 sm:p-10"><div class="prose-site whitespace-pre-line">{{ data_get($siteSettings, 'about.body') }}</div></div>
        <aside class="space-y-6"><x-ui.card><span class="mb-5 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand-text)]"><x-icon name="graduation-cap" class="h-6 w-6" /></span><h2 class="text-xl font-semibold">{{ data_get($siteSettings, 'about.approach_title', __('ui.about_approach')) }}</h2><p class="text-muted mt-4 text-sm leading-8 whitespace-pre-line">{{ data_get($siteSettings, 'about.approach_body') }}</p></x-ui.card><x-ui.card><h2 class="text-lg font-semibold">{{ __('ui.about_tools') }}</h2><div class="mt-5 flex flex-wrap gap-2">@foreach(data_get($siteSettings, 'about.tools', []) as $tool)<x-ui.badge variant="brand">{{ $tool }}</x-ui.badge>@endforeach</div></x-ui.card></aside>
    </div>
    <div class="mt-12 flex flex-wrap items-center justify-between gap-5 border-t border-token pt-8"><h2 class="text-xl font-semibold">{{ __('ui.about_contact') }}</h2><x-ui.button :href="route('contact')">{{ __('ui.get_in_touch') }}<x-icon name="arrow-right" /></x-ui.button></div>
</section>
@endsection
