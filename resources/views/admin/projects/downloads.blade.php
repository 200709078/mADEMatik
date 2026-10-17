<section id="downloads" class="mt-10 scroll-mt-24" aria-labelledby="project-downloads-heading">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div><h2 id="project-downloads-heading" class="text-2xl font-semibold">{{ __('downloads.heading') }}</h2><p class="mt-2 text-sm text-muted">{{ __('downloads.manage') }}</p></div>
        <x-ui.button :href="route('admin.downloads.create', $project)"><x-icon name="plus" />{{ __('downloads.add') }}</x-ui.button>
    </div>
    @if($project->downloads->isEmpty())
        <x-ui.card><p class="text-muted">{{ __('downloads.empty') }}</p></x-ui.card>
    @else
        <div class="grid gap-5 xl:grid-cols-2">
            @foreach($project->downloads as $download)
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0"><h3 class="font-semibold break-words">{{ $download->translated('title') ?: $download->platform_label }}</h3><p class="mt-1 text-sm text-muted">{{ $download->platform_label }}@if($download->architecture) · {{ $download->architecture }}@endif @if($download->version) · {{ $download->version }}@endif</p></div>
                        <x-ui.badge :variant="$download->is_published && $download->download_url ? 'success' : 'warning'">{{ $download->is_published && $download->download_url ? __('downloads.published') : __('downloads.draft') }}</x-ui.badge>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2"><x-ui.badge>{{ $download->distribution_label }}</x-ui.badge><x-ui.badge>{{ $download->channel_label }}</x-ui.badge>@if($download->file_size_label)<x-ui.badge>{{ $download->file_size_label }}</x-ui.badge>@endif</div>
                    @unless($download->download_url)<p class="mt-3 text-sm text-muted">{{ __('downloads.missing_url') }}</p>@endunless
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-ui.button variant="outline" size="sm" :href="route('admin.downloads.edit', [$project, $download])">{{ __('downloads.edit') }}</x-ui.button>
                        <form method="POST" action="{{ route('admin.downloads.destroy', [$project, $download]) }}" x-data @submit="if (!confirm(@js(__('downloads.confirm_delete')))) $event.preventDefault()">
                            @csrf @method('DELETE')<input type="hidden" name="confirm_delete" value="1">
                            <x-ui.button type="submit" variant="danger" size="sm">{{ __('downloads.delete') }}</x-ui.button>
                        </form>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</section>
