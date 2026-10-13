@extends('layouts.app')
@section('title', 'Parolamı Unuttum')
@section('content')
<section class="container-site section-space"><div class="mx-auto max-w-lg"><x-ui.card><h1 class="text-3xl font-semibold">Parolanızı yenileyin.</h1><p class="mt-2 text-muted">Kayıtlı e-posta adresinize güvenli bir sıfırlama bağlantısı göndereceğiz.</p><form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">@csrf
<x-ui.input name="email" label="E-Posta Adresi" type="email" required autocomplete="username" autofocus />
<x-ui.button type="submit" class="w-full">Sıfırlama Bağlantısı Gönder</x-ui.button></form><a href="{{ route('login') }}" class="mt-5 inline-block text-brand">Girişe dön</a></x-ui.card></div></section>
@endsection
