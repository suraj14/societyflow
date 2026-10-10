<?php

namespace App\Support;

use App\Exceptions\ProfileImageException;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place a person's profile picture is validated, stored, replaced
 * and cleaned up - web self-service, the admin user/owner/tenant forms and
 * the mobile API all go through here.
 *
 * Storage convention (unchanged): files live under public/storage/avatars
 * (public/storage is the symlink/junction to storage/app/public, so no
 * `storage:link` is needed on the dev box) and the database keeps the
 * relative "avatars/<name>" path. users.avatar is the canonical column;
 * owners.profile_image mirrors it for an owner's login account so the owner
 * screens and the owner's own app/header agree. Before this class an
 * owner's picture was written to owners.profile_image only, so the picture
 * an admin uploaded never reached the owner's own account.
 *
 * Replacing is ordered so a failure can never leave a profile broken: the
 * new file is stored first, the row is saved, and only then is the old file
 * removed - and only if no other row still points at it.
 */
class ProfileImage
{
    public const MAX_KB = 2048;

    /** Test seam only (same idea as ServerDiscovery.overrideBaseUrl): points file I/O at a scratch directory. */
    private static ?string $rootOverride = null;

    public static function useRoot(?string $directory): void
    {
        self::$rootOverride = $directory;
    }

    /** The directory served as /storage - public/storage unless a test overrode it. */
    public static function root(): string
    {
        return rtrim(self::$rootOverride ?? public_path('storage'), '/\\');
    }

    /** Extensions we will write. guessExtension() is content-based, never the client's filename. */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * Validation rules for an uploaded picture: a real file, of an image type
     * detected from its CONTENT (`mimes` asks fileinfo, not the client's
     * filename or Content-Type), at most MAX_KB. No pixel-dimension bounds:
     * the server never decodes or resizes the image - it stores and serves
     * the bytes - so there is no decoder to protect, and every device camera
     * size is acceptable.
     */
    public static function rules(): array
    {
        return [
            'nullable',
            'file',
            'mimes:jpg,jpeg,png,gif',
            'max:' . self::MAX_KB,
        ];
    }

    /** Store the upload under a server-generated name; returns the relative "avatars/<name>" path. */
    public static function store(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new ProfileImageException('The image upload failed. Please try again.');
        }

        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new ProfileImageException('Unsupported image type. Use JPG, PNG or GIF.');
        }

        $directory = self::root() . DIRECTORY_SEPARATOR . 'avatars';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        // Never any part of the client's filename: no colon/slash/traversal
        // byte can reach the filesystem.
        $name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

        try {
            $file->move($directory, $name);
        } catch (\Throwable $e) {
            Log::warning('Profile image could not be written', ['error' => $e->getMessage()]);

            throw new ProfileImageException('The image could not be saved. Please try again.');
        }

        return 'avatars/' . $name;
    }

    /**
     * Apply a picture change to $subject's account and, for an owner's login,
     * to the linked owner record as well.
     *
     * - no file and no remove  -> nothing changes (current picture kept);
     * - a file                 -> stored, saved, old file cleaned up;
     * - remove                 -> picture cleared, old file cleaned up.
     *
     * @return string|null the picture path now in effect (null = none)
     */
    public static function change(User|Owner $subject, ?UploadedFile $file = null, bool $remove = false): ?string
    {
        [$user, $owner] = self::accounts($subject);

        $current = $user?->avatar ?: $owner?->profile_image;

        if (!$file && !$remove) {
            return $current ?: null;
        }

        $new      = $file ? self::store($file) : null;
        $previous = array_values(array_unique(array_filter([$user?->avatar, $owner?->profile_image])));

        try {
            DB::transaction(function () use ($user, $owner, $new) {
                if ($user) {
                    $user->forceFill(['avatar' => $new])->save();
                }
                if ($owner) {
                    // Query-builder write: only this column, no model hooks.
                    Owner::whereKey($owner->getKey())->update(['profile_image' => $new]);
                    $owner->profile_image = $new;
                }
            });
        } catch (\Throwable $e) {
            // The row was not saved, so the freshly stored file is an orphan.
            if ($new) {
                self::delete($new);
            }

            throw $e;
        }

        // Only after the OUTERMOST transaction commits (runs immediately when
        // none is open): callers wrap this in their own transaction, and a
        // rollback after the old file was already gone would leave the row
        // pointing at nothing.
        DB::afterCommit(function () use ($previous, $new) {
            foreach ($previous as $path) {
                if ($path !== $new) {
                    self::deleteIfUnreferenced($path);
                }
            }
        });

        return $new;
    }

    /** Remove a stored picture if (and only if) no account or owner row still uses it. */
    public static function deleteIfUnreferenced(?string $path): void
    {
        if (!self::isManagedPath($path)) {
            return;
        }

        $stillUsed = User::where('avatar', $path)->exists()
            || Owner::where('profile_image', $path)->exists();

        if (!$stillUsed) {
            self::delete($path);
        }
    }

    /** Delete the file itself. Cleanup only - a missing file or a failed unlink never fails the request. */
    public static function delete(?string $path): void
    {
        if (!self::isManagedPath($path)) {
            return;
        }

        $full = self::root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        try {
            if (is_file($full)) {
                @unlink($full);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not remove a replaced profile image', ['path' => $path]);
        }
    }

    /**
     * Only plain relative paths inside our own folders are ever touched:
     * no external URLs, no "..", no absolute paths. (Owner pictures saved
     * before this class live under "owners/".)
     */
    public static function isManagedPath(?string $path): bool
    {
        if (!$path || str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }

        return (bool) preg_match('#^(avatars|owners|tenants)/[A-Za-z0-9._-]+$#', $path);
    }

    /** The URL a browser can load for $path, or null when there is no picture. */
    public static function webUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/' . ltrim($path, '/'));
    }

    /** @return array{0:?User,1:?Owner} the login account and linked owner record for either side. */
    private static function accounts(User|Owner $subject): array
    {
        if ($subject instanceof User) {
            return [$subject, $subject->owner_id ? Owner::find($subject->owner_id) : null];
        }

        return [User::where('owner_id', $subject->getKey())->first(), $subject];
    }
}
