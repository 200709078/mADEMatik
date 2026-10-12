@extends('layouts.admin')
@section('title','Proje Kategorileri')
@section('content')
<div class="flex flex-wrap justify-between items-center gap-4 mb-6"><h1 class="heading-page">Proje kategorileri</h1><x-ui.button :href="route('admin.categories.create')">Kategori Ekle</x-ui.button></div>
<x-ui.table><thead><tr><th>Kategori</th><th>Adres adı</th><th>Proje</th><th>Sıra</th><th>İşlemler</th></tr></thead><tbody>@forelse($categories as $category)<tr><td>{{ $category->translated('name') }}</td><td>{{ $category->slug }}</td><td>{{ $category->projects_count }}</td><td>{{ $category->sort_order }}</td><td><div class="flex gap-2"><x-ui.button size="sm" variant="outline" :href="route('admin.categories.edit',$category)">Düzenle</x-ui.button><form method="POST" action="{{ route('admin.categories.destroy',$category) }}" x-data @submit="if(!confirm('Kategori silinsin mi?')) $event.preventDefault()">@csrf @method('DELETE')<x-ui.button type="submit" size="sm" variant="danger">Sil</x-ui.button></form></div></td></tr>@empty<tr><td colspan="5">Henüz kategori yok.</td></tr>@endforelse</tbody></x-ui.table><x-ui.pagination :paginator="$categories"/>
@endsection
