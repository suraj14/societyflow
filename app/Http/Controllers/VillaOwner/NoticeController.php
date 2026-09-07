<?php

namespace App\Http\Controllers\VillaOwner;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        $notices = Notice::where('society_id', $villa->society_id)
            ->where('status', 'approved')
            ->latest()
            ->paginate(10);

        return view('villa-owner.notices.index', compact('notices', 'villa'));
    }

    public function show(Notice $notice)
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa || $notice->society_id !== $villa->society_id) {
            abort(403);
        }

        return view('villa-owner.notices.show', compact('notice', 'villa'));
    }
}
