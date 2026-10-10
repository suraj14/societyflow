<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use App\Http\Controllers\Concerns\AuthorizesSocietyAccess;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller
{
    use AuthorizesRequests, ValidatesRequests, HandlesFormSubmissions, AuthorizesSocietyAccess;

    /**
     * Check if user can manage facilities
     */
    protected function canManageFacilities()
    {
        return auth()->user()?->can('manage_facilities') ?? false;
    }

    /**
     * Check if user can book facilities
     */
    protected function canBookFacilities()
    {
        return auth()->user()?->can('book_facility') ?? false;
    }

    /**
     * Check if user can manage visitors
     */
    protected function canManageVisitors()
    {
        return auth()->user()?->can('view_all_visitors') ?? false;
    }

    /**
     * Check if user can view own visitors
     */
    protected function canViewOwnVisitors()
    {
        return auth()->user()?->can('view_own_visitors') ?? false;
    }

    /**
     * Check if user can check in/out visitors
     */
    protected function canCheckInVisitors()
    {
        return auth()->user()?->can('checkin_visitor') ?? false;
    }

    /**
     * Check if user can manage bills
     */
    protected function canManageBills()
    {
        return auth()->user()?->can('manage_bills') ?? false;
    }

    /**
     * Check if user can view own bills
     */
    protected function canViewOwnBills()
    {
        return auth()->user()?->can('view_own_bills') ?? false;
    }

    /**
     * Check if user can pay bills
     */
    protected function canPayBills()
    {
        return auth()->user()?->can('pay_bill') ?? false;
    }

    /**
     * Check if user can manage tickets
     */
    protected function canManageTickets()
    {
        return auth()->user()?->can('view_all_tickets') ?? false;
    }

    /**
     * Check if user can create tickets
     */
    protected function canCreateTickets()
    {
        return auth()->user()?->can('create_ticket') ?? false;
    }

    /**
     * Check if user can view own tickets
     */
    protected function canViewOwnTickets()
    {
        return auth()->user()?->can('view_own_ticket') ?? false;
    }

    /**
     * Check if user can resolve tickets
     */
    protected function canResolveTickets()
    {
        return auth()->user()?->can('resolve_ticket') ?? false;
    }

    /**
     * Check if user can manage services
     */
    protected function canManageServices()
    {
        return auth()->user()?->can('manage_services') ?? false;
    }

    /**
     * Check if user can clock in/out services
     */
    protected function canClockServices()
    {
        return auth()->user()?->can('clock_service') ?? false;
    }

    /**
     * Check if user can manage notices
     */
    protected function canManageNotices()
    {
        return auth()->user()?->can('manage_notices') ?? false;
    }

    /**
     * Check if user can view notices
     */
    protected function canViewNotices()
    {
        return auth()->user()?->can('view_notices') ?? false;
    }

    /**
     * Check if user is owner or tenant
     */
    protected function isOwnerOrTenant()
    {
        return auth()->user()?->hasAnyRole(['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant']) ?? false;
    }

    /**
     * Check if user is staff
     */
    protected function isStaff()
    {
        return auth()->user()?->hasRole('Staff') ?? false;
    }

    /**
     * Check if user is admin
     */
    protected function isAdmin()
    {
        return auth()->user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Authorize action with permission
     */
    protected function authorizePermission($permission)
    {
        if (!auth()->user()?->can($permission)) {
            Log::warning('Unauthorized permission access attempt', [
                'permission' => $permission,
                'user_id' => auth()->id(),
                'url' => request()->url()
            ]);
            abort(403, 'Unauthorized action');
        }
    }

    /**
     * Authorize action with role
     */
    protected function authorizeRole(...$roles)
    {
        if (!auth()->user()?->hasAnyRole($roles)) {
            Log::warning('Unauthorized role access attempt', [
                'required_roles' => $roles,
                'user_roles' => auth()->user()?->getRoleNames()->toArray() ?? [],
                'user_id' => auth()->id(),
                'url' => request()->url()
            ]);
            abort(403, 'Unauthorized action');
        }
    }

    /**
     * Get current user
     */
    protected function user()
    {
        return auth()->user();
    }

    /**
     * Get user's society ID with validation
     */
    protected function getSocietyId()
    {
        $user = auth()->user();
        
        if (!$user) {
            Log::error('Attempted to get society ID for unauthenticated user');
            abort(401, 'Authentication required');
        }
        
        // If user has a society_id (Admin, Manager, Owner, Tenant, etc.), return it
        if ($user->society_id) {
            return $user->society_id;
        }
        
        // For Super Admin (no society_id), return null to indicate global access
        // Controllers should handle Super Admin differently if needed
        return null;
    }

    /**
     * Get user's unit/villa
     */
    protected function getUserUnit()
    {
        return auth()->user()?->flat ?? auth()->user()?->villa;
    }

    /**
     * Validate society access for operations
     */
    /**
     * The society a NEW record belongs to. A Super Admin has none of their own (getSocietyId() is null), and
     * a create that took it from there either crashed on the NOT NULL column or stored a society-less record.
     * They add records for a society through Impersonate / "Login as Admin" on that society.
     */
    protected function requireSocietyForWrite(): int
    {
        $societyId = $this->getSocietyId();

        if (!$societyId) {
            abort(403, 'Select a society first (Super Admin: use "Login as Admin" on the society) to add records to it.');
        }

        return (int) $societyId;
    }

    /**
     * Whether the signed-in user may act on a record of this society: their own, or any society for a
     * Super Admin (who has none of their own - getSocietyId() is null for them, so a bare
     * `!== getSocietyId()` comparison silently refuses them every record). Existing-record actions only:
     * creating a record still needs a society, which a Super Admin does not have.
     */
    protected function societyMatches($recordSocietyId): bool
    {
        return (auth()->user()?->hasRole('Super Admin') ?? false)
            || (int) $recordSocietyId === (int) $this->getSocietyId();
    }

    protected function validateSocietyAccess($modelSocietyId)
    {
        $userSocietyId = $this->getSocietyId();
        
        // Super Admin has access to all societies
        if ($userSocietyId === null && auth()->user()?->hasRole('Super Admin')) {
            return true;
        }
        
        // Regular users must match society
        if ($userSocietyId !== $modelSocietyId) {
            Log::warning('Society access violation attempt', [
                'user_society_id' => $userSocietyId,
                'model_society_id' => $modelSocietyId,
                'user_id' => auth()->id(),
                'url' => request()->url()
            ]);
            abort(403, 'Access denied to this society\'s data');
        }
        
        return true;
    }
}
