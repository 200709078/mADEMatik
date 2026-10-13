@php
    $worldLabels = ['locale' => app()->getLocale()];
    foreach (['storageUnavailable'=>'storage_unavailable','cityLimitReached'=>'city_limit_reached','invalidCity'=>'invalid_city','sameDay'=>'same_day','previousDay'=>'previous_day','nextDay'=>'next_day','locationIdle'=>'location_idle','locationLoading'=>'location_loading','locationReady'=>'location_ready','locationDefault'=>'location_default','locationDenied'=>'location_denied','locationUnavailable'=>'location_unavailable','locationTimeout'=>'location_timeout','locationUnsupported'=>'location_unsupported','locationInsecure'=>'location_insecure','locationInvalid'=>'location_invalid','solarUnavailable'=>'solar_unavailable','polarDay'=>'polar_day','polarNight'=>'polar_night','solarNoEvent'=>'solar_no_event','hoursShort'=>'hours_short','minutesShort'=>'minutes_short'] as $key => $translation) {
        $worldLabels[$key] = __('clock.'.$translation);
    }
@endphp
<div x-data="worldClockTool(@js($worldLabels))" x-show="mode==='clock'" class="mt-10 space-y-6">
    <x-ui.card data-testid="world-clocks" class="card-padding" aria-labelledby="world-clocks-title">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 id="world-clocks-title" class="flex items-center gap-3 text-xl font-semibold"><x-icon name="globe" class="h-5 w-5 text-[var(--brand-text)]" />{{ __('clock.world_title') }}</h2><p class="text-muted mt-2 text-sm leading-6">{{ __('clock.world_intro') }}</p></div>
            <x-ui.button variant="ghost" size="sm" @click="resetCities()" title="{{ __('clock.reset_cities') }}"><x-icon name="rotate-ccw" class="h-4 w-4" />{{ __('clock.reset_cities') }}</x-ui.button>
        </div>
        <p x-cloak x-show="worldNotice" x-text="worldNotice" role="status" class="mt-3 text-sm text-[var(--warning)]"></p>
        <p x-cloak x-show="!selectedCities.length" class="text-muted mt-6 py-6 text-center text-sm">{{ __('clock.world_empty') }}</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <template x-for="city in selectedCities" :key="city.id">
                <article class="surface-muted min-w-0 rounded-xl p-4" :aria-label="city.name">
                    <div class="flex items-center justify-between gap-2"><h3 class="font-semibold" x-text="city.name"></h3><button type="button" class="button button-ghost button-icon shrink-0" @click="removeCity(city.id)" :aria-label="@js(__('clock.remove_city',['city'=>':city'])).replace(':city',city.name)" :title="@js(__('clock.remove_city',['city'=>':city'])).replace(':city',city.name)"><x-icon name="x" class="h-4 w-4" /></button></div>
                    <p class="text-muted text-xs" x-text="city.offset"></p>
                    <time class="mt-4 block font-mono text-3xl font-semibold tabular-nums" x-text="city.time" :datetime="new Date(worldNow).toISOString()"></time>
                    <p class="text-muted mt-3 text-xs leading-5"><span x-text="city.date"></span> · <span x-text="city.dayLabel"></span></p>
                </article>
            </template>
        </div>
        <form class="mt-6 flex flex-wrap items-end gap-3" @submit.prevent="addCity()">
            <div class="w-full sm:max-w-xs"><x-ui.select name="world_city" :label="__('clock.city')" x-model="selectedCityId" ::disabled="selectedIds.length>=maxCities||!cityOptions.length"><template x-for="city in cityOptions" :key="city.id"><option :value="city.id" x-text="city.name"></option></template></x-ui.select></div>
            <x-ui.button type="submit" size="sm" ::disabled="selectedIds.length>=maxCities||!selectedCityId" title="{{ __('clock.add_city') }}"><x-icon name="plus" class="h-4 w-4" />{{ __('clock.add_city') }}</x-ui.button>
        </form>
        <p class="text-muted mt-3 text-xs leading-6">{{ __('clock.world_privacy') }}</p>
    </x-ui.card>
    <x-ui.card x-show="locationState==='loading'||hasLocation" data-testid="solar-times" class="card-padding" aria-labelledby="solar-title">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 id="solar-title" class="flex items-center gap-3 text-xl font-semibold"><x-icon name="sun" class="h-5 w-5 text-[var(--brand-text)]" />{{ __('clock.solar_title') }}</h2><p class="text-muted mt-2 text-sm leading-6">{{ __('clock.solar_intro') }}</p></div>
        </div>
        <p class="text-muted mt-5 text-sm leading-6" role="status" x-text="locationMessage"></p>
        <template x-if="hasLocation">
            <div>
                <p class="mt-4 text-sm font-medium" x-text="solarData.date"></p>
                <p class="text-muted mt-1 break-words text-xs leading-6">{{ __('clock.device_timezone') }}: <span x-text="solarData.timeZone+' · '+solarData.offset"></span></p>
                <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="surface-muted rounded-xl p-5"><dt class="text-muted text-sm">{{ __('clock.sunrise') }}</dt><dd class="mt-3 font-mono text-3xl font-semibold tabular-nums" x-text="solarData.sunrise"></dd><dd class="text-muted mt-2 text-xs" x-text="solarData.sunriseDayLabel"></dd></div>
                    <div class="surface-muted rounded-xl p-5"><dt class="text-muted text-sm">{{ __('clock.sunset') }}</dt><dd class="mt-3 font-mono text-3xl font-semibold tabular-nums" x-text="solarData.sunset"></dd><dd class="text-muted mt-2 text-xs" x-text="solarData.sunsetDayLabel"></dd></div>
                    <div class="surface-muted rounded-xl p-5"><dt class="text-muted text-sm">{{ __('clock.daylight') }}</dt><dd class="mt-3 text-2xl font-semibold" x-text="solarData.daylight"></dd></div>
                </dl>
                <p x-show="solarData.eventMessage" class="text-muted mt-4 text-sm leading-6" x-text="solarData.eventMessage"></p>
            </div>
        </template>
        <p class="text-muted mt-5 text-xs leading-6">{{ __('clock.solar_privacy') }}</p>
    </x-ui.card>
</div>
