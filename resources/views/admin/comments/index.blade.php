@extends('layouts.admin')
@section('title','Yorum Moderasyonu')
@section('content')
<h1 class="heading-page mb-3">Yorum moderasyonu</h1>
<p class="text-muted mb-6">Bütün yeni yorumlar, yanıtlar ve düzenlemeler onay bekler. Halka yalnızca onaylanmış içerik gösterilir.</p>
@php
    $commentTabs = [];
    foreach (['all' => 'Tümü', 'pending' => 'Onay Bekleyen', 'approved' => 'Onaylanan', 'rejected' => 'Reddedilen', 'hidden' => 'Gizlenen'] as $tabStatus => $label) {
        $parameters = ['status' => $tabStatus];
        if (request()->filled('q')) {
            $parameters['q'] = request('q');
        }
        $commentTabs[] = ['label' => $label, 'url' => route('admin.comments.index', $parameters), 'active' => $status === $tabStatus];
    }
@endphp
<x-ui.tabs :items="$commentTabs" class="mb-6" />
<form method="GET" action="{{ route('admin.comments.index') }}" class="mb-6 max-w-md" role="search" aria-label="Yorumlarda Ara">
    <input type="hidden" name="status" value="{{ $status }}">
    <x-ui.input name="q" type="search" label="Yorumda Ara" :value="request('q')" maxlength="100" help="Aramak için Enter'a basın." />
</form>
<div class="space-y-5">@forelse($comments as $comment)<x-ui.card><div class="flex justify-between flex-wrap gap-3"><div><strong>{{ $comment->user?->name ?? 'Silinmiş üye' }}</strong><p class="text-sm text-muted">{{ $comment->project?->translated('title') ?? 'Genel ziyaretçi yorumu' }} · {{ $comment->created_at->format('d.m.Y H:i') }} @if($comment->parent_id)· Yanıt #{{ $comment->parent_id }}@endif</p></div><x-ui.badge :variant="match($comment->status){'approved'=>'success','rejected'=>'danger',default=>'warning'}">{{ ['pending'=>'Onay bekliyor','approved'=>'Onaylandı','rejected'=>'Reddedildi','hidden'=>'Gizlendi'][$comment->status] ?? $comment->status }}</x-ui.badge></div>@if($comment->parent)<blockquote class="mt-4 border-l-2 border-token pl-4 text-sm text-muted">Yanıtlanan: {{ Str::limit($comment->parent->body,250) }}</blockquote>@endif<p class="whitespace-pre-line my-5">{{ $comment->body }}</p><form method="POST" action="{{ route('admin.comments.update',$comment) }}" class="grid gap-4 md:grid-cols-3">@csrf @method('PUT')<x-ui.select name="status" :id="'comment-status-'.$comment->id" label="Yeni durum" :options="['approved'=>'Onayla','rejected'=>'Reddet','hidden'=>'Gizle']"/><x-ui.input name="moderation_note" :id="'comment-note-'.$comment->id" label="Üyeye gösterilen not" :value="$comment->moderation_note" maxlength="1000"/><x-ui.button type="submit" class="self-end">Durumu Kaydet</x-ui.button></form><form method="POST" action="{{ route('admin.comments.destroy',$comment) }}" class="mt-4" x-data @submit="if(!confirm('Yorum ve bağlı yanıtları silinsin mi?')) $event.preventDefault()">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger" size="sm">Kalıcı Sil</x-ui.button></form></x-ui.card>@empty
    <x-ui.empty-state title="Bu sekmede yorum yok." icon="message-square" />
@endforelse</div><x-ui.pagination :paginator="$comments"/>
@if($reports->isNotEmpty())<h2 class="text-xl font-semibold mt-10 mb-5">Açık yorum şikâyetleri</h2><div class="space-y-4">@foreach($reports as $report)<x-ui.card><p class="font-semibold">Yorum #{{ $report->comment_id }} · {{ $report->user?->name ?? 'Silinmiş üye' }}</p><p class="mt-2 text-muted">{{ $report->reason }}</p><blockquote class="mt-3">{{ $report->comment?->body }}</blockquote><form method="POST" action="{{ route('admin.comment-reports.resolve',$report) }}" class="mt-4">@csrf @method('PATCH')<x-ui.button type="submit" size="sm" variant="outline">Değerlendirildi İşaretle</x-ui.button></form></x-ui.card>@endforeach</div>@endif
@endsection
