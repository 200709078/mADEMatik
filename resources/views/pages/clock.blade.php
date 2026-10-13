@extends('layouts.app')
@section('title', __('ui.clock'))
@section('content')
<section class="container-site section-space">
    <div class="section-heading">
        <p class="eyebrow mb-4">{{ __('ui.local_time') }} · {{ __('ui.countdown') }}</p>
        <h1 class="heading-page">{{ __('ui.clock') }}</h1>
        <p class="text-muted mt-5 leading-8">{{ __('ui.clock_intro') }}</p>
    </div>
    <div x-data="clockTool(@js(['storageUnavailable'=>__('ui.timer_storage_unavailable'),'zoomStorageUnavailable'=>__('clock.zoom_storage_unavailable'),'invalidDuration'=>__('ui.timer_invalid_duration'),'shrink'=>__('ui.shrink'),'enlarge'=>__('ui.enlarge')]))" @keydown.escape.window="expanded=false" x-effect="document.body.style.overflow = expanded ? 'hidden' : ''">
        <div x-ref="panel" data-testid="clock-panel" class="clock-panel card p-6 sm:p-10" :style="{'--clock-zoom':zoomScale}" :class="{'clock-panel-expanded':expanded}" :role="expanded ? 'dialog' : null" :aria-modal="expanded ? 'true' : null" aria-label="{{ __('ui.clock') }}">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-1 rounded-xl surface-muted p-1" role="tablist" aria-label="{{ __('ui.clock') }}">
                    <button type="button" x-ref="clockTab" id="clock-tab" role="tab" @click="setMode('clock')" @keydown.arrow-right.prevent="setMode('timer');$refs.timerTab.focus()" @keydown.arrow-left.prevent="setMode('timer');$refs.timerTab.focus()" :aria-selected="mode==='clock'" :tabindex="mode==='clock'?0:-1" aria-controls="clock-display" class="button button-sm" :class="mode==='clock'?'button-primary':'button-ghost'"><x-icon name="clock" class="h-4 w-4" />{{ __('ui.clock_tab') }}</button>
                    <button type="button" x-ref="timerTab" id="timer-tab" role="tab" @click="setMode('timer')" @keydown.arrow-right.prevent="setMode('clock');$refs.clockTab.focus()" @keydown.arrow-left.prevent="setMode('clock');$refs.clockTab.focus()" :aria-selected="mode==='timer'" :tabindex="mode==='timer'?0:-1" aria-controls="timer-display" class="button button-sm" :class="mode==='timer'?'button-primary':'button-ghost'"><x-icon name="timer" class="h-4 w-4" />{{ __('ui.timer_tab') }}</button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-theme-toggle data-testid="clock-theme" />
                    <x-ui.button variant="ghost" size="sm" @click="toggleFullscreen()" ::aria-label="(fullScreen||expanded) ? labels.shrink : labels.enlarge">
                        <span x-show="!(fullScreen||expanded)" class="inline-flex items-center gap-2"><x-icon name="maximize" class="h-4 w-4" /><span class="hidden sm:inline">{{ __('ui.enlarge') }}</span></span>
                        <span x-cloak x-show="fullScreen||expanded" class="inline-flex items-center gap-2"><x-icon name="minimize" class="h-4 w-4" /><span class="hidden sm:inline">{{ __('ui.shrink') }}</span></span>
                    </x-ui.button>
                    <x-ui.button :href="route('home')" variant="outline" size="sm" :aria-label="__('ui.home_control')"><x-icon name="home" class="h-4 w-4" /><span class="hidden sm:inline">{{ __('ui.home_control') }}</span></x-ui.button>
                </div>
            </div>
            <div class="mx-auto flex w-fit items-center gap-1 rounded-xl surface-muted p-1" role="group" aria-label="{{ __('clock.zoom') }}">
                <x-ui.button variant="ghost" size="sm" @click="changeZoom(-10)" ::disabled="zoomPercent<=60" :aria-label="__('clock.zoom_out')">{{ __('clock.zoom_out') }}</x-ui.button>
                <x-ui.button variant="ghost" size="sm" class="min-w-16 font-mono tabular-nums" @click="changeZoom(0)" :aria-label="__('clock.zoom_reset')"><span x-text="zoomPercent+'%'" aria-live="polite">100%</span></x-ui.button>
                <x-ui.button variant="ghost" size="sm" @click="changeZoom(10)" ::disabled="zoomPercent>=300" :aria-label="__('clock.zoom_in')">{{ __('clock.zoom_in') }}</x-ui.button>
            </div>
            <div id="clock-display" role="tabpanel" aria-labelledby="clock-tab" x-show="mode==='clock'" class="py-10 text-center">
                <h2 class="text-muted mb-6 text-lg font-medium">{{ __('clock.now') }}</h2>
                <time class="clock-face block" x-text="clockText" :datetime="new Date(now).toISOString()"></time>
                <p class="mt-6 text-base sm:text-lg" x-text="dateText"></p>
                <p class="text-muted mt-2 text-sm" x-text="@js(__('clock.week', ['week'=>':week'])).replace(':week',weekNumber)"></p>
                <p class="eyebrow mt-6">{{ __('ui.local_time') }}</p>
            </div>
            <div id="timer-display" role="tabpanel" aria-labelledby="timer-tab" x-cloak x-show="mode==='timer'" class="py-6 text-center">
                <h2 class="text-muted mb-6 text-lg font-medium">{{ __('clock.timer_heading') }}</h2>
                <output class="clock-face block" role="timer" aria-live="off" x-text="countdownText"></output>
                <p class="mt-4 min-h-7 text-sm text-muted" aria-live="polite">
                    <span x-show="completed">{{ __('ui.timer_complete') }}</span><span x-show="running">{{ __('ui.timer_running') }}</span><span x-show="!running&&!completed&&startedAt!==null">{{ __('ui.timer_paused') }}</span><span x-show="!running&&!completed&&startedAt===null">{{ __('ui.timer_ready') }}</span>
                </p>
                <div class="surface-muted mx-auto mt-5 h-1 max-w-lg overflow-hidden rounded-full" role="progressbar" aria-label="{{ __('clock.progress') }}" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(remainingPercent)">
                    <div class="h-full bg-[var(--brand)]" :style="{width:remainingPercent+'%'}"></div>
                </div>
                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <x-ui.button x-show="!running" @click="start()"><x-icon name="play" class="h-4 w-4" /><span x-show="startedAt===null||completed">{{ __('ui.start') }}</span><span x-show="startedAt!==null&&!completed">{{ __('ui.resume') }}</span></x-ui.button>
                    <x-ui.button x-cloak x-show="running" @click="pause()" variant="secondary"><x-icon name="pause" class="h-4 w-4" />{{ __('ui.pause') }}</x-ui.button>
                    <x-ui.button @click="reset()" variant="outline"><x-icon name="rotate-ccw" class="h-4 w-4" />{{ __('ui.reset') }}</x-ui.button>
                </div>
                <div class="surface-muted mx-auto mt-8 max-w-xl rounded-xl p-5" x-show="!running&&(startedAt===null||completed)">
                    <div class="mx-auto grid max-w-sm grid-cols-3 gap-3">
                        <x-ui.input name="timer_hours" :label="__('ui.hours')" type="number" min="0" max="99" x-model.number="hours" inputmode="numeric" />
                        <x-ui.input name="timer_minutes" :label="__('ui.minutes')" type="number" min="0" max="59" x-model.number="minutes" inputmode="numeric" />
                        <x-ui.input name="timer_seconds" :label="__('ui.seconds')" type="number" min="0" max="59" x-model.number="seconds" inputmode="numeric" />
                    </div>
                    <p id="timer-presets-label" class="text-muted mt-5 text-xs font-medium">{{ __('clock.presets') }}</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2" role="group" aria-labelledby="timer-presets-label">
                        <template x-for="preset in presetMinutes" :key="preset">
                            <button type="button" class="button button-sm" :class="isPreset(preset)?'button-primary':'button-outline'" @click="selectPreset(preset)" :aria-pressed="isPreset(preset)" x-text="@js(__('clock.preset_minutes',['minutes'=>':minutes'])).replace(':minutes',preset)"></button>
                        </template>
                    </div>
                </div>
                <p class="text-muted mx-auto mt-6 max-w-xl text-xs leading-6">{{ __('ui.timer_storage') }}</p>
            </div>
            <p x-cloak x-show="notice" x-text="notice" role="status" class="mt-4 text-center text-sm text-[var(--warning)]"></p>
            <p x-cloak x-show="viewNotice" x-text="viewNotice" role="status" class="mt-4 text-center text-sm text-[var(--warning)]"></p>
            <p class="text-muted border-t border-[var(--border)] pt-6 text-center text-sm">{{ __('clock.tagline') }}</p>
        </div>
        <p class="text-muted mt-5 text-xs">{{ __('ui.timer_privacy') }}</p>
        @include('pages.clock.world-times')
    </div>
</section>
@endsection
