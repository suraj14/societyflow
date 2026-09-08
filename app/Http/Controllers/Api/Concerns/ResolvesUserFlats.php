<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use App\Support\UserFlatResolver;

/**
 * Resolves every flat/villa a user is attached to, for API queries.
 *
 * Previously bills/dashboard only looked at the active resident record,
 * so owners (especially villa owners, who have no resident row) saw
 * empty bills and a zero dashboard. It then grew ownership handling but
 * still ignored tenancy entirely, so a tenant-only account had the same
 * problem in reverse.
 *
 * The rules now live in App\Support\UserFlatResolver so the web policies
 * and controllers resolve the same set as the API, rather than each
 * reimplementing a different subset.
 */
trait ResolvesUserFlats
{
    /**
     * @return array<int> distinct flat IDs (residency + tenancy + ownership)
     */
    protected function userFlatIds(User $user): array
    {
        return UserFlatResolver::flatIdsFor($user);
    }
}
