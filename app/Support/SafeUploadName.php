<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * File names for uploads that are stored where the web server can serve them (public/storage).
 *
 * The extension of a stored file decides how Apache treats it: a name ending in .php is run, not downloaded. The client's own extension
 * (UploadedFile::getClientOriginalExtension) is whatever the sender typed, and the `mimes:` validation rule judges the file's CONTENT, so
 * a real JPEG carrying code, uploaded as "proof.php", used to be saved as proof.php. The extension here is the one the content says it is,
 * from a short allow-list, and the name is random so that a stored receipt cannot be found by counting.
 */
class SafeUploadName
{
    /**
     * @param  list<string>  $allowed  lower-case extensions this upload may have (jpeg is treated as jpg)
     */
    public static function extension(UploadedFile $file, array $allowed = ['jpg', 'png', 'pdf'], string $field = 'file'): string
    {
        $guessed = strtolower((string) $file->guessExtension());
        $guessed = $guessed === 'jpeg' ? 'jpg' : $guessed;

        if (!in_array($guessed, $allowed, true)) {
            throw ValidationException::withMessages([$field => 'This file type is not allowed. Upload ' . implode(', ', $allowed) . '.']);
        }

        return $guessed;
    }

    /** "<prefix>_<id>_<unix time>_<16 random hex>.<extension from the content>" */
    public static function make(UploadedFile $file, string $prefix, int|string $id, array $allowed = ['jpg', 'png', 'pdf'], string $field = 'file'): string
    {
        return $prefix . '_' . $id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . self::extension($file, $allowed, $field);
    }
}
