@extends('layouts.app')
@section('title', __('ui.projects'))
@section('content')
<section class="container-site section-space">
    <div class="section-heading"><p class="eyebrow mb-4">{{ __('ui.selected_projects') }}</p><h1 class="heading-page">{{ __('ui.projects') }}</h1></div>
    @if($projects->isNotEmpty())<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($projects as $project)<x-project-card :project="$project" />@endforeach</div><x-ui.pagination :paginator="$projects" />@else<x-ui.empty-state :title="__('ui.project_empty')" :description="__('ui.project_empty_body')" />@endif
</section>
@endsection
