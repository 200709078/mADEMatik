@extends('layouts.app')
@section('title', 'Parolayı Sıfırla')
@section('content')
<section class="container-site section-space"><div class="mx-auto max-w-lg"><x-ui.card><h1 class="text-3xl font-semibold">Yeni parolanız.</h1><form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">@csrf<input type="hidden" name="token" value="{{ $token }}">
<x-ui.input name="email" label="E-posta adresi" type="email" :value="$email" required autocomplete="username" />
<x-ui.input name="password" label="Yeni parola" type="password" required minlength="12" autocomplete="new-password" help="En az 12 karakter, büyük/küçük harf, rakam ve sembol kullanın." />
<x-ui.input name="password_confirmation" label="Yeni parola tekrarı" type="password" required autocomplete="new-password" />
<x-ui.button type="submit" class="w-full">Parolayı Güncelle</x-ui.button></form></x-ui.card></div></section>
@endsection
