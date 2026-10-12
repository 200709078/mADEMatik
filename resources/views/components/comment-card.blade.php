@props(['comment', 'project' => null, 'reply' => false])
<article id="comment-{{ $comment->id }}" {{ $attributes->class(['card p-5 sm:p-6', 'ml-5 sm:ml-12' => $reply]) }} x-data="{replyOpen:false,reportOpen:false}">
    <div class="flex items-center gap-3"><x-ui.avatar :name="$comment->user->name" /><div><h3 class="text-sm font-semibold">{{ $comment->user->name }}</h3><time datetime="{{ $comment->created_at->toIso8601String() }}" class="text-muted text-xs">{{ $comment->created_at->translatedFormat('d F Y H:i') }}</time></div></div>
    <p class="mt-4 text-sm leading-7 whitespace-pre-line break-words">{{ $comment->body }}</p>
    @auth
        @if(auth()->user()->hasVerifiedEmail() && !auth()->user()->is_blocked)
            <div class="mt-4 flex flex-wrap gap-2">@unless($reply)<x-ui.button variant="ghost" size="sm" @click="replyOpen=!replyOpen" :aria-expanded="replyOpen" aria-controls="reply-{{ $comment->id }}"><x-icon name="message-square" class="h-4 w-4" />{{ __('ui.reply') }}</x-ui.button>@endunless<x-ui.button variant="ghost" size="sm" @click="reportOpen=!reportOpen" :aria-expanded="reportOpen" aria-controls="report-{{ $comment->id }}"><x-icon name="flag" class="h-4 w-4" />{{ __('ui.report') }}</x-ui.button></div>
            @unless($reply)<div id="reply-{{ $comment->id }}" x-cloak x-show="replyOpen" class="mt-5 border-t border-token pt-5"><x-comment-form :project="$project" :parent="$comment" /></div>@endunless
            <form id="report-{{ $comment->id }}" action="{{ route('comments.report', $comment) }}" method="POST" x-cloak x-show="reportOpen" class="mt-5 space-y-4 border-t border-token pt-5">@csrf<x-ui.textarea name="reason" :id="'report-reason-'.$comment->id" :label="__('ui.report_reason')" :placeholder="__('ui.report_placeholder')" rows="2" required minlength="10" maxlength="1000" /><x-ui.button type="submit" variant="outline" size="sm">{{ __('ui.report_send') }}</x-ui.button></form>
        @endif
    @endauth
</article>
