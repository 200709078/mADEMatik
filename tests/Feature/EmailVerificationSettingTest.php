<?php

namespace Tests\Feature;

use App\Models\{Comment, Project, User};
use App\Notifications\{SiteMail, SiteNotification};
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\{ResetPassword, VerifyEmail};
use Illuminate\Support\Facades\{Event, Notification, Password, URL};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailVerificationSettingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.email_verification_enabled' => false, 'mail.admin_notification_address' => null]);
        Notification::fake();
    }

    private function registration(array $changes = []): array
    {
        return array_replace([
            'name' => 'Yeni Deneme Üyesi',
            'email' => 'new-member@example.test',
            'email_confirmation' => 'new-member@example.test',
            'password' => self::USER_PASSWORD,
            'password_confirmation' => self::USER_PASSWORD,
            'terms' => '1',
            'website' => '',
        ], $changes);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    private function approvedComment(User $user): Comment
    {
        $comment = $user->comments()->create(['body' => 'Herkese açık, onaylanmış deneme yorumu.']);
        $comment->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
        return $comment;
    }

    public function test_paused_registration_accepts_matching_case_insensitive_emails_without_falsely_verifying_the_account(): void
    {
        $this->post(route('register'), $this->registration([
            'email' => 'New-Member@Example.Test',
            'email_confirmation' => 'NEW-MEMBER@example.test',
            'email_verified_at' => now()->toDateTimeString(),
            'role' => 'super_user',
        ]))->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

        $user = User::where('email', 'new-member@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('member', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue($user->meetsEmailVerificationRequirement());
        $this->assertFalse($user->notificationPreference->project_mail);
        Notification::assertNothingSent();
    }

    public static function invalidEmailConfirmations(): array
    {
        return [
            'paused missing confirmation' => [false, null],
            'enabled missing confirmation' => [true, null],
            'paused mismatched confirmation' => [false, 'different@example.test'],
            'enabled mismatched confirmation' => [true, 'different@example.test'],
            'paused malformed confirmation' => [false, ['unexpected' => 'array']],
            'enabled malformed confirmation' => [true, ['unexpected' => 'array']],
        ];
    }

    #[DataProvider('invalidEmailConfirmations')]
    public function test_email_confirmation_is_required_and_must_match_in_both_modes(bool $enabled, mixed $confirmation): void
    {
        config(['auth.email_verification_enabled' => $enabled]);
        $data = $this->registration(['email_confirmation' => $confirmation]);
        if ($confirmation === null) {
            unset($data['email_confirmation']);
        }

        $this->post(route('register'), $data)->assertSessionHasErrors('email_confirmation');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_paused_registration_still_rejects_invalid_and_duplicate_email_addresses(): void
    {
        $this->post(route('register'), $this->registration([
            'email' => 'not-an-email', 'email_confirmation' => 'not-an-email',
        ]))->assertSessionHasErrors('email');
        $existing = $this->createUser();
        $this->post(route('register'), $this->registration([
            'email' => strtoupper($existing->email), 'email_confirmation' => $existing->email,
        ]))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    public function test_enabled_registration_still_sends_verification_and_redirects_to_the_notice(): void
    {
        config(['auth.email_verification_enabled' => true]);

        $this->post(route('register'), $this->registration())->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'new-member@example.test')->sole();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->meetsEmailVerificationRequirement());
        Notification::assertSentToOnce($user, VerifyEmail::class);
    }

    public function test_registration_and_guest_comment_copy_do_not_promise_verification_when_paused(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('name="email_confirmation"', false);
        $this->get(route('comments.index'))->assertOk()->assertDontSee('giriş yapın ve e-posta adresinizi doğrulayın');
    }

    public function test_notice_resend_and_registered_event_do_not_send_or_write_verification_when_paused(): void
    {
        $user = $this->createUser(false);

        $this->actingAs($user)->get(route('verification.notice'))->assertRedirect(route('profile.edit'));
        $this->post(route('verification.send'))->assertRedirect(route('profile.edit'));
        $user->sendEmailVerificationNotification();
        Event::dispatch(new Registered($user));

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_signed_verification_link_is_inactive_until_the_setting_is_enabled(): void
    {
        $user = $this->createUser(false);
        $url = $this->verificationUrl($user);

        $this->actingAs($user)->get($url)->assertRedirect(route('profile.edit'));
        $this->assertNull($user->fresh()->email_verified_at);
        config(['auth.email_verification_enabled' => true]);
        $this->get($url)->assertRedirect(route('profile.edit'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_paused_verification_links_keep_signature_and_account_ownership_checks(): void
    {
        $owner = $this->createUser(false);
        $other = $this->createUser(false);
        $url = $this->verificationUrl($owner);

        $this->actingAs($owner)->get($url.'&tampered=1')->assertForbidden();
        $this->actingAs($other)->get($url)->assertForbidden();

        $this->assertNull($owner->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_paused_profile_email_change_revokes_verification_without_sending_an_email(): void
    {
        $user = $this->createUser();
        $user->forceFill(['remember_token' => 'previous-token'])->save();
        $oldEmail = $user->email;
        Password::createToken($user);

        $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
            'name' => 'Yeni Üye Adı', 'email' => 'Changed@Example.Test', 'current_password' => self::USER_PASSWORD,
        ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('changed@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame('previous-token', $user->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $this->get(route('profile.edit'))->assertOk()->assertDontSee('E-posta adresiniz doğrulanmadı.');
        Notification::assertNothingSent();
    }

    public function test_paused_profile_changes_keep_password_checks_and_existing_verified_state(): void
    {
        $user = $this->createUser();
        $verifiedAt = $user->email_verified_at->toDateTimeString();
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Değişmesin', 'email' => 'change@example.test', 'current_password' => 'incorrect',
        ])->assertSessionHasErrors('current_password');
        $this->assertNotSame('change@example.test', $user->fresh()->email);
        $this->patch(route('profile.update'), [
            'name' => 'Yeni Ad Soyad', 'email' => $user->email, 'current_password' => self::USER_PASSWORD,
        ])->assertSessionHasNoErrors();
        $this->assertSame($verifiedAt, $user->fresh()->email_verified_at->toDateTimeString());
        Notification::assertNothingSent();
    }

    public function test_unverified_members_can_submit_edit_reply_and_report_while_all_new_content_stays_pending(): void
    {
        $member = $this->createUser(false);
        $parent = $this->approvedComment($this->createUser());
        $this->actingAs($member)->post(route('comments.store'), [
            'body' => 'E-posta doğrulaması askıdayken gönderilen yeni yorum.', 'status' => 'approved',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $own = $member->comments()->sole();
        $this->assertSame('pending', $own->status);
        $own->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
        $this->get(route('comments.edit', $own))->assertOk();
        $this->patch(route('comments.update', $own), ['body' => 'Onaylanmış yorum yeniden düzenlendi.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $own->fresh()->status);
        $this->assertNull($own->fresh()->approved_at);
        $this->post(route('comments.store'), [
            'body' => 'Onaylanmış yoruma yazılan yeni yanıt.', 'parent_id' => $parent->id, 'status' => 'approved',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('comments', ['user_id' => $member->id, 'parent_id' => $parent->id, 'status' => 'pending']);
        $this->post(route('comments.report', $parent), ['reason' => 'İçeriğin incelenmesi için deneme bildirimi.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('comment_reports', ['user_id' => $member->id, 'comment_id' => $parent->id]);
        $this->assertNull($member->fresh()->email_verified_at);
    }

    public function test_paused_comment_forms_and_reply_report_controls_match_server_permissions(): void
    {
        $member = $this->createUser(false);
        $comment = $this->approvedComment($this->createUser());
        $this->actingAs($member)->get(route('comments.index'))->assertOk()
            ->assertSee('action="'.route('comments.store').'"', false)
            ->assertSee('id="reply-'.$comment->id.'"', false)
            ->assertSee('id="report-'.$comment->id.'"', false)
            ->assertDontSee(__('ui.comment_verify'));
        $this->get(route('profile.edit'))->assertOk()->assertDontSee('E-posta adresiniz doğrulanmadı.');

        config(['auth.email_verification_enabled' => true]);
        $this->get(route('comments.index'))->assertOk()->assertSee(__('ui.comment_verify'))
            ->assertDontSee('id="reply-'.$comment->id.'"', false)
            ->assertDontSee('id="report-'.$comment->id.'"', false);
        $this->get(route('profile.edit'))->assertOk()->assertSee('E-posta adresiniz doğrulanmadı.');
    }

    public function test_reenabling_verification_requires_existing_unverified_members_to_verify_again(): void
    {
        $user = $this->createUser(false);
        $comment = $user->comments()->create(['body' => 'Daha önce oluşturulmuş bekleyen yorum.']);
        $this->actingAs($user)->get(route('comments.edit', $comment))->assertOk();

        config(['auth.email_verification_enabled' => true]);
        $this->get(route('comments.edit', $comment))->assertRedirect(route('verification.notice'));
        $this->post(route('comments.store'), ['body' => 'Artık doğrulama isteyen yeni yorum.'])
            ->assertRedirect(route('verification.notice'));
        $this->postJson(route('comments.store'), ['body' => 'JSON üzerinden doğrulama isteyen yorum.'])->assertForbidden();
        $this->get(route('verification.notice'))->assertOk();
        $this->post(route('verification.send'))->assertRedirect();

        $this->assertDatabaseCount('comments', 1);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->meetsEmailVerificationRequirement());
        Notification::assertSentToOnce($user, VerifyEmail::class);
    }

    public function test_paused_verification_keeps_admin_roles_password_confirmation_and_blocked_account_rules(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->createUser(false))->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('admin.dashboard'))->assertForbidden();

        $admin = $this->createUser(false, true);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => 0]);
        $this->get(route('admin.dashboard'))->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.dashboard'))->assertOk();
        config(['auth.email_verification_enabled' => true]);
        $this->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
        config(['auth.email_verification_enabled' => false]);
        $admin->forceFill(['is_blocked' => true])->save();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->assertGuest();

        $blocked = $this->createUser(false);
        $blocked->forceFill(['is_blocked' => true])->save();
        $this->actingAs($blocked)->post(route('comments.store'), ['body' => 'Engellenmiş hesabın yorum gönderme denemesi.'])->assertForbidden();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_password_reset_emails_continue_for_unverified_users_when_verification_is_paused(): void
    {
        $user = $this->createUser(false);

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHas('status');

        Notification::assertSentToOnce($user, ResetPassword::class);
        Notification::assertNotSentTo($user, VerifyEmail::class);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_optional_member_mail_respects_preferences_and_reenabling_verification(): void
    {
        $user = $this->createUser(false);
        $preference = $user->notificationPreference()->create(['site_notifications' => true, 'reply_mail' => false]);
        $service = app(NotificationService::class);
        $service->send($user, 'comment_reply', 'Deneme yanıtı', 'Yalnız test içeriği.', route('profile.edit'), 'reply_mail');
        Notification::assertSentToOnce($user, SiteNotification::class);
        Notification::assertNotSentTo($user, SiteMail::class);

        $preference->update(['reply_mail' => true]);
        $user->unsetRelation('notificationPreference');
        $service->send($user, 'comment_reply', 'İzinli yanıt', 'Yalnız test içeriği.', route('profile.edit'), 'reply_mail');
        Notification::assertSentToOnce($user, SiteMail::class);
        config(['auth.email_verification_enabled' => true]);
        $service->send($user, 'comment_reply', 'Doğrulama isteyen yanıt', 'Yalnız test içeriği.', route('profile.edit'), 'reply_mail');
        Notification::assertSentToTimes($user, SiteMail::class, 1);
        Notification::assertSentToTimes($user, SiteNotification::class, 3);
        Notification::assertSentOnDemandTimes(SiteMail::class, 0);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public static function verificationModes(): array
    {
        return ['paused' => [false], 'enabled' => [true]];
    }

    #[DataProvider('verificationModes')]
    public function test_project_announcements_respect_verification_setting_preferences_and_blocked_accounts(bool $enabled): void
    {
        config(['auth.email_verification_enabled' => $enabled]);
        $admin = $this->createUser(true, true);
        $verified = $this->createUser();
        $verified->notificationPreference()->create(['project_mail' => true]);
        $unverified = $this->createUser(false);
        $unverified->notificationPreference()->create(['project_mail' => true]);
        $optedOut = $this->createUser(false);
        $optedOut->notificationPreference()->create(['project_mail' => false]);
        $blocked = $this->createUser();
        $blocked->forceFill(['is_blocked' => true])->save();
        $blocked->notificationPreference()->create(['project_mail' => true]);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.projects.store'), [
                'title' => 'Yeni Deneme Projesi', 'slug' => 'yeni-deneme-projesi',
                'summary' => 'Sahte proje açıklaması.', 'status' => 'live', 'sort_order' => 0,
                'is_published' => '1', 'announce' => '1',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Project::count());
        Notification::assertSentToOnce($verified, SiteMail::class);
        Notification::assertSentToOnce($verified, SiteNotification::class);
        if ($enabled) {
            Notification::assertNotSentTo($unverified, SiteMail::class);
            Notification::assertNotSentTo($unverified, SiteNotification::class);
        } else {
            Notification::assertSentToOnce($unverified, SiteMail::class);
            Notification::assertSentToOnce($unverified, SiteNotification::class);
        }
        foreach ([$optedOut, $blocked, $admin] as $excluded) {
            Notification::assertNotSentTo($excluded, SiteMail::class);
            Notification::assertNotSentTo($excluded, SiteNotification::class);
        }
        Notification::assertSentOnDemandTimes(SiteMail::class, 0);
        $this->assertNull($unverified->fresh()->email_verified_at);
    }
}
