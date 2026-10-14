<?php

namespace Tests\Feature;

use App\Models\{Comment, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{File, Storage};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    private array $temporaryFiles = [];
    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->diskRoot = storage_path('framework/testing/profile-public-'.Str::uuid());
        Storage::fake('public', ['root' => $this->diskRoot]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        File::deleteDirectory($this->diskRoot);
        parent::tearDown();
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        $directory = storage_path('framework/testing/profile-fixtures');
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/'.Str::uuid().'-'.basename($name);
        file_put_contents($path, $bytes);
        $this->temporaryFiles[] = $path;
        return new UploadedFile($path, $name, null, null, true);
    }

    private function image(string $format = 'png', int $width = 800, int $height = 600): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 19, 105, 189));
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image),
            'webp' => imagewebp($image),
            'gif' => imagegif($image),
            default => imagepng($image),
        };
        $bytes = ob_get_clean();
        imagedestroy($image);
        return $this->upload('profile.'.$format, $bytes);
    }

    private function uploadPhoto(User $user, ?UploadedFile $image = null): string
    {
        $this->actingAs($user)->from(route('profile.edit'))
            ->post(route('profile.avatar.store'), ['avatar' => $image ?? $this->image()])
            ->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
        return $user->fresh()->avatar_path;
    }

    public function test_profile_offers_photo_upload_and_initials_before_a_photo_exists(): void
    {
        $user = $this->createUser();
        $user->forceFill(['name' => 'Adem VAROL'])->save();
        $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('Profil fotoğrafı')->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="avatar"', false);
        $this->assertMatchesRegularExpression('~>\s*AV\s*</span>~', $response->getContent());
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->avatar_url);
    }

    public static function supportedFormats(): array
    {
        return ['JPEG' => ['jpeg'], 'PNG' => ['png'], 'WebP' => ['webp']];
    }

    #[DataProvider('supportedFormats')]
    public function test_supported_photos_are_saved_as_bounded_square_webp(string $format): void
    {
        $user = $this->createUser();
        $path = $this->uploadPhoto($user, $this->image($format));
        $this->assertMatchesRegularExpression('~^avatars/[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\.webp$~', $path);
        Storage::disk('public')->assertExists($path);
        $size = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame('image/webp', $size['mime']);
        $this->assertSame([512, 512], [$size[0], $size[1]]);
        $this->assertSame(Storage::disk('public')->url($path), $user->fresh()->avatar_url);
    }

    public function test_wide_photos_use_the_centre_crop_and_strip_embedded_payloads(): void
    {
        $image = imagecreatetruecolor(900, 600);
        imagefilledrectangle($image, 0, 0, 149, 599, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 150, 0, 749, 599, imagecolorallocate($image, 0, 0, 255));
        imagefilledrectangle($image, 750, 0, 899, 599, imagecolorallocate($image, 0, 255, 0));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $payload = '<?php echo "profile-fixture-only"; ?>';
        $path = $this->uploadPhoto($this->createUser(), $this->upload('wide.jpeg', $bytes.$payload));
        $stored = Storage::disk('public')->get($path);
        $this->assertStringNotContainsString($payload, $stored);
        $cropped = imagecreatefromstring($stored);
        $this->assertSame(512, imagesx($cropped));
        $this->assertSame(512, imagesy($cropped));
        foreach ([[16, 256], [256, 256], [495, 256]] as [$x, $y]) {
            $pixel = imagecolorat($cropped, $x, $y);
            $this->assertLessThan(10, ($pixel >> 16) & 255);
            $this->assertLessThan(10, ($pixel >> 8) & 255);
            $this->assertGreaterThan(245, $pixel & 255);
        }
        imagedestroy($cropped);
    }

    public function test_replacing_and_removing_a_photo_updates_only_the_owners_avatar(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $otherPath = 'avatars/'.Str::uuid().'.webp';
        Storage::disk('public')->put($otherPath, 'other users photo fixture');
        $other->forceFill(['avatar_path' => $otherPath])->save();
        $oldPath = $this->uploadPhoto($user);
        $this->post(route('profile.avatar.store'), ['avatar' => $this->image('jpeg'), 'user_id' => $other->id, 'avatar_path' => $otherPath, 'role' => 'super_user'])
            ->assertSessionHasNoErrors();
        $newPath = $user->fresh()->avatar_path;
        $this->assertNotSame($oldPath, $newPath);
        $this->assertSame('member', $user->fresh()->role);
        $this->assertSame($otherPath, $other->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists([$newPath, $otherPath]);
        $this->delete(route('profile.avatar.destroy'), ['user_id' => $other->id])
            ->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing($newPath);
        Storage::disk('public')->assertExists($otherPath);
        $this->assertSame($otherPath, $other->fresh()->avatar_path);
    }

    public function test_header_and_approved_comments_share_the_photo_and_removed_photo_returns_to_initials(): void
    {
        $user = $this->createUser();
        $user->forceFill(['name' => 'Adem VAROL'])->save();
        $path = $this->uploadPhoto($user);
        $url = Storage::disk('public')->url($path);
        $comment = new Comment(['body' => 'Avatar görünürlük kontrolü']);
        $comment->forceFill(['user_id' => $user->id, 'status' => 'approved', 'approved_at' => now()])->save();
        $this->get(route('profile.edit'))->assertOk()->assertSee('src="'.$url.'"', false);
        $response = $this->get(route('comments.index'))->assertOk();
        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'src="'.$url.'"'));
        $this->delete(route('profile.avatar.destroy'))->assertSessionHasNoErrors();
        $response = $this->get(route('profile.edit'))->assertOk()->assertDontSee($url, false);
        $this->assertMatchesRegularExpression('~>\s*AV\s*</span>~', $response->getContent());
    }

    public static function invalidUploads(): array
    {
        return [
            'missing file' => ['missing'],
            'array instead of file' => ['array'],
            'executable disguised as JPEG' => ['php'],
            'scriptable SVG' => ['svg'],
            'unsupported GIF' => ['gif'],
            'larger than two megabytes' => ['size'],
            'larger than 4096 pixels' => ['edge'],
            'larger than twelve megapixels' => ['pixels'],
        ];
    }

    #[DataProvider('invalidUploads')]
    public function test_invalid_uploads_leave_the_previous_photo_and_account_unchanged(string $case): void
    {
        $user = $this->createUser();
        $oldPath = $this->uploadPhoto($user);
        $payload = match ($case) {
            'missing' => [],
            'array' => ['avatar' => ['not-a-file']],
            'php' => ['avatar' => $this->upload('payload.jpg', '<?php echo "fixture";')],
            'svg' => ['avatar' => $this->upload('payload.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            'gif' => ['avatar' => $this->image('gif')],
            'size' => ['avatar' => $this->upload('large.jpg', file_get_contents($this->image('jpeg')->getRealPath()).str_repeat('x', 2 * 1024 * 1024))],
            'edge' => ['avatar' => $this->image('png', 4097, 1)],
            'pixels' => ['avatar' => $this->image('png', 4096, 2930)],
        };
        $this->postJson(route('profile.avatar.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->assertSame($oldPath, $user->fresh()->avatar_path);
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles());
    }

    public function test_guest_and_blocked_accounts_cannot_add_or_remove_photos(): void
    {
        $this->post(route('profile.avatar.store'), ['avatar' => $this->image()])->assertRedirect(route('login'));
        $this->delete(route('profile.avatar.destroy'))->assertRedirect(route('login'));
        $user = $this->createUser();
        $path = $this->uploadPhoto($user);
        $user->forceFill(['is_blocked' => true])->save();
        $this->post(route('profile.avatar.store'), ['avatar' => $this->image()])->assertForbidden();
        $this->actingAs($user)->delete(route('profile.avatar.destroy'))->assertForbidden();
        $this->assertSame($path, $user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_unverified_members_can_manage_their_own_profile_photo(): void
    {
        $user = $this->createUser(false);
        $path = $this->uploadPhoto($user);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->delete(route('profile.avatar.destroy'))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_avatar_path_is_not_mass_assignable_or_accepted_in_regular_profile_updates(): void
    {
        $user = $this->createUser();
        $path = $this->uploadPhoto($user);
        $user->fill(['avatar_path' => 'avatars/'.Str::uuid().'.webp'])->save();
        $this->assertSame($path, $user->fresh()->avatar_path);
        $this->patch(route('profile.update'), ['name' => 'Adem VAROL', 'email' => $user->email, 'current_password' => self::USER_PASSWORD, 'avatar_path' => '../untrusted.php'])
            ->assertSessionHasNoErrors();
        $this->assertSame($path, $user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_registration_cannot_claim_an_existing_avatar_path(): void
    {
        $path = 'avatars/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, 'existing unrelated avatar fixture');
        $this->post(route('register'), [
            'name' => 'Yeni Üye', 'email' => 'avatar-registration@example.test', 'email_confirmation' => 'avatar-registration@example.test',
            'password' => self::USER_PASSWORD, 'password_confirmation' => self::USER_PASSWORD,
            'terms' => '1', 'website' => '', 'avatar_path' => $path,
        ])->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();
        $user = User::where('email', 'avatar-registration@example.test')->sole();
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->avatar_url);
        Storage::disk('public')->assertExists($path);
    }

    public function test_missing_or_unsafe_avatar_paths_fall_back_without_deleting_unrelated_media(): void
    {
        $user = $this->createUser();
        $user->forceFill(['name' => 'Adem VAROL'])->save();
        Storage::disk('public')->put('media/keep.webp', 'unrelated media fixture');
        foreach (['avatars/'.Str::uuid().'.webp', '../private.txt', 'media/keep.webp', 'https://example.test/photo.webp'] as $path) {
            $user->forceFill(['avatar_path' => $path])->save();
            $this->assertNull($user->fresh()->avatar_url);
            $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();
            $this->assertMatchesRegularExpression('~>\s*AV\s*</span>~', $response->getContent());
        }
        $user->forceFill(['avatar_path' => 'media/keep.webp'])->save();
        $this->delete(route('profile.avatar.destroy'))->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists('media/keep.webp');
    }

    public function test_database_failure_removes_the_new_upload_and_preserves_the_old_file_and_record(): void
    {
        $user = $this->createUser();
        $oldPath = $this->uploadPhoto($user);
        User::saving(function (User $model) use ($user): void {
            if ($model->id === $user->id && $model->isDirty('avatar_path')) {
                throw new \RuntimeException('Simulated avatar record failure.');
            }
        });
        try {
            $this->post(route('profile.avatar.store'), ['avatar' => $this->image()])->assertStatus(500);
            $this->assertSame($oldPath, $user->fresh()->avatar_path);
            $this->assertSame([$oldPath], Storage::disk('public')->allFiles());
        } finally {
            User::flushEventListeners();
        }
    }

    public static function storageFailures(): array
    {
        return ['write returns false' => [false], 'write throws' => [true]];
    }

    #[DataProvider('storageFailures')]
    public function test_storage_failure_returns_a_field_error_and_preserves_the_previous_photo(bool $throws): void
    {
        $user = $this->createUser();
        $oldPath = $this->uploadPhoto($user);
        $disk = Storage::disk('public');
        $failingDisk = \Mockery::mock($disk);
        $expectation = $failingDisk->shouldReceive('put')->once();
        if ($throws) {
            $expectation->andThrow(new \RuntimeException('Simulated profile photo storage failure.'));
        } else {
            $expectation->andReturn(false);
        }
        Storage::set('public', $failingDisk);
        $this->postJson(route('profile.avatar.store'), ['avatar' => $this->image()])
            ->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->assertSame($oldPath, $user->fresh()->avatar_path);
        $this->assertSame([$oldPath], $disk->allFiles());
    }

    public function test_admin_account_deletion_cleans_the_deleted_users_photo_and_keeps_other_photos(): void
    {
        $member = $this->createUser();
        $memberPath = $this->uploadPhoto($member);
        $other = $this->createUser();
        $otherPath = $this->uploadPhoto($other);
        $admin = $this->createUser(true, true);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('admin.users.destroy', $member), ['confirm_delete' => '1'])
            ->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'avatar_path' => $otherPath]);
        Storage::disk('public')->assertMissing($memberPath);
        Storage::disk('public')->assertExists($otherPath);
    }

    public function test_repeated_avatar_updates_are_throttled_before_further_files_are_written(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('profile.avatar.store'), [])->assertSessionHasErrors('avatar');
        }
        $this->post(route('profile.avatar.store'), ['avatar' => $this->image()])->assertStatus(429);
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
