<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SiteNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthAndAccountTest extends TestCase
{
    private const PASSWORD = 'ExamplePassword123!';
    private const NEW_PASSWORD = 'Aa1!bc';

    private function account(bool $verified = true, bool $superUser = false): User
    {
        $user = $this->createUser($verified, $superUser);
        $user->forceFill(['password' => Hash::make(self::PASSWORD), 'remember_token' => 'previous-remember-token'])->save();
        return $user;
    }

    private function registration(array $overrides = []): array
    {
        $data = array_replace(['name' => 'Deneme Üyesi', 'email' => 'new-member@example.test', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD, 'terms' => '1', 'website' => ''], $overrides);
        return array_replace(['email_confirmation' => $data['email']], $data);
    }

    private function verificationUrl(User $user, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);
    }

    private function sessionRecord(User $user, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'PHPUnit fixture', 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
    }

    public function test_authentication_pages_show_turkish_labeled_forms(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Giriş Yap')->assertSee('E-Posta Adresi')->assertSee('autocomplete="current-password"', false);
        $this->get(route('register'))->assertOk()->assertSee('Kayıt Ol')->assertSee('En az 6 karakter')->assertSee('sembol')->assertSee('minlength="6"', false)->assertDontSee('minlength="12"', false)->assertSee('name="terms"', false);
        $this->get(route('password.request'))->assertOk()->assertSee('Sıfırlama Bağlantısı Gönder');
        $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'new-member@example.test']))->assertOk()->assertSee('Yeni parola')->assertSee('En az 6 karakter')->assertSee('minlength="6"', false)->assertDontSee('minlength="12"', false);
    }

    public function test_registration_normalizes_email_sends_verification_and_never_accepts_privileged_fields(): void
    {
        Notification::fake();
        $this->withSession(['before_registration' => true]);
        $before = $this->app['session']->getId();
        $this->post(route('register'), $this->registration(['email' => 'New-Member@Example.Test', 'role' => 'super_user', 'is_blocked' => false, 'email_verified_at' => now()->toDateTimeString(), 'locale' => 'en']))->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'new-member@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('member', $user->role);
        $this->assertSame('tr', $user->locale);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->is_blocked);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertNotSame($before, $this->app['session']->getId());
        $this->assertFalse($user->notificationPreference->comment_mail);
        $this->assertFalse($user->notificationPreference->reply_mail);
        $this->assertFalse($user->notificationPreference->project_mail);
        $this->assertTrue($user->notificationPreference->site_notifications);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public static function rejectedRegistrations(): array
    {
        return [
            'five character password' => [['password' => 'Aa1!b', 'password_confirmation' => 'Aa1!b'], 'password'],
            'password without symbol' => [['password' => 'ExamplePassword123', 'password_confirmation' => 'ExamplePassword123'], 'password'],
            'password without mixed case' => [['password' => 'examplepassword123!', 'password_confirmation' => 'examplepassword123!'], 'password'],
            'missing consent' => [['terms' => '0'], 'terms'],
            'honeypot submission' => [['website' => 'spam.example.test'], 'website'],
            'password confirmation mismatch' => [['password_confirmation' => 'WrongPassword123!'], 'password'],
        ];
    }

    #[DataProvider('rejectedRegistrations')]
    public function test_registration_rejects_weak_passwords_missing_consent_and_spam(array $override, string $field): void
    {
        Notification::fake();
        $this->post(route('register'), $this->registration($override))->assertSessionHasErrors($field);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_registration_rejects_a_duplicate_email_regardless_of_letter_case(): void
    {
        Notification::fake();
        $user = $this->account();
        $hash = $user->password;
        $this->post(route('register'), $this->registration(['email' => strtoupper($user->email)]))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, $user->fresh()->password);
        Notification::assertNothingSent();
    }

    public function test_login_accepts_case_insensitive_email_and_regenerates_session(): void
    {
        $user = $this->account();
        $this->withSession(['url.intended' => route('notifications.index')]);
        $before = $this->app['session']->getId();
        $this->post(route('login'), ['email' => strtoupper($user->email), 'password' => self::PASSWORD, 'remember' => '1'])->assertRedirect(route('notifications.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, $this->app['session']->getId());
    }

    public function test_blocked_users_and_wrong_passwords_receive_the_same_login_error(): void
    {
        $user = $this->account();
        $wrong = $this->post(route('login'), ['email' => $user->email, 'password' => 'incorrect']);
        $wrong->assertSessionHasErrors('email');
        $firstError = $this->app['session']->get('errors')->first('email');
        $user->forceFill(['is_blocked' => true])->save();
        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertSame($firstError, $this->app['session']->get('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_repeated_failed_login_attempts_temporarily_block_a_correct_password(): void
    {
        $user = $this->account();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        }
        $this->post(route('login'), ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Çok fazla giriş denemesi', $this->app['session']->get('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_blocking_an_existing_account_terminates_authenticated_access(): void
    {
        $user = $this->account();
        $this->actingAs($user);
        $user->forceFill(['is_blocked' => true])->save();
        $this->get(route('profile.edit'))->assertForbidden();
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $user = $this->account();
        $this->actingAs($user)->withSession(['private_marker' => 'remove-me', '_token' => 'old-csrf-token']);
        $this->post(route('logout'))->assertRedirect(route('home'))->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->assertNotSame('old-csrf-token', $this->app['session']->token());
    }

    public function test_account_and_notification_routes_require_authentication(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->put(route('profile.preferences'), ['project_mail' => true])->assertRedirect(route('login'));
        $this->patch(route('notifications.read-all'))->assertRedirect(route('login'));
        $this->post(route('profile.deletion'), [])->assertRedirect(route('login'));
    }

    public function test_a_valid_signed_verification_link_verifies_its_authenticated_owner(): void
    {
        $user = $this->account(false);
        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect(route('profile.edit'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_links_cannot_verify_another_authenticated_user(): void
    {
        $owner = $this->account(false);
        $other = $this->account(false);
        $this->actingAs($other)->get($this->verificationUrl($owner))->assertForbidden();
        $this->assertNull($owner->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_expired_modified_or_wrong_hash_verification_links_are_rejected(): void
    {
        $user = $this->account(false);
        $this->actingAs($user)->get($this->verificationUrl($user, -1))->assertForbidden();
        $this->get($this->verificationUrl($user).'&tampered=1')->assertForbidden();
        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1('different@example.test')]);
        $this->get($wrongHash)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_resend_only_sends_to_unverified_users_and_is_throttled(): void
    {
        Notification::fake();
        $user = $this->account(false);
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('verification.send'))->assertRedirect();
        }
        $this->post(route('verification.send'))->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_verified_members_do_not_receive_a_new_verification_email(): void
    {
        Notification::fake();
        $this->actingAs($this->account())->post(route('verification.send'))->assertRedirect(route('profile.edit'));
        Notification::assertNothingSent();
    }

    public function test_password_confirmation_requires_the_current_password(): void
    {
        $this->actingAs($this->account())->withSession(['url.intended' => route('profile.edit')]);
        $this->post(route('password.confirm'), ['password' => 'incorrect'])->assertSessionHasErrors('password')->assertSessionMissing('auth.password_confirmed_at');
        $this->post(route('password.confirm'), ['password' => self::PASSWORD])->assertRedirect(route('profile.edit'))->assertSessionHas('auth.password_confirmed_at');
    }

    public function test_reset_link_response_does_not_disclose_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = $this->account();
        $this->post(route('password.email'), ['email' => strtoupper($user->email)])->assertRedirect()->assertSessionHas('status');
        $knownStatus = $this->app['session']->get('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertRedirect()->assertSessionHas('status', $knownStatus);
        Notification::assertCount(1);
    }

    public function test_password_reset_replaces_the_password_invalidates_sessions_and_consumes_the_token(): void
    {
        $user = $this->account();
        $token = Password::createToken($user);
        $this->sessionRecord($user, 'other-device-before-reset');
        $unrelatedUser = $this->account();
        $this->sessionRecord($unrelatedUser, 'unrelated-device-before-reset');
        $this->post(route('password.store'), ['token' => $token, 'email' => strtoupper($user->email), 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertRedirect(route('login'));
        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame('previous-remember-token', $user->remember_token);
        $this->assertFalse(Password::tokenExists($user, $token));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-device-before-reset', 'user_id' => $unrelatedUser->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.password_reset', 'subject_id' => $user->id]);
        $this->post(route('password.store'), ['token' => $token, 'email' => $user->email, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_invalid_reset_tokens_and_weak_new_passwords_do_not_change_the_account(): void
    {
        $user = $this->account();
        $oldHash = $user->password;
        $this->post(route('password.store'), ['token' => 'invalid', 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertSessionHasErrors('email');
        $token = Password::createToken($user);
        $this->post(route('password.store'), ['token' => $token, 'email' => $user->email, 'password' => 'Aa1!b', 'password_confirmation' => 'Aa1!b'])->assertSessionHasErrors('password');
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_malformed_reset_tokens_are_rejected_as_validation_errors_without_changing_password(): void
    {
        $user = $this->account();
        $oldHash = $user->password;
        Password::createToken($user);
        $this->post(route('password.store'), ['token' => ['unexpected' => 'array'], 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertSessionHasErrors('token');
        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_profile_password_change_rejects_a_weak_password_even_with_the_correct_current_password(): void
    {
        $user = $this->account();
        $oldHash = $user->password;
        $this->actingAs($user)->put(route('profile.password'), ['current_password' => self::PASSWORD, 'password' => 'Aa1!b', 'password_confirmation' => 'Aa1!b'])->assertSessionHasErrors('password');
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertSame('previous-remember-token', $user->fresh()->remember_token);
    }

    public function test_array_shaped_account_fields_produce_validation_errors_instead_of_server_errors(): void
    {
        Notification::fake();
        $this->post(route('register'), $this->registration(['email' => ['unexpected' => 'array']]))->assertSessionHasErrors('email');
        $user = $this->account();
        $this->post(route('password.store'), ['token' => 'invalid-token', 'email' => ['unexpected' => 'array'], 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertSessionHasErrors('email');
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Güvenli Ad', 'email' => ['unexpected' => 'array'], 'current_password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->patch(route('profile.update'), ['name' => 'Güvenli Ad', 'email' => $user->email, 'current_password' => ['unexpected' => 'array']])->assertSessionHasErrors('current_password');
        $this->assertNotSame('Güvenli Ad', $user->fresh()->name);
        Notification::assertNothingSent();
    }

    public function test_array_shaped_reset_page_email_does_not_cause_a_server_error(): void
    {
        $this->get(route('password.reset', ['token' => 'invalid-token', 'email' => ['unexpected' => 'array']]))->assertOk();
    }

    public function test_changing_email_requires_reverification_and_invalidates_old_email_reset_tokens(): void
    {
        Notification::fake();
        $user = $this->account();
        $oldEmail = $user->email;
        Password::createToken($user);
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Yeni Ad', 'email' => 'Changed-Email@Example.Test', 'current_password' => self::PASSWORD])->assertRedirect(route('verification.notice'));
        $user->refresh();
        $this->assertSame('changed-email@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Yeni Ad', $user->name);
        $this->assertNotSame('previous-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_changing_only_the_name_preserves_email_verification_and_does_not_send_mail(): void
    {
        Notification::fake();
        $user = $this->account();
        $verifiedAt = $user->email_verified_at->toDateTimeString();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Düzenlenmiş Ad', 'email' => strtoupper($user->email), 'current_password' => self::PASSWORD, 'role' => 'super_user'])->assertRedirect();
        $this->assertSame($verifiedAt, $user->fresh()->email_verified_at->toDateTimeString());
        $this->assertSame('member', $user->fresh()->role);
        Notification::assertNothingSent();
    }

    public function test_profile_rejects_wrong_password_and_another_members_email(): void
    {
        Notification::fake();
        $user = $this->account();
        $other = $this->account();
        $oldName = $user->name;
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Değişmesin', 'email' => $user->email, 'current_password' => 'incorrect'])->assertSessionHasErrors('current_password');
        $this->patch(route('profile.update'), ['name' => 'Değişmesin', 'email' => strtoupper($other->email), 'current_password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertSame($oldName, $user->fresh()->name);
        $this->assertNotSame($other->email, $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_preferences_start_with_optional_mail_disabled_and_can_be_opted_in_and_out(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Bildirim tercihlerim')->assertSee('En az 6 karakter')->assertSee('minlength="6"', false)->assertDontSee('minlength="12"', false);
        $preference = $user->notificationPreference()->sole();
        $this->assertFalse($preference->comment_mail);
        $this->assertFalse($preference->reply_mail);
        $this->assertFalse($preference->project_mail);
        $this->assertTrue($preference->site_notifications);
        $this->put(route('profile.preferences'), ['comment_mail' => '1', 'reply_mail' => '1', 'project_mail' => '1', 'site_notifications' => '1'])->assertRedirect();
        $this->assertTrue($preference->fresh()->project_mail);
        $this->assertTrue($preference->fresh()->comment_mail);
        $this->put(route('profile.preferences'), [])->assertRedirect();
        $this->assertFalse($preference->fresh()->comment_mail);
        $this->assertFalse($preference->fresh()->reply_mail);
        $this->assertFalse($preference->fresh()->project_mail);
        $this->assertFalse($preference->fresh()->site_notifications);
    }

    public function test_invalid_notification_preferences_do_not_modify_existing_opt_ins(): void
    {
        $user = $this->account();
        $preference = $user->notificationPreference()->updateOrCreate([], ['project_mail' => true]);
        $this->actingAs($user)->put(route('profile.preferences'), ['project_mail' => 'untrusted'])->assertSessionHasErrors('project_mail');
        $this->assertTrue($preference->fresh()->project_mail);
    }

    public function test_notifications_are_scoped_to_their_owner_in_lists_and_mark_read_actions(): void
    {
        $owner = $this->account();
        $other = $this->account();
        $owner->notify(new SiteNotification('test', 'Bana ait bildirim', 'Benim mesajım', route('profile.edit')));
        $other->notify(new SiteNotification('test', 'Başkasının gizli bildirimi', 'Diğer mesaj', route('profile.edit')));
        $own = $owner->notifications()->sole();
        $foreign = $other->notifications()->sole();
        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee('Bana ait bildirim')->assertDontSee('Başkasının gizli bildirimi');
        $this->patch(route('notifications.read', $foreign->id))->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);
        $this->patch(route('notifications.read', $own->id))->assertRedirect();
        $this->assertNotNull($own->fresh()->read_at);
        $owner->notify(new SiteNotification('test', 'İkinci bildirim', 'İkinci mesaj', route('profile.edit')));
        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $owner->unreadNotifications()->count());
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_password_change_requires_current_password_revokes_other_sessions_and_records_an_audit_entry(): void
    {
        $user = $this->account();
        $oldHash = $user->password;
        $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'incorrect', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertSessionHasErrors('current_password');
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->sessionRecord($user, 'other-device-before-change');
        $unrelatedUser = $this->account();
        $this->sessionRecord($unrelatedUser, 'unrelated-device-before-change');
        $this->put(route('profile.password'), ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertNotSame('previous-remember-token', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-before-change']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-device-before-change', 'user_id' => $unrelatedUser->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.password_changed', 'subject_id' => $user->id]);
    }

    public function test_account_deletion_request_is_idempotent_preserves_the_account_and_notifies_only_administrators(): void
    {
        $user = $this->account();
        $admin = $this->account(true, true);
        $other = $this->account();
        $this->actingAs($user)->post(route('profile.deletion'), ['current_password' => self::PASSWORD, 'confirm_deletion' => '1'])->assertRedirect()->assertSessionHas('status');
        $this->assertNotNull($user->fresh()->deletion_requested_at);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.deletion_requested', 'subject_id' => $user->id]);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->post(route('profile.deletion'), ['current_password' => self::PASSWORD, 'confirm_deletion' => '1'])->assertRedirect();
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_account_deletion_request_requires_password_and_explicit_consent(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post(route('profile.deletion'), ['current_password' => 'incorrect', 'confirm_deletion' => '1'])->assertSessionHasErrors('current_password');
        $this->post(route('profile.deletion'), ['current_password' => self::PASSWORD, 'confirm_deletion' => '0'])->assertSessionHasErrors('confirm_deletion');
        $this->assertNull($user->fresh()->deletion_requested_at);
        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }
    private function runSuperUserCommand(bool $interactive,bool $confirm,string $password=self::USER_PASSWORD): array
    {
        $command=\Mockery::mock(\App\Console\Commands\CreateSuperUser::class)->makePartial();
        $command->__construct();
        $command->setLaravel($this->app);
        if ($interactive) {
            $command->shouldReceive('ask')->twice()->andReturn('Test CLI Yöneticisi','cli-admin@example.test');
            $command->shouldReceive('secret')->twice()->andReturn($password,$password);
            if ($password===self::USER_PASSWORD) $command->shouldReceive('confirm')->once()->andReturn($confirm);
        } else {
            $command->shouldReceive('ask')->never(); $command->shouldReceive('secret')->never();
        }
        $input=new \Symfony\Component\Console\Input\ArrayInput([]);$input->setInteractive($interactive);
        $output=new \Symfony\Component\Console\Output\BufferedOutput;
        return [$command->run($input,$output),$output->fetch()];
    }
    public function test_super_user_command_refuses_noninteractive_automatic_account_creation(): void
    {
        [$code,$output]=$this->runSuperUserCommand(false,false);
        $this->assertSame(1,$code); $this->assertStringContainsString('etkileşimli',$output);
        $this->assertDatabaseCount('users',0);
    }
    public function test_super_user_command_requires_strong_password_and_explicit_confirmation(): void
    {
        [$code]=$this->runSuperUserCommand(true,true,'abc');
        $this->assertSame(1,$code); $this->assertDatabaseCount('users',0);
        [$code]=$this->runSuperUserCommand(true,false);
        $this->assertSame(1,$code); $this->assertDatabaseCount('users',0);
    }
    public function test_confirmed_super_user_command_creates_only_unverified_main_site_account(): void
    {
        [$code,$output]=$this->runSuperUserCommand(true,true);
        $this->assertSame(0,$code); $user=\App\Models\User::where('email','cli-admin@example.test')->firstOrFail();
        $this->assertSame('super_user',$user->role); $this->assertNull($user->email_verified_at);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(self::USER_PASSWORD,$user->password));
        $this->assertStringNotContainsString(self::USER_PASSWORD,$output);
        $this->assertFalse($user->notificationPreference->project_mail);
        $this->assertDatabaseHas('activity_logs',['action'=>'user.super_user_created','subject_id'=>$user->id]);
    }

}
