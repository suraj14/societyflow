<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Society (tenant) isolation for controllers that resolve models through
 * route-model binding.
 *
 * Route-model binding happily resolves any ID in the table, so
 * `GET /payments/{payment}` returns whatever row the URL names unless the
 * controller checks ownership. Several controllers did check; several did
 * not, and PaymentController's check was gated behind
 * `if ($user->hasRole('Admin') && ...)`, which every non-Admin role
 * short-circuited past.
 *
 * The logic here already existed as BaseController::validateSocietyAccess.
 * It is extracted so controllers that extend the plain Controller can use
 * the same rule instead of each growing its own variant.
 */
trait AuthorizesSocietyAccess
{
    /**
     * Abort with 403 unless the current user may act on $societyId.
     *
     * Super Admin is global. Everyone else must match their own society.
     */
    protected function authorizeSocietyId(?int $societyId): void
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Authentication required');
        }

        // Super Admin is cross-society by design. Checked by role rather
        // than by "has no society_id", so a Super Admin who also belongs
        // to a society keeps global access.
        if ($user->hasRole('Super Admin')) {
            return;
        }

        if ($societyId === null || (int) $user->society_id !== $societyId) {
            Log::warning('Society access violation blocked', [
                'user_id'          => $user->id,
                'user_society_id'  => $user->society_id,
                'model_society_id' => $societyId,
                'url'              => request()->fullUrl(),
                'method'           => request()->method(),
            ]);

            abort(403, 'Access denied to this society\'s data.');
        }
    }

    /**
     * Abort with 403 unless $model belongs to the current user's society.
     *
     * Models without a society_id column are rejected rather than allowed:
     * silently passing an unscopable model would reintroduce the hole this
     * trait exists to close.
     */
    protected function authorizeSocietyAccess(Model $model): void
    {
        if (!array_key_exists('society_id', $model->getAttributes())) {
            Log::error('authorizeSocietyAccess called on a model with no society_id', [
                'model' => $model::class,
            ]);

            abort(403, 'Access denied.');
        }

        $societyId = $model->getAttribute('society_id');

        $this->authorizeSocietyId($societyId === null ? null : (int) $societyId);
    }

    /**
     * Abort with 403 unless the user owns $model or may administer it.
     *
     * Used for records that belong to an individual resident (their own
     * payments, their own complaints) rather than to the society at large.
     * Society isolation is enforced first, then ownership.
     *
     * @param  string  $ownerColumn  column on $model holding the owning user id
     */
    protected function authorizeOwnedRecord(Model $model, string $ownerColumn = 'user_id'): void
    {
        $this->authorizeSocietyAccess($model);

        $user = auth()->user();

        // Society staff administer every record inside their own society.
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return;
        }

        $ownerId = $model->getAttribute($ownerColumn);

        if ($ownerId === null || (int) $ownerId !== (int) $user->id) {
            Log::warning('Record ownership violation blocked', [
                'user_id'      => $user->id,
                'model'        => $model::class,
                'model_id'     => $model->getKey(),
                'owner_column' => $ownerColumn,
                'url'          => request()->fullUrl(),
            ]);

            abort(403, 'You do not have access to this record.');
        }
    }
}
