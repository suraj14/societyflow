<?php

namespace App\Http\Controllers\ApartmentOwner;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\Resident;
use Illuminate\Support\Facades\Auth;

class MyApartmentController extends Controller
{
    /**
     * Show the apartment owner's apartment (view-only)
     */
    public function show()
    {
        $user = Auth::user();
        
        // Get the apartment owned by this user
        $apartment = $user->ownedFlat;
        
        if (!$apartment) {
            return view('apartment-owner.my-apartment', [
                'apartment' => null,
                'message' => 'No apartment assigned to your account.'
            ]);
        }
        
        // Load all relationships
        $apartment->load(['building', 'residents']);
        
        return view('apartment-owner.my-apartment', [
            'apartment' => $apartment,
            'message' => null
        ]);
    }
}
