<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Comment, ContactMessage, Project, ProjectCategory, ProjectMedia, User};
use App\Services\{MediaService, Settings};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, File, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminAndMediaTest extends TestCase
{
    private array $temporaryFiles = [];
    private array $temporaryDiskRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach ($this->temporaryDiskRoots as $path) {
            File::deleteDirectory($path);
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = $this->createUser(true, true);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        return $user;
    }

    private function project(array $attributes = []): Project
    {
        return Project::create(array_replace([
            'title' => ['tr' => 'Doğrulama projesi'],
            'slug' => 'proje-'.Str::lower(Str::random(10)),
            'summary' => ['tr' => 'Gerçek test verisi açıklaması.'],
            'description' => ['tr' => 'Test kapsamında oluşturulan içerik.'],
            'status' => 'developing',
            'is_published' => false,
            'is_featured' => false,
            'sort_order' => 0,
        ], $attributes));
    }

    private function projectPayload(array $attributes = []): array
    {
        return array_replace([
            'title' => 'Yeni etkileşimli araç',
            'slug' => 'yeni-arac',
            'summary' => 'Kısa açıklama',
            'description' => 'Uzun açıklama',
            'features' => "Birinci özellik\nİkinci özellik",
            'technologies' => 'Laravel, JavaScript',
            'status' => 'developing',
            'is_published' => '0',
            'is_featured' => '0',
            'sort_order' => '2',
            'external_url' => '/drAW',
        ], $attributes);
    }

    private function settingsPayload(): array
    {
        $payload = array_map(fn ($group) => $group['tr'], config('content'));
        $payload['about']['tools'] = implode(', ', $payload['about']['tools']);
        $payload['sliders'] = array_map(
            fn ($slide) => array_intersect_key($slide, array_flip(['title', 'description', 'cta_label', 'cta_url'])),
            $payload['sliders'],
        );
        return $payload;
    }

    private function uploadedFile(string $name, string $contents): UploadedFile
    {
        $directory = storage_path('framework/testing/admin-fixtures');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/'.Str::uuid().'-'.$name;
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;
        return new UploadedFile($path, $name, null, null, true);
    }

    private function image(int $width = 640, int $height = 360): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 9, 106, 182));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        return $this->uploadedFile('test-image.png', $bytes);
    }

    private function fakePublicStorage(): void
    {
        $root = storage_path('framework/testing/admin-public-'.Str::uuid());
        $this->temporaryDiskRoots[] = $root;
        Storage::fake('public', ['root' => $root]);
    }

    public function test_members_and_unverified_accounts_cannot_manage_admin_endpoints(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $member = $this->createUser();
        $this->actingAs($member)->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['admin.dashboard', 'admin.projects.index', 'admin.categories.index', 'admin.users.index', 'admin.settings.edit', 'admin.activity.index', 'admin.contact-messages.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('admin.projects.store'), $this->projectPayload())->assertForbidden();
        $this->actingAs($this->createUser(false, true));
        $this->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_category_and_project_crud_preserve_translations_and_published_visibility(): void
    {
        $this->admin();
        $this->post(route('admin.categories.store'), ['name' => 'Eğitim', 'slug' => 'egitim', 'sort_order' => 1])
            ->assertRedirect(route('admin.categories.index'));
        $category = ProjectCategory::sole();
        $category->update(['name' => ['tr' => 'Eğitim', 'en' => 'Education']]);
        $this->put(route('admin.categories.update', $category), ['name' => 'Eğitim araçları', 'slug' => 'egitim', 'sort_order' => 2])->assertSessionHasNoErrors();
        $this->assertSame(['tr' => 'Eğitim araçları', 'en' => 'Education'], $category->fresh()->name);

        $payload = $this->projectPayload(['project_category_id' => $category->id]);
        $this->post(route('admin.projects.store'), $payload)->assertSessionHasNoErrors();
        $project = Project::sole();
        $this->assertSame(['Birinci özellik', 'İkinci özellik'], $project->features['tr']);
        $this->assertSame(['Laravel', 'JavaScript'], $project->technologies);
        $this->get(route('projects.show', $project->slug))->assertNotFound();
        $this->get(route('projects.index'))->assertDontSee('Yeni etkileşimli araç');
        $project->update(['title' => ['tr' => 'Yeni etkileşimli araç', 'en' => 'New tool']]);
        $this->put(route('admin.projects.update', $project), array_replace($payload, ['title' => 'Yayınlanan etkileşimli araç', 'is_published' => '1', 'status' => 'live']))->assertSessionHasNoErrors();
        $this->assertSame('New tool', $project->fresh()->title['en']);
        $this->get(route('projects.show', $project->slug))->assertOk()->assertSee('Yayınlanan etkileşimli araç');
        $this->get(route('projects.index'))->assertOk()->assertSee('Yayınlanan etkileşimli araç');
        $this->delete(route('admin.categories.destroy', $category))->assertSessionHasErrors('category');
        $this->assertDatabaseHas('project_categories', ['id' => $category->id]);
        $this->delete(route('admin.projects.destroy', $project))->assertRedirect(route('admin.projects.index'));
        $this->delete(route('admin.categories.destroy', $category))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_categories', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'project.created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'category.deleted']);
    }

    public function test_admin_project_search_finds_turkish_titles_and_all_editor_pages_render(): void
    {
        $this->admin();
        $category = ProjectCategory::create(['name' => ['tr' => 'Çizim araçları'], 'slug' => 'cizim', 'sort_order' => 0]);
        $project = $this->project(['title' => ['tr' => 'Öğrenmeyi kolaylaştıran çizim'], 'project_category_id' => $category->id]);
        $other = $this->project(['title' => ['tr' => 'Ayrı tablo uygulaması']]);
        $this->get(route('admin.projects.index', ['q' => 'Öğrenmeyi']))->assertOk()->assertSee('Öğrenmeyi kolaylaştıran çizim')->assertDontSee('Ayrı tablo uygulaması');
        foreach ([
            route('admin.projects.create'), route('admin.projects.edit', $project),
            route('admin.categories.create'), route('admin.categories.edit', $category),
            route('admin.categories.index'), route('admin.users.index'),
            route('admin.users.edit', $this->createUser()), route('admin.dashboard'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_project_slug_status_and_category_constraints_are_enforced(): void
    {
        $this->admin();
        $this->project(['slug' => 'existing-project']);
        foreach (['Invalid-Title', '../escape', 'two--dashes', 'with space'] as $slug) {
            $this->postJson(route('admin.projects.store'), $this->projectPayload(['slug' => $slug]))->assertUnprocessable()->assertJsonValidationErrors('slug');
        }
        $this->postJson(route('admin.projects.store'), $this->projectPayload(['slug' => 'existing-project']))->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->postJson(route('admin.projects.store'), $this->projectPayload(['status' => 'unknown']))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson(route('admin.projects.store'), $this->projectPayload(['project_category_id' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('project_category_id');
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_project_and_settings_reject_unsafe_urls(): void
    {
        $this->admin();
        foreach (['javascript:alert(1)', 'data:text/html,<h1>x</h1>', '//evil.example', '/\\evil.example', "https://example.test/\nnext", 'https://'] as $url) {
            $this->postJson(route('admin.projects.store'), $this->projectPayload(['external_url' => $url]))->assertUnprocessable()->assertJsonValidationErrors('external_url');
            $settings = $this->settingsPayload();
            $settings['sliders'][0]['cta_url'] = $url;
            $this->putJson(route('admin.settings.update'), $settings)->assertUnprocessable()->assertJsonValidationErrors('sliders.0.cta_url');
        }
        $settings = $this->settingsPayload();
        $settings['social'] = [['label' => 'Geçersiz bağlantı', 'url' => 'javascript:alert(1)']];
        $this->putJson(route('admin.settings.update'), $settings)->assertUnprocessable()->assertJsonValidationErrors('social.0.url');
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_settings_save_keeps_brand_images_translations_and_real_product_links(): void
    {
        $this->admin();
        Settings::put('about', ['tr' => config('content.about.tr'), 'en' => ['title' => 'Future English title']]);
        $settings = $this->settingsPayload();
        $settings['brand']['email'] = '';
        $settings['home']['intro_title'] = 'Yönetimden güncellenen tanıtım';
        $settings['social'] = [['label' => 'aWa', 'url' => 'https://awa.madematik.com'], ['label' => '', 'url' => '']];
        $settings['sliders'][0]['cta_url'] = '/drAW';
        $this->put(route('admin.settings.update'), $settings)->assertSessionHasNoErrors();
        $slides = Settings::localized('sliders');
        foreach (config('content.sliders.tr') as $index => $original) {
            $this->assertSame($original['image'], $slides[$index]['image']);
            $this->assertSame($original['mobile_image'], $slides[$index]['mobile_image']);
        }
        $this->assertSame([['label' => 'aWa', 'url' => 'https://awa.madematik.com']], Settings::localized('social'));
        $this->assertSame('Future English title', Settings::get('about')['en']['title']);
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('slider-01-madematik.webp');
        $this->get(route('home'))->assertOk()->assertSee('Yönetimden güncellenen tanıtım')->assertSee('/drAW');
        $this->get(route('contact'))->assertOk()->assertDontSee('mailto:');
        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.updated']);
    }

    public function test_settings_cannot_replace_brand_images_or_use_nonsequential_slide_keys(): void
    {
        $this->admin();
        $payload = $this->settingsPayload();
        $payload['sliders'][0]['image'] = 'unapproved-logo.png';
        $this->putJson(route('admin.settings.update'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sliders.0');
        $payload = $this->settingsPayload();
        $payload['sliders'] = array_combine([3, 4, 5], $payload['sliders']);
        $this->putJson(route('admin.settings.update'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sliders');
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_cover_upload_is_reencoded_resized_and_replacement_is_cleaned(): void
    {
        $this->admin();
        $this->fakePublicStorage();
        $this->post(route('admin.projects.store'), $this->projectPayload(['cover' => $this->image(2400, 600)]))->assertSessionHasNoErrors();
        $project = Project::sole();
        $oldPath = $project->cover_path;
        $this->assertMatchesRegularExpression('~^media/[a-f0-9-]+\.webp$~', $oldPath);
        Storage::disk('public')->assertExists($oldPath);
        $size = getimagesize(Storage::disk('public')->path($oldPath));
        $this->assertSame('image/webp', $size['mime']);
        $this->assertSame([1920, 480], [$size[0], $size[1]]);
        $this->put(route('admin.projects.update', $project), $this->projectPayload(['cover' => $this->image()]))->assertSessionHasNoErrors();
        $newPath = $project->fresh()->cover_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
        $this->put(route('admin.projects.update', $project), $this->projectPayload(['remove_cover' => '1']))->assertSessionHasNoErrors();
        $this->assertNull($project->fresh()->cover_path);
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_project_media_crud_enforces_nested_project_ownership(): void
    {
        $this->admin();
        $this->fakePublicStorage();
        $project = $this->project();
        $otherProject = $this->project();
        $this->post(route('admin.media.store', $project), ['image' => $this->image(), 'alt' => 'Gerçek ekran görüntüsü', 'sort_order' => 1])->assertSessionHasNoErrors();
        $media = ProjectMedia::sole();
        $path = $media->path;
        Storage::disk('public')->assertExists($path);
        $this->patch(route('admin.media.update', [$otherProject, $media]), ['alt' => 'Yetkisiz değişiklik', 'sort_order' => 9])->assertNotFound();
        $this->delete(route('admin.media.destroy', [$otherProject, $media]))->assertNotFound();
        $this->assertSame('Gerçek ekran görüntüsü', $media->fresh()->alt['tr']);
        $this->patch(route('admin.media.update', [$project, $media]), ['alt' => 'Güncel açıklama', 'sort_order' => 5])->assertSessionHasNoErrors();
        $this->assertSame('Güncel açıklama', $media->fresh()->alt['tr']);
        $this->assertSame(5, (int) $media->fresh()->sort_order);
        $this->delete(route('admin.media.destroy', [$project, $media]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseCount('project_media', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'media.deleted']);
    }

    public function test_invalid_cover_dimensions_report_the_cover_field_and_create_no_files(): void
    {
        $this->admin();
        $this->fakePublicStorage();
        $this->postJson(route('admin.projects.store'), $this->projectPayload(['cover' => $this->image(6001, 1)]))
            ->assertUnprocessable()->assertJsonValidationErrors('cover');
        $this->assertDatabaseCount('projects', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_executable_svg_and_extreme_dimension_media_are_rejected(): void
    {
        $this->admin();
        $this->fakePublicStorage();
        $project = $this->project();
        foreach ([['payload.png', '<?php echo "unsafe";'], ['image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>']] as [$name, $contents]) {
            $this->postJson(route('admin.media.store', $project), ['image' => $this->uploadedFile($name, $contents), 'alt' => 'Görsel', 'sort_order' => 0])->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        try {
            app(MediaService::class)->store($this->image(6001, 1));
            $this->fail('Oversized dimensions should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $this->assertDatabaseCount('project_media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_project_delete_cleans_its_media_and_comments_without_touching_other_content(): void
    {
        $this->admin();
        $this->fakePublicStorage();
        $owner = $this->createUser();
        $project = $this->project(['cover_path' => 'media/cover.webp']);
        $other = $this->project(['cover_path' => 'media/other.webp']);
        $project->media()->create(['path' => 'media/gallery.webp', 'alt' => ['tr' => 'Kapak']]);
        foreach (['media/cover.webp', 'media/gallery.webp', 'media/other.webp', 'keep.txt'] as $path) {
            Storage::disk('public')->put($path, 'test asset');
        }
        $comment = new Comment(['body' => 'Projeye ait yorum', 'project_id' => $project->id]);
        $comment->forceFill(['user_id' => $owner->id])->save();
        $general = new Comment(['body' => 'Genel yorum']);
        $general->forceFill(['user_id' => $owner->id])->save();
        $this->delete(route('admin.projects.destroy', $project))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertDatabaseHas('comments', ['id' => $general->id]);
        $this->assertDatabaseHas('projects', ['id' => $other->id]);
        Storage::disk('public')->assertMissing(['media/cover.webp', 'media/gallery.webp']);
        Storage::disk('public')->assertExists(['media/other.webp', 'keep.txt']);
    }

    public function test_user_blocking_revokes_sessions_but_cannot_change_roles_or_block_self(): void
    {
        $admin = $this->admin();
        $member = $this->createUser();
        DB::table('sessions')->insert(['id' => 'member-session', 'user_id' => $member->id, 'payload' => 'test', 'last_activity' => time()]);
        $this->put(route('admin.users.update', $member), ['name' => 'Güncel isim', 'is_blocked' => '1', 'role' => 'super_user', 'email' => 'changed@example.test'])->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->is_blocked);
        $this->assertSame('member', $member->fresh()->role);
        $this->assertSame($member->email, $member->fresh()->email);
        $this->assertDatabaseMissing('sessions', ['id' => 'member-session']);
        $this->put(route('admin.users.update', $admin), ['name' => $admin->name, 'is_blocked' => '1'])->assertSessionHasErrors('is_blocked');
        $this->assertFalse($admin->fresh()->is_blocked);
        $this->put(route('admin.users.update', $member), ['name' => 'Güncel isim', 'is_blocked' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->is_blocked);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.updated', 'subject_id' => $member->id]);
    }

    public function test_user_deletion_requires_confirmation_and_protects_super_users(): void
    {
        $admin = $this->admin();
        $member = $this->createUser();
        $this->deleteJson(route('admin.users.destroy', $member))->assertUnprocessable()->assertJsonValidationErrors('confirm_delete');
        $this->delete(route('admin.users.destroy', $admin), ['confirm_delete' => '1'])->assertSessionHasErrors('user');
        $otherAdmin = $this->createUser(true, true);
        $this->delete(route('admin.users.destroy', $otherAdmin), ['confirm_delete' => '1'])->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_blocked' => false]);
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    public function test_user_deletion_cascade_removes_attached_replies_and_preserves_separate_records(): void
    {
        $admin = $this->admin();
        $member = $this->createUser();
        $other = $this->createUser();
        $rootComment = new Comment(['body' => 'Silinecek kök yorum']);
        $rootComment->forceFill(['user_id' => $member->id])->save();
        $reply = new Comment(['body' => 'Başka üyenin bağlı yanıtı', 'parent_id' => $rootComment->id]);
        $reply->forceFill(['user_id' => $other->id])->save();
        $unrelated = new Comment(['body' => 'Korunacak bağımsız yorum']);
        $unrelated->forceFill(['user_id' => $other->id])->save();
        $contact = ContactMessage::create(['name' => $member->name, 'email' => $member->email, 'subject' => 'Bağımsız kayıt', 'body' => 'Ayrıca değerlendirilecek iletişim içeriği.']);
        $historicalAudit = ActivityLog::create(['user_id' => $member->id, 'action' => 'account.deletion_requested']);
        DB::table('sessions')->insert(['id' => 'delete-session', 'user_id' => $member->id, 'payload' => 'test', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $member->email, 'token' => 'test-token']);
        $member->notifications()->create(['id' => Str::uuid(), 'type' => 'test', 'data' => ['title' => 'Test']]);
        $this->delete(route('admin.users.destroy', $member), ['confirm_delete' => '1'])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('comments', ['id' => $rootComment->id]);
        $this->assertDatabaseMissing('comments', ['id' => $reply->id]);
        $this->assertDatabaseHas('comments', ['id' => $unrelated->id]);
        $this->assertDatabaseHas('contact_messages', ['id' => $contact->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $historicalAudit->id, 'user_id' => null]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deleted', 'subject_id' => $member->id, 'user_id' => $admin->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'delete-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $member->email]);
        $this->assertDatabaseMissing('notification_preferences', ['user_id' => $member->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $member->id]);
    }

    public function test_admin_contact_crud_and_activity_endpoints_use_correct_route_bindings(): void
    {
        $this->admin();
        $message = ContactMessage::create(['name' => 'Test üyesi', 'email' => 'member@example.test', 'subject' => 'Bir iletişim konusu', 'body' => '<script>unsafe()</script> Ziyaretçi mesajı.']);
        $this->get(route('admin.contact-messages.show', $message))->assertOk()->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
        $this->assertSame('read', $message->fresh()->status);
        $this->put(route('admin.contact-messages.update', $message), ['status' => 'archived'])->assertSessionHasNoErrors();
        $this->get(route('admin.contact-messages.index', ['status' => 'archived']))->assertOk()->assertSee('Bir iletişim konusu');
        $this->putJson(route('admin.contact-messages.update', $message), ['status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->delete(route('admin.contact-messages.destroy', $message))->assertRedirect(route('admin.contact-messages.index'));
        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
        $this->get(route('admin.activity.index'))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'contact.deleted', 'subject_id' => $message->id]);
    }

    public function test_malformed_project_types_are_validation_errors_without_creating_records(): void
    {
        $this->admin();
        $category = ProjectCategory::create(['name' => ['tr' => 'Test kategorisi'], 'slug' => 'test-kategorisi']);
        foreach ([
            'external_url' => ['https://example.test'],
            'slug' => ['new-project'],
            'project_category_id' => [$category->id],
            'status' => ['developing'],
            'sort_order' => ['1'],
            'cover' => ['not-a-file'],
        ] as $field => $value) {
            $this->postJson(route('admin.projects.store'), $this->projectPayload([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_malformed_settings_urls_are_validation_errors_without_saving_settings(): void
    {
        $this->admin();
        $payload = $this->settingsPayload();
        $payload['sliders'][0]['cta_url'] = ['https://example.test'];
        $this->putJson(route('admin.settings.update'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sliders.0.cta_url');
        $payload = $this->settingsPayload();
        $payload['social'] = [['label' => 'Bağlantı', 'url' => ['https://example.test']]];
        $this->putJson(route('admin.settings.update'), $payload)->assertUnprocessable()->assertJsonValidationErrors('social.0.url');
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_malformed_admin_search_and_contact_status_filters_are_rejected(): void
    {
        $this->admin();
        foreach (['admin.projects.index', 'admin.users.index'] as $route) {
            $this->getJson(route($route, ['q' => ['nested' => 'invalid']]))->assertUnprocessable()->assertJsonValidationErrors('q');
            $this->getJson(route($route, ['q' => str_repeat('x', 101)]))->assertUnprocessable()->assertJsonValidationErrors('q');
        }
        $this->getJson(route('admin.contact-messages.index', ['status' => ['new']]))->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_malformed_category_and_media_inputs_are_validation_errors(): void
    {
        $this->admin();
        $this->fakePublicStorage();
        $project = $this->project();
        $categoryPayload = ['name' => 'Kategori', 'slug' => 'kategori', 'sort_order' => 0];
        foreach (['slug' => [['invalid']], 'sort_order' => ['2'], 'name' => ['invalid']] as $field => $value) {
            $this->postJson(route('admin.categories.store'), array_replace($categoryPayload, [$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson(route('admin.media.store', $project), ['image' => ['invalid-file'], 'alt' => 'Görsel', 'sort_order' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseCount('project_categories', 0);
        $this->assertDatabaseCount('project_media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_malformed_browser_form_inputs_redirect_with_field_errors(): void
    {
        $this->admin();
        $this->from(route('admin.projects.create'))->post(route('admin.projects.store'), $this->projectPayload(['external_url' => ['nested' => 'invalid']]))
            ->assertRedirect(route('admin.projects.create'))->assertSessionHasErrors('external_url');
        $payload = $this->settingsPayload();
        $payload['sliders'][0]['cta_url'] = ['invalid'];
        $this->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $payload)
            ->assertRedirect(route('admin.settings.edit'))->assertSessionHasErrors('sliders.0.cta_url');
        $this->from(route('admin.projects.index'))->get(route('admin.projects.index', ['q' => ['invalid']]))
            ->assertRedirect(route('admin.projects.index'))->assertSessionHasErrors('q');
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('settings', 0);
    }
    public function test_actor_blocked_after_request_authentication_cannot_modify_members(): void
    {
        $actor=$this->createUser(true,true); $member=$this->createUser();
        $this->actingAs($actor)->withSession(['auth.password_confirmed_at'=>time()]);
        \Illuminate\Support\Facades\DB::table('users')->where('id',$actor->id)->update(['is_blocked'=>true]);
        $this->patch(route('admin.users.update',$member),['name'=>'Değiştirme denemesi','is_blocked'=>true])->assertForbidden();
        $this->assertFalse($member->fresh()->is_blocked);
        $this->assertSame('Test Üyesi',$member->fresh()->name);
    }

}
