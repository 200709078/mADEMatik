<?php

namespace Tests\Feature;

use App\Models\{Project, ProjectDownload, User};
use Illuminate\Support\Facades\{DB, Notification};
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function admin(): User
    {
        $admin = $this->createUser(true, true);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        return $admin;
    }

    private function project(array $attributes = []): Project
    {
        return Project::create(array_replace([
            'title' => ['tr' => 'İndirilebilir proje'],
            'slug' => 'indirme-'.Str::lower(Str::random(10)),
            'summary' => ['tr' => 'Proje indirmeleri için test açıklaması.'],
            'description' => ['tr' => 'Paketler bu projeye aittir.'],
            'external_url' => '/drAW',
            'status' => 'live',
            'is_published' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    private function download(Project $project, array $attributes = []): ProjectDownload
    {
        return $project->downloads()->create(array_replace([
            'title' => ['tr' => 'Windows kurulum paketi'],
            'platform' => 'windows',
            'architecture' => 'x64',
            'distribution_type' => 'direct',
            'channel' => 'stable',
            'download_url' => 'https://downloads.example.test/app/setup.exe',
            'sort_order' => 0,
            'is_published' => true,
        ], $attributes));
    }

    private function payload(array $attributes = []): array
    {
        return array_replace([
            'title' => 'Windows kurulum paketi',
            'platform' => 'windows',
            'architecture' => 'x64',
            'distribution_type' => 'direct',
            'channel' => 'stable',
            'version' => '2.1.0',
            'download_url' => 'https://downloads.example.test/app/setup.exe',
            'file_size' => 104857600,
            'released_at' => '2026-10-17',
            'notes' => 'Kurulum sihirbazını izleyin.',
            'requirements' => 'Windows 10 veya üzeri.',
            'license_url' => '/kullanim-kosullari',
            'sha256' => str_repeat('a1', 32),
            'sort_order' => 2,
            'is_published' => '1',
        ], $attributes);
    }

    public function test_public_project_shows_only_published_downloads_with_links(): void
    {
        $project = $this->project();
        $this->download($project);
        $this->download($project, [
            'title' => ['tr' => 'Android mağaza sürümü'],
            'platform' => 'android',
            'architecture' => null,
            'distribution_type' => 'store',
            'download_url' => 'https://play.google.com/store/apps/details?id=test.draw',
        ]);
        $this->download($project, [
            'title' => ['tr' => 'iOS deneme sürümü'],
            'platform' => 'ios',
            'distribution_type' => 'test',
            'channel' => 'beta',
            'download_url' => 'https://testflight.apple.com/join/TestDraw',
        ]);
        $this->download($project, [
            'title' => ['tr' => 'Gizli yayımlanmamış paket'],
            'download_url' => 'https://downloads.example.test/private/unreleased.exe',
            'is_published' => false,
        ]);

        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertSee('id="indirmeler"', false)
            ->assertSee('href="#indirmeler"', false)
            ->assertSee('Windows kurulum paketi')
            ->assertSee('Android mağaza sürümü')
            ->assertSee('iOS deneme sürümü')
            ->assertSee('https://downloads.example.test/app/setup.exe', false)
            ->assertSee('https://play.google.com/store/apps/details?id=test.draw', false)
            ->assertSee('https://testflight.apple.com/join/TestDraw', false)
            ->assertSee('Google Play’de Aç')
            ->assertSee('TestFlight ile Dene')
            ->assertSee('Beta')
            ->assertDontSee('Gizli yayımlanmamış paket')
            ->assertDontSee('private/unreleased.exe', false);
    }

    public function test_empty_or_draft_only_downloads_do_not_render_an_empty_public_section(): void
    {
        $project = $this->project();
        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertDontSee('id="indirmeler"', false)
            ->assertDontSee('href="#indirmeler"', false);

        $this->download($project, ['is_published' => false]);
        $this->download($project, ['download_url' => null, 'is_published' => false]);
        $this->download($project, ['download_url' => null, 'is_published' => true]);
        $this->download($project, ['download_url' => '', 'is_published' => true]);
        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertDontSee('id="indirmeler"', false)
            ->assertDontSee('href="#indirmeler"', false);
    }

    public function test_published_package_cannot_expose_an_unpublished_parent_project(): void
    {
        $project = $this->project(['is_published' => false]);
        $this->download($project);

        $this->get(route('projects.show', $project->slug))->assertNotFound();
        $this->get(route('projects.index'))->assertOk()->assertDontSee($project->slug);
    }

    public function test_public_downloads_follow_their_configured_order(): void
    {
        $project = $this->project();
        $this->download($project, ['title' => ['tr' => 'Üçüncü paket'], 'sort_order' => 30]);
        $this->download($project, ['title' => ['tr' => 'Birinci paket'], 'sort_order' => 10]);
        $this->download($project, ['title' => ['tr' => 'İkinci paket'], 'sort_order' => 20]);

        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertSeeInOrder(['Birinci paket', 'İkinci paket', 'Üçüncü paket']);
    }

    public function test_public_download_metadata_is_escaped_and_optional_details_are_visible(): void
    {
        $project = $this->project();
        $this->download($project, [
            'title' => ['tr' => '<script>paket()</script>'],
            'notes' => ['tr' => '<img src=x onerror=kurulum()>'],
            'requirements' => ['tr' => 'En az 4 GB bellek'],
            'version' => '3.2.1',
            'released_at' => '2026-10-17',
            'file_size' => 104857600,
            'license_url' => 'https://example.test/license',
            'sha256' => str_repeat('ab', 32),
        ]);

        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertSee('<script>paket()</script>')
            ->assertDontSee('<script>paket()</script>', false)
            ->assertSee('<img src=x onerror=kurulum()>')
            ->assertDontSee('<img src=x onerror=kurulum()>', false)
            ->assertSee('En az 4 GB bellek')
            ->assertSee('3.2.1')
            ->assertSee('https://example.test/license', false)
            ->assertSee(str_repeat('ab', 32));
    }

    public function test_administrator_can_create_a_package_with_full_metadata(): void
    {
        $this->admin();
        $project = $this->project();

        $this->post(route('admin.downloads.store', $project), $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $download = ProjectDownload::sole();
        $this->assertSame($project->id, $download->project_id);
        $this->assertSame('Windows kurulum paketi', $download->title['tr']);
        $this->assertSame('Kurulum sihirbazını izleyin.', $download->notes['tr']);
        $this->assertSame('Windows 10 veya üzeri.', $download->requirements['tr']);
        $this->assertSame(104857600, $download->file_size);
        $this->assertSame('2026-10-17', $download->released_at->format('Y-m-d'));
        $this->assertSame(str_repeat('a1', 32), $download->sha256);
        $this->assertTrue($download->is_published);
        Notification::assertNothingSent();
    }

    public function test_a_draft_can_be_saved_without_a_download_link_or_optional_metadata(): void
    {
        $this->admin();
        $project = $this->project();

        $this->post(route('admin.downloads.store', $project), [
            'platform' => 'linux',
            'distribution_type' => 'direct',
            'channel' => 'stable',
            'sort_order' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $download = ProjectDownload::sole();
        $this->assertFalse($download->is_published);
        $this->assertNull($download->download_url);
        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertDontSee('id="indirmeler"', false);
    }

    public function test_future_platform_and_multiple_distribution_options_need_no_schema_change(): void
    {
        $this->admin();
        $project = $this->project();
        foreach ([
            ['platform' => 'android', 'architecture' => 'arm64', 'distribution_type' => 'direct'],
            ['platform' => 'android', 'architecture' => null, 'distribution_type' => 'store'],
            ['platform' => 'ios', 'architecture' => null, 'distribution_type' => 'test', 'channel' => 'beta'],
            ['platform' => 'ipados', 'architecture' => null, 'distribution_type' => 'store'],
            ['platform' => 'chromeos', 'architecture' => 'arm64', 'distribution_type' => 'store'],
        ] as $attributes) {
            $this->post(route('admin.downloads.store', $project), $this->payload($attributes))
                ->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('project_downloads', 5);
        $this->assertDatabaseHas('project_downloads', ['project_id' => $project->id, 'platform' => 'chromeos']);
        $this->assertSame(2, $project->downloads()->where('platform', 'android')->count());
    }

    public function test_update_preserves_other_locales_and_cannot_reassign_the_parent(): void
    {
        $this->admin();
        $project = $this->project();
        $otherProject = $this->project();
        $download = $this->download($project, [
            'title' => ['tr' => 'İlk başlık', 'en' => 'English title'],
            'notes' => ['tr' => 'İlk not', 'en' => 'English notes'],
            'requirements' => ['tr' => 'İlk gereksinimler', 'en' => 'English requirements'],
        ]);

        $this->put(route('admin.downloads.update', [$project, $download]), $this->payload([
            'title' => 'Güncel başlık',
            'notes' => 'Güncel kurulum notu',
            'requirements' => 'Güncel gereksinimler',
            'project_id' => $otherProject->id,
            'role' => 'super_user',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $download->refresh();
        $this->assertSame($project->id, $download->project_id);
        $this->assertSame(['tr' => 'Güncel başlık', 'en' => 'English title'], $download->title);
        $this->assertSame(['tr' => 'Güncel kurulum notu', 'en' => 'English notes'], $download->notes);
        $this->assertSame(['tr' => 'Güncel gereksinimler', 'en' => 'English requirements'], $download->requirements);
        $this->assertSame('super_user', auth()->user()->role);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_package_can_be_unpublished_without_deletion_or_changing_the_web_application(): void
    {
        $this->admin();
        $project = $this->project();
        $download = $this->download($project);

        $this->put(route('admin.downloads.update', [$project, $download]), $this->payload(['is_published' => '0']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($download->fresh()->is_published);
        $this->assertSame('/drAW', $project->fresh()->external_url);
        $this->assertDatabaseCount('project_downloads', 1);
        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertDontSee('https://downloads.example.test/app/setup.exe', false);
    }

    public function test_deleting_a_download_requires_confirmation_and_keeps_the_project(): void
    {
        $this->admin();
        $project = $this->project();
        $download = $this->download($project);
        $other = $this->download($project, ['title' => ['tr' => 'Korunacak paket']]);

        $this->deleteJson(route('admin.downloads.destroy', [$project, $download]))
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_delete');
        $this->assertDatabaseHas('project_downloads', ['id' => $download->id]);

        $this->delete(route('admin.downloads.destroy', [$project, $download]), ['confirm_delete' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('project_downloads', ['id' => $download->id]);
        $this->assertDatabaseHas('project_downloads', ['id' => $other->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_deleting_a_project_cleans_only_its_own_download_records(): void
    {
        $this->admin();
        $project = $this->project();
        $otherProject = $this->project();
        $download = $this->download($project);
        $other = $this->download($otherProject);

        $this->delete(route('admin.projects.destroy', $project))->assertRedirect(route('admin.projects.index'));
        $this->assertDatabaseMissing('project_downloads', ['id' => $download->id]);
        $this->assertDatabaseHas('project_downloads', ['id' => $other->id, 'project_id' => $otherProject->id]);
        $this->assertDatabaseHas('projects', ['id' => $otherProject->id]);
    }

    public function test_nested_routes_reject_a_download_belonging_to_another_project(): void
    {
        $this->admin();
        $project = $this->project();
        $otherProject = $this->project();
        $download = $this->download($project);

        $this->get(route('admin.downloads.edit', [$otherProject, $download]))->assertNotFound();
        $this->put(route('admin.downloads.update', [$otherProject, $download]), $this->payload(['title' => 'Yetkisiz değişiklik']))->assertNotFound();
        $this->delete(route('admin.downloads.destroy', [$otherProject, $download]), ['confirm_delete' => '1'])->assertNotFound();
        $this->assertSame('Windows kurulum paketi', $download->fresh()->title['tr']);
        $this->assertDatabaseCount('project_downloads', 1);
    }

    public function test_guests_cannot_use_any_download_management_route(): void
    {
        $project = $this->project();
        $download = $this->download($project);

        $this->get(route('admin.downloads.create', $project))->assertRedirect(route('login'));
        $this->get(route('admin.downloads.edit', [$project, $download]))->assertRedirect(route('login'));
        $this->post(route('admin.downloads.store', $project), $this->payload())->assertRedirect(route('login'));
        $this->put(route('admin.downloads.update', [$project, $download]), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('admin.downloads.destroy', [$project, $download]), ['confirm_delete' => '1'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('project_downloads', 1);
    }

    public function test_members_cannot_use_any_download_management_route(): void
    {
        $project = $this->project();
        $download = $this->download($project);
        $member = $this->createUser();
        $this->actingAs($member)->withSession(['auth.password_confirmed_at' => time()]);

        $this->get(route('admin.downloads.create', $project))->assertForbidden();
        $this->get(route('admin.downloads.edit', [$project, $download]))->assertForbidden();
        $this->post(route('admin.downloads.store', $project), $this->payload())->assertForbidden();
        $this->put(route('admin.downloads.update', [$project, $download]), $this->payload())->assertForbidden();
        $this->delete(route('admin.downloads.destroy', [$project, $download]), ['confirm_delete' => '1'])->assertForbidden();
        $this->assertSame('member', $member->fresh()->role);
        $this->assertDatabaseCount('project_downloads', 1);
    }

    public function test_verification_setting_and_password_confirmation_apply_to_download_management(): void
    {
        $project = $this->project();
        $download = $this->download($project);
        $this->actingAs($this->createUser(false, true))->withSession(['auth.password_confirmed_at' => time()]);
        config(['auth.email_verification_enabled' => true]);
        $this->get(route('admin.downloads.edit', [$project, $download]))->assertRedirect(route('verification.notice'));

        config(['auth.email_verification_enabled' => false]);
        $this->get(route('admin.downloads.edit', [$project, $download]))->assertOk();

        $this->withSession(['auth.password_confirmed_at' => 0]);
        $this->get(route('admin.downloads.edit', [$project, $download]))->assertRedirect(route('password.confirm'));
    }

    public function test_blocked_super_user_cannot_change_downloads(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['is_blocked' => true])->save();
        $project = $this->project();

        $this->post(route('admin.downloads.store', $project), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('project_downloads', 0);
    }

    public function test_unsafe_download_urls_are_validation_errors_and_never_saved(): void
    {
        $this->admin();
        $project = $this->project();
        foreach ([
            'javascript:alert(1)',
            'data:text/html,<h1>unsafe</h1>',
            'http://downloads.example.test/app.exe',
            '//evil.example/app.exe',
            '/\\evil.example/app.exe',
            '/%5Cevil.example/app.exe',
            "https://downloads.example.test/\napp.exe",
            'https://user:password@downloads.example.test/app.exe',
            'https://',
        ] as $url) {
            $this->postJson(route('admin.downloads.store', $project), $this->payload(['download_url' => $url]))
                ->assertUnprocessable()->assertJsonValidationErrors('download_url');
        }

        $this->assertDatabaseCount('project_downloads', 0);
    }

    public function test_license_links_use_the_same_safe_url_rules(): void
    {
        $this->admin();
        $project = $this->project();
        foreach (['javascript:alert(1)', '//evil.example', 'https://user:password@example.test/license', '/\\evil.example', ['https://example.test']] as $url) {
            $this->postJson(route('admin.downloads.store', $project), $this->payload(['license_url' => $url]))
                ->assertUnprocessable()->assertJsonValidationErrors('license_url');
        }

        $this->assertDatabaseCount('project_downloads', 0);
    }

    public function test_internal_file_paths_are_accepted_and_publishing_requires_a_link(): void
    {
        $this->admin();
        $project = $this->project();
        $this->postJson(route('admin.downloads.store', $project), $this->payload(['download_url' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('download_url');
        $this->post(route('admin.downloads.store', $project), $this->payload(['download_url' => '/drAW/downloads/drAW.AppImage']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('/drAW/downloads/drAW.AppImage', ProjectDownload::sole()->download_url);
    }

    public function test_invalid_package_metadata_is_rejected_without_creating_records(): void
    {
        $this->admin();
        $project = $this->project();
        foreach ([
            ['platform', 'Windows'],
            ['platform', '../linux'],
            ['platform', str_repeat('a', 41)],
            ['distribution_type', 'torrent'],
            ['channel', 'unknown'],
            ['file_size', -1],
            ['file_size', 'not-a-size'],
            ['released_at', 'not-a-date'],
            ['sha256', 'invalid-sha256'],
            ['sha256', str_repeat('g', 64)],
            ['sort_order', -1],
            ['sort_order', 10001],
            ['is_published', 'definitely'],
        ] as [$field, $value]) {
            $this->postJson(route('admin.downloads.store', $project), $this->payload([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('project_downloads', 0);
    }

    public function test_array_inputs_are_validation_errors_instead_of_server_errors(): void
    {
        $this->admin();
        $project = $this->project();
        foreach (['title', 'platform', 'architecture', 'distribution_type', 'channel', 'version', 'download_url', 'file_size', 'released_at', 'notes', 'requirements', 'license_url', 'sha256', 'sort_order', 'is_published'] as $field) {
            $this->postJson(route('admin.downloads.store', $project), $this->payload([$field => ['nested' => 'invalid']]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('project_downloads', 0);
    }

    public function test_admin_project_editor_and_download_editor_render_saved_metadata(): void
    {
        $this->admin();
        $project = $this->project();
        $download = $this->download($project, ['title' => ['tr' => 'Düzenlenecek Linux paketi'], 'platform' => 'linux']);

        $this->get(route('admin.projects.edit', $project))->assertOk()
            ->assertSee('Düzenlenecek Linux paketi')
            ->assertSee(route('admin.downloads.create', $project), false)
            ->assertSee(route('admin.downloads.edit', [$project, $download]), false);
        $this->get(route('admin.downloads.create', $project))->assertOk()
            ->assertSee(route('admin.downloads.store', $project), false);
        $this->get(route('admin.downloads.edit', [$project, $download]))->assertOk()
            ->assertSee('Düzenlenecek Linux paketi')
            ->assertSee('https://downloads.example.test/app/setup.exe', false)
            ->assertSee(route('admin.downloads.update', [$project, $download]), false);
    }

    public function test_draw_import_creates_the_two_verified_linux_packages_as_drafts(): void
    {
        $project = $this->project(['slug' => 'draw', 'title' => ['tr' => 'drAW']]);

        $this->artisan('madematik:import-draw-downloads')
            ->expectsOutput('2 yeni drAW indirme taslağı eklendi. Mevcut kayıtlar korundu.')
            ->assertSuccessful();

        $this->assertDatabaseCount('project_downloads', 2);
        foreach ([
            ['AppImage', 'x86_64', 98796802, '78003b3cc5554190f783a684abdfd361e0b43b3c038dfcb119ab48bdd305c681', 'drAW-0.0.3-linux-x86_64.AppImage'],
            ['DEB Paketi', 'amd64', 100367184, 'ca970d57916c85047ee125e82f93588fd11846de2d20a3d4194d092ae572a85e', 'drAW-0.0.3-linux-amd64.deb'],
        ] as [$title, $architecture, $size, $hash, $filename]) {
            $download = $project->downloads()->where('sha256', $hash)->sole();
            $this->assertSame($title, $download->title['tr']);
            $this->assertSame('linux', $download->platform);
            $this->assertSame($architecture, $download->architecture);
            $this->assertSame('0.0.3', $download->version);
            $this->assertSame($size, $download->file_size);
            $this->assertSame('2026-10-16', $download->released_at->format('Y-m-d'));
            $this->assertNotEmpty($download->requirements['tr']);
            $this->assertFalse($download->is_published);
            $this->assertSame('https://github.com/200709078/drAW/releases/download/v0.0.3/'.$filename, $download->download_url);
        }
        $this->get(route('projects.show', $project->slug))->assertOk()->assertDontSee('id="indirmeler"', false);
        Notification::assertNothingSent();
    }

    public function test_repeating_draw_import_preserves_admin_edits_and_does_not_duplicate_packages(): void
    {
        $project = $this->project(['slug' => 'draw']);
        $this->artisan('madematik:import-draw-downloads')->assertSuccessful();
        $download = $project->downloads()->where('architecture', 'x86_64')->sole();
        $download->update([
            'download_url' => 'https://downloads.example.test/drAW.AppImage',
            'is_published' => true,
            'notes' => ['tr' => 'Yönetici tarafından düzenlenen not.', 'en' => 'Edited English note.'],
            'sort_order' => 71,
        ]);

        $this->artisan('madematik:import-draw-downloads')
            ->expectsOutput('0 yeni drAW indirme taslağı eklendi. Mevcut kayıtlar korundu.')
            ->assertSuccessful();

        $download->refresh();
        $this->assertDatabaseCount('project_downloads', 2);
        $this->assertSame('https://downloads.example.test/drAW.AppImage', $download->download_url);
        $this->assertTrue($download->is_published);
        $this->assertSame(['tr' => 'Yönetici tarafından düzenlenen not.', 'en' => 'Edited English note.'], $download->notes);
        $this->assertSame(71, $download->sort_order);
    }

    public function test_draw_import_without_its_project_fails_without_creating_a_project(): void
    {
        $this->project(['slug' => 'different-project']);

        $this->artisan('madematik:import-draw-downloads')
            ->expectsOutput('drAW proje kaydı bulunamadı. Önce projeyi oluşturun.')
            ->assertFailed();

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('project_downloads', 0);
        $this->assertDatabaseMissing('projects', ['slug' => 'draw']);
    }

    public function test_draw_import_rejects_unowned_connections_before_querying_them(): void
    {
        $this->project(['slug' => 'draw']);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        // Even the rejected target is a new in-memory test connection. No real
        // database credentials or connection are used by this guard test.
        config([
            'database.connections.unowned_download_test' => config('database.connections.sqlite'),
            'database.default' => 'unowned_download_test',
        ]);

        try {
            $this->artisan('madematik:import-draw-downloads')
                ->expectsOutput('İndirme taslakları eklenemedi. Proje veritabanı bağlantısını ve migration durumunu kontrol edin.')
                ->assertFailed();
            $this->assertSame([], $queries);
        } finally {
            config(['database.default' => 'sqlite']);
        }

        $this->assertDatabaseCount('project_downloads', 0);
        $this->assertDatabaseCount('projects', 1);
    }
}
