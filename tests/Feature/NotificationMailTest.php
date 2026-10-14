<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Notifications\{SiteMail, SiteNotification};
use App\Providers\AppServiceProvider;
use App\Services\NotificationService;
use Illuminate\Contracts\Mail\{Factory, Mailer};
use Illuminate\Support\Facades\{DB, Log, Notification};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationMailTest extends TestCase
{
    private const ADMIN_ADDRESS = 'admin-notifications@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.admin_notification_address' => self::ADMIN_ADDRESS]);
    }

    public static function mailEnvironments(): array
    {
        return [
            'production without local permission' => ['production', false, 'smtp'],
            'production with local permission' => ['production', true, 'smtp'],
            'local permission absent' => ['local', false, 'log'],
            'local explicitly authorized' => ['local', true, 'smtp'],
            'testing ignores local permission' => ['testing', true, 'log'],
            'qa ignores local permission' => ['qa', true, 'log'],
        ];
    }

    #[DataProvider('mailEnvironments')]
    public function test_real_mail_is_only_enabled_in_production_or_explicitly_authorized_local_environment(string $environment, bool $localPermission, string $expectedMailer): void
    {
        $this->app->detectEnvironment(fn () => $environment);
        config(['mail.default' => 'smtp', 'mail.allow_local_send' => $localPermission]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame($expectedMailer, config('mail.default'));
    }

    public function test_admin_notification_uses_the_configured_inbox_once_instead_of_the_account_email(): void
    {
        Notification::fake();
        $admin = $this->createUser(false, true);
        $admin->notificationPreference()->create(['site_notifications' => false, 'comment_mail' => true]);
        $url = route('admin.comments.index');

        app(NotificationService::class)->send($admin, 'comment_pending', 'Test bildirimi', 'Yalnız test içeriği.', $url, 'comment_mail');

        Notification::assertSentToOnce($admin, SiteNotification::class);
        Notification::assertNotSentTo($admin, SiteMail::class);
        Notification::assertSentOnDemandOnce(SiteMail::class);
        Notification::assertSentOnDemand(SiteMail::class, function (SiteMail $notification, array $channels, object $notifiable) use ($url) {
            return $channels === ['mail']
                && $notifiable->routeNotificationFor('mail') === self::ADMIN_ADDRESS
                && $notification->title === 'Test bildirimi'
                && $notification->url === $url;
        });
        Notification::assertCount(2);
    }

    public function test_one_event_notifies_each_active_admin_but_sends_only_one_shared_inbox_email(): void
    {
        Notification::fake();
        $first = $this->createUser(true, true);
        $second = $this->createUser(false, true);
        $blocked = $this->createUser(true, true);
        $blocked->forceFill(['is_blocked' => true])->save();
        $member = $this->createUser();

        app(NotificationService::class)->superUsers('contact_message', 'Yeni test mesajı', 'Sahte iletişim bildirimi.', route('admin.contact-messages.index'));

        Notification::assertSentToOnce($first, SiteNotification::class);
        Notification::assertSentToOnce($second, SiteNotification::class);
        Notification::assertNotSentTo($first, SiteMail::class);
        Notification::assertNotSentTo($second, SiteMail::class);
        Notification::assertNotSentTo($blocked, SiteNotification::class);
        Notification::assertNotSentTo($blocked, SiteMail::class);
        Notification::assertNotSentTo($member, SiteNotification::class);
        Notification::assertSentOnDemandOnce(SiteMail::class);
        Notification::assertCount(3);
    }

    public function test_a_blocked_admin_does_not_produce_a_bell_or_an_email(): void
    {
        Notification::fake();
        $admin = $this->createUser(true, true);
        $admin->forceFill(['is_blocked' => true])->save();
        $service = app(NotificationService::class);

        $service->send($admin, 'test', 'Test', 'Sahte içerik.', route('profile.edit'), 'comment_mail');
        $service->superUsers('test', 'Test', 'Sahte içerik.', route('admin.comments.index'));

        Notification::assertNothingSent();
    }

    public function test_an_empty_admin_inbox_keeps_the_verified_account_email_fallback(): void
    {
        Notification::fake();
        config(['mail.admin_notification_address' => null]);
        $verified = $this->createUser(true, true);
        $unverified = $this->createUser(false, true);

        app(NotificationService::class)->superUsers('test', 'Test', 'Sahte içerik.', route('admin.comments.index'));

        Notification::assertSentToOnce($verified, SiteNotification::class);
        Notification::assertSentToOnce($unverified, SiteNotification::class);
        Notification::assertSentToOnce($verified, SiteMail::class);
        Notification::assertNotSentTo($unverified, SiteMail::class);
        Notification::assertSentOnDemandTimes(SiteMail::class, 0);
        Notification::assertCount(3);
    }

    public function test_invalid_admin_inbox_does_not_send_or_fall_back_to_another_address(): void
    {
        Notification::fake();
        Log::spy();
        config(['mail.admin_notification_address' => 'not-an-email']);
        $admin = $this->createUser(true, true);

        app(NotificationService::class)->send($admin, 'test', 'Test', 'Sahte içerik.', route('admin.comments.index'));

        Notification::assertSentToOnce($admin, SiteNotification::class);
        Notification::assertNotSentTo($admin, SiteMail::class);
        Notification::assertSentOnDemandTimes(SiteMail::class, 0);
        Notification::assertCount(1);
        Log::shouldHaveReceived('error')->once()->with('Yönetici bildirim e-posta adresi geçersiz.');
    }

    public function test_member_private_notifications_and_optional_mail_are_not_copied_to_the_admin_inbox(): void
    {
        Notification::fake();
        $member = $this->createUser();
        $member->notificationPreference()->create(['site_notifications' => true, 'comment_mail' => false]);
        $service = app(NotificationService::class);

        $service->send($member, 'comment_status', 'Özel üye bildirimi', 'Yalnız bu üyeye ait içerik.', route('profile.edit'), 'comment_mail');

        Notification::assertSentToOnce($member, SiteNotification::class);
        Notification::assertNotSentTo($member, SiteMail::class);
        Notification::assertSentOnDemandTimes(SiteMail::class, 0);

        $member->notificationPreference()->update(['site_notifications' => false, 'comment_mail' => true]);
        $member->unsetRelation('notificationPreference');
        $service->send($member, 'comment_status', 'Tercihli e-posta', 'Yalnız üyeye gönderilecek içerik.', route('profile.edit'), 'comment_mail');

        Notification::assertSentToOnce($member, SiteMail::class);
        Notification::assertSentToOnce($member, SiteNotification::class);
        Notification::assertSentOnDemandTimes(SiteMail::class, 0);
        Notification::assertCount(2);
    }

    public function test_optional_member_mail_requires_verified_email_even_when_the_preference_is_enabled(): void
    {
        Notification::fake();
        $member = $this->createUser(false);
        $member->notificationPreference()->create(['site_notifications' => true, 'reply_mail' => true]);

        app(NotificationService::class)->send($member, 'comment_reply', 'Yanıt bildirimi', 'Sahte yanıt içeriği.', route('profile.edit'), 'reply_mail');

        Notification::assertSentToOnce($member, SiteNotification::class);
        Notification::assertNotSentTo($member, SiteMail::class);
        Notification::assertSentOnDemandTimes(SiteMail::class, 0);
        Notification::assertCount(1);
    }

    public function test_notification_mail_is_deferred_until_a_successful_commit(): void
    {
        Notification::fake();
        $admin = $this->createUser(true, true);
        DB::beginTransaction();

        app(NotificationService::class)->superUsers('test', 'Test', 'Sahte içerik.', route('admin.comments.index'));

        Notification::assertSentToOnce($admin, SiteNotification::class);
        Notification::assertSentOnDemandTimes(SiteMail::class, 0);
        DB::commit();
        Notification::assertSentOnDemandOnce(SiteMail::class);
    }

    public function test_rolled_back_transaction_does_not_send_notification_mail(): void
    {
        Notification::fake();
        $this->createUser(true, true);
        DB::beginTransaction();

        app(NotificationService::class)->superUsers('test', 'Test', 'Sahte içerik.', route('admin.comments.index'));
        DB::rollBack();

        Notification::assertSentOnDemandTimes(SiteMail::class, 0);
    }

    public function test_smtp_failure_after_commit_preserves_contact_message_and_bell_notification(): void
    {
        $admin = $this->createUser(true, true);
        $mailer = \Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('FAKE_SMTP_ERROR_MUST_NOT_LEAK'));
        $factory = \Mockery::mock(Factory::class);
        $factory->shouldReceive('mailer')->with(null)->andReturn($mailer);
        $this->app->instance(Factory::class, $factory);
        Log::spy();

        $this->post(route('contact.store'), [
            'name' => 'Test Ziyaretçisi',
            'email' => 'visitor@example.test',
            'subject' => 'Sahte SMTP hata testi',
            'body' => 'Gerçek SMTP kullanılmadan oluşturulan iletişim mesajı.',
        ])->assertRedirect()->assertSessionHas('status')->assertSessionHasNoErrors();

        $message = ContactMessage::sole();
        $notification = $admin->notifications()->sole();
        $this->assertSame('new', $message->status);
        $this->assertSame('contact_message', $notification->data['type']);
        $this->assertSame($message->id, $notification->data['contact_message_id']);
        $this->assertNull($notification->read_at);
        Log::shouldHaveReceived('error')->once()->with('E-posta bildirimi gönderilemedi.', ['exception_type' => \RuntimeException::class]);
    }
}
