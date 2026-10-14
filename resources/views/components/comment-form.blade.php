@props(['project' => null, 'parent' => null])
@auth
    @if(auth()->user()->is_blocked)
        <x-ui.alert type="warning">{{ __('ui.comment_blocked') }}</x-ui.alert>
    @elseif(!auth()->user()->meetsEmailVerificationRequirement())
        <x-ui.alert type="warning"><p>{{ __('ui.comment_verify') }}</p><a href="{{ route('verification.notice') }}" class="link mt-2 inline-block">{{ __('ui.verify_email') }}</a></x-ui.alert>
    @else
        <form action="{{ route('comments.store') }}" method="POST" class="space-y-4">
            @csrf
            @if($project)<input type="hidden" name="project_id" value="{{ $project->id }}">@endif
            @if($parent)<input type="hidden" name="parent_id" value="{{ $parent->id }}">@endif
            <div class="hidden" aria-hidden="true"><label for="website-{{ $parent?->id ?? 'new' }}">Website</label><input id="website-{{ $parent?->id ?? 'new' }}" name="website" type="text" tabindex="-1" autocomplete="off"></div>
            <x-ui.textarea name="body" :id="'comment-body-'.($parent?->id ?? 'new')" :label="$parent ? __('ui.reply_label') : __('ui.comment_label')" :placeholder="__('ui.comment_placeholder')" rows="4" required minlength="10" maxlength="3000" />
            <p class="text-muted text-xs leading-6">{{ __('ui.comment_pending_note') }}</p>
            <div class="flex flex-wrap items-center gap-4"><x-ui.button type="submit"><x-icon name="send" class="h-4 w-4" />{{ __('ui.comment_send') }}</x-ui.button>@unless($parent)<a href="{{ route('profile.edit') }}#comments" class="link text-xs">{{ __('ui.my_comments') }}</a>@endunless</div>
        </form>
    @endif
@else
    <x-ui.alert><div><p>{{ __(config('auth.email_verification_enabled', true) ? 'ui.comment_login' : 'ui.comment_login_without_verification') }}</p><div class="mt-4 flex flex-wrap gap-2"><x-ui.button :href="route('login')" size="sm">{{ __('ui.login') }}</x-ui.button><x-ui.button :href="route('register')" variant="outline" size="sm">{{ __('ui.register') }}</x-ui.button></div></div></x-ui.alert>
@endauth
