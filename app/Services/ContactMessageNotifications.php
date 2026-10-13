<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessageNotifications
{
    public function markAsRead(User $user, ContactMessage $message): void
    {
        if (! $user->isSuperUser()) {
            return;
        }

        $user->unreadNotifications()
            ->whereIn('data->type', ['contact_message', 'account.deletion_requested'])
            ->where('data->contact_message_id', $message->getKey())
            ->update(['read_at' => now()]);

        $this->syncLegacy($user);
    }

    public function syncLegacy(User $user): void
    {
        if (! $user->isSuperUser() || ContactMessage::where('status', 'new')->exists()) {
            return;
        }

        // Older alerts identify the inbox, not an individual message. Clear them
        // only once the inbox has no unread messages; never guess a message ID.
        $user->unreadNotifications()
            ->where('data->type', 'contact_message')
            ->whereNull('data->contact_message_id')
            ->update(['read_at' => now()]);
    }
}
