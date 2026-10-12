@extends('layouts.app')
@section('title', 'E-posta Doğrulama')
@section('content')
<section class="container-site section-space"><div class="mx-auto max-w-lg"><x-ui.card><x-icon name="mail-check" class="mb-4 size-10 text-brand"/><h1 class="text-3xl font-semibold">E-posta adresinizi doğrulayın.</h1><p class="mt-3 text-muted">{{ auth()->user()->email }} adresine gönderilen bağlantıyı açın. Yorum yazmak ve yönetim paneline erişmek için doğrulama gereklidir.</p><form method="POST" action="{{ route('verification.send') }}" class="mt-6">@csrf<x-ui.button type="submit">Bağlantıyı Yeniden Gönder</x-ui.button></form><a class="mt-5 inline-block text-brand" href="{{ route('profile.edit') }}">E-posta adresimi düzelt</a></x-ui.card></div></section>
@endsection
