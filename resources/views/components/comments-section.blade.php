@props(['comments', 'project' => null])
<div class="space-y-6">
    @forelse($comments as $comment)
        <x-comment-card :comment="$comment" :project="$project" />
        @foreach($comment->replies as $reply)
            @if($reply->status === 'approved' && $reply->user && !$reply->user->is_blocked)<x-comment-card :comment="$reply" :project="$project" reply />@endif
        @endforeach
    @empty
        <x-ui.empty-state :title="__('ui.comments_empty')" :description="__('ui.comments_empty_body')" icon="message-square" />
    @endforelse
</div>
<x-ui.pagination :paginator="$comments" />
<x-ui.card class="mt-8"><x-comment-form :project="$project" /></x-ui.card>
