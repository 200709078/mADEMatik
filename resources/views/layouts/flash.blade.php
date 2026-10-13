@php
    $toasts = [];
    foreach (['success' => session('success') ?? session('status'), 'error' => session('error'), 'warning' => session('warning'), 'info' => session('info')] as $type => $message) {
        if (is_string($message) && trim($message) !== '') {
            $toasts[] = ['type' => $type, 'title' => __('ui.toast_'.$type), 'messages' => [$message]];
        }
    }
    if ($errors->any()) {
        $toasts[] = ['type' => 'error', 'title' => __('ui.validation_title'), 'messages' => array_values(array_unique($errors->all()))];
    }
@endphp
<x-ui.toast-stack :items="$toasts" />
<noscript>
    @foreach($toasts as $toast)
        <div class="container-site mt-5"><x-ui.alert :type="$toast['type'] === 'error' ? 'danger' : $toast['type']" :title="$toast['title']"><ul>@foreach($toast['messages'] as $message)<li>{{ $message }}</li>@endforeach</ul></x-ui.alert></div>
    @endforeach
</noscript>
