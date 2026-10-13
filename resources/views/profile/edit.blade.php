@extends('layouts.app')
@section('title', 'Profilim')
@section('content')
<section class="container-site section-space"><div class="mb-8"><p class="eyebrow">HESABIM</p><h1>Profilim</h1><p class="text-muted">Hesap bilgilerinizi, bildirimlerinizi ve yorumlarınızı yönetin.</p></div>
@if(! $user->hasVerifiedEmail())<x-ui.alert type="warning" title="E-posta adresiniz doğrulanmadı."><a class="underline" href="{{ route('verification.notice') }}">Doğrulama bağlantısını yeniden gönderin.</a></x-ui.alert>@endif
<x-ui.card class="mt-6" x-data="profilePhoto">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
        <div class="shrink-0">
            <div x-show="!previewUrl"><x-ui.avatar :name="$user->name" :src="$user->avatar_url" size="xl" /></div>
            <template x-if="previewUrl"><img :src="previewUrl" alt="" width="96" height="96" class="h-24 w-24 rounded-full object-cover" x-on:error="clearPreview()"></template>
            <p class="mt-3 text-sm font-medium">{{ $user->name }}</p>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-xl font-semibold">{{ __('profile.avatar_title') }}</h2>
            <p class="mt-2 text-sm text-muted">{{ __('profile.avatar_description') }}</p>
            <form method="POST" action="{{ route('profile.avatar.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <x-ui.input type="file" name="avatar" :label="__('profile.avatar_label')" accept="image/jpeg,image/png,image/webp" :help="__('profile.avatar_hint')" required @change="selectPhoto($event)" />
                <x-ui.button type="submit" ::disabled="!hasFile">{{ $user->avatar_path ? __('profile.avatar_change') : __('profile.avatar_save') }}</x-ui.button>
            </form>
            @if($user->avatar_path)
                <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="mt-3">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="ghost" size="sm">{{ __('profile.avatar_remove') }}</x-ui.button>
                </form>
            @endif
            <p class="mt-4 text-xs text-muted">{{ __('profile.avatar_initials_hint') }}</p>
        </div>
    </div>
</x-ui.card>
<div class="grid gap-6 lg:grid-cols-2 mt-6"><x-ui.card><h2 class="text-xl font-semibold">Hesap bilgilerim</h2><form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-5">@csrf @method('PATCH')<x-ui.input name="name" label="Ad Soyad" :value="$user->name" required autocomplete="name" /><x-ui.input name="email" label="E-posta adresi" type="email" :value="$user->email" required autocomplete="username" /><x-ui.input name="current_password" label="Mevcut parola" type="password" required autocomplete="current-password" help="Değişiklikleri kaydetmek için gereklidir." /><x-ui.button type="submit">Bilgileri Kaydet</x-ui.button></form></x-ui.card>
<x-ui.card><h2 class="text-xl font-semibold">Parola değiştir</h2><form method="POST" action="{{ route('profile.password') }}" class="mt-5 space-y-5">@csrf @method('PUT')<x-ui.input name="current_password" id="password-current" label="Mevcut parola" type="password" required autocomplete="current-password" /><x-ui.input name="password" label="Yeni parola" type="password" required minlength="6" autocomplete="new-password" help="En az 6 karakter, büyük/küçük harf, rakam ve sembol." /><x-ui.input name="password_confirmation" label="Yeni Parola Tekrarı" type="password" required autocomplete="new-password" /><x-ui.button type="submit">Parolayı Güncelle</x-ui.button></form></x-ui.card>
<x-ui.card><h2 class="text-xl font-semibold">Bildirim tercihlerim</h2><p class="mt-2 text-sm text-muted">Hesap doğrulama ve parola sıfırlama gibi güvenlik e-postaları bu tercihlerden bağımsızdır.</p><form method="POST" action="{{ route('profile.preferences') }}" class="mt-5 space-y-4">@csrf @method('PUT')<x-ui.checkbox name="site_notifications" label="Site içi bildirimleri göster" value="1" :checked="$preference->site_notifications" /><x-ui.checkbox name="comment_mail" label="Yorum durumlarını e-posta ile bildir" value="1" :checked="$preference->comment_mail" /><x-ui.checkbox name="reply_mail" label="Yorum yanıtlarını e-posta ile bildir" value="1" :checked="$preference->reply_mail" /><x-ui.checkbox name="project_mail" label="Yeni proje duyurularını e-posta ile gönder" value="1" :checked="$preference->project_mail" /><x-ui.button type="submit">Tercihleri Kaydet</x-ui.button></form></x-ui.card>
<x-ui.card><h2 class="text-xl font-semibold">Hesap ve veri silme talebi</h2><p class="mt-2 text-sm text-muted">Talebiniz yönetici tarafından değerlendirilir; gerekli saklama koşulları ve yorumların ve bağlı yanıtların silinmesi gözden geçirilir.</p>@if($user->deletion_requested_at)<x-ui.alert class="mt-4">{{ $user->deletion_requested_at->format('d.m.Y H:i') }} tarihli talebiniz alındı.</x-ui.alert>@else<form method="POST" action="{{ route('profile.deletion') }}" class="mt-5 space-y-4">@csrf<x-ui.input name="current_password" id="deletion-password" label="Mevcut parola" type="password" required autocomplete="current-password" /><x-ui.checkbox name="confirm_deletion" label="Hesabımın ve kişisel verilerimin silinmesini talep ediyorum." value="1" required /><x-ui.button type="submit" variant="danger">Silme Talebini İlet</x-ui.button></form>@endif</x-ui.card></div>
<div id="comments" class="mt-10"><h2 class="text-2xl font-semibold mb-5">Yorumlarım</h2><p class="mb-5 text-muted">Yeni ve düzenlenmiş bütün yorumlar yönetici onayından geçer.</p>@forelse($comments as $comment)<x-ui.card class="mb-4"><div class="flex flex-wrap gap-3 justify-between"><p class="text-sm text-muted">{{ $comment->project?->translated('title') ?? 'Genel ziyaretçi yorumu' }} · {{ $comment->created_at->format('d.m.Y') }}</p><x-ui.badge :variant="match($comment->status){'approved'=>'success','rejected'=>'danger',default=>'warning'}">{{ ['pending'=>'Onay bekliyor','approved'=>'Onaylandı','rejected'=>'Reddedildi','hidden'=>'Gizlendi'][$comment->status] ?? $comment->status }}</x-ui.badge></div><p class="mt-3 whitespace-pre-line">{{ $comment->body }}</p>@if($comment->moderation_note)<p class="mt-3 text-sm text-muted">Yönetici notu: {{ $comment->moderation_note }}</p>@endif<div class="mt-4 flex gap-3"><x-ui.button size="sm" variant="outline" :href="route('comments.edit', $comment)">Düzenle</x-ui.button><form method="POST" action="{{ route('comments.destroy',$comment) }}" x-data @submit="if (!confirm('Yorumunuz silinsin mi?')) $event.preventDefault()">@csrf @method('DELETE')<x-ui.button size="sm" variant="ghost" type="submit">Sil</x-ui.button></form></div></x-ui.card>@empty<x-ui.empty-state title="Henüz yorumunuz yok." description="Deneyiminizi genel yorumlar bölümünde veya proje sayfalarında paylaşabilirsiniz." icon="message-square"/>@endforelse<x-ui.pagination :paginator="$comments"/></div></section>
@endsection
