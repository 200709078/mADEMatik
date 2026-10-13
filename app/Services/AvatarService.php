<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AvatarService
{
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_SIDE = 4096;
    private const MAX_PIXELS = 12000000;
    private const OUTPUT_SIZE = 512;

    public function store(User $user, UploadedFile $file): string
    {
        $path = $this->encodeAndStore($file);

        try {
            $previousPath = DB::transaction(function () use ($user, $path) {
                $owner = User::lockForUpdate()->findOrFail($user->getKey());
                abort_if($owner->is_blocked, 403);
                $previousPath = $owner->avatar_path;
                $owner->forceFill(['avatar_path' => $path])->save();
                ActivityLog::record('user.avatar_updated', $owner);

                return $previousPath;
            }, 3);
        } catch (Throwable $exception) {
            // The saved photo remains intact if the new user record cannot be committed.
            $this->deleteStored($path);
            throw $exception;
        }

        DB::afterCommit(fn () => $this->deleteStored($previousPath));
        $user->forceFill(['avatar_path' => $path]);

        return $path;
    }

    public function remove(User $user): void
    {
        $previousPath = DB::transaction(function () use ($user) {
            $owner = User::lockForUpdate()->findOrFail($user->getKey());
            abort_if($owner->is_blocked, 403);
            $previousPath = $owner->avatar_path;
            if ($previousPath !== null) {
                $owner->forceFill(['avatar_path' => null])->save();
                ActivityLog::record('user.avatar_removed', $owner);
            }

            return $previousPath;
        }, 3);

        DB::afterCommit(fn () => $this->deleteStored($previousPath));
        $user->forceFill(['avatar_path' => null]);
    }

    public function urlFor(?string $path): ?string
    {
        if (! $this->isStoredPath($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function deleteStored(?string $path): void
    {
        if (! $this->isStoredPath($path)) {
            return;
        }

        try {
            if (! Storage::disk('public')->delete($path)) {
                Log::warning('Profile photo cleanup failed.', ['path' => $path]);
            }
        } catch (Throwable $exception) {
            Log::warning('Profile photo cleanup failed.', ['path' => $path, 'exception' => $exception::class]);
        }
    }

    private function isStoredPath(?string $path): bool
    {
        return $path !== null && preg_match('/\Aavatars\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.webp\z/', $path) === 1;
    }

    private function encodeAndStore(UploadedFile $file): string
    {
        if (! $file->isValid() || ! is_string($file->getRealPath())) {
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_invalid')]);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_too_large')]);
        }

        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_invalid')]);
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_dimensions')]);
        }

        $inputBytes = @file_get_contents($file->getRealPath(), false, null, 0, self::MAX_BYTES + 1);
        if (! is_string($inputBytes)) {
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_unreadable')]);
        }
        if (strlen($inputBytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_too_large')]);
        }
        $source = @imagecreatefromstring($inputBytes);
        if (! $source) {
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_unreadable')]);
        }

        // Re-encoding keeps only the pixels: file names, metadata and trailing content are discarded.
        $source = $this->orientJpeg($source, $file, $info['mime']);
        $width = imagesx($source);
        $height = imagesy($source);
        $cropSize = min($width, $height);
        $outputSize = min(self::OUTPUT_SIZE, $cropSize);
        $target = imagecreatetruecolor($outputSize, $outputSize);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefilledrectangle($target, 0, 0, $outputSize, $outputSize, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, (int) floor(($width - $cropSize) / 2), (int) floor(($height - $cropSize) / 2), $outputSize, $outputSize, $cropSize, $cropSize);

        ob_start();
        try {
            $encoded = imagewebp($target, null, 85);
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($source);
            imagedestroy($target);
        }

        if (! $encoded || ! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_unreadable')]);
        }

        $path = 'avatars/'.Str::uuid().'.webp';
        try {
            $saved = Storage::disk('public')->put($path, $bytes);
        } catch (Throwable $exception) {
            $saved = false;
        }
        if (! $saved) {
            $this->deleteStored($path);
            throw ValidationException::withMessages(['avatar' => __('profile.avatar_save_failed')]);
        }

        return $path;
    }

    private function orientJpeg(\GdImage $image, UploadedFile $file, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath(), 'IFD0');
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        if (in_array($orientation, [2, 4], true)) {
            imageflip($image, $orientation === 2 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }
        if (in_array($orientation, [3, 5, 6, 7, 8], true)) {
            $angle = match ($orientation) { 3 => 180, 8 => 90, default => -90 };
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated !== false) {
                imagedestroy($image);
                $image = $rotated;
                if (in_array($orientation, [5, 7], true)) {
                    imageflip($image, $orientation === 5 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
                }
            }
        }

        return $image;
    }
}
