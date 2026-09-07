<?php

namespace App\Http\Controllers\VillaOwner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MyVillaController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        $villa->load(['society', 'villaArea', 'residents']);

        return view('villa-owner.my-villa', compact('villa'));
    }

    /**
     * Show the villa owner's villa (view-only)
     */
    public function show()
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return view('villa-owner.my-villa-view', [
                'villa' => null,
                'message' => 'No villa assigned to your account.'
            ]);
        }

        $villa->load(['society', 'villaArea', 'residents']);

        return view('villa-owner.my-villa-view', [
            'villa' => $villa,
            'message' => null
        ]);
    }
}
