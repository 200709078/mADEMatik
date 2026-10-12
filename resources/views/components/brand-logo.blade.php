@props(['loading' => 'eager'])
<img data-brand-logo data-light-src="{{ asset('assets/brand/logos/logo-header-light.png') }}" data-dark-src="{{ asset('assets/brand/logos/logo-header-dark.png') }}" width="810" height="210" alt="mADEMatik Solutions" loading="{{ $loading }}" {{ $attributes }}>
<script>{const logo=document.currentScript.previousElementSibling;logo.src=document.documentElement.dataset.theme==='dark'?logo.dataset.darkSrc:logo.dataset.lightSrc}</script>
<noscript><img src="{{ asset('assets/brand/logos/logo-header-light.png') }}" width="810" height="210" alt="mADEMatik Solutions" loading="{{ $loading }}" {{ $attributes }}></noscript>
