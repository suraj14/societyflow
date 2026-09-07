<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\OtpAuthController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\VisitorApiController;
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

    // Public auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/login',           [AuthApiController::class, 'login']);
        Route::post('/check-account',   [AuthApiController::class, 'checkAccount']);
        Route::post('/register',        [AuthApiController::class, 'register']);
        Route::post('/forgot-password', function (Request $request) {
            return response()->json(['success' => true, 'message' => 'If this email exists, a reset link has been sent.']);
        });

        // Mobile OTP login (does not affect web login)
        Route::post('/send-otp',   [OtpAuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [OtpAuthController::class, 'verifyOtp']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/logout', [AuthApiController::class, 'logout']);
        Route::get('/auth/me',      [AuthApiController::class, 'me']);

        Route::get('/dashboard', [DashboardApiController::class, 'index']);

        Route::get('/visitors',          [VisitorApiController::class, 'index']);
        Route::post('/visitors',         [VisitorApiController::class, 'store']);
        Route::get('/visitors/dashboard',[VisitorApiController::class, 'dashboard']);
        Route::get('/visitors/flats',    [VisitorApiController::class, 'flats']);
        Route::post('/visitors/{id}/approve', [VisitorApiController::class, 'approve']);
        Route::post('/visitors/{id}/reject',  [VisitorApiController::class, 'reject']);
        Route::post('/visitors/{id}/check-in',  [VisitorApiController::class, 'checkIn']);
        Route::post('/visitors/{id}/check-out', [VisitorApiController::class, 'checkOut']);
        Route::get('/visitors/{id}',     [VisitorApiController::class, 'show']);

        Route::get('/complaints',            [ComplaintApiController::class, 'index']);
        Route::post('/complaints',           [ComplaintApiController::class, 'store']);
        Route::get('/complaints/{id}',       [ComplaintApiController::class, 'show']);
        Route::get('/complaints/categories', [ComplaintApiController::class, 'categories']);

        Route::get('/payments',         [PaymentApiController::class, 'index']);
        Route::get('/bills',            [PaymentApiController::class, 'bills']);
        Route::get('/payments/history', [PaymentApiController::class, 'history']);

        Route::get('/notices',      [NoticeApiController::class, 'index']);
        Route::get('/notices/{id}', [NoticeApiController::class, 'show']);

        Route::get('/profile',              [ProfileApiController::class, 'show']);
        Route::put('/profile',              [ProfileApiController::class, 'update']);
        Route::post('/change-password',     [ProfileApiController::class, 'changePassword']);
        Route::get('/profile/family-members', [ProfileApiController::class, 'familyMembers']);

        // Facilities
        Route::get('/facilities',                    [FacilityApiController::class, 'index']);
        Route::get('/facility-bookings',             [FacilityApiController::class, 'bookings']);
        Route::post('/facility-bookings',            [FacilityApiController::class, 'book']);
        Route::post('/facility-bookings/{id}/cancel',[FacilityApiController::class, 'cancelBooking']);

        // Services
        Route::get('/services',                      [ServiceApiController::class, 'index']);
        Route::get('/service-providers',             [ServiceApiController::class, 'providers']);
        Route::post('/service-requests',             [ServiceApiController::class, 'requestService']);

        // Events
        Route::get('/events',                        [EventApiController::class, 'index']);
        Route::get('/events/{id}',                   [EventApiController::class, 'show']);
    });
});

// Legacy sanctum user endpoint
Route::middleware('auth:sanctum')->get('/user', fn(Request $request) => $request->user());