@if($project->downloads->isNotEmpty())
<section id="indirmeler" class="mt-12 scroll-mt-24" aria-labelledby="downloads-heading">
    <div class="section-heading"><h2 id="downloads-heading" class="heading-section">{{ __('downloads.heading') }}</h2><p class="mt-3 text-muted">{{ __('downloads.intro') }}</p></div>
    <div class="grid items-start gap-6 md:grid-cols-2 xl:grid-cols-3">
        @foreach($project->downloads as $download)
        <x-ui.card class="min-w-0">
            <div class="mb-5 flex items-start gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl surface-muted text-brand"><x-icon :name="in_array($download->platform, ['android','ios','ipados']) ? 'smartphone' : 'monitor'" class="size-6" /></span>
                <div class="min-w-0"><p class="text-sm text-muted">{{ $download->platform_label }}</p><h3 class="mt-1 text-lg font-semibold break-words">{{ $download->translated('title') ?: $download->platform_label }}</h3></div>
            </div>
            <div class="mb-5 flex flex-wrap gap-2"><x-ui.badge :variant="$download->channel === 'beta' ? 'warning' : 'success'">{{ $download->channel_label }}</x-ui.badge><x-ui.badge>{{ $download->distribution_label }}</x-ui.badge>@if($download->architecture)<x-ui.badge>{{ $download->architecture }}</x-ui.badge>@endif</div>
            <dl class="mb-5 space-y-2 text-sm">
                @if($download->version)<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted">{{ __('downloads.version') }}</dt><dd class="font-medium break-all">{{ $download->version }}</dd></div>@endif
                @if($download->file_size_label)<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted">{{ __('downloads.size') }}</dt><dd>{{ $download->file_size_label }}</dd></div>@endif
                @if($download->released_at)<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted">{{ __('downloads.date') }}</dt><dd><time datetime="{{ $download->released_at->format('Y-m-d') }}">{{ $download->released_at->format('d.m.Y') }}</time></dd></div>@endif
            </dl>
            <x-ui.button :href="$download->download_url" target="_blank" rel="noopener noreferrer" class="w-full"><x-icon :name="$download->distribution_type === 'direct' ? 'download' : 'external-link'" />{{ $download->action_label }}<span class="sr-only"> — {{ $download->platform_label }} {{ $download->translated('title') }} {{ $download->architecture }}</span></x-ui.button>
            @if($download->translated('requirements'))<div class="mt-5"><h4 class="text-sm font-semibold">{{ __('downloads.requirements') }}</h4><p class="mt-2 whitespace-pre-line break-words text-sm text-muted">{{ $download->translated('requirements') }}</p></div>@endif
            @if($download->translated('notes'))<div class="mt-5"><h4 class="text-sm font-semibold">{{ __('downloads.notes') }}</h4><p class="mt-2 whitespace-pre-line break-words text-sm text-muted">{{ $download->translated('notes') }}</p></div>@endif
            @if($download->license_url)<a href="{{ $download->license_url }}" target="_blank" rel="noopener noreferrer" class="link mt-5 inline-block text-sm">{{ __('downloads.license') }}</a>@endif
            @if($download->sha256)<details class="mt-5 border-t border-token pt-4"><summary class="text-sm font-medium">{{ __('downloads.checksum') }}</summary><p class="mt-3 text-xs text-muted">SHA-256</p><code class="mt-2 block break-all text-xs leading-6">{{ $download->sha256 }}</code></details>@endif
        </x-ui.card>
        @endforeach
    </div>
</section>
@endif
