@extends('layouts.app')
@section('title', $title)
@section('content')
<section class="container-site section-space"><div class="mx-auto max-w-3xl"><h1 class="heading-page mb-8">{{ $title }}</h1><x-ui.alert type="warning" class="mb-8">{{ __('ui.legal_draft') }}</x-ui.alert><x-ui.card><div class="prose-site whitespace-pre-line">{{ $body ?: __('ui.legal_not_set') }}</div></x-ui.card><p class="mt-8"><a href="{{ route('contact') }}" class="link text-sm">{{ __('ui.contact') }}</a></p></div></section>
@endsection
