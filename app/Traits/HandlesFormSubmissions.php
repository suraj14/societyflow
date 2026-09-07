<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

trait HandlesFormSubmissions
{
    /**
     * Handle form submission with proper error handling and transaction safety
     */
    protected function handleFormSubmission(callable $callback, string $successMessage = 'Operation completed successfully', string $redirectRoute = null, bool $expectsJson = false)
    {
        try {
            DB::beginTransaction();
            
            $result = $callback();
            
            DB::commit();
            
            // Check if this is an AJAX request
            $isAjax = $expectsJson || request()->expectsJson() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';
            
            // Always return JSON for AJAX requests
            if ($isAjax) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'redirect' => $redirectRoute ? route($redirectRoute) : null,
                    'data' => $result
                ]);
            }
            
            if ($redirectRoute) {
                return redirect()->route($redirectRoute)->with('success', $successMessage);
            }
            
            return back()->with('success', $successMessage);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            
            $isAjax = $expectsJson || request()->expectsJson() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';
            
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            
            return back()
                ->withErrors($e->errors())
                ->withInput();
                
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Form submission failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => request()->all(),
                'user_id' => auth()->id(),
                'url' => request()->url()
            ]);
            
            $isAjax = $expectsJson || request()->expectsJson() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';
            
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while processing your request. Please try again.'
                ], 500);
            }
            
            return back()
                ->withErrors(['error' => 'An error occurred while processing your request. Please try again.'])
                ->withInput();
        }
    }
    
    /**
     * Validate and handle file uploads
     */
    protected function handleFileUpload(Request $request, string $fieldName, string $directory, array $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'])
    {
        if (!$request->hasFile($fieldName)) {
            return null;
        }
        
        $file = $request->file($fieldName);
        
        if (!$file->isValid()) {
            throw new Exception("Invalid file upload for {$fieldName}");
        }
        
        $extension = $file->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allowedTypes)) {
            throw new Exception("Invalid file type for {$fieldName}. Allowed types: " . implode(', ', $allowedTypes));
        }
        
        // Use direct file move to public/storage - no symlink needed
        $uploadPath = public_path('storage/' . $directory);
        
        // Try to create directory, but don't fail if it exists
        @mkdir($uploadPath, 0777, true);
        
        $filename = time() . '_' . $file->getClientOriginalName();
        try {
            $file->move($uploadPath, $filename);
        } catch (\Exception $e) {
            // If move fails, try with different permissions
            @chmod($uploadPath, 0777);
            $file->move($uploadPath, $filename);
        }
        
        return $directory . '/' . $filename;
    }
    
    /**
     * Ensure proper error display
     */
    protected function withFormErrors($errors)
    {
        if (is_string($errors)) {
            $errors = ['error' => $errors];
        }
        
        return back()->withErrors($errors)->withInput();
    }
}