@extends('layouts.app')
@section('title', 'Kayıt Ol')
@section('content')
<section class="container-site section-space"><div class="mx-auto max-w-lg"><x-ui.card><h1 class="text-3xl font-semibold">Birlikte paylaşalım.</h1><p class="mt-2 text-muted">Ücretsiz üyelikle yorum yazın ve projeleri takip edin.</p><form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">@csrf
<x-ui.input name="name" label="Ad Soyad" required maxlength="100" autocomplete="name" autofocus />
<x-ui.input name="email" label="E-Posta Adresi" type="email" required autocomplete="username" />
<x-ui.input name="email_confirmation" :label="__('ui.email_confirmation')" type="email" required maxlength="254" autocomplete="email" />
<x-ui.input name="password" label="Parola" type="password" required minlength="6" autocomplete="new-password" help="En az 6 karakter, büyük/küçük harf, rakam ve sembol kullanın." />
<x-ui.input name="password_confirmation" label="Parola Tekrarı" type="password" required autocomplete="new-password" />
<div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
<x-ui.checkbox name="terms" label="Üyelik koşullarını kabul ediyor, üyelik aydınlatma metnini okuduğumu doğruluyorum." value="1" required />
<p class="text-sm"><a href="{{ route('legal.terms') }}" class="link">Üyelik Koşulları</a> · <a href="{{ route('legal.privacy') }}" class="link">Gizlilik ve aydınlatma</a> · <a href="{{ route('legal.membership') }}" class="link">Üyelik Aydınlatma Metni</a></p>
<x-ui.button type="submit" class="w-full">Hesap Oluştur</x-ui.button></form><p class="mt-6 text-sm text-muted">Zaten üye misiniz? <a href="{{ route('login') }}" class="link">Giriş Yapın</a></p></x-ui.card></div></section>
@endsection
