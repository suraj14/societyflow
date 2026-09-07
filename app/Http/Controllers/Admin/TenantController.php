<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Flat;
use App\Models\Tenant;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TenantController extends BaseController
{
    use HandlesFormSubmissions;
    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $societyId = $this->getSocietyId();
        
        // Get all tenants with their apartment information
        $tenants = Tenant::bySociety($societyId)
            ->with(['flat.building', 'flat.villaArea', 'user'])
            ->get();
        
        return view('admin.tenants.index', compact('tenants'));
    }
    
    public function create()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $societyId = $this->getSocietyId();
        
        // Get ONLY vacant properties that meet ALL conditions:
        // 1. status = 'vacant'
        // 2. society_id = logged_in_society_id  
        // 3. Property is NOT already assigned to any active tenant
        // 4. Property is NOT owner-occupied
        $availableUnits = Flat::bySociety($societyId)
            ->where('status', 'vacant')
            ->whereDoesntHave('tenants', function($query) {
                $query->where('status', 'active');
            })
            ->whereNull('owner_id') // Not owner-occupied
            ->with(['building', 'villaArea'])
            ->orderBy('property_type')
            ->orderBy('flat_number')
            ->get();
        
        return view('admin.tenants.create', compact('availableUnits'));
    }
    
    public function show(Tenant $tenant)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Verify tenant belongs to the same society
        if ($tenant->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Tenant not found.');
        }
        
        // Load user and flat information
        $tenant->load(['user', 'flat.building', 'flat.villaArea']);
        
        return view('admin.tenants.show', compact('tenant'));
    }
    
    public function store(Request $request)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Debug: Log all request data
        \Log::info('Tenant form submission data:', $request->all());
        
        return $this->handleFormSubmission(function() use ($request) {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'phone' => 'required|string|max:20',
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|string|min:8|same:password',
                'status' => 'required|in:active,inactive',
                'profile_image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'contract_start_date' => 'required|date',
                'contract_end_date' => 'required|date|after:contract_start_date',
                'rent_amount' => 'required|numeric|min:0',
                'rent_billing_cycle' => 'required|in:Monthly,Quarterly,Annually',
                'move_in_date' => 'nullable|date',
                'move_out_date' => 'nullable|date',
                'flat_id' => 'nullable|exists:flats,id',
                'id_type' => 'nullable|string|max:50',
                'document_path' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
                'notes' => 'nullable|string',
                'family_members' => 'nullable|array',
                'family_members.*.name' => 'nullable|string|max:255',
                'family_members.*.relationship' => 'nullable|string|max:100',
                'family_members.*.phone' => 'nullable|string|max:20',
                
                // Simplified property assignment validation
                'property_type' => 'nullable|in:apartment,villa',
            ]);
            
            $societyId = $this->getSocietyId();
            
            // Create tenant user
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'society_id' => $societyId,
                'status' => $request->status,
            ];
            
            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                $userData['avatar'] = $this->handleFileUpload($request, 'profile_image', 'tenants', ['jpg', 'jpeg', 'png']);
            }
            
            $user = User::create($userData);
            
            // Assign Tenant role
            $user->assignRole('Tenant');
            
            // Handle document upload
            $documentPath = null;
            if ($request->hasFile('document_path')) {
                $documentPath = $this->handleFileUpload($request, 'document_path', 'tenant-documents');
            }
            
            // Create tenant record with all rental information
            $tenantData = [
                'society_id' => $societyId,
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => $request->status,
                'contract_start_date' => $request->contract_start_date,
                'contract_end_date' => $request->contract_end_date,
                'move_in_date' => $request->move_in_date ?? Carbon::now(),
                'monthly_rent' => $request->rent_amount,
                'rent_billing_cycle' => $request->rent_billing_cycle,
                'id_type' => $request->id_type,
                'document_path' => $documentPath,
                'notes' => $request->notes,
                'flat_id' => $request->flat_id,
                'owner_id' => null,
            ];
            
            // Handle family members
            if ($request->has('family_members')) {
                $familyMembers = [];
                foreach ($request->family_members as $member) {
                    if (!empty($member['name'])) {
                        $familyMembers[] = [
                            'name' => $member['name'],
                            'relationship' => $member['relationship'] ?? '',
                            'phone' => $member['phone'] ?? '',
                        ];
                    }
                }
                if (!empty($familyMembers)) {
                    $tenantData['family_members'] = $familyMembers;
                }
            }
            
            // Handle flat assignment (optional)
            if ($request->flat_id) {
                $flat = Flat::findOrFail($request->flat_id);
                
                // Verify flat belongs to the same society
                if ($flat->society_id !== $societyId) {
                    throw new \Exception('Selected unit must belong to the same society.');
                }
                
                // CRITICAL VALIDATION: Ensure flat meets all assignment criteria
                if ($flat->status !== 'vacant') {
                    throw new \Exception('Selected unit is not vacant and cannot be assigned.');
                }
                
                // Check if flat is already assigned to any active tenant
                $existingTenant = $flat->tenants()->where('status', 'active')->first();
                if ($existingTenant) {
                    throw new \Exception('Selected unit is already assigned to an active tenant.');
                }
                
                // Check if flat is owner-occupied
                if ($flat->owner_id) {
                    throw new \Exception('Selected unit is owner-occupied and cannot be assigned to a tenant.');
                }
                
                $tenantData['flat_id'] = $flat->id;
                $tenantData['owner_id'] = null; // Tenant properties don't have owner_id
                
                // Update flat status to on_rent since it's occupied by a tenant
                $flat->update(['status' => 'on_rent']);
            } else {
                // No flat assigned yet
                $tenantData['flat_id'] = null;
                $tenantData['owner_id'] = null;
            }
            
            $tenant = Tenant::create($tenantData);

            // Dispatch event to trigger email notification
            \App\Events\TenantOwnerAdded::dispatch($tenant);
            
            return $tenant;
        }, 'Tenant created successfully!', 'admin.tenants.index');
    }
    
    public function edit(Tenant $tenant)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Verify tenant belongs to the same society
        if ($tenant->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Tenant not found.');
        }
        
        $societyId = $this->getSocietyId();
        
        // Get ONLY vacant properties that meet ALL conditions:
        // 1. status = 'vacant'
        // 2. society_id = logged_in_society_id  
        // 3. Property is NOT already assigned to any active tenant
        // 4. Property is NOT owner-occupied
        // PLUS: Include the currently assigned flat (if any) for editing
        $availableUnits = Flat::bySociety($societyId)
            ->where(function($query) use ($tenant) {
                $query->where('status', 'vacant')
                      ->whereDoesntHave('tenants', function($q) {
                          $q->where('status', 'active');
                      })
                      ->whereNull('owner_id');
                
                // Also include the currently assigned flat for editing
                if ($tenant->flat_id) {
                    $query->orWhere('id', $tenant->flat_id);
                }
            })
            ->with(['building', 'villaArea'])
            ->orderBy('property_type')
            ->orderBy('flat_number')
            ->get();
        
        // Load user information
        $tenant->load('user');
        
        return view('admin.tenants.edit', compact('tenant', 'availableUnits'));
    }
    
    public function update(Request $request, Tenant $tenant)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Verify tenant belongs to the same society
        $this->validateSocietyAccess($tenant->society_id);
        
        return $this->handleFormSubmission(function() use ($request, $tenant) {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $tenant->user_id,
                'phone' => 'required|string|max:20',
                'password' => 'nullable|string|min:8|confirmed',
                'status' => 'required|in:active,inactive',
                'flat_id' => 'nullable|exists:flats,id',
                'profile_image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'contract_start_date' => 'required|date',
                'contract_end_date' => 'required|date|after:contract_start_date',
                'rent_amount' => 'required|numeric|min:0',
                'rent_billing_cycle' => 'required|in:Monthly,Quarterly,Annually',
                'id_type' => 'nullable|string|max:50',
                'document_path' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
                'notes' => 'nullable|string',
                'family_members' => 'nullable|array',
                'family_members.*.name' => 'nullable|string|max:255',
                'family_members.*.relationship' => 'nullable|string|max:100',
                'family_members.*.phone' => 'nullable|string|max:20',
            ]);
            
            $societyId = $this->getSocietyId();
            
            // Update user information
            $user = $tenant->user;
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => $request->status,
            ];
            
            if ($request->password) {
                $updateData['password'] = Hash::make($request->password);
            }
            
            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                $updateData['avatar'] = $this->handleFileUpload($request, 'profile_image', 'tenants', ['jpg', 'jpeg', 'png']);
            }
            
            $user->update($updateData);
            
            // Update tenant record
            $tenantUpdateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => $request->status,
                'contract_start_date' => $request->contract_start_date,
                'contract_end_date' => $request->contract_end_date,
                'monthly_rent' => $request->rent_amount,
                'rent_billing_cycle' => $request->rent_billing_cycle,
                'id_type' => $request->id_type,
                'notes' => $request->notes,
            ];
            
            // Handle document upload
            if ($request->hasFile('document_path')) {
                $tenantUpdateData['document_path'] = $this->handleFileUpload($request, 'document_path', 'tenant-documents');
            }
            
            // Handle family members
            if ($request->has('family_members')) {
                $familyMembers = [];
                foreach ($request->family_members as $member) {
                    if (!empty($member['name'])) {
                        $familyMembers[] = [
                            'name' => $member['name'],
                            'relationship' => $member['relationship'] ?? '',
                            'phone' => $member['phone'] ?? '',
                        ];
                    }
                }
                if (!empty($familyMembers)) {
                    $tenantUpdateData['family_members'] = $familyMembers;
                }
            }
            
            // Handle flat assignment - check if assignment changed
            $oldFlatId = $tenant->flat_id;
            
            if ($request->flat_id) {
                $flat = Flat::findOrFail($request->flat_id);
                
                // Verify flat belongs to the same society
                if ($flat->society_id !== $societyId) {
                    throw new \Exception('Selected unit must belong to the same society.');
                }
                
                // CRITICAL VALIDATION: Only validate if assigning a different flat
                if ($oldFlatId != $request->flat_id) {
                    // Ensure new flat meets all assignment criteria
                    if ($flat->status !== 'vacant') {
                        throw new \Exception('Selected unit is not vacant and cannot be assigned.');
                    }
                    
                    // Check if flat is already assigned to any active tenant
                    $existingTenant = $flat->tenants()->where('status', 'active')->first();
                    if ($existingTenant) {
                        throw new \Exception('Selected unit is already assigned to an active tenant.');
                    }
                    
                    // Check if flat is owner-occupied
                    if ($flat->owner_id) {
                        throw new \Exception('Selected unit is owner-occupied and cannot be assigned to a tenant.');
                    }
                    
                    // Mark old flat as vacant if it exists and no other active tenants
                    if ($oldFlatId) {
                        $oldFlat = Flat::find($oldFlatId);
                        if ($oldFlat && $oldFlat->tenants()->where('status', 'active')->count() == 1) {
                            $oldFlat->update(['status' => 'vacant']);
                        }
                    }
                    
                    // Update new flat status
                    $flat->update(['status' => 'on_rent']);
                }
                
                $tenantUpdateData['flat_id'] = $flat->id;
                $tenantUpdateData['owner_id'] = null; // Tenant properties don't have owner_id
            } else {
                // If no flat_id provided, clear the assignment
                if ($oldFlatId) {
                    $oldFlat = Flat::find($oldFlatId);
                    if ($oldFlat && $oldFlat->tenants()->where('status', 'active')->count() == 1) {
                        $oldFlat->update(['status' => 'vacant']);
                    }
                }
                
                $tenantUpdateData['flat_id'] = null;
                $tenantUpdateData['owner_id'] = null;
            }
            
            $tenant->update($tenantUpdateData);
            
            return $tenant;
        }, 'Tenant updated successfully!', 'admin.tenants.index');
    }
    
    public function destroy(Tenant $tenant)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Verify tenant belongs to the same society
        if ($tenant->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Tenant not found.');
        }
        
        // Mark flat as vacant if exists
        if ($tenant->flat_id) {
            $flat = $tenant->flat;
            if ($flat && $flat->tenants()->where('status', 'active')->count() == 1) {
                $flat->update(['status' => 'vacant']);
            }
        }
        
        // Delete associated user first
        if ($tenant->user) {
            $tenant->user->delete();
        }
        
        // Delete the tenant record completely
        $tenant->delete();
        
        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant deleted successfully!');
    }
}