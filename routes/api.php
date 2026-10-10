<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\OtpAuthController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\VisitorApiController;
use App\Http\Controllers\Api\AccessPassApiController;
use App\Http\Controllers\Api\ComplaintApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\NoticeApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\FacilityApiController;
use App\Http\Controllers\Api\ServiceApiController;
use App\Http\Controllers\Api\EventApiController;

/*
|--------------------------------------------------------------------------
| Mobile App API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
     * Server identity check.
     *
     * The mobile app discovers its server by scanning the local subnet. It
     * used to accept the first host that completed a TCP connection on port
     * 8000 and then send that host a bearer token - any machine on the
     * network answering on that port would have received it. The app now
     * calls this endpoint and requires the marker below before trusting a
     * host.
     *
     * Deliberately unauthenticated and free of any tenant data.
     */
    Route::get('/health', function () {
        return response()->json([
            'application' => 'societyflow',
            'api'         => 'v1',
            'status'      => 'ok',
        ]);
    });

    // Public auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/login',           [AuthApiController::class, 'login']);
        Route::post('/check-account',   [AuthApiController::class, 'checkAccount']);
        Route::post('/register',        [AuthApiController::class, 'register']);
        Route::post('/forgot-password', [OtpAuthController::class, 'forgotPassword']);
        Route::post('/reset-password',  [OtpAuthController::class, 'resetPassword']);

        // Mobile OTP login (does not affect web login)
        Route::post('/send-otp',   [OtpAuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [OtpAuthController::class, 'verifyOtp']);
        // Only after verify-otp answered requires_account_choice (one number,
        // several accounts): sign in to the one the user picked.
        Route::post('/select-account', [OtpAuthController::class, 'selectAccount']);
    });

    // Protected routes
    // restrict.guard.api: a Guard's token reaches only the gate surface (see the middleware).
    Route::middleware(['auth:sanctum', 'restrict.guard.api'])->group(function () {

        Route::post('/auth/logout', [AuthApiController::class, 'logout']);
        Route::get('/auth/me',      [AuthApiController::class, 'me']);

        Route::get('/dashboard', [DashboardApiController::class, 'index']);

        Route::get('/visitors',          [VisitorApiController::class, 'index']);
        Route::post('/visitors',         [VisitorApiController::class, 'store']);
        Route::get('/visitors/dashboard',[VisitorApiController::class, 'dashboard']);
        Route::get('/visitors/flats',    [VisitorApiController::class, 'flats']);
        // Before the /visitors/{id} route below, or 'lookup' is read as an id.
        Route::post('/visitors/lookup',   [VisitorApiController::class, 'lookup']);
        Route::post('/visitors/{id}/approve', [VisitorApiController::class, 'approve']);
        Route::post('/visitors/{id}/reject',  [VisitorApiController::class, 'reject']);
        Route::post('/visitors/{id}/check-in',  [VisitorApiController::class, 'checkIn']);
        Route::post('/visitors/{id}/check-out', [VisitorApiController::class, 'checkOut']);
        Route::get('/visitors/{id}',     [VisitorApiController::class, 'show']);

        // Guard mobile QR scan - reuses AccessPassService, the same
        // verification the web gate (GuardController::scan()) uses.
        Route::post('/access-passes/scan', [AccessPassApiController::class, 'scan']);
        // Managing a pass from the app. Same AccessPassEditService as the
        // web screen, so the rules cannot drift between the two clients.
        Route::get('/access-passes',                  [AccessPassApiController::class, 'index']);
        Route::get('/access-passes/{accessPass}',     [AccessPassApiController::class, 'show']);
        Route::put('/access-passes/{accessPass}',     [AccessPassApiController::class, 'update']);
        Route::post('/access-passes/{accessPass}/extend', [AccessPassApiController::class, 'extend']);

        Route::get('/complaints',            [ComplaintApiController::class, 'index']);
        Route::post('/complaints',           [ComplaintApiController::class, 'store']);
        Route::get('/complaints/categories', [ComplaintApiController::class, 'categories']);
        Route::get('/complaints/{id}',       [ComplaintApiController::class, 'show'])->whereNumber('id');

        Route::get('/payments',         [PaymentApiController::class, 'index']);
        Route::get('/bills',            [PaymentApiController::class, 'bills']);
        Route::get('/payments/history', [PaymentApiController::class, 'history']);

        Route::get('/notices',      [NoticeApiController::class, 'index']);
        Route::get('/notices/{id}', [NoticeApiController::class, 'show']);

        // Society office, managers and national emergency numbers (Emergency Contacts, Help & Support)
        Route::get('/society/contacts', [\App\Http\Controllers\Api\SocietyContactApiController::class, 'index']);

        // In-app notifications (the same entries as the web header bell)
        Route::get('/notifications',            [\App\Http\Controllers\Api\NotificationApiController::class, 'index']);
        Route::post('/notifications/read-all',  [\App\Http\Controllers\Api\NotificationApiController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationApiController::class, 'markRead']);

        // Society Info: details + committee (the Documents section below is unchanged)
        Route::get('/society/info',                        [\App\Http\Controllers\Api\SocietyInfoApiController::class, 'show']);

        // Society Rules & Documents (read + acknowledge; management is web-only)
        Route::get('/society-documents',                   [\App\Http\Controllers\Api\SocietyDocumentApiController::class, 'index']);
        Route::get('/society-documents/{id}',              [\App\Http\Controllers\Api\SocietyDocumentApiController::class, 'show'])->whereNumber('id');
        Route::get('/society-documents/{id}/file',         [\App\Http\Controllers\Api\SocietyDocumentApiController::class, 'file'])->whereNumber('id');
        Route::post('/society-documents/{id}/acknowledge', [\App\Http\Controllers\Api\SocietyDocumentApiController::class, 'acknowledge'])->whereNumber('id');

        Route::get('/profile',              [ProfileApiController::class, 'show']);
        Route::put('/profile',              [ProfileApiController::class, 'update']);
        // Same action as PUT: PHP only parses a multipart body (the picture) on POST.
        Route::post('/profile',             [ProfileApiController::class, 'update']);
        Route::post('/change-password',     [ProfileApiController::class, 'changePassword']);
        Route::get('/profile/family-members', [ProfileApiController::class, 'familyMembers']);

        // Society admin on the phone: the society at a glance, and what is waiting
        Route::prefix('admin')->group(function () {
            $c = \App\Http\Controllers\Api\AdminApiController::class;
            Route::get('/dashboard',                              [$c, 'dashboard']);
            Route::get('/facility-bookings',                      [$c, 'facilityBookings']);
            Route::post('/facility-bookings/{id}/status',         [$c, 'facilityBookingStatus'])->whereNumber('id');
            Route::get('/complaints',                             [$c, 'complaints']);
            Route::post('/complaints/{id}/status',                [$c, 'complaintStatus'])->whereNumber('id');
            Route::get('/service-requests',                       [$c, 'serviceRequests']);
            Route::post('/service-requests/{id}/assign',          [$c, 'assignServiceRequest'])->whereNumber('id');
            Route::post('/service-requests/{id}/status',          [$c, 'serviceRequestStatus'])->whereNumber('id');
        });

        // Guard: own shift, from the roster the office manages on the web
        Route::prefix('guard/shift')->group(function () {
            Route::get('/',           [\App\Http\Controllers\Api\GuardShiftApiController::class, 'show']);
            Route::post('/start',     [\App\Http\Controllers\Api\GuardShiftApiController::class, 'start']);
            Route::post('/end',       [\App\Http\Controllers\Api\GuardShiftApiController::class, 'end']);
            Route::post('/handover',  [\App\Http\Controllers\Api\GuardShiftApiController::class, 'handover']);
        });

        // Facilities
        Route::get('/facilities',                    [FacilityApiController::class, 'index']);
        Route::get('/facilities/{id}/availability',  [FacilityApiController::class, 'availability'])->whereNumber('id');
        Route::get('/facility-bookings',             [FacilityApiController::class, 'bookings']);
        Route::post('/facility-bookings',            [FacilityApiController::class, 'book']);
        Route::post('/facility-bookings/{id}/cancel',[FacilityApiController::class, 'cancelBooking']);

        // Services
        Route::get('/services',                      [ServiceApiController::class, 'index']);
        Route::get('/service-providers',             [ServiceApiController::class, 'providers']);
        Route::post('/service-requests',             [ServiceApiController::class, 'requestService']);
        Route::get('/service-requests',               [ServiceApiController::class, 'myRequests']);
        Route::post('/service-requests/{id}/cancel',  [ServiceApiController::class, 'cancelRequest']);

        // Events
        Route::get('/events',                        [EventApiController::class, 'index']);
        Route::get('/events/{id}',                   [EventApiController::class, 'show']);
    });
});

// Legacy sanctum user endpoint
Route::middleware('auth:sanctum')->get('/user', fn(Request $request) => $request->user());