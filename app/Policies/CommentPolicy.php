<?php
namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function create(User $user): bool { return ! $user->is_blocked && $user->hasVerifiedEmail(); }
    public function update(User $user, Comment $comment): bool { return ! $user->is_blocked && $user->hasVerifiedEmail() && $comment->user_id === $user->id; }
    public function delete(User $user, Comment $comment): bool { return ! $user->is_blocked && ($user->isSuperUser() || $comment->user_id === $user->id); }
    public function moderate(User $user, Comment $comment): bool { return ! $user->is_blocked && $user->isSuperUser(); }
}
