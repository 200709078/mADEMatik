@if(session('status') || session('success'))
<div class="container-site mt-5"><x-ui.alert type="success" dismissible>{{ session('success') ?? session('status') }}</x-ui.alert></div>
@endif
@if(session('error'))
<div class="container-site mt-5"><x-ui.alert type="danger">{{ session('error') }}</x-ui.alert></div>
@endif
@if($errors->any())
<div class="container-site mt-5"><x-ui.alert type="danger" :title="__('ui.validation_title')"><ul class="list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></x-ui.alert></div>
@endif
