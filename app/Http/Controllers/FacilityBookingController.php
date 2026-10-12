<?php

namespace App\Http\Controllers;

use App\Exceptions\FacilityBookingException;
use App\Models\Building;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Society;
use App\Models\VillaArea;
use App\Services\FacilityBookingService;
use App\Support\UserFlatResolver;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\AuthorizesSocietyAccess;

class FacilityBookingController extends Controller
{
    use AuthorizesSocietyAccess;

    public function index(Request $request)
    {
        // Check if user has permission to view bookings
        // Allow: Users with view_own_bookings or manage_facilities permissions, or specific roles
        $user = auth()->user();
        if (!$user->can('view_own_bookings') && !$user->can('manage_facilities') &&
            !$user->hasRole('Owner') && !$user->hasRole('Villa Owner') && !$user->hasRole('Apartment Owner') &&
            !$user->hasRole('Resident') && !$user->hasRole('Tenant')) {
            abort(403, 'You do not have permission to view bookings.');
        }

        $societyId = $user->society_id;
        
        // If user has no society_id, deny access (except Super Admin)
        if (!$societyId && !$user->hasRole('Super Admin')) {
            abort(403, 'Access denied: No society assigned.');
        }

        $query = FacilityBooking::with(['facility', 'user', 'flat.building']);
        
        // Apply society filter (Super Admin sees all, others see only their society)
        if ($societyId) {
            $query->where('society_id', $societyId);
        }

        // Role-based filtering
        if ($user->hasRole('Villa Owner')) {
            // Villa Owner: See their own villas' bookings OR bookings they created
            $villas = collect();
            
            // Try to get villa through ownedVilla relationship (if user has owner_id)
            if ($user->owner_id) {
                $ownedVilla = $user->ownedVilla;
                if ($ownedVilla) {
                    $villas->push($ownedVilla->id);
                }
            }
            
            // Also check for villas with direct owner_id match
            $directVillas = Flat::ownedBy($user->owner_id)
                               ->where('property_type', 'villa')
                               ->pluck('id');
            
            $villas = $villas->merge($directVillas)->unique();
            
            if ($villas->count() > 0) {
                $query->where(function ($q) use ($villas, $user) {
                    $q->whereIn('flat_id', $villas)
                      ->orWhere('user_id', $user->id);
                });
            } else {
                // No villas owned, show only bookings created by this user
                $query->where('user_id', $user->id);
            }
        } elseif ($user->hasRole('Apartment Owner')) {
            // Apartment Owner: See bookings for their own apartments OR bookings they created
            $ownedApartments = Flat::ownedBy($user->owner_id)
                                   ->where('property_type', 'apartment')
                                   ->pluck('id');
            if ($ownedApartments->count() > 0) {
                $query->where(function ($q) use ($ownedApartments, $user) {
                    $q->whereIn('flat_id', $ownedApartments)
                      ->orWhere('user_id', $user->id);
                });
            } else {
                // No apartments owned, show only bookings created by this user
                $query->where('user_id', $user->id);
            }
        } elseif ($user->hasRole('Owner')) {
            // Owner (canonical role): see bookings for every property they
            // own, villa or apartment, OR bookings they created.
            $ownedFlats = Flat::ownedBy($user->owner_id)->pluck('id');
            if ($ownedFlats->count() > 0) {
                $query->where(function ($q) use ($ownedFlats, $user) {
                    $q->whereIn('flat_id', $ownedFlats)
                      ->orWhere('user_id', $user->id);
                });
            } else {
                $query->where('user_id', $user->id);
            }
        } elseif ($user->hasRole('Resident') || $user->hasRole('Tenant')) {
            // Resident/Tenant: See only their own bookings
            $query->where('user_id', $user->id);
        }
        // Admin: See all bookings for their society (no additional filter needed)

        // For admins, default to showing pending bookings if no status filter is applied
        $defaultStatus = null;
        if ($user->can('manage_facilities') && !$request->filled('status') && !$request->hasAny(['search', 'facility_id', 'date_from', 'date_to'])) {
            $defaultStatus = 'pending';
        }

        // Filters
        //
        // The two clauses must be grouped. Ungrouped, the trailing
        // orWhereHas bound at the top level of the WHERE and escaped the
        // role-based scoping applied above - so a resident searching for a
        // facility by name received every booking of that facility in the
        // system, not just their own.
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($outer) use ($search) {
                $outer->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })->orWhereHas('facility', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            });
        }

        if ($request->filled('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif ($defaultStatus) {
            $query->where('status', $defaultStatus);
        }

        if ($request->filled('date_from')) {
            $query->where('booking_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('booking_date', '<=', $request->date_to);
        }

        $bookings = $query->latest()->paginate(15);

        // Facilities dropdown should also be filtered by society
        $facilitiesQuery = Facility::where('status', 'active');
        if ($societyId) {
            $facilitiesQuery->where('society_id', $societyId);
        }
        $facilities = $facilitiesQuery->get();

        // Stats should also be filtered by role
        $statsQuery = FacilityBooking::query();
        if ($societyId) {
            $statsQuery->where('society_id', $societyId);
        }

        // Apply same role-based filtering to stats
        if ($user->hasRole('Villa Owner')) {
            $villas = collect();
            
            // Try to get villa through ownedVilla relationship (if user has owner_id)
            if ($user->owner_id) {
                $ownedVilla = $user->ownedVilla;
                if ($ownedVilla) {
                    $villas->push($ownedVilla->id);
                }
            }
            
            // Also check for villas with direct owner_id match
            $directVillas = Flat::ownedBy($user->owner_id)
                               ->where('property_type', 'villa')
                               ->pluck('id');
            
            $villas = $villas->merge($directVillas)->unique();
            
            if ($villas->count() > 0) {
                $statsQuery->where(function ($q) use ($villas, $user) {
                    $q->whereIn('flat_id', $villas)
                      ->orWhere('user_id', $user->id);
                });
            } else {
                // No villas owned, show only bookings created by this user
                $statsQuery->where('user_id', $user->id);
            }
        } elseif ($user->hasRole('Apartment Owner')) {
            $ownedApartments = Flat::ownedBy($user->owner_id)
                                   ->where('property_type', 'apartment')
                                   ->pluck('id');
            if ($ownedApartments->count() > 0) {
                $statsQuery->where(function ($q) use ($ownedApartments, $user) {
                    $q->whereIn('flat_id', $ownedApartments)
                      ->orWhere('user_id', $user->id);
                });
            } else {
                $statsQuery->where('user_id', $user->id);
            }
        } elseif ($user->hasRole('Owner')) {
            $ownedFlats = Flat::ownedBy($user->owner_id)->pluck('id');
            if ($ownedFlats->count() > 0) {
                $statsQuery->where(function ($q) use ($ownedFlats, $user) {
                    $q->whereIn('flat_id', $ownedFlats)
                      ->orWhere('user_id', $user->id);
                });
            } else {
                $statsQuery->where('user_id', $user->id);
            }
        } elseif ($user->hasRole('Resident') || $user->hasRole('Tenant')) {
            $statsQuery->where('user_id', $user->id);
        }

        $stats = [
            'total' => $statsQuery->count(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
        ];

        return view('facility-bookings.index', compact('bookings', 'facilities', 'stats', 'defaultStatus'));
    }

    public function create()
    {
        $user = auth()->user();
        
        // Check if user has permission to book facilities
        // Allow: Users with book_facility permission, Admins, Owners (canonical or legacy)
        if (!$user->can('book_facility') && !$user->can('manage_facilities') &&
            !$user->hasRole('Owner') && !$user->hasRole('Villa Owner') && !$user->hasRole('Apartment Owner')) {
            abort(403, 'You do not have permission to book facilities.');
        }

        $societyId = $user->society_id;
        
        // Get facilities for the current society
        $facilities = Facility::where('status', 'active')
                             ->where('society_id', $societyId)
                             ->get();
        
        // Determine property type and get relevant properties based on user's role
        $propertyType = null;
        $properties = collect();
        $society = Society::find($societyId);
        
        if ($user->hasRole('Villa Owner')) {
            $propertyType = 'villa';
            // Get user's owned villas through the ownedVilla relationship or direct owner_id
            $villas = collect();
            
            // Try to get villa through ownedVilla relationship (if user has owner_id)
            if ($user->owner_id) {
                $ownedVilla = $user->ownedVilla;
                if ($ownedVilla) {
                    $villas->push($ownedVilla);
                }
            }
            
            // Also check for villas with direct owner_id match
            $directVillas = Flat::with('villaArea')
                            ->ownedBy($user->owner_id)
                            ->where('property_type', 'villa')
                            ->where('society_id', $societyId)
                            ->get();
            
            $villas = $villas->merge($directVillas)->unique('id');
            
            // If no villas found, get all villas in the society as fallback
            if ($villas->isEmpty()) {
                $villas = Flat::with('villaArea')
                            ->where('property_type', 'villa')
                            ->where('society_id', $societyId)
                            ->get();
            }
            
            $properties = $villas->map(function ($villa) use ($society) {
                $villaName = !empty($villa->villa_name) ? $villa->villa_name : $villa->flat_number;
                return [
                    'id' => $villa->id,
                    'display_name' => "Villa Owner {$society->name} Villa - {$villaName}"
                ];
            });
            
            // If no properties found, show admin controls to manually select
            if ($properties->isEmpty()) {
                $villaAreas = VillaArea::where('status', 'active')
                                      ->where('society_id', $societyId)
                                      ->get();
                
                return view('facility-bookings.create', compact('facilities', 'villaAreas', 'user', 'propertyType', 'properties', 'society'));
            }
        }
        elseif ($user->hasRole('Apartment Owner')) {
            $propertyType = 'apartment';
            // Get user's owned apartments - check both owner_id and residents relationship
            $properties = Flat::with('building')
                            ->where('property_type', 'apartment')
                            ->where('society_id', $societyId)
                            ->where(function ($query) use ($user) {
                                $query->ownedBy($user->owner_id)
                                      ->orWhereHas('residents', function ($q) use ($user) {
                                          $q->where('user_id', $user->id)
                                            ->where('status', 'active');
                                      });
                            })
                            ->get()
                            ->map(function ($apartment) use ($society) {
                                $buildingName = $apartment->building ? $apartment->building->name : 'Unknown';
                                return [
                                    'id' => $apartment->id,
                                    'display_name' => "Apartment Owner {$society->name} Apartment - {$buildingName} {$apartment->flat_number}"
                                ];
                            });
            
            // If no properties found, show admin controls to manually select
            if ($properties->isEmpty()) {
                $buildings = Building::where('status', 'active')
                                    ->where('society_id', $societyId)
                                    ->get();

                return view('facility-bookings.create', compact('facilities', 'buildings', 'user', 'propertyType', 'properties', 'society'));
            }
        }
        elseif ($user->hasRole('Owner')) {
            // Owner (canonical role): every property they own, villa or
            // apartment, in one list - property type is per-flat, not
            // something the form needs to ask again.
            $ownedFlats = Flat::with(['building', 'villaArea'])
                            ->ownedBy($user->owner_id)
                            ->where('society_id', $societyId)
                            ->get();

            $properties = $ownedFlats->map(function ($flat) use ($society) {
                if ($flat->property_type === 'villa') {
                    $villaName = !empty($flat->villa_name) ? $flat->villa_name : $flat->flat_number;
                    return ['id' => $flat->id, 'display_name' => "Villa - {$villaName}"];
                }

                $buildingName = $flat->building ? $flat->building->name : 'Unknown';
                return ['id' => $flat->id, 'display_name' => "Apartment - {$buildingName} {$flat->flat_number}"];
            });

            $propertyType = $ownedFlats->pluck('property_type')->unique()->count() === 1
                ? $ownedFlats->first()?->property_type
                : null;
        } elseif ($user->hasRole('Resident') || $user->hasRole('Tenant')) {
            // Get user's flats (could be apartment or villa)
            // Don't filter by status - check both owner_id and residents relationship
            $userFlats = $this->unitsOf($user, $societyId);
            
            // Separate apartments and villas
            $apartments = $userFlats->where('property_type', 'apartment');
            $villas = $userFlats->where('property_type', 'villa');
            
            // If user has both types, we'll handle it in the view
            // For now, determine primary type
            if ($apartments->count() > 0) {
                $propertyType = 'apartment';
                $properties = $apartments->map(function ($apartment) use ($society) {
                    $buildingName = $apartment->building ? $apartment->building->name : 'Unknown';
                    return [
                        'id' => $apartment->id,
                        'display_name' => "Tenant {$society->name} Apartment - {$buildingName} {$apartment->flat_number}"
                    ];
                });
            } elseif ($villas->count() > 0) {
                $propertyType = 'villa';
                $properties = $villas->map(function ($villa) use ($society) {
                    $villaName = !empty($villa->villa_name) ? $villa->villa_name : $villa->flat_number;
                    return [
                        'id' => $villa->id,
                        'display_name' => "Tenant {$society->name} Villa - {$villaName}"
                    ];
                });
            }
        } elseif ($user->can('manage_facilities')) {
            // Admin can book for any property - show both apartments and villas
            $buildings = Building::where('status', 'active')
                                ->where('society_id', $societyId)
                                ->get();
            
            $villaAreas = VillaArea::where('status', 'active')
                                  ->where('society_id', $societyId)
                                  ->get();
            
            return view('facility-bookings.create', compact('facilities', 'buildings', 'villaAreas', 'user', 'propertyType', 'properties', 'society'));
        }
        
        return view('facility-bookings.create', compact('facilities', 'user', 'propertyType', 'properties', 'society'));
    }

    /**
     * The units this person may book for: the same set store() enforces
     * (UserFlatResolver - active tenancy, active residency, ownership), scoped
     * to the society. The form used to list ownedBy(owner_id) plus ANY
     * residency row, closed ones included, and in edit() only `occupied` flats:
     * a tenant who moved was offered the unit they had LEFT, a tenant created
     * from Admin -> Tenants (no residency row) saw an empty list, and a unit
     * that is `on_rent` was never offered to the tenant living in it.
     */
    private function unitsOf(\App\Models\User $user, int $societyId)
    {
        $ids = UserFlatResolver::flatIdsFor($user);

        return Flat::with('building', 'villaArea')
            ->where('society_id', $societyId)
            ->whereIn('id', $ids === [] ? [0] : $ids)
            ->get();
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        // Check if user has permission to book facilities
        // Allow: Users with book_facility permission, Admins, Owners (canonical or legacy)
        if (!$user->can('book_facility') && !$user->can('manage_facilities') &&
            !$user->hasRole('Owner') && !$user->hasRole('Villa Owner') && !$user->hasRole('Apartment Owner')) {
            abort(403, 'You do not have permission to book facilities.');
        }

        $validated = $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'flat_id' => 'required|exists:flats,id',
            // "Today" is the society's calendar day, not the server's (UTC): `after_or_equal:today` refused a valid
            // same-day booking for a society whose local date is behind UTC. The service re-checks the whole window.
            'booking_date' => 'required|date|after_or_equal:' . \App\Support\SocietyClock::today(auth()->user()->society)->toDateString(),
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'purpose' => 'nullable|string|max:500',
            'expected_guests' => 'nullable|integer|min:0',
        ]);

        $flat = Flat::findOrFail($validated['flat_id']);

        // Verify the flat belongs to the current user's society
        if ($flat->society_id !== $user->society_id) {
            return back()->with('error', 'Invalid property selection.');
        }

        // A resident/owner picks their own flat from a pre-populated list the
        // server built (create(), above) - the browser never showed anyone
        // else's flat_id as an option. But the field is still a plain form
        // input, so a same-society resident could otherwise hand-craft a
        // request naming a neighbour's flat_id and book on their behalf.
        // Only manage_facilities (Admin/Staff booking for someone else) may
        // name a flat the requester does not themselves occupy or own.
        if (!$user->can('manage_facilities') && !UserFlatResolver::userHasFlat($user, $flat->id)) {
            return back()->with('error', 'You can only book a facility for your own property.');
        }

        // The overlap check and the insert run together under a lock on the
        // facility row (see FacilityBookingService::create), so two requests
        // for one slot cannot both succeed. The facility must belong to the
        // same society as the property being booked for - a forged
        // facility_id from another society is "not found". The facility's own
        // rules (hours, days, how far ahead, longest booking, approval,
        // charge) apply to residents; the office (manage_facilities) may book
        // outside them on someone's behalf, but never into a taken slot.
        try {
            $booking = FacilityBookingService::create(
                (int) $flat->society_id,
                $user,
                (int) $validated['facility_id'],
                (int) $validated['flat_id'],
                $validated,
                enforceRules: !$user->can('manage_facilities')
            );
        } catch (FacilityBookingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // This path used to tell nobody: the office never learned a request
        // existed and the resident got no acknowledgement (the API and the
        // legacy portal both already notified). Only after the booking is
        // saved, and not when the office books for itself.
        if (!$user->can('manage_facilities')) {
            \App\Support\BusinessNotifications::facilityBookingRequested($booking->load('facility'));
        }

        return redirect()->route('facility-bookings.index')
            ->with('success', $booking->status === 'approved'
                ? 'Booking confirmed.'
                : 'Booking request submitted successfully. Waiting for admin approval.');
    }

    public function show(FacilityBooking $facilityBooking)
    {
        // Nothing here checked society membership, so any signed-in user of
        // any role in any society could view any other society's booking by
        // id - it is not gated by manage_facilities the way edit/update/
        // destroy are, so this was the widest-reach of the four.
        $this->authorizeSocietyAccess($facilityBooking);

        $facilityBooking->load(['facility', 'user', 'flat.building', 'society']);
        return view('facility-bookings.show', compact('facilityBooking'));
    }

    public function edit(FacilityBooking $facilityBooking)
    {
        $user = auth()->user();

        // The manage_facilities bypass below is a role permission, not
        // scoped to one society, so an Admin holding it could otherwise
        // reach any society's booking by id.
        $this->authorizeSocietyAccess($facilityBooking);

        // Users can edit their own booking details, but only admin can edit status
        // Allow: Users with book_facility permission, Admins, Owners (canonical or legacy)
        if (!$user->can('book_facility') && !$user->can('manage_facilities') &&
            !$user->hasRole('Owner') && !$user->hasRole('Villa Owner') && !$user->hasRole('Apartment Owner')) {
            abort(403, 'You do not have permission to edit bookings.');
        }

        // Users can only edit their own bookings (except admin who can edit all)
        if (!$user->can('manage_facilities') && $facilityBooking->user_id !== $user->id) {
            abort(403, 'You can only edit your own bookings.');
        }

        // Only pending bookings can be edited
        if (!in_array($facilityBooking->status, ['pending'])) {
            return redirect()->route('facility-bookings.index')
                ->with('error', 'Only pending bookings can be edited.');
        }

        // Eager load flat relationships for display
        $facilityBooking->load(['flat.building', 'flat.villaArea', 'user']);

        $societyId = $user->society_id;
        
        // Get facilities for the current society
        $facilities = Facility::where('status', 'active')
                             ->where('society_id', $societyId)
                             ->get();
        
        // Determine property type and get relevant properties based on user's role
        $propertyType = $facilityBooking->flat->property_type;
        $properties = collect();
        $society = Society::find($societyId);
        
        if ($user->hasRole('Villa Owner')) {
            // Get user's owned villas through the ownedVilla relationship or direct owner_id
            $villas = collect();
            
            // Try to get villa through ownedVilla relationship (if user has owner_id)
            if ($user->owner_id) {
                $ownedVilla = $user->ownedVilla;
                if ($ownedVilla) {
                    $villas->push($ownedVilla);
                }
            }
            
            // Also check for villas with direct owner_id match
            $directVillas = Flat::with('villaArea')
                            ->ownedBy($user->owner_id)
                            ->where('property_type', 'villa')
                            ->where('society_id', $societyId)
                            ->get();
            
            $villas = $villas->merge($directVillas)->unique('id');
            
            // If no villas found, get all villas in the society as fallback
            if ($villas->isEmpty()) {
                $villas = Flat::with('villaArea')
                            ->where('property_type', 'villa')
                            ->where('society_id', $societyId)
                            ->get();
            }
            
            $properties = $villas->map(function ($villa) use ($society) {
                $villaName = !empty($villa->villa_name) ? $villa->villa_name : $villa->flat_number;
                return [
                    'id' => $villa->id,
                    'display_name' => "Villa Owner {$society->name} Villa - {$villaName}"
                ];
            });
        } elseif ($user->hasRole('Apartment Owner')) {
            // Get user's owned apartments - check both owner_id and residents relationship
            $properties = Flat::with('building')
                            ->where('property_type', 'apartment')
                            ->where('society_id', $societyId)
                            ->where(function ($query) use ($user) {
                                $query->ownedBy($user->owner_id)
                                      ->orWhereHas('residents', function ($q) use ($user) {
                                          $q->where('user_id', $user->id)
                                            ->where('status', 'active');
                                      });
                            })
                            ->get()
                            ->map(function ($apartment) use ($society) {
                                $buildingName = $apartment->building ? $apartment->building->name : 'Unknown';
                                return [
                                    'id' => $apartment->id,
                                    'display_name' => "Apartment Owner {$society->name} Apartment - {$buildingName} {$apartment->flat_number}"
                                ];
                            });
        } elseif ($user->hasRole('Owner')) {
            // Owner (canonical role): every property they own, in one list.
            $properties = Flat::with(['building', 'villaArea'])
                            ->ownedBy($user->owner_id)
                            ->where('society_id', $societyId)
                            ->get()
                            ->map(function ($flat) use ($society) {
                                if ($flat->property_type === 'villa') {
                                    $villaName = !empty($flat->villa_name) ? $flat->villa_name : $flat->flat_number;
                                    return ['id' => $flat->id, 'display_name' => "Villa - {$villaName}"];
                                }

                                $buildingName = $flat->building ? $flat->building->name : 'Unknown';
                                return ['id' => $flat->id, 'display_name' => "Apartment - {$buildingName} {$flat->flat_number}"];
                            });
        } elseif ($user->hasRole('Resident') || $user->hasRole('Tenant')) {
            // Get user's flats (could be apartment or villa)
            $userFlats = $this->unitsOf($user, $societyId);
            
            // Filter by property type
            $properties = $userFlats->where('property_type', $propertyType)
                                   ->map(function ($flat) use ($society, $propertyType) {
                                       if ($propertyType === 'apartment') {
                                           $buildingName = $flat->building ? $flat->building->name : 'Unknown';
                                           return [
                                               'id' => $flat->id,
                                               'display_name' => "Tenant {$society->name} Apartment - {$buildingName} {$flat->flat_number}"
                                           ];
                                       } else {
                                           $villaName = !empty($flat->villa_name) ? $flat->villa_name : $flat->flat_number;
                                           return [
                                               'id' => $flat->id,
                                               'display_name' => "Tenant {$society->name} Villa - {$villaName}"
                                           ];
                                       }
                                   });
        } elseif ($user->can('manage_facilities')) {
            // Admin can edit any property - show both apartments and villas
            $buildings = Building::where('status', 'active')
                                ->where('society_id', $societyId)
                                ->get();
            
            $villaAreas = VillaArea::where('status', 'active')
                                  ->where('society_id', $societyId)
                                  ->get();
            
            return view('facility-bookings.edit', compact('facilityBooking', 'facilities', 'buildings', 'villaAreas', 'user', 'propertyType', 'properties', 'society'));
        }
        
        return view('facility-bookings.edit', compact('facilityBooking', 'facilities', 'user', 'propertyType', 'properties', 'society'));
    }

    public function editStatus(FacilityBooking $facilityBooking)
    {
        // Only admin can edit booking status
        if (!auth()->user()->can('manage_facilities')) {
            abort(403, 'You do not have permission to edit booking status. Only Admin users can change booking status.');
        }

        // Same bug class as updateStatus() (fixed earlier this session) -
        // this sibling method had no society check at all, so an Admin in
        // any society could view another society's booking details
        // (resident name, facility, dates) by id. Found by sweeping every
        // method in this controller for the same pattern, not by a report.
        $this->authorizeSocietyAccess($facilityBooking);

        return view('facility-bookings.edit-status', compact('facilityBooking'));
    }

    public function update(Request $request, FacilityBooking $facilityBooking)
    {
        $user = auth()->user();

        $this->authorizeSocietyAccess($facilityBooking);

        // Users can update their own booking details, but only admin can update status
        // Allow: Users with book_facility permission, Admins, Owners (canonical or legacy)
        if (!$user->can('book_facility') && !$user->can('manage_facilities') &&
            !$user->hasRole('Owner') && !$user->hasRole('Villa Owner') && !$user->hasRole('Apartment Owner')) {
            abort(403, 'You do not have permission to update bookings.');
        }

        // Users can only update their own bookings (except admin who can update all)
        if (!$user->can('manage_facilities') && $facilityBooking->user_id !== $user->id) {
            abort(403, 'You can only update your own bookings.');
        }

        // Only pending bookings can be updated
        if (!in_array($facilityBooking->status, ['pending'])) {
            return redirect()->route('facility-bookings.index')
                ->with('error', 'Only pending bookings can be updated.');
        }

        $validated = $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'flat_id' => 'required|exists:flats,id',
            // "Today" is the society's calendar day, not the server's (UTC): `after_or_equal:today` refused a valid
            // same-day booking for a society whose local date is behind UTC. The service re-checks the whole window.
            'booking_date' => 'required|date|after_or_equal:' . \App\Support\SocietyClock::today(auth()->user()->society)->toDateString(),
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'purpose' => 'nullable|string|max:500',
            'expected_guests' => 'nullable|integer|min:0',
        ]);

        $flat = Flat::findOrFail($validated['flat_id']);

        // Verify the flat belongs to the current user's society
        if ($flat->society_id !== auth()->user()->society_id) {
            return back()->with('error', 'Invalid property selection.');
        }

        // Same rule as store(): a resident may only keep the booking on a
        // property they themselves occupy or own, not re-point it at a
        // neighbour's flat. Only the office may move it to another unit.
        if (!$user->can('manage_facilities') && !UserFlatResolver::userHasFlat($user, $flat->id)) {
            return back()->with('error', 'You can only book a facility for your own property.');
        }

        // The facility must be one of this society's - never another's.
        $facility = Facility::where('id', $validated['facility_id'])
            ->where('society_id', $flat->society_id)
            ->first();
        if (!$facility) {
            return back()->with('error', 'Facility not found.');
        }

        // The facility's rules apply to a resident's change as they do to a
        // new booking; the office may adjust outside them.
        if (!$user->can('manage_facilities')
            && ($message = FacilityBookingService::violation($facility, $validated['booking_date'], $validated['start_time'], $validated['end_time']))) {
            return back()->withInput()->with('error', $message);
        }

        // Check-then-write, both inside the lock. Doing the overlap check in
        // its own transaction and the update() afterwards would release the
        // lock between the two, reopening the race store() has.
        $updated = DB::transaction(function () use ($validated, $facilityBooking, $facility) {
            Facility::where('id', $facility->id)->lockForUpdate()->firstOrFail();

            if (FacilityBookingService::overlaps(
                $facility,
                $validated['booking_date'],
                FacilityBookingService::minutes($validated['start_time']),
                FacilityBookingService::minutes($validated['end_time']),
                $facilityBooking->id
            )) {
                return false;
            }

            $facilityBooking->update([
                'facility_id' => $facility->id,
                'flat_id' => $validated['flat_id'],
                'booking_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'purpose' => $validated['purpose'] ?? null,
                'expected_guests' => $validated['expected_guests'] ?? 0,
                'booking_amount' => $facility->booking_charge ?? 0,
            ]);

            return true;
        });

        if (!$updated) {
            return back()->with('error', 'This time slot is already booked. Please select a different time.');
        }

        // A pending request that changed under the office's feet: tell them.
        if (!$user->can('manage_facilities')) {
            \App\Support\BusinessNotifications::facilityBookingEdited($facilityBooking->fresh('facility'));
        }

        return redirect()->route('facility-bookings.index')
            ->with('success', 'Booking updated successfully.');
    }

    public function updateStatus(Request $request, FacilityBooking $facilityBooking)
    {
        // Only admin can update booking status
        if (!auth()->user()->can('manage_facilities')) {
            abort(403, 'You do not have permission to update booking status. Only Admin users can change booking status.');
        }

        // `can('manage_facilities')` is a bare role permission with no
        // model attached - exactly the pattern CLAUDE.md §3 warns about.
        // Every sibling method here (destroy(), update()) already calls
        // this; updateStatus() did not, so an Admin in any society could
        // approve/reject/cancel another society's booking by id alone.
        // Found while adding this method's first notification - a real
        // cross-society IDOR, not a hypothetical one.
        $this->authorizeSocietyAccess($facilityBooking);

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,cancelled,completed',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $previousStatus = $facilityBooking->status;

        // Never approve (or reopen) a booking on top of another one that holds the slot.
        if ($conflict = FacilityBookingService::transitionConflict($facilityBooking, $validated['status'])) {
            return back()->with('error', $conflict);
        }

        $facilityBooking->update([
            'status' => $validated['status'],
            // 'admin_notes' is nullable, so validate() omits the key
            // entirely when the field is absent from the request rather
            // than returning null for it (the same Laravel behaviour
            // EventController::update() was fixed for) - approving or
            // cancelling with no notes previously threw an undefined-array-key
            // error here instead of saving.
            'admin_notes' => $validated['admin_notes'] ?? $facilityBooking->admin_notes,
            'approved_at' => $validated['status'] === 'approved' ? now() : $facilityBooking->approved_at,
            'cancelled_at' => $validated['status'] === 'cancelled' ? now() : $facilityBooking->cancelled_at,
        ]);

        if ($previousStatus !== $facilityBooking->status) {
            \App\Events\FacilityBookingStatusUpdated::dispatch($facilityBooking, $previousStatus);
        }

        return redirect()->route('facility-bookings.index')
            ->with('success', 'Booking status updated successfully.');
    }

    public function destroy(FacilityBooking $facilityBooking)
    {
        $this->authorizeSocietyAccess($facilityBooking);

        // Users can delete their own bookings, admin can delete any booking
        if (!auth()->user()->can('manage_facilities') && $facilityBooking->user_id !== auth()->id()) {
            abort(403, 'You can only delete your own bookings.');
        }

        // Only pending bookings can be deleted by regular users
        if (!auth()->user()->can('manage_facilities') && !in_array($facilityBooking->status, ['pending'])) {
            return redirect()->route('facility-bookings.index')
                ->with('error', 'Only pending bookings can be deleted.');
        }

        $facilityBooking->loadMissing('facility');
        $withdrawnByResident = !auth()->user()->can('manage_facilities');
        $previousStatus = $facilityBooking->status;
        // The office removing a booking that someone else still expects to use is, to that person, a cancellation.
        $cancelsSomeoneElsesBooking = !$withdrawnByResident
            && in_array($previousStatus, ['pending', 'approved'], true)
            && $facilityBooking->user_id && (int) $facilityBooking->user_id !== (int) auth()->id()
            && !\App\Support\SocietyClock::dateHasPassed($facilityBooking->booking_date, $facilityBooking->society_id);

        $facilityBooking->delete();

        if ($cancelsSomeoneElsesBooking) {
            // Tell the requester it was cancelled (email through the usual rules + in-app). The row is gone, so the
            // in-memory copy carries the new status and the links point at the list.
            $facilityBooking->status = 'cancelled';
            \App\Events\FacilityBookingStatusUpdated::dispatch($facilityBooking, $previousStatus);
        }

        // A resident withdrawing their own request is a cancellation the
        // office should hear about; the office deleting a record is not.
        if ($withdrawnByResident) {
            \App\Support\BusinessNotifications::facilityBookingCancelled($facilityBooking);
        }

        return redirect()->route('facility-bookings.index')
            ->with('success', 'Booking deleted successfully.');
    }

    // API endpoints for cascading dropdowns
    public function getFloors(Request $request)
    {
        $buildingId = $request->get('building_id');
        $societyId = auth()->user()->society_id;
        
        // Validate building belongs to user's society
        $building = Building::where('id', $buildingId)
                           ->where('society_id', $societyId)
                           ->first();
        
        if (!$building) {
            return response()->json([], 403);
        }
        
        $floors = Flat::where('building_id', $buildingId)
                     ->where('society_id', $societyId)
                     ->where('property_type', 'apartment')
                     ->distinct()
                     ->orderBy('floor')
                     ->pluck('floor')
                     ->filter()
                     ->values();
        
        return response()->json($floors);
    }

    public function getFlats(Request $request)
    {
        $buildingId = $request->get('building_id');
        $floor = $request->get('floor');
        $societyId = auth()->user()->society_id;
        
        // Validate building belongs to user's society
        $building = Building::where('id', $buildingId)
                           ->where('society_id', $societyId)
                           ->first();
        
        if (!$building) {
            return response()->json([], 403);
        }
        
        $flats = Flat::with('building')
                    ->where('building_id', $buildingId)
                    ->where('floor', $floor)
                    ->where('society_id', $societyId)
                    ->where('property_type', 'apartment')
                    ->get()
                    ->map(function ($flat) {
                        return [
                            'id' => $flat->id,
                            'flat_number' => $flat->flat_number,
                            'display_name' => $flat->flat_number
                        ];
                    });
        
        return response()->json($flats);
    }

    public function getVillas(Request $request)
    {
        $villaAreaId = $request->get('villa_area_id');
        $societyId = auth()->user()->society_id;
        
        // Validate villa area belongs to user's society
        $villaArea = VillaArea::where('id', $villaAreaId)
                             ->where('society_id', $societyId)
                             ->first();
        
        if (!$villaArea) {
            return response()->json([], 403);
        }
        
        $villas = Flat::with('villaArea')
                     ->where('villa_area_id', $villaAreaId)
                     ->where('society_id', $societyId)
                     ->where('property_type', 'villa')
                     ->get()
                     ->map(function ($villa) {
                         return [
                             'id' => $villa->id,
                             'villa_name' => $villa->villa_name ?? $villa->flat_number,
                             'display_name' => $villa->villa_name ?? $villa->flat_number
                         ];
                     });
        
        return response()->json($villas);
    }

    public function showFlat(Flat $flat)
    {
        // Validate flat belongs to user's society
        if ($flat->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        // The owner is the Owner record - flats.owner_id points at owners.id.
        // ownerUser is only their login account, which may not exist, so the
        // identity comes from the owner and the account id is reported
        // alongside it rather than standing in for it.
        $flat->load(['ownerModel', 'ownerUser']);

        return response()->json([
            'id' => $flat->id,
            'flat_number' => $flat->flat_number,
            'owner' => $flat->ownerModel ? [
                'id' => $flat->ownerModel->id,
                'name' => $flat->ownerModel->name,
                'email' => $flat->ownerModel->email,
                'user_id' => $flat->ownerUser?->id,
            ] : null
        ]);
    }
}
