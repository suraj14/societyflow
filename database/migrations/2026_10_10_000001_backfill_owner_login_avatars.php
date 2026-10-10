<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Before App\Support\ProfileImage, an owner's picture was saved to
 * owners.profile_image only; the owner's login account (users.avatar - what
 * the header, the profile page and the mobile app read) never received it.
 * The picture an admin uploaded therefore never appeared for the owner.
 *
 * This copies the owner record's picture to the linked login when - and only
 * when - that login has no picture of its own. It never overwrites a
 * users.avatar, never touches a file on disk, and both rows end up pointing
 * at the same path (which is why ProfileImage only deletes a file once no
 * user or owner row still references it).
 *
 * Idempotent: a second run finds nothing left to fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('owners') || !Schema::hasColumn('owners', 'profile_image')
            || !Schema::hasTable('users') || !Schema::hasColumn('users', 'avatar')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('owner_id')
            ->where(function ($query) {
                $query->whereNull('avatar')->orWhere('avatar', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    $image = DB::table('owners')->where('id', $user->owner_id)->value('profile_image');

                    if ($image) {
                        DB::table('users')->where('id', $user->id)->update(['avatar' => $image]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Intentionally a no-op. This only copied a value that already
        // existed on the owner row into an empty column; after the copy the
        // user's picture may have been replaced or removed through the app,
        // and there is no record of which avatars were backfilled versus
        // set by hand. Clearing "the backfilled ones" would risk wiping a
        // picture a person chose themselves, so a rollback leaves the
        // (harmless, correct) copies in place.
    }
};
