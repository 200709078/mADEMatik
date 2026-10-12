@extends('layouts.admin')
@section('title','İşlem Kayıtları')
@section('content')
<h1 class="heading-page mb-3">İşlem kayıtları</h1><p class="mb-6 text-muted">İçerik değişiklikleri, moderasyon ve önemli hesap işlemleri.</p><x-ui.table><thead><tr><th>Zaman</th><th>İşlem yapan</th><th>İşlem</th><th>Kayıt</th><th>Ayrıntı</th></tr></thead><tbody>@forelse($activities as $activity)<tr><td>{{ $activity->created_at->format('d.m.Y H:i:s') }}</td><td>{{ $activity->user?->name ?? 'Sistem / silinmiş üye' }}</td><td>{{ $activity->action_label }}</td><td>{{ class_basename($activity->subject_type ?? '') }} @if($activity->subject_id)#{{ $activity->subject_id }}@endif</td><td><span class="text-xs break-all">{{ json_encode($activity->metadata ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span></td></tr>@empty<tr><td colspan="5">Henüz işlem kaydı yok.</td></tr>@endforelse</tbody></x-ui.table><x-ui.pagination :paginator="$activities"/>
@endsection
