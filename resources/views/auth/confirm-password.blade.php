@extends('layouts.app')
@section('title', 'Güvenli İşlem Onayı')
@section('content')
<section class="container-site section-space"><div class="mx-auto max-w-lg"><x-ui.card><h1 class="text-3xl font-semibold">Parolanızı doğrulayın.</h1><p class="mt-2 text-muted">Yönetim panelindeki işlemler için parolanızı yeniden girin.</p><form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5">@csrf<x-ui.input name="password" label="Mevcut parola" type="password" required autocomplete="current-password" autofocus /><x-ui.button type="submit">Doğrula ve Devam Et</x-ui.button></form></x-ui.card></div></section>
@endsection
