<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Flat;
use App\Models\Society;
use App\Support\PropertyOccupancy;

/**
 * Single entry point for "who lives where" - brief §15/§16.
 *
 * Apartment Assignments, Villa Assignments and Tenant Assignments already
 * exist as separate, tested, working screens (UserApartmentAssignment
 * Controller, UserVillaAssignmentController, UserTenantAssignmentController).
 * Consolidating them into one bespoke screen would mean rebuilding three
 * already-correct assign/unassign/reassign flows with different underlying
 * data shapes (owner_id vs tenant records) from scratch, which is exactly
 * the kind of rewrite CLAUDE.md §10/§12 and this brief's own §23 warn
 * against ("do not remove necessary business functionality").
 *
 * This controller is the ONE place an admin lands to manage occupancy: it
 * shows society-wide numbers and links to the specific assignment screen
 * for the operation the admin actually wants, filtered to what the
 * society's property type supports (an apartment-only society never sees a
 * "Villa Assignments" card). The three underlying routes stay reachable
 * directly too - nothing was deleted - but this page is now the sidebar's
 * one "Property & Occupancy" entry instead of three separate ones.
 */
class PropertyOccupancyController extends BaseController
{
    public function index()
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $societyId = $this->getSocietyId();
        $society = Society::find($societyId);

        $flats = Flat::bySociety($societyId);

        // Counters and the per-row badge come from the same canonical rule
        // (PropertyOccupancy), so the cards can never disagree with the rows
        // - or with the Villas page, dashboard and owner screens.
        $stats = PropertyOccupancy::counts($societyId);
        $stats['on_rent'] = $stats['tenant_occupied'];

        $properties = (clone $flats)
            ->with(['building', 'villaArea'])
            ->orderBy('property_type')
            ->orderBy('flat_number')
            ->paginate(20);

        $occupancy = PropertyOccupancy::statesFor($properties->getCollection());

        return view('admin.property-occupancy.index', compact('society', 'stats', 'properties', 'occupancy'));
    }
}
