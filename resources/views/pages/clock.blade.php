@extends('layouts.app')
@section('title', __('ui.clock'))
@section('content')
<section class="container-site section-space">
    <div class="section-heading">
        <p class="eyebrow mb-4">{{ __('ui.local_time') }} · {{ __('ui.countdown') }} · {{ __('ui.stopwatch') }}</p>
        <h1 class="heading-page">{{ __('ui.clock') }}</h1>
        <p class="text-muted mt-5 leading-8">{{ __('ui.clock_intro') }}</p>
    </div>
    <div x-data="clockTool(@js(['storageUnavailable'=>__('ui.timer_storage_unavailable'),'zoomStorageUnavailable'=>__('clock.zoom_storage_unavailable'),'invalidDuration'=>__('ui.timer_invalid_duration'),'shrink'=>__('ui.shrink'),'enlarge'=>__('ui.enlarge'),'soundOn'=>__('clock.sound_on'),'soundOff'=>__('clock.sound_off'),'timeUp'=>__('clock.time_up')]))" @keydown.escape.window="expanded=false" x-effect="document.body.style.overflow = expanded ? 'hidden' : ''">
        <div x-ref="panel" data-testid="clock-panel" class="clock-panel card p-6 sm:p-10" :style="{'--clock-zoom':zoomScale,'--clock-em':faceEm}" :class="{'clock-panel-expanded':expanded}" :role="expanded ? 'dialog' : null" :aria-modal="expanded ? 'true' : null" aria-label="{{ __('ui.clock') }}">
            <div data-testid="clock-toolbar" class="clock-toolbar mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-1 rounded-xl surface-muted p-1">
                    <a href="{{ route('home') }}" class="button button-sm button-ghost button-icon" aria-label="{{ __('ui.home_control') }}" title="{{ __('ui.home_control') }}"><x-icon name="home" class="h-4 w-4" /></a>
                    <div class="flex gap-1" role="tablist" aria-label="{{ __('ui.clock') }}">
                        <button type="button" x-ref="clockTab" id="clock-tab" role="tab" @click="setMode('clock')" @keydown.arrow-right.prevent="cycleMode(1)" @keydown.arrow-left.prevent="cycleMode(-1)" :aria-selected="mode==='clock'" :tabindex="mode==='clock'?0:-1" aria-controls="clock-display" aria-label="{{ __('ui.clock_tab') }}" title="{{ __('ui.clock_tab') }}" class="button button-sm button-icon" :class="mode==='clock'?'button-primary':'button-ghost'"><x-icon name="clock" class="h-4 w-4" /></button>
                        <button type="button" x-ref="timerTab" id="timer-tab" role="tab" @click="setMode('timer')" @keydown.arrow-right.prevent="cycleMode(1)" @keydown.arrow-left.prevent="cycleMode(-1)" :aria-selected="mode==='timer'" :tabindex="mode==='timer'?0:-1" aria-controls="timer-display" aria-label="{{ __('ui.timer_tab') }}" title="{{ __('ui.timer_tab') }}" class="button button-sm button-icon" :class="mode==='timer'?'button-primary':'button-ghost'"><x-icon name="hourglass" class="h-4 w-4" /></button>
                        <button type="button" x-ref="stopwatchTab" id="stopwatch-tab" role="tab" @click="setMode('stopwatch')" @keydown.arrow-right.prevent="cycleMode(1)" @keydown.arrow-left.prevent="cycleMode(-1)" :aria-selected="mode==='stopwatch'" :tabindex="mode==='stopwatch'?0:-1" aria-controls="stopwatch-display" aria-label="{{ __('ui.stopwatch_tab') }}" title="{{ __('ui.stopwatch_tab') }}" class="button button-sm button-icon" :class="mode==='stopwatch'?'button-primary':'button-ghost'"><x-icon name="timer" class="h-4 w-4" /></button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-theme-toggle data-testid="clock-theme" />
                    <div x-cloak x-show="mode==='timer'||fullScreen||expanded" class="clock-sound-slot flex shrink-0 items-center justify-center">
                        <x-ui.button x-cloak x-show="mode==='timer'" data-testid="clock-sound" variant="ghost" size="sm" class="button-icon" @click="toggleSound()" ::aria-pressed="soundOn ? 'true' : 'false'" ::aria-label="soundOn ? labels.soundOn : labels.soundOff" ::title="soundOn ? labels.soundOn : labels.soundOff">
                            <span x-show="soundOn"><x-icon name="volume-2" class="h-4 w-4" /></span>
                            <span x-cloak x-show="!soundOn"><x-icon name="volume-x" class="h-4 w-4" /></span>
                        </x-ui.button>
                    </div>
                    <div class="hidden w-fit items-center gap-1 rounded-xl surface-muted p-1 sm:flex" role="group" aria-label="{{ __('clock.zoom') }}">
                        <x-ui.button variant="ghost" size="sm" class="button-icon" @click="changeZoom(-10)" ::disabled="zoomPercent<=60" :aria-label="__('clock.zoom_out')" title="{{ __('clock.zoom_out_title') }}"><x-icon name="zoom-out" class="h-4 w-4" /></x-ui.button>
                        <x-ui.button variant="ghost" size="sm" class="min-w-16 font-mono tabular-nums" @click="changeZoom(0)" :aria-label="__('clock.zoom_reset')" title="{{ __('clock.zoom_reset_title') }}"><span x-text="zoomPercent+'%'" aria-live="polite">100%</span></x-ui.button>
                        <x-ui.button data-testid="clock-zoom-in" variant="ghost" size="sm" class="button-icon" @click="changeZoom(10)" ::disabled="!canZoomIn" :aria-label="__('clock.zoom_in')" title="{{ __('clock.zoom_in_title') }}"><x-icon name="zoom-in" class="h-4 w-4" /></x-ui.button>
                    </div>
                    <x-ui.button variant="ghost" size="sm" class="button-icon" @click="toggleFullscreen()" ::aria-label="(fullScreen||expanded) ? labels.shrink : labels.enlarge" title="{{ __('ui.fullscreen') }}">
                        <span x-show="!(fullScreen||expanded)"><x-icon name="maximize" class="h-4 w-4" /></span>
                        <span x-cloak x-show="fullScreen||expanded"><x-icon name="minimize" class="h-4 w-4" /></span>
                    </x-ui.button>
                </div>
            </div>
            <div data-testid="clock-content" class="clock-panel-content">
            <div id="clock-display" role="tabpanel" aria-labelledby="clock-tab" x-show="mode==='clock'" class="surface-muted mb-6 rounded-xl py-10 text-center">
                <h2 class="clock-fullscreen-hide text-muted mb-6 text-lg font-medium">{{ __('clock.now') }}</h2>
                <time x-ref="clockFace" class="clock-face block" x-text="clockText" :datetime="new Date(now).toISOString()"></time>
                <div class="mt-6 flex flex-wrap items-baseline justify-center gap-x-3 gap-y-1 text-base sm:text-lg">
                    <p x-text="dateText"></p>
                    <p class="text-muted" x-text="@js(__('clock.week', ['week'=>':week'])).replace(':week',weekNumber)"></p>
                </div>
                <p class="clock-fullscreen-hide eyebrow mt-6">{{ __('clock.local_time_label') }}</p>
            </div>
            <div id="timer-display" role="tabpanel" aria-labelledby="timer-tab" x-cloak x-show="mode==='timer'" class="surface-muted rounded-xl py-6 text-center">
                <h2 class="clock-fullscreen-hide text-muted mb-6 text-lg font-medium">{{ __('clock.timer_heading') }}</h2>
                <output x-ref="timerFace" class="clock-face block" :class="{'clock-face-done':completed}" role="timer" aria-live="off" x-text="timerFaceText"></output>
                <p class="mt-4 min-h-7 text-sm text-muted" aria-live="polite">
                    <span x-show="completed">{{ __('ui.timer_complete') }}</span><span x-show="running">{{ __('ui.timer_running') }}</span><span x-show="!running&&!completed&&startedAt!==null">{{ __('ui.timer_paused') }}</span><span x-show="!running&&!completed&&startedAt===null">{{ __('ui.timer_ready') }}</span>
                </p>
                <p x-cloak x-show="running&&soundOn&&!audioReady" class="text-sm text-[var(--warning)]">{{ __('clock.sound_locked') }}</p>
                <div class="clock-progress surface-muted mx-auto mt-5 h-1 max-w-lg overflow-hidden rounded-full" role="progressbar" aria-label="{{ __('clock.progress') }}" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(remainingPercent)">
                    <div class="h-full" :class="lastMinute?'bg-[var(--danger)]':'bg-[var(--brand)]'" :style="{width:remainingPercent+'%'}"></div>
                </div>
                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <x-ui.button x-show="!running&&(startedAt===null||completed)" @click="start()" title="{{ __('ui.start') }}"><x-icon name="play" class="h-4 w-4" />{{ __('ui.start') }}</x-ui.button>
                    <x-ui.button x-cloak x-show="!running&&startedAt!==null&&!completed" @click="start()" title="{{ __('ui.resume') }}"><x-icon name="play" class="h-4 w-4" />{{ __('ui.resume') }}</x-ui.button>
                    <x-ui.button x-cloak x-show="running" @click="pause()" variant="secondary" title="{{ __('ui.pause') }}"><x-icon name="pause" class="h-4 w-4" />{{ __('ui.pause') }}</x-ui.button>
                    <x-ui.button @click="reset()" variant="outline" title="{{ __('ui.reset') }}"><x-icon name="rotate-ccw" class="h-4 w-4" />{{ __('ui.reset') }}</x-ui.button>
                </div>
                <div class="surface-muted mx-auto mt-8 max-w-xl rounded-xl p-5" x-show="!running&&(startedAt===null||completed)">
                    <div class="mx-auto grid max-w-sm grid-cols-3 gap-3">
                        <x-ui.input name="timer_hours" :label="__('ui.hours')" type="number" min="0" max="99" x-model.number="hours" inputmode="numeric" />
                        <x-ui.input name="timer_minutes" :label="__('ui.minutes')" type="number" min="0" max="59" x-model.number="minutes" inputmode="numeric" />
                        <x-ui.input name="timer_seconds" :label="__('ui.seconds')" type="number" min="0" max="59" x-model.number="seconds" inputmode="numeric" />
                    </div>
                    <div class="mt-4 flex justify-center">
                        <x-ui.button @click="applyDuration()" variant="secondary" size="sm" title="{{ __('clock.set_duration') }}"><x-icon name="check" class="h-4 w-4" />{{ __('clock.set_duration') }}</x-ui.button>
                    </div>
                    <p id="timer-presets-label" class="text-muted mt-5 text-xs font-medium">{{ __('clock.presets') }}</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2" role="group" aria-labelledby="timer-presets-label">
                        <template x-for="preset in presetMinutes" :key="preset">
                            <button type="button" class="button button-sm" :class="isPreset(preset)?'button-primary':'button-outline'" @click="selectPreset(preset)" :aria-pressed="isPreset(preset)" :title="@js(__('clock.preset_minutes',['minutes'=>':minutes'])).replace(':minutes',preset)" x-text="@js(__('clock.preset_minutes',['minutes'=>':minutes'])).replace(':minutes',preset)"></button>
                        </template>
                    </div>
                </div>
                <p class="clock-fullscreen-hide text-muted mx-auto mt-6 max-w-xl text-xs leading-6">{{ __('ui.timer_storage') }}</p>
            </div>
            <div id="stopwatch-display" role="tabpanel" aria-labelledby="stopwatch-tab" x-cloak x-show="mode==='stopwatch'" class="surface-muted rounded-xl py-6 text-center">
                <h2 class="clock-fullscreen-hide text-muted mb-6 text-lg font-medium">{{ __('clock.stopwatch_heading') }}</h2>
                <output x-ref="stopwatchFace" class="clock-face block" role="timer" aria-live="off" x-text="stopwatchText"></output>
                <p class="mt-4 min-h-7 text-sm text-muted" aria-live="polite">
                    <span x-show="stopwatchRunning">{{ __('clock.stopwatch_running') }}</span><span x-show="!stopwatchRunning&&stopwatchElapsed>0">{{ __('clock.stopwatch_paused') }}</span><span x-show="!stopwatchRunning&&stopwatchElapsed===0">{{ __('clock.stopwatch_ready') }}</span>
                </p>
                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <x-ui.button x-show="!stopwatchRunning&&stopwatchElapsed===0" @click="startStopwatch()" title="{{ __('ui.start') }}"><x-icon name="play" class="h-4 w-4" />{{ __('ui.start') }}</x-ui.button>
                    <x-ui.button x-cloak x-show="!stopwatchRunning&&stopwatchElapsed>0" @click="startStopwatch()" title="{{ __('ui.resume') }}"><x-icon name="play" class="h-4 w-4" />{{ __('ui.resume') }}</x-ui.button>
                    <x-ui.button x-cloak x-show="stopwatchRunning" @click="pauseStopwatch()" variant="secondary" title="{{ __('ui.pause') }}"><x-icon name="pause" class="h-4 w-4" />{{ __('ui.pause') }}</x-ui.button>
                    <x-ui.button x-cloak x-show="stopwatchRunning" @click="lap()" variant="outline" ::disabled="laps.length>=maxLaps" title="{{ __('clock.lap') }}"><x-icon name="flag" class="h-4 w-4" />{{ __('clock.lap') }}</x-ui.button>
                    <x-ui.button @click="resetStopwatch()" variant="outline" ::disabled="!stopwatchRunning&&stopwatchElapsed===0" title="{{ __('ui.reset') }}"><x-icon name="rotate-ccw" class="h-4 w-4" />{{ __('ui.reset') }}</x-ui.button>
                </div>
                <ol x-cloak x-show="laps.length" data-testid="stopwatch-laps" class="mx-auto mt-8 max-w-md divide-y divide-[var(--border)] text-left" aria-label="{{ __('clock.laps') }}">
                    <template x-for="row in lapRows" :key="row.number">
                        <li class="clock-digits flex items-baseline justify-between gap-4 px-2 py-2">
                            <span class="text-muted font-sans text-sm" x-text="@js(__('clock.lap_number',['number'=>':number'])).replace(':number',row.number)"></span>
                            <span class="text-muted text-sm" x-text="'+'+row.split"></span>
                            <span class="font-semibold" x-text="row.total"></span>
                        </li>
                    </template>
                </ol>
                <p class="clock-fullscreen-hide text-muted mx-auto mt-6 max-w-xl text-xs leading-6">{{ __('clock.stopwatch_storage') }}</p>
            </div>
            <p x-cloak x-show="notice" x-text="notice" role="status" class="mt-4 text-center text-sm text-[var(--warning)]"></p>
            <p x-cloak x-show="viewNotice" x-text="viewNotice" role="status" class="mt-4 text-center text-sm text-[var(--warning)]"></p>
            <p x-cloak x-show="mode!=='clock'&&!fullScreen&&!expanded" class="text-muted mt-4 hidden text-center text-xs sm:block">{{ __('clock.shortcuts') }}</p>
            <p class="clock-fullscreen-hide text-muted border-t border-[var(--border)] pt-6 text-center text-sm">{{ __('clock.tagline') }}</p>
            </div>
            <span x-ref="zoomLimit" aria-hidden="true" class="clock-face invisible pointer-events-none absolute" style="--clock-zoom:4"></span>
            <div x-cloak x-show="fullScreen||expanded" class="absolute bottom-4 left-4 z-10 sm:bottom-6 sm:left-6">
                <a href="{{ route('home') }}" aria-label="{{ __('ui.home_control') }}" title="{{ __('ui.home_control') }}" class="block rounded-lg opacity-60 transition hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">
                    <x-brand-logo loading="lazy" class="h-10 w-auto max-w-64 shrink-0 object-contain" />
                </a>
            </div>
        </div>
        <noscript><p class="mt-5 text-center text-sm text-muted">{{ __('clock.noscript') }}</p></noscript>
        @include('pages.clock.world-times')
    </div>
</section>
@endsection
