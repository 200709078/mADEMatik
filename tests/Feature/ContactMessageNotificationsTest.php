<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\SiteNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactMessageNotificationsTest extends TestCase
{
    private function admin(): User
    {
        $admin = $this->createUser(true, true);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        return $admin;
    }

    private function message(string $status = 'new'): ContactMessage
    {
        return ContactMessage::create([
            'name' => 'Test Ziyaretçisi',
            'email' => 'visitor@example.test',
            'subject' => 'İletişim bildirimi testi',
            'body' => 'Yalnız bellek içi test veritabanındaki iletişim mesajı.',
            'status' => $status,
            'read_at' => $status === 'new' ? null : now(),
        ]);
    }

    private function notification(User $owner, ?ContactMessage $message = null, string $type = 'contact_message'): DatabaseNotification
    {
        $data = [
            'type' => $type,
            'title' => 'Test bildirimi',
            'message' => 'Yalnız test kapsamında oluşturulan bildirim.',
            'url' => $message ? route('admin.contact-messages.show', $message) : route('admin.contact-messages.index'),
        ];
        if ($message) {
            $data['contact_message_id'] = $message->id;
        }

        return $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => SiteNotification::class,
            'data' => $data,
        ]);
    }

    public function test_contact_submission_links_the_admin_notification_to_its_message(): void
    {
        $admin = $this->createUser(true, true);

        $this->post(route('contact.store'), [
            'name' => 'Test Ziyaretçisi',
            'email' => 'visitor@example.test',
            'subject' => 'Yeni iletişim konusu',
            'body' => 'Bildirim bağlantısı doğrulaması için test mesajı.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $message = ContactMessage::sole();
        $notification = $admin->notifications()->sole();
        $this->assertSame('new', $message->status);
        $this->assertSame('contact_message', $notification->data['type']);
        $this->assertSame($message->id, $notification->data['contact_message_id']);
        $this->assertSame(route('admin.contact-messages.show', $message), $notification->data['url']);
        $this->assertNull($notification->read_at);
    }

    public function test_opening_a_message_reads_only_the_current_admins_matching_notification(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->createUser(true, true);
        $message = $this->message();
        $otherMessage = $this->message();
        $own = $this->notification($admin, $message);
        $otherMessageNotification = $this->notification($admin, $otherMessage);
        $otherOwnerNotification = $this->notification($otherAdmin, $message);
        $unrelatedType = $this->notification($admin, $message, 'comment_status');

        $this->get(route('admin.contact-messages.show', $message))->assertOk();

        $this->assertSame('read', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->read_at);
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($otherMessageNotification->fresh()->read_at);
        $this->assertNull($otherOwnerNotification->fresh()->read_at);
        $this->assertNull($unrelatedType->fresh()->read_at);

        // A globally read message must also clear a still-unread notification when reopened.
        $own->forceFill(['read_at' => null])->save();
        $this->get(route('admin.contact-messages.show', $message))->assertOk();
        $this->assertNotNull($own->fresh()->read_at);
    }

    public static function handledStatuses(): array
    {
        return [['read'], ['archived']];
    }

    #[DataProvider('handledStatuses')]
    public function test_marking_a_message_read_or_archived_reads_its_own_notification(string $status): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->createUser(true, true);
        $message = $this->message();
        $own = $this->notification($admin, $message);
        $other = $this->notification($otherAdmin, $message);

        $this->put(route('admin.contact-messages.update', $message), ['status' => $status])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($status, $message->fresh()->status);
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_marking_a_message_new_keeps_unread_notifications_unread(): void
    {
        $admin = $this->admin();
        $message = $this->message('read');
        $own = $this->notification($admin, $message);
        $legacy = $this->notification($admin);

        $this->put(route('admin.contact-messages.update', $message), ['status' => 'new'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('new', $message->fresh()->status);
        $this->assertNull($message->fresh()->read_at);
        $this->assertNull($own->fresh()->read_at);
        $this->assertNull($legacy->fresh()->read_at);
    }

    public function test_deleting_a_message_reads_only_the_current_admins_related_notification(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->createUser(true, true);
        $message = $this->message();
        $otherMessage = $this->message();
        $own = $this->notification($admin, $message);
        $otherOwner = $this->notification($otherAdmin, $message);
        $unrelated = $this->notification($admin, $otherMessage);

        $this->delete(route('admin.contact-messages.destroy', $message))
            ->assertRedirect(route('admin.contact-messages.index'));

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($otherOwner->fresh()->read_at);
        $this->assertNull($unrelated->fresh()->read_at);
    }

    public static function reconciliationPages(): array
    {
        return [['admin_index'], ['admin_show'], ['admin_update'], ['notifications']];
    }

    private function visitReconciliationPage(string $page, ContactMessage $message): void
    {
        match ($page) {
            'admin_index' => $this->get(route('admin.contact-messages.index'))->assertOk(),
            'admin_show' => $this->get(route('admin.contact-messages.show', $message))->assertOk(),
            'admin_update' => $this->put(route('admin.contact-messages.update', $message), ['status' => 'read'])->assertRedirect()->assertSessionHasNoErrors(),
            'notifications' => $this->get(route('notifications.index'))->assertOk(),
        };
    }

    #[DataProvider('reconciliationPages')]
    public function test_legacy_inbox_notifications_are_read_only_when_no_new_message_remains(string $page): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->createUser(true, true);
        $message = $this->message('read');
        $ownLegacy = $this->notification($admin);
        $otherOwnerLegacy = $this->notification($otherAdmin);
        $unrelatedType = $this->notification($admin, null, 'comment_status');
        $linked = $this->notification($admin, $this->message('archived'));

        $this->visitReconciliationPage($page, $message);

        $this->assertNotNull($ownLegacy->fresh()->read_at);
        $this->assertNull($otherOwnerLegacy->fresh()->read_at);
        $this->assertNull($unrelatedType->fresh()->read_at);
        $this->assertNull($linked->fresh()->read_at);
    }

    #[DataProvider('reconciliationPages')]
    public function test_legacy_inbox_notifications_stay_unread_while_another_new_message_exists(string $page): void
    {
        $admin = $this->admin();
        $message = $this->message('read');
        $pending = $this->message();
        $legacy = $this->notification($admin);

        $this->visitReconciliationPage($page, $message);

        $this->assertSame('new', $pending->fresh()->status);
        $this->assertNull($legacy->fresh()->read_at);
    }

    public function test_a_members_notifications_page_does_not_reconcile_admin_contact_notifications(): void
    {
        $member = $this->createUser();
        $legacy = $this->notification($member);
        $this->actingAs($member)->get(route('notifications.index'))->assertOk();

        $this->assertNull($legacy->fresh()->read_at);
    }

    public function test_account_deletion_requests_have_a_message_link_and_are_read_on_open(): void
    {
        $admin = $this->createUser(true, true);
        $member = $this->createUser();
        $this->actingAs($member)->post(route('profile.deletion'), [
            'current_password' => self::USER_PASSWORD,
            'confirm_deletion' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $message = ContactMessage::sole();
        $notification = $admin->notifications()->sole();
        $this->assertSame('account.deletion_requested', $notification->data['type']);
        $this->assertSame($message->id, $notification->data['contact_message_id']);
        $this->assertSame(route('admin.contact-messages.show', $message), $notification->data['url']);
        $this->assertNull($notification->read_at);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('admin.contact-messages.show', $message))->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
