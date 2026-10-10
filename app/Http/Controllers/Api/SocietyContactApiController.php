<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who to call: the caller's own society office, its managers (active Admin
 * accounts that have a phone), and India's national emergency numbers.
 * Feeds the app's Emergency Contacts sheet and Help & Support screen, which
 * used to show hard-coded "1800-XXX-XXX" placeholders. Society-scoped to the
 * authenticated user; never another society's people.
 */
class SocietyContactApiController extends Controller
{
    private const EMERGENCY = [
        ['label' => 'Emergency (all services)', 'phone' => '112', 'kind' => 'emergency'],
        ['label' => 'Police', 'phone' => '100', 'kind' => 'police'],
        ['label' => 'Ambulance', 'phone' => '108', 'kind' => 'ambulance'],
        ['label' => 'Fire', 'phone' => '101', 'kind' => 'fire'],
    ];

    /**
     * Societies created from the registration form carry placeholder values
     * ("To be updated", pincode 000000) until the office fills them in. On a
     * real phone those showed as an address of "To be updated, To be
     * updated, 000000" - shown as missing instead.
     */
    private static function real(?string $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || strcasecmp($v, 'to be updated') === 0 || preg_match('/^0+$/', $v)) {
            return null;
        }

        return $v;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $society = $user->society;

        $managers = $society
            ? User::where('society_id', $society->id)
                ->where('status', 'active')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'phone', 'email'])
                ->map(fn ($m) => ['name' => $m->name, 'role' => 'Society Manager', 'phone' => $m->phone, 'email' => $m->email])
                ->values()
            : collect();

        return response()->json([
            'success' => true,
            'data'    => [
                'society' => $society ? [
                    'name'    => $society->name,
                    'phone'   => self::real($society->phone),
                    'email'   => self::real($society->email),
                    'address' => trim(implode(', ', array_filter(array_map(
                        fn ($v) => self::real($v),
                        [$society->address, $society->city, $society->pincode]
                    )))) ?: null,
                    'website' => self::real($society->website),
                ] : null,
                'managers'  => $managers,
                'emergency' => self::EMERGENCY,
            ],
        ]);
    }
}
