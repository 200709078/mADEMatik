@php
    $siteSettings = $siteSettings ?? [];
    $pageTitle = $seo['title'] ?? (trim($__env->yieldContent('title')) ?: data_get($siteSettings, 'seo.title', 'mADEMatik Solutions'));
    $pageDescription = $seo['description'] ?? data_get($siteSettings, 'seo.description', data_get($siteSettings, 'brand.description', ''));
    $pageImage = $seo['image'] ?? asset('assets/brand/social/og-madematik-1200x630.png');
    $canonical = $seo['canonical'] ?? url()->current();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}{{ !str_contains($pageTitle, 'mADEMatik') ? ' · mADEMatik Solutions' : '' }}</title>
<meta name="description" content="{{ $pageDescription }}">
@if(request()->routeIs('login','register','password.*','verification.*','profile.*','notifications.*','admin.*','comments.edit'))
<meta name="robots" content="noindex,nofollow">
@endif
<meta name="theme-color" content="#096ab6">
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="tr_TR">
<meta property="og:site_name" content="mADEMatik Solutions">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $pageImage }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="{{ asset('assets/brand/icons/favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('assets/brand/icons/icon-32.png') }}" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('assets/brand/icons/icon-180.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<script>try{const m=localStorage.getItem('madematik-theme');document.documentElement.dataset.theme=m==='dark'||(!m&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light'}catch(e){document.documentElement.dataset.theme=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@stack('head')
