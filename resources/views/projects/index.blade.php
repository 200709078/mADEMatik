@extends('layouts.app')
@section('title', __('ui.projects'))
@section('content')
<section class="container-site section-space">
    <div class="section-heading"><p class="eyebrow mb-4">{{ __('ui.selected_projects') }}</p><h1 class="heading-page">{{ __('ui.projects') }}</h1></div>
    <form action="{{ route('projects.index') }}" method="GET" class="card mb-8 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_auto] lg:items-end" role="search">
        <x-ui.input name="q" :label="__('ui.search_projects')" :value="request('q')" :placeholder="__('ui.search_placeholder')" maxlength="100" />
        <x-ui.select name="category" :label="__('ui.category')" :value="request('category')" :placeholder="__('ui.all_categories')">@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->translated('name') }}</option>@endforeach</x-ui.select>
        <x-ui.select name="status" :label="__('ui.status')" :value="request('status')" :placeholder="__('ui.all_statuses')" :options="['developing'=>__('ui.status_developing'),'live'=>__('ui.status_live'),'completed'=>__('ui.status_completed')]" />
        <x-ui.button type="submit"><x-icon name="filter" class="h-4 w-4" />{{ __('ui.filter') }}</x-ui.button>
    </form>
    @if(request()->filled('q') || request()->filled('category') || request()->filled('status'))<div class="mb-6"><a href="{{ route('projects.index') }}" class="link text-sm">{{ __('ui.reset_filters') }}</a></div>@endif
    @if($projects->isNotEmpty())<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($projects as $project)<x-project-card :project="$project" />@endforeach</div><x-ui.pagination :paginator="$projects" />@else<x-ui.empty-state :title="__('ui.no_project_results')" :description="__('ui.no_project_results_body')" icon="search"><x-ui.button :href="route('projects.index')" variant="outline">{{ __('ui.reset_filters') }}</x-ui.button></x-ui.empty-state>@endif
</section>
@endsection
