@extends('layouts.app')
@section('title', 'Giriş Yap')
@section('content')
<section class="container-site section-space"><div class="mx-auto max-w-lg"><x-ui.card><h1 class="text-3xl font-semibold">Tekrar hoş geldiniz.</h1><p class="mt-2 text-muted">Yorumlarınızı ve bildirimlerinizi yönetmek için giriş yapın.</p><form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">@csrf
<x-ui.input name="email" label="E-posta adresi" type="email" required autocomplete="username" autofocus />
<x-ui.input name="password" label="Parola" type="password" required autocomplete="current-password" />
<div class="flex flex-wrap items-center justify-between gap-3"><x-ui.checkbox name="remember" label="Beni hatırla" value="1" /><a class="text-brand text-sm" href="{{ route('password.request') }}">Parolamı unuttum</a></div>
<x-ui.button type="submit" class="w-full">Giriş Yap</x-ui.button></form><p class="mt-6 text-sm text-muted">Hesabınız yok mu? <a class="text-brand" href="{{ route('register') }}">Ücretsiz kayıt olun</a></p></x-ui.card></div></section>
@endsection
