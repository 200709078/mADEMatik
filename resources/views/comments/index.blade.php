@extends('layouts.app')
@section('title', __('ui.comments'))
@section('content')
<section class="container-site section-space"><div class="mx-auto max-w-3xl"><div class="section-heading"><p class="eyebrow mb-4">mADEMatik Solutions</p><h1 class="heading-page">{{ __('ui.comments') }}</h1><p class="text-muted mt-5 leading-8">{{ __('ui.comments_intro') }}</p></div><x-comments-section :comments="$comments" /></div></section>
@endsection
