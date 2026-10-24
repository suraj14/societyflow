<?php

namespace App\Support;

/**
 * An opaque tag for the release that is running, so a deployment can prove from OUTSIDE that the live site serves the code it just shipped
 * instead of assuming it (scripts/deploy/server/smoke-check.sh --expect-release).
 *
 * The packaged deploy writes a RELEASE.txt with a `release_id=` line into the application root. The tag is the first 16 hex characters of
 * sha256(release id); the pipeline computes the same value from the id it deployed (`printf %s ID | sha256sum | cut -c1-16`). It is a hash
 * on purpose: the public health response must not reveal the build time or the commit, it only has to allow a match.
 *
 * null when there is no RELEASE.txt (development, tests, a site not deployed from a package) or it holds no well-formed release id.
 */
final class ReleaseFingerprint
{
    /** Response header carrying the tag (the health endpoint only). */
    public const HEADER = 'X-SocietyFlow-Release';

    public static function of(string $releaseId): string
    {
        return substr(hash('sha256', $releaseId), 0, 16);
    }

    public static function current(): ?string
    {
        $file = config('app.release_file');
        if (!is_string($file) || !is_file($file) || !is_readable($file)) {
            return null;
        }

        $text = @file_get_contents($file, false, null, 0, 4096);
        if ($text === false || !preg_match('/^release_id=([A-Za-z0-9._-]{3,80})\r?$/m', $text, $m)) {
            return null;
        }

        return self::of($m[1]);
    }
}
