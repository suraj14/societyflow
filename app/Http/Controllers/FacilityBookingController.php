<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Society;
use App\Models\VillaArea;
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
            $directVillas = Flat::where('owner_id', $user->owner_id)
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
            $ownedApartments = Flat::where('owner_id', $user->owner_id)
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
            $ownedFlats = Flat::where('owner_id', $user->owner_id)->pluck('id');
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
            $directVillas = Flat::where('owner_id', $user->owner_id)
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
            $ownedApartments = Flat::where('owner_id', $user->owner_id)
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
            $ownedFlats = Flat::where('owner_id', $user->owner_id)->pluck('id');
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
                            ->where('owner_id', $user->owner_id)
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
                                $query->where('owner_id', $user->owner_id)
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
                            ->where('owner_id', $user->owner_id)
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
            $userFlats = Flat::with('building', 'villaArea')
                            ->where('society_id', $societyId)
                            ->where(function ($query) use ($user) {
                                $query->where('owner_id', $user->owner_id)
                                      ->orWhereHas('residents', function ($q) use ($user) {
                                          $q->where('user_id', $user->id);
                                      });
                            })
                            ->get();
            
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
     * True if $facilityId already has a pending/approved booking on $date
     * overlapping [$startTime, $endTime] (both "H:i").
     *
     * Deliberately does not filter by booking_date or compare start_time/
     * end_time with raw SQL. Two independent casts make raw comparison
     * unreliable:
     *   - booking_date casts as `date`, but on write Eloquent persists the
     *     full `Y-m-d H:i:s` string, not a bare date. A raw
     *     where('booking_date', '2026-09-17') then never matches the stored
     *     '2026-09-17 00:00:00' on SQLite.
     *   - start_time/end_time cast as `datetime:H:i`, and on SQLite that
     *     persists a full datetime using *today's* date rather than a bare
     *     time, so a raw BETWEEN against "10:00"/"12:00" never matches
     *     either.
     * Both meant the original overlap query returned zero rows on every
     * call, silently disabling the double-booking check entirely on
     * SQLite (proven by test before this fix). MySQL's real DATE/TIME
     * column types happen to tolerate the same query, which is exactly
     * why this went unnoticed - the two engines were hiding each other's
     * bug, the failure mode CLAUDE.md §8 names directly. Filtering by
     * facility_id/status in SQL (safe on both engines) and comparing date
     * and time via the model's own casted values in PHP is correct
     * regardless of how a given driver serializes date/time casts.
     */
    private function hasOverlappingBooking(int $facilityId, string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): bool
    {
        $newStart = Carbon::createFromFormat('H:i', $startTime);
        $newEnd = Carbon::createFromFormat('H:i', $endTime);

        $query = FacilityBooking::where('facility_id', $facilityId)
            ->whereIn('status', ['pending', 'approved']);

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        foreach ($query->get(['booking_date', 'start_time', 'end_time']) as $existing) {
            if ($existing->booking_date->toDateString() !== $date) {
                continue;
            }

            $existingStart = Carbon::createFromFormat('H:i', $existing->start_time->format('H:i'));
            $existingEnd = Carbon::createFromFormat('H:i', $existing->end_time->format('H:i'));

            // Standard interval overlap: two ranges overlap unless one ends
            // at or before the other starts.
            if ($newStart->lt($existingEnd) && $existingStart->lt($newEnd)) {
                return true;
            }
        }

        return false;
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
            'booking_date' => 'required|date|after_or_equal:today',
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

        // The read-then-create below (check for an overlap, then insert) is a
        // classic TOCTOU race: two concurrent requests for the same slot can
        // both pass the overlap check before either has inserted its row.
        // lockForUpdate() on the facility row serializes every booking
        // attempt against that facility - a second transaction's
        // lockForUpdate() blocks until the first commits (or rolls back), so
        // its overlap check always sees the first booking's already-committed
        // row. This does not add a row lock on SQLite (no FOR UPDATE
        // support there), but SQLite already serializes writers at the
        // database level, so the property under test - no double booking
        // survives - holds on both engines; only the mechanism differs.
        $booking = DB::transaction(function () use ($validated, $user, $flat) {
            $facility = Facility::where('id', $validated['facility_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->hasOverlappingBooking(
                $validated['facility_id'],
                $validated['booking_date'],
                $validated['start_time'],
                $validated['end_time']
            )) {
                return null;
            }

            return FacilityBooking::create([
                'society_id' => $flat->society_id,
                'facility_id' => $validated['facility_id'],
                'user_id' => $user->id,
                'flat_id' => $validated['flat_id'],
                'booking_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'purpose' => $validated['purpose'] ?? null,
                'expected_guests' => $validated['expected_guests'] ?? 0,
                'booking_amount' => $facility->booking_charge ?? 0,
                'status' => 'pending', // Always require admin approval
            ]);
        });

        if ($booking === null) {
            return back()->with('error', 'This time slot is already booked. Please select a different time.');
        }

        return redirect()->route('facility-bookings.index')
            ->with('success', 'Booking request submitted successfully. Waiting for admin approval.');
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
                            ->where('owner_id', $user->owner_id)
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
                                $query->where('owner_id', $user->owner_id)
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
                            ->where('owner_id', $user->owner_id)
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
            $userFlats = Flat::with('building', 'villaArea')
                            ->where('society_id', $societyId)
                            ->where('status', 'occupied')
                            ->where(function ($query) use ($user) {
                                $query->where('owner_id', $user->owner_id)
                                      ->orWhereHas('residents', function ($q) use ($user) {
                                          $q->where('user_id', $user->id);
                                      });
                            })
                            ->get();
            
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
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'purpose' => 'nullable|string|max:500',
            'expected_guests' => 'nullable|integer|min:0',
        ]);

        $facility = Facility::findOrFail($validated['facility_id']);
        $flat = Flat::findOrFail($validated['flat_id']);

        // Verify the flat belongs to the current user's society
        if ($flat->society_id !== auth()->user()->society_id) {
            return back()->with('error', 'Invalid property selection.');
        }

        // Check-then-write, both inside the lock. Doing the overlap check in
        // its own transaction and the update() afterwards (as this used to)
        // would release the lock between the two, reopening the same race
        // store() has - see hasOverlappingBooking() for why the check itself
        // has to compare casted values rather than raw columns.
        $updated = DB::transaction(function () use ($validated, $facilityBooking, $facility) {
            Facility::where('id', $validated['facility_id'])->lockForUpdate()->firstOrFail();

            if ($this->hasOverlappingBooking(
                $validated['facility_id'],
                $validated['booking_date'],
                $validated['start_time'],
                $validated['end_time'],
                $facilityBooking->id
            )) {
                return false;
            }

            $facilityBooking->update([
                'facility_id' => $validated['facility_id'],
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

        return redirect()->route('facility-bookings.index')
            ->with('success', 'Booking updated successfully.');
    }

    public function updateStatus(Request $request, FacilityBooking $facilityBooking)
    {
        // Only admin can update booking status
        if (!auth()->user()->can('manage_facilities')) {
            abort(403, 'You do not have permission to update booking status. Only Admin users can change booking status.');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,cancelled,completed',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $facilityBooking->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'],
            'approved_at' => $validated['status'] === 'approved' ? now() : $facilityBooking->approved_at,
            'cancelled_at' => $validated['status'] === 'cancelled' ? now() : $facilityBooking->cancelled_at,
        ]);

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

        $facilityBooking->delete();

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
