<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ServiceApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Services are global (no society_id) — return all enabled/active services
        $services = Service::where(function ($q) {
                $q->where('is_enabled', true)
                  ->orWhereNull('is_enabled');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'description' => null,
                'category'    => $s->category,
                'charges'     => 0.0,
                'status'      => 'active',
            ]);

        return response()->json(['success' => true, 'data' => $services]);
    }

    public function providers(Request $request): JsonResponse
    {
        $providers = ServiceProvider::where('society_id', $request->user()->society_id)
            ->where('status', 'active')
            ->with('service')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'phone'        => $p->contact_number, // correct field name
                'service_name' => $p->service?->name ?? 'General',
                'category'     => $p->service?->category ?? 'General',
                'charges'      => (float) ($p->price ?? 0),
                'price_type'   => $p->price_type ?? '',
                'availability' => $p->availability,
                'is_daily_help'=> (bool) $p->is_daily_help,
                'rating'       => 0, // no rating column — default 0
                'status'       => $p->status,
            ]);

        return response()->json(['success' => true, 'data' => $providers]);
    }

    public function requestService(Request $request): JsonResponse
    {
        $request->validate([
            'service_id'  => 'required|exists:services,id',
            'description' => 'required|string|max:500',
            'preferred_date' => 'nullable|date|after_or_equal:today',
        ]);

        $user = $request->user();

        // Create a complaint-style service request
        $serviceRequest = DB::table('service_requests')->insertGetId([
            'society_id'     => $user->society_id,
            'user_id'        => $user->id,
            'service_id'     => $request->service_id,
            'description'    => $request->description,
            'preferred_date' => $request->preferred_date,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service request submitted successfully!',
            'data'    => ['id' => $serviceRequest],
        ], 201);
    }
}
