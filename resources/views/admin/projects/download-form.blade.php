@extends('layouts.admin')
@section('title', $download->exists ? __('downloads.edit') : __('downloads.add'))
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><p class="mb-2 text-sm text-muted">{{ $project->translated('title') }}</p><h1 class="heading-page">{{ $download->exists ? __('downloads.edit') : __('downloads.add') }}</h1></div>
    <x-ui.button variant="ghost" :href="route('admin.projects.edit', $project).'#downloads'">{{ __('downloads.back') }}</x-ui.button>
</div>
<form method="POST" action="{{ $download->exists ? route('admin.downloads.update', [$project, $download]) : route('admin.downloads.store', $project) }}" class="grid gap-6 lg:grid-cols-3" x-data="{ published: @js((bool) old('is_published', $download->is_published)) }">
    @csrf @if($download->exists) @method('PUT') @endif
    <div class="space-y-6 lg:col-span-2">
        <x-ui.card class="space-y-5">
            <h2 class="text-xl font-semibold">{{ __('downloads.heading') }}</h2>
            <x-ui.input name="title" :label="__('downloads.fields.title')" :value="$download->translated('title')" maxlength="160" :help="__('downloads.help.title')" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="platform" :label="__('downloads.fields.platform')" :value="$download->platform" list="download-platforms" required maxlength="40" :help="__('downloads.help.platform')" />
                <datalist id="download-platforms">@foreach(\App\Models\ProjectDownload::PLATFORMS as $platform)<option value="{{ $platform }}">{{ __('downloads.platforms.'.$platform) }}</option>@endforeach</datalist>
                <x-ui.input name="architecture" :label="__('downloads.fields.architecture')" :value="$download->architecture" maxlength="80" :help="__('downloads.help.architecture')" />
                <x-ui.select name="distribution_type" :label="__('downloads.fields.distribution_type')" :value="$download->distribution_type" :options="__('downloads.types')" required />
                <x-ui.select name="channel" :label="__('downloads.fields.channel')" :value="$download->channel" :options="__('downloads.channels')" required />
                <x-ui.input name="version" :label="__('downloads.fields.version')" :value="$download->version" maxlength="80" />
                <x-ui.input name="released_at" :label="__('downloads.fields.released_at')" type="date" :value="$download->released_at?->format('Y-m-d')" />
            </div>
            <x-ui.input name="download_url" :label="__('downloads.fields.download_url')" :value="$download->download_url" maxlength="2048" x-bind:required="published" :help="__('downloads.help.download_url')" />
            <x-ui.input name="file_size" :label="__('downloads.fields.file_size')" type="number" :value="$download->file_size" min="0" :max="PHP_INT_MAX" :help="__('downloads.help.file_size')" />
        </x-ui.card>
        <x-ui.card class="space-y-5">
            <x-ui.textarea name="requirements" :label="__('downloads.fields.requirements')" :value="$download->translated('requirements')" maxlength="4000" rows="3" :help="__('downloads.help.text')" />
            <x-ui.textarea name="notes" :label="__('downloads.fields.notes')" :value="$download->translated('notes')" maxlength="8000" rows="4" :help="__('downloads.help.text')" />
            <x-ui.input name="license_url" :label="__('downloads.fields.license_url')" :value="$download->license_url" maxlength="2048" />
            <x-ui.input name="sha256" :label="__('downloads.fields.sha256')" :value="$download->sha256" maxlength="64" minlength="64" pattern="[a-fA-F0-9]{64}" class="font-mono" :help="__('downloads.help.sha256')" />
        </x-ui.card>
    </div>
    <aside><x-ui.card class="space-y-5">
        <x-ui.input name="sort_order" :label="__('downloads.fields.sort_order')" type="number" :value="$download->sort_order ?? 0" min="0" max="10000" required />
        <x-ui.checkbox name="is_published" :label="__('downloads.fields.is_published')" value="1" :checked="$download->is_published" x-model="published" />
        <p class="field-help">{{ __('downloads.help.is_published') }}</p>
        <x-ui.button type="submit" class="w-full">{{ __('downloads.save') }}</x-ui.button>
    </x-ui.card></aside>
</form>
@endsection
