@props(['type' => 'info', 'title' => null, 'dismissible' => false])
<div {{ $attributes->class(['alert', 'alert-'.$type => $type !== 'info']) }} role="{{ $type === 'danger' ? 'alert' : 'status' }}" @if($dismissible) x-data="{show:true}" x-show="show" @endif>
    <x-icon :name="match($type) {'success'=>'check-circle','warning','danger'=>'alert-triangle',default=>'info'}" class="mt-0.5 h-5 w-5" />
    <div class="min-w-0 flex-1">@if($title)<p class="font-semibold">{{ $title }}</p>@endif{{ $slot }}</div>
    @if($dismissible)<button type="button" @click="show=false" aria-label="{{ __('ui.close') }}"><x-icon name="x" class="h-4 w-4" /></button>@endif
</div>
