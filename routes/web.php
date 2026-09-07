<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocietyController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UtilityBillController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\FlatController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FacilityBookingController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\VillaAreaController;
use App\Http\Controllers\VillaController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\SocietyController as SuperAdminSocietyController;
use App\Http\Controllers\SuperAdmin\PackageController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\LandingSiteController;
use App\Http\Controllers\VillaOwner\DashboardController as VillaOwnerDashboardController;
use App\Http\Controllers\VillaOwner\MyVillaController;
use App\Http\Controllers\VillaOwner\BillController as VillaOwnerBillController;
use App\Http\Controllers\VillaOwner\VisitorController as VillaOwnerVisitorController;
use App\Http\Controllers\VillaOwner\FacilityController as VillaOwnerFacilityController;
use App\Http\Controllers\VillaOwner\RequestController as VillaOwnerRequestController;
use App\Http\Controllers\VillaOwner\NoticeController as VillaOwnerNoticeController;
use App\Http\Controllers\Admin\UserVillaAssignmentController;

use App\Http\Controllers\LandingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('landing');

// API Routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/api/flats', [FlatController::class, 'apiIndex'])->name('api.flats.all');
    
    // Debug route for visitor form
    Route::get('/debug/visitor-flats', function() {
        $user = auth()->user();
        $societyId = $user->society_id;
        
        $flats = \App\Models\Flat::with(['building', 'villaArea'])
            ->where('society_id', $societyId)
            ->get();
            
        $villaFlats = $flats->filter(function($flat) {
            return $flat->property_type === 'villa' && $flat->villaArea;
        });
        
        return response()->json([
            'user_society_id' => $societyId,
            'total_flats' => $flats->count(),
            'villa_flats_with_areas' => $villaFlats->count(),
            'villa_areas' => $villaFlats->pluck('villaArea')->unique('id')->values(),
            'sample_villa_flats' => $villaFlats->take(3)->values()
        ]);
    });
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
    Route::get('/register', [App\Http\Controllers\AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register'])->name('register.submit');
    Route::get('/forgot-password', [App\Http\Controllers\AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [App\Http\Controllers\AuthController::class, 'forgotPassword'])->name('password.email');
});

Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout'])->name('logout')->middleware('auth');

// GET logout fallback - redirect to login page
Route::get('/logout', function () {
    return redirect()->route('login');
});

// Super Admin Routes
Route::prefix('super-admin')->name('super-admin.')->middleware(['auth', 'role:Super Admin'])->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
    
    // Societies Management
    Route::resource('societies', SuperAdminSocietyController::class);
    Route::post('societies/{society}/approve', [SuperAdminSocietyController::class, 'approve'])->name('societies.approve');
    Route::post('societies/{society}/reject', [SuperAdminSocietyController::class, 'reject'])->name('societies.reject');
    
    // Society Admin Management
    Route::post('societies/{society}/reset-admin-password', [SuperAdminSocietyController::class, 'resetAdminPassword'])->name('societies.reset-admin-password');
    Route::post('societies/{society}/assign-admin', [SuperAdminSocietyController::class, 'assignAdmin'])->name('societies.assign-admin');
    Route::delete('societies/{society}/remove-admin', [SuperAdminSocietyController::class, 'removeAdmin'])->name('societies.remove-admin');
    
    // Packages Management
    Route::resource('packages', PackageController::class);
    
    // Billing Management
    Route::resource('billing', BillingController::class)->only(['index', 'show']);
    Route::post('billing/{payment}/mark-paid', [BillingController::class, 'markAsPaid'])->name('billing.mark-paid');
    Route::get('billing/{payment}/invoice', [BillingController::class, 'downloadInvoice'])->name('billing.invoice');
    
    // Role Management
    Route::get('roles', [App\Http\Controllers\SuperAdmin\RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}', [App\Http\Controllers\SuperAdmin\RoleController::class, 'show'])->name('roles.show');
    Route::put('roles/{role}', [App\Http\Controllers\SuperAdmin\RoleController::class, 'update'])->name('roles.update');
    
    // Landing Site Management
    Route::get('landing-site', [LandingSiteController::class, 'index'])->name('landing-site.index');
    Route::post('landing-site/toggle', [LandingSiteController::class, 'toggleLanding'])->name('landing-site.toggle');
    Route::post('landing-site/header', [LandingSiteController::class, 'updateHeader'])->name('landing-site.header');
    Route::post('landing-site/contact', [LandingSiteController::class, 'updateContact'])->name('landing-site.contact');
    
    // Features
    Route::post('landing-site/features', [LandingSiteController::class, 'storeFeature'])->name('landing-site.features.store');
    Route::put('landing-site/features/{feature}', [LandingSiteController::class, 'updateFeature'])->name('landing-site.features.update');
    Route::delete('landing-site/features/{feature}', [LandingSiteController::class, 'deleteFeature'])->name('landing-site.features.destroy');
    Route::post('landing-site/features/{feature}/toggle', [LandingSiteController::class, 'toggleFeature'])->name('landing-site.features.toggle');
    
    // Reviews
    Route::post('landing-site/reviews', [LandingSiteController::class, 'storeReview'])->name('landing-site.reviews.store');
    Route::put('landing-site/reviews/{review}', [LandingSiteController::class, 'updateReview'])->name('landing-site.reviews.update');
    Route::delete('landing-site/reviews/{review}', [LandingSiteController::class, 'deleteReview'])->name('landing-site.reviews.destroy');
    Route::post('landing-site/reviews/{review}/toggle', [LandingSiteController::class, 'toggleReview'])->name('landing-site.reviews.toggle');
    
    // FAQs
    Route::post('landing-site/faqs', [LandingSiteController::class, 'storeFaq'])->name('landing-site.faqs.store');
    Route::put('landing-site/faqs/{faq}', [LandingSiteController::class, 'updateFaq'])->name('landing-site.faqs.update');
    Route::delete('landing-site/faqs/{faq}', [LandingSiteController::class, 'deleteFaq'])->name('landing-site.faqs.destroy');
    Route::post('landing-site/faqs/{faq}/toggle', [LandingSiteController::class, 'toggleFaq'])->name('landing-site.faqs.toggle');
    
    // Pricing
    Route::post('landing-site/pricing', [LandingSiteController::class, 'storePricing'])->name('landing-site.pricing.store');
    Route::put('landing-site/pricing/{pricing}', [LandingSiteController::class, 'updatePricing'])->name('landing-site.pricing.update');
    Route::delete('landing-site/pricing/{pricing}', [LandingSiteController::class, 'deletePricing'])->name('landing-site.pricing.destroy');
    Route::post('landing-site/pricing/{pricing}/toggle', [LandingSiteController::class, 'togglePricing'])->name('landing-site.pricing.toggle');
    
    // Reorder
    Route::post('landing-site/reorder/{type}', [LandingSiteController::class, 'reorder'])->name('landing-site.reorder');
});

// Main Dashboard - Redirects based on role
Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

// Profile Route - Available to all authenticated users
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
});

// Admin Dashboard
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    
    // Villa Assignment Management (Admin & Super Admin only)
    Route::middleware('role:Super Admin|Admin')->group(function () {
        Route::get('/villa-assignments', [App\Http\Controllers\Admin\UserVillaAssignmentController::class, 'index'])->name('villa-assignments.index');
        Route::post('/villa-assignments/assign', [App\Http\Controllers\Admin\UserVillaAssignmentController::class, 'assign'])->name('villa-assignments.assign');
        Route::post('/villa-assignments/unassign', [App\Http\Controllers\Admin\UserVillaAssignmentController::class, 'unassign'])->name('villa-assignments.unassign');
        Route::post('/villa-assignments/reassign', [App\Http\Controllers\Admin\UserVillaAssignmentController::class, 'reassign'])->name('villa-assignments.reassign');
        
        // Apartment Assignment Management
        Route::get('/apartment-assignments', [App\Http\Controllers\Admin\UserApartmentAssignmentController::class, 'index'])->name('apartment-assignments.index');
        Route::post('/apartment-assignments/assign', [App\Http\Controllers\Admin\UserApartmentAssignmentController::class, 'assign'])->name('apartment-assignments.assign');
        Route::post('/apartment-assignments/unassign', [App\Http\Controllers\Admin\UserApartmentAssignmentController::class, 'unassign'])->name('apartment-assignments.unassign');
        Route::post('/apartment-assignments/reassign', [App\Http\Controllers\Admin\UserApartmentAssignmentController::class, 'reassign'])->name('apartment-assignments.reassign');
        
        // Tenant Management
        Route::get('/tenants', [App\Http\Controllers\Admin\TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [App\Http\Controllers\Admin\TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [App\Http\Controllers\Admin\TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [App\Http\Controllers\Admin\TenantController::class, 'show'])->name('tenants.show');
        Route::get('/tenants/{tenant}/edit', [App\Http\Controllers\Admin\TenantController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{tenant}', [App\Http\Controllers\Admin\TenantController::class, 'update'])->name('tenants.update');
        Route::delete('/tenants/{tenant}', [App\Http\Controllers\Admin\TenantController::class, 'destroy'])->name('tenants.destroy');
        
        // Rent Management
        Route::get('/rents', [App\Http\Controllers\Admin\RentController::class, 'index'])->name('rents.index');
        Route::get('/rents/create', [App\Http\Controllers\Admin\RentController::class, 'create'])->name('rents.create');
        Route::post('/rents', [App\Http\Controllers\Admin\RentController::class, 'store'])->name('rents.store');
        Route::get('/rents/{rent}/edit', [App\Http\Controllers\Admin\RentController::class, 'edit'])->name('rents.edit');
        Route::put('/rents/{rent}', [App\Http\Controllers\Admin\RentController::class, 'update'])->name('rents.update');
        Route::delete('/rents/{rent}', [App\Http\Controllers\Admin\RentController::class, 'destroy'])->name('rents.destroy');
        Route::get('/rents/{rent}/receipt', [App\Http\Controllers\Admin\RentController::class, 'receipt'])->name('rents.receipt');
        Route::post('/rents/add-payment', [App\Http\Controllers\Admin\RentController::class, 'addPayment'])->name('rents.add-payment');
        
        // Rent API endpoints for simplified form
        Route::get('api/rent/units', [App\Http\Controllers\Admin\RentController::class, 'getUnitsOnRent'])->name('api.rent.units');
        Route::post('api/rent/validate', [App\Http\Controllers\Admin\RentController::class, 'validateRentRecord'])->name('api.rent.validate');
        
        // Debug route to test villa data
        Route::get('api/rent/debug', function() {
            $societyId = auth()->user()->society_id ?? 1;
            
            $villas = \App\Models\Flat::where('society_id', $societyId)
                ->where('property_type', 'villa')
                ->with(['tenants' => function($q) {
                    $q->where('status', 'active')->with('user');
                }, 'villaArea'])
                ->get();
                
            $result = [
                'society_id' => $societyId,
                'total_villas' => $villas->count(),
                'villas' => $villas->map(function($villa) {
                    return [
                        'id' => $villa->id,
                        'flat_number' => $villa->flat_number,
                        'villa_name' => $villa->villa_name,
                        'status' => $villa->status,
                        'tenants_count' => $villa->tenants->count(),
                        'tenant_names' => $villa->tenants->map(function($t) {
                            return $t->user ? $t->user->name : 'No user';
                        })
                    ];
                })
            ];
            
            return response()->json($result);
        });
        
        // Tenant Assignment Management
        Route::get('/tenant-assignments', [App\Http\Controllers\Admin\UserTenantAssignmentController::class, 'index'])->name('tenant-assignments.index');
        Route::post('/tenant-assignments/assign', [App\Http\Controllers\Admin\UserTenantAssignmentController::class, 'assign'])->name('tenant-assignments.assign');
        Route::post('/tenant-assignments/unassign', [App\Http\Controllers\Admin\UserTenantAssignmentController::class, 'unassign'])->name('tenant-assignments.unassign');
        Route::post('/tenant-assignments/reassign', [App\Http\Controllers\Admin\UserTenantAssignmentController::class, 'reassign'])->name('tenant-assignments.reassign');
    });
});

// Owner Dashboard (Apartment Owner / Tenant)
Route::prefix('owner')->name('owner.')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
});

// Tenant Dashboard
Route::prefix('tenant')->name('tenant.')->middleware(['auth', 'role:Tenant'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/rent', [App\Http\Controllers\Tenant\RentController::class, 'index'])->name('rent');
    Route::post('/rent/payment', [App\Http\Controllers\Tenant\RentController::class, 'submitPayment'])->name('rent.payment');
    Route::get('/rent/{rent}/receipt', [App\Http\Controllers\Tenant\RentController::class, 'showReceipt'])->name('rent.receipt');
});

// Staff Dashboard
Route::prefix('staff')->name('staff.')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('staff.dashboard');
    })->name('dashboard');
});

// Society Management Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::resource('societies', SocietyController::class);
});

// Building Management Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::resource('buildings', BuildingController::class);
});

// Flat Management Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::resource('flats', FlatController::class);
    Route::post('flats/{flat}/change-status', [FlatController::class, 'changeStatus'])->name('flats.change-status');
});

// Resident Management Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::resource('residents', ResidentController::class);
});

// User Management Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin|Society Admin', 'restrict.staff'])->group(function () {
    Route::resource('users', App\Http\Controllers\UserController::class);
});

// Redirect /residents to /owners (Phase 8 refactor - Residents deprecated)
Route::redirect('/residents', '/owners');

// Owner Management Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin|Society Admin', 'restrict.staff'])->group(function () {
    Route::resource('owners', App\Http\Controllers\OwnerController::class);
    Route::post('owners/convert-resident', [App\Http\Controllers\OwnerController::class, 'convertFromResident'])->name('owners.convert-resident');
    
    // API endpoints for cascading dropdowns
    Route::get('api/owners/buildings', [App\Http\Controllers\OwnerController::class, 'getBuildings'])->name('api.owners.buildings');
    Route::get('api/owners/floors', [App\Http\Controllers\OwnerController::class, 'getFloors'])->name('api.owners.floors');
    Route::get('api/owners/flats', [App\Http\Controllers\OwnerController::class, 'getFlats'])->name('api.owners.flats');
    Route::get('api/owners/villa-areas', [App\Http\Controllers\OwnerController::class, 'getVillaAreas'])->name('api.owners.villa-areas');
    Route::get('api/owners/villa-numbers', [App\Http\Controllers\OwnerController::class, 'getVillaNumbers'])->name('api.owners.villa-numbers');
});

// Payment Management Routes (Admin can manage all, others see own) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'restrict.staff'])->group(function () {
    Route::resource('payments', PaymentController::class);
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    // Route::resource('utility-bills', UtilityBillController::class);
    // Route::patch('utility-bills/{utilityBill}/payment', [UtilityBillController::class, 'addPayment'])->name('utility-bills.add-payment');
});

// Temporary: Move utility-bills outside middleware for testing
Route::middleware(['auth'])->group(function () {
    Route::resource('utility-bills', UtilityBillController::class);
    Route::patch('utility-bills/{utilityBill}/payment', [UtilityBillController::class, 'addPayment'])->name('utility-bills.add-payment');
});

// API endpoints for cascading dropdowns
Route::middleware(['auth'])->group(function () {
    Route::get('api/buildings', [UtilityBillController::class, 'getBuildings'])->name('api.buildings');
    Route::get('api/floors', [UtilityBillController::class, 'getFloors'])->name('api.floors');
    Route::get('api/utility-bills/flats', [UtilityBillController::class, 'getFlats'])->name('api.utility-bills.flats');
    Route::get('api/villa-areas', [UtilityBillController::class, 'getVillaAreas'])->name('api.villa-areas');
    Route::get('api/villas', [UtilityBillController::class, 'getVillas'])->name('api.villas');
    
    // Payment API endpoints for cascading dropdowns
    Route::get('api/payments/buildings', [PaymentController::class, 'getBuildings'])->name('api.payments.buildings');
    Route::get('api/payments/floors', [PaymentController::class, 'getFloors'])->name('api.payments.floors');
    Route::get('api/payments/flats', [PaymentController::class, 'getFlats'])->name('api.payments.flats');
    Route::get('api/payments/villa-areas', [PaymentController::class, 'getVillaAreas'])->name('api.payments.villa-areas');
    Route::get('api/payments/villas', [PaymentController::class, 'getVillas'])->name('api.payments.villas');
});

// Test route for utility bills
Route::get('/test-utility-bills', function() {
    return 'Utility Bills route is working!';
});

// Complaint Management Routes (All authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::resource('complaints', ComplaintController::class);
    Route::post('complaints/{complaint}/update', [ComplaintController::class, 'addUpdate'])->name('complaints.add-update');
    Route::post('complaints/{complaint}/assign', [ComplaintController::class, 'assign'])->name('complaints.assign');
});

// Facility Management Routes (Admin manages, others book)
Route::middleware(['auth'])->group(function () {
    Route::resource('facilities', FacilityController::class);
});

// Facility Booking Routes (All authenticated users)
Route::middleware(['auth'])->group(function () {
    // API endpoints for cascading dropdowns (must come before resource route)
    Route::get('api/facility-bookings/floors', [FacilityBookingController::class, 'getFloors'])->name('api.facility-bookings.floors');
    Route::get('api/facility-bookings/flats', [FacilityBookingController::class, 'getFlats'])->name('api.facility-bookings.flats');
    Route::get('api/facility-bookings/villas', [FacilityBookingController::class, 'getVillas'])->name('api.facility-bookings.villas');
    Route::get('facility-bookings/flats/{flat}', [FacilityBookingController::class, 'showFlat'])->name('facility-bookings.show-flat');
    
    // Resource routes
    Route::resource('facility-bookings', FacilityBookingController::class);
    Route::get('facility-bookings/{facility_booking}/edit-status', [FacilityBookingController::class, 'editStatus'])->name('facility-bookings.edit-status');
    Route::put('facility-bookings/{facility_booking}/update-status', [FacilityBookingController::class, 'updateStatus'])->name('facility-bookings.update-status');
});

// Visitor Management Routes (Admin, Owners, Staff)
Route::middleware(['auth'])->group(function () {
    Route::get('visitors/stats', [VisitorController::class, 'stats'])->name('visitors.stats');
    Route::resource('visitors', VisitorController::class);
    Route::get('visitors/export/csv', [VisitorController::class, 'export'])->name('visitors.export');
    Route::post('visitors/{visitor}/check-in', [VisitorController::class, 'checkIn'])->name('visitors.check-in');
    Route::post('visitors/{visitor}/check-out', [VisitorController::class, 'checkOut'])->name('visitors.check-out');
    Route::post('visitors/{visitor}/update-status', [VisitorController::class, 'updateStatus'])->name('visitors.update-status');
    Route::post('visitors/{visitor}/allow', [VisitorController::class, 'allow'])->name('visitors.allow');
    Route::post('visitors/{visitor}/deny', [VisitorController::class, 'deny'])->name('visitors.deny');
});

// Notice Board Routes (All authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::resource('notices', NoticeController::class);
    Route::get('notices-pending', [NoticeController::class, 'pending'])->name('notices.pending');
    Route::get('notices-history', [NoticeController::class, 'history'])->name('notices.history');
    Route::post('notices/{notice}/approve', [NoticeController::class, 'approve'])->name('notices.approve');
    Route::post('notices/{notice}/reject', [NoticeController::class, 'reject'])->name('notices.reject');
});

// Settings Routes (Super Admin & Admin - Unified) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::get('settings', [App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings/society', [App\Http\Controllers\SettingsController::class, 'updateSociety'])->name('settings.society.update');
    Route::post('settings/app', [App\Http\Controllers\SettingsController::class, 'updateApp'])->name('settings.app.update');
    Route::post('settings/language', [App\Http\Controllers\SettingsController::class, 'updateLanguage'])->name('settings.language.update');
    Route::post('settings/storage', [App\Http\Controllers\SettingsController::class, 'updateStorage'])->name('settings.storage.update');
    Route::post('settings/storage/test', [App\Http\Controllers\SettingsController::class, 'testStorage'])->name('settings.storage.test');
    Route::post('settings/theme', [App\Http\Controllers\SettingsController::class, 'updateTheme'])->name('settings.theme.update');
    Route::post('settings/currency', [App\Http\Controllers\SettingsController::class, 'updateCurrency'])->name('settings.currency.update');
    Route::post('settings/email', [App\Http\Controllers\SettingsController::class, 'updateEmail'])->name('settings.email.update');
    Route::post('settings/email/test', [App\Http\Controllers\SettingsController::class, 'testEmail'])->name('settings.email.test');
    Route::post('settings/payment', [App\Http\Controllers\SettingsController::class, 'updatePayment'])->name('settings.payment.update');
    Route::post('settings/push', [App\Http\Controllers\SettingsController::class, 'updatePush'])->name('settings.push.update');
    Route::post('settings/sms', [App\Http\Controllers\SettingsController::class, 'updateSms'])->name('settings.sms.update');
    Route::post('settings/sms/test', [App\Http\Controllers\SettingsController::class, 'testSms'])->name('settings.sms.test');
    Route::post('settings/security', [App\Http\Controllers\SettingsController::class, 'updateSecurity'])->name('settings.security.update');
    Route::post('settings/clear-cache', [App\Http\Controllers\SettingsController::class, 'clearCache'])->name('settings.clear-cache');
    
    // Admin-only settings
    Route::middleware('role:Admin')->group(function () {
        Route::post('settings/permissions', [App\Http\Controllers\SettingsController::class, 'updatePermissions'])->name('settings.permissions.update');
        Route::post('settings/currency-features', [App\Http\Controllers\SettingsController::class, 'updateCurrencyAndFeatures'])->name('settings.currency-features.update');
        
        // Complaint Categories Management
        Route::post('settings/complaint-categories', [App\Http\Controllers\SettingsController::class, 'storeComplaintCategory'])->name('settings.complaint-categories.store');
        Route::post('settings/complaint-categories/{id}/toggle', [App\Http\Controllers\SettingsController::class, 'toggleComplaintCategory'])->name('settings.complaint-categories.toggle');
        Route::delete('settings/complaint-categories/{id}', [App\Http\Controllers\SettingsController::class, 'destroyComplaintCategory'])->name('settings.complaint-categories.destroy');
    });
});

// Email Template Management Routes (Super Admin & Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('email-templates', App\Http\Controllers\Admin\EmailTemplateController::class);
        Route::post('email-templates/initialize', [App\Http\Controllers\Admin\EmailTemplateController::class, 'initializeDefaults'])->name('email-templates.initialize');
        Route::post('email-templates/{emailTemplate}/test', [App\Http\Controllers\Admin\EmailTemplateController::class, 'test'])->name('email-templates.test');
        
        // Email Logs Management
        Route::get('email-logs', [App\Http\Controllers\Admin\EmailLogController::class, 'index'])->name('email-logs.index');
        Route::get('email-logs/{emailLog}', [App\Http\Controllers\Admin\EmailLogController::class, 'show'])->name('email-logs.show');
        Route::post('email-logs/{emailLog}/retry', [App\Http\Controllers\Admin\EmailLogController::class, 'retry'])->name('email-logs.retry');

        // Email Template Mapping Management
        Route::get('email-template-mappings', [App\Http\Controllers\Admin\EmailTemplateMappingController::class, 'index'])->name('email-template-mappings.index');
        Route::get('email-template-mappings/templates/{eventValue}', [App\Http\Controllers\Admin\EmailTemplateMappingController::class, 'getTemplatesForEvent'])->name('email-template-mappings.templates');
        Route::post('email-template-mappings/{eventValue}/test', [App\Http\Controllers\Admin\EmailTemplateMappingController::class, 'test'])->name('email-template-mappings.test');
        Route::put('email-template-mappings/{eventValue}', [App\Http\Controllers\Admin\EmailTemplateMappingController::class, 'update'])->name('email-template-mappings.update');
        Route::delete('email-template-mappings/{eventValue}', [App\Http\Controllers\Admin\EmailTemplateMappingController::class, 'destroy'])->name('email-template-mappings.destroy');

        // Push Notification Management
        Route::get('push-notifications/settings', [App\Http\Controllers\Admin\PushNotificationController::class, 'settings'])->name('push-notifications.settings');
        Route::post('push-notifications/settings', [App\Http\Controllers\Admin\PushNotificationController::class, 'updateSettings'])->name('push-notifications.update-settings');
        Route::get('push-notifications/logs', [App\Http\Controllers\Admin\PushNotificationController::class, 'logs'])->name('push-notifications.logs');
        Route::post('push-notifications/test', [App\Http\Controllers\Admin\PushNotificationController::class, 'test'])->name('push-notifications.test');
        Route::get('push-notifications/templates', [App\Http\Controllers\Admin\PushNotificationController::class, 'templates'])->name('push-notifications.templates');
        Route::get('push-notifications/templates/{template}/edit', [App\Http\Controllers\Admin\PushNotificationController::class, 'editTemplate'])->name('push-notifications.templates.edit');
        Route::put('push-notifications/templates/{template}', [App\Http\Controllers\Admin\PushNotificationController::class, 'updateTemplate'])->name('push-notifications.templates.update');
        Route::get('push-notifications/statistics', [App\Http\Controllers\Admin\PushNotificationController::class, 'statistics'])->name('push-notifications.statistics');
    });
});

// Push Subscription Routes (All authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::post('api/push/subscribe', [App\Http\Controllers\PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::post('api/push/unsubscribe', [App\Http\Controllers\PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::get('api/push/vapid-key', [App\Http\Controllers\PushSubscriptionController::class, 'getVapidPublicKey'])->name('push.vapid-key');
});

// Services Routes (All authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::get('services', [App\Http\Controllers\ServiceController::class, 'index'])->name('services.index');
    Route::get('services/providers', [App\Http\Controllers\ServiceController::class, 'providers'])->name('services.providers');
    Route::get('services/attendance', [App\Http\Controllers\ServiceController::class, 'attendance'])->name('services.attendance');
    Route::post('services/clock-in', [App\Http\Controllers\ServiceController::class, 'clockIn'])->name('services.clock-in');
    Route::post('services/clock-out', [App\Http\Controllers\ServiceController::class, 'clockOut'])->name('services.clock-out');
});

// Service Providers Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::resource('service-providers', App\Http\Controllers\ServiceProviderController::class);
    Route::patch('service-providers/{service_provider}/quick-update', [App\Http\Controllers\ServiceProviderController::class, 'quickUpdate'])->name('service-providers.quick-update');
    Route::post('service-providers/{service_provider}/toggle-availability', [App\Http\Controllers\ServiceProviderController::class, 'toggleAvailability'])->name('service-providers.toggle-availability');
});

// Villa Area Management Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::resource('villa-areas', VillaAreaController::class);
});

// Villa Management Routes (Admin & Super Admin only) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin', 'restrict.staff'])->group(function () {
    Route::resource('villas', VillaController::class);
});

// Villa Owner Routes
Route::prefix('my')->name('villa-owner.')->middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [VillaOwnerDashboardController::class, 'index'])->name('dashboard');
    
    // My Villa
    Route::get('/villa', [MyVillaController::class, 'index'])->name('my-villa');
    
    // My Bills
    Route::get('/bills', [VillaOwnerBillController::class, 'index'])->name('bills');
    Route::get('/bills/{bill}', [VillaOwnerBillController::class, 'show'])->name('bills.show');
    
    // My Visitors
    Route::get('/visitors', [VillaOwnerVisitorController::class, 'index'])->name('visitors');
    Route::get('/visitors/create', [VillaOwnerVisitorController::class, 'create'])->name('visitors.create');
    Route::post('/visitors', [VillaOwnerVisitorController::class, 'store'])->name('visitors.store');
    Route::get('/visitors/{visitor}', [VillaOwnerVisitorController::class, 'show'])->name('visitors.show');
    
    // Facilities
    Route::get('/facilities', [VillaOwnerFacilityController::class, 'index'])->name('facilities');
    Route::get('/facilities/{facility}/book', [VillaOwnerFacilityController::class, 'book'])->name('facilities.book');
    Route::post('/facilities/{facility}/book', [VillaOwnerFacilityController::class, 'storeBooking'])->name('facilities.store-booking');
    
    // My Bookings
    Route::get('/bookings', [VillaOwnerFacilityController::class, 'bookings'])->name('bookings');
    Route::post('/bookings/{booking}/cancel', [VillaOwnerFacilityController::class, 'cancelBooking'])->name('bookings.cancel');
    
    // Maintenance Requests
    Route::get('/requests', [VillaOwnerRequestController::class, 'index'])->name('requests');
    Route::get('/requests/create', [VillaOwnerRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [VillaOwnerRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{request}', [VillaOwnerRequestController::class, 'show'])->name('requests.show');
    
    // Notices
    Route::get('/notices', [VillaOwnerNoticeController::class, 'index'])->name('notices');
    Route::get('/notices/{notice}', [VillaOwnerNoticeController::class, 'show'])->name('notices.show');
});

// Apartment Owner Routes - My Apartment (View Only)
Route::middleware(['auth', 'role:Apartment Owner'])->group(function () {
    Route::get('/my-apartment', [App\Http\Controllers\ApartmentOwner\MyApartmentController::class, 'show'])->name('my-apartment');
});

// Villa Owner Routes - My Villa (View Only)
Route::middleware(['auth', 'role:Villa Owner'])->group(function () {
    Route::get('/my-villa', [App\Http\Controllers\VillaOwner\MyVillaController::class, 'show'])->name('my-villa');
});

// Reports Routes (Super Admin, Admin, Owner, Tenant) - RESTRICTED FOR STAFF
Route::middleware(['auth', 'role:Super Admin|Admin|Owner|Tenant', 'restrict.staff'])->group(function () {
    Route::get('/reports/maintenance', [App\Http\Controllers\ReportController::class, 'maintenanceReport'])->name('reports.maintenance');
    Route::post('/reports/maintenance/download', [App\Http\Controllers\ReportController::class, 'downloadMaintenanceReport'])->name('reports.download-maintenance');
    Route::get('/reports/financial', [App\Http\Controllers\ReportController::class, 'financialReport'])->name('reports.financial');
    Route::post('/reports/financial/download', [App\Http\Controllers\ReportController::class, 'downloadFinancialReport'])->name('reports.download-financial');
});

// Events Routes (All authenticated users)
Route::middleware(['auth'])->group(function () {
    // Specific routes must come BEFORE resource route to avoid conflicts
    Route::get('/events/calendar/view', [App\Http\Controllers\EventController::class, 'calendar'])->name('events.calendar');
    Route::get('/api/events/calendar', [App\Http\Controllers\EventController::class, 'getCalendarEvents'])->name('api.events.calendar');
    
    // Resource routes (index, create, store, show, edit, update, destroy)
    Route::resource('events', App\Http\Controllers\EventController::class);
});


