<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DataPrivacyTest extends TestCase
{
    public function test_user_serialization_only_includes_public_identity_fields(): void
    {
        $user = $this->createUser();
        $user->forceFill(['avatar_path' => 'avatars/private-test.webp', 'backend_note' => 'PRIVATE_INTERNAL_MARKER']);
        $user->setRelation('notificationPreference', new NotificationPreference(['comment_mail' => true]));

        $this->assertEqualsCanonicalizing(['id', 'name', 'locale'], array_keys($user->toArray()));
        $this->assertStringNotContainsString($user->email, $user->toJson());
        $this->assertStringNotContainsString('PRIVATE_INTERNAL_MARKER', $user->toJson());
        $this->assertStringNotContainsString('private-test.webp', $user->toJson());
        $this->assertSame('member', $user->role);
    }

    public function test_account_and_authenticated_pages_are_not_stored_or_indexed(): void
    {
        foreach (['/giris', '/kayit', '/sifremi-unuttum'] as $path) {
            $response = $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        }

        $user = $this->createUser();
        foreach (['/', '/profilim', '/bildirimler'] as $path) {
            $response = $this->actingAs($user)->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->actingAs($user)->get('/profilim')->assertOk()->assertSee($user->email);
    }

    public function test_guests_and_members_cannot_read_private_backend_records(): void
    {
        foreach (['/admin/users', '/admin/contact-messages', '/profilim', '/bildirimler'] as $path) {
            $this->getJson($path)->assertUnauthorized()->assertJsonStructure(['message']);
        }
        $this->actingAs($this->createUser())->getJson('/admin/users')->assertForbidden();
        $this->getJson('/admin/contact-messages')->assertForbidden();
    }

    public function test_password_pages_do_not_forward_reset_tokens_in_referrers(): void
    {
        $this->get('/sifremi-unuttum')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/sifre-sifirla/fake-test-token')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_production_never_renders_debug_details_or_server_error_messages(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => true]);
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $this->assertFalse(config('app.debug'));

        Route::get('/__privacy-test-failure', fn () => throw new \RuntimeException('PRIVATE_INTERNAL_SQL_MARKER'));
        Route::get('/__privacy-test-unavailable', fn () => abort(503, 'PRIVATE_INTERNAL_SQL_MARKER'));

        $this->get('/__privacy-test-failure')->assertStatus(500)->assertDontSee('PRIVATE_INTERNAL_SQL_MARKER')
            ->assertDontSee('Stack trace')->assertDontSee('RuntimeException');
        foreach (['/__privacy-test-failure' => 500, '/__privacy-test-unavailable' => 503] as $path => $status) {
            $response = $this->getJson($path)->assertStatus($status)
                ->assertExactJson(['message' => __('ui.error_500_body')])->assertDontSee('PRIVATE_INTERNAL_SQL_MARKER')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_private_disk_has_no_download_route_or_public_symlink(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertNotContains(storage_path('app/private'), config('filesystems.links'));
        $this->assertFalse(Route::has('storage.local'));
        $this->get('/storage/seo/sitemap.xml')->assertNotFound();
        $this->get('/storage/app/private/seo/sitemap.xml')->assertNotFound();
    }
}
