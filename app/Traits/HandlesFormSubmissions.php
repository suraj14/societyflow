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
                // Never write credentials or tokens into the log file.
                'request' => request()->except([
                    'password', 'password_confirmation', 'current_password', 'new_password',
                    'new_password_confirmation', 'temporary_password', 'otp', 'token', '_token',
                ]),
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
     * Validate and handle file uploads.
     *
     * The filename used to be `time() . '_' . $file->getClientOriginalName()`
     * - the client's original filename, taken verbatim, straight into a
     * filesystem path. A real phone photo with a colon in its name (common
     * export/share-sheet naming on both Android and iOS - e.g.
     * "IMG_2026-10-03 17:30:00.jpg") is perfectly valid as an upload but
     * illegal as a Windows filename; `move()` threw an uncaught
     * FileException and the whole request 500'd - confirmed live against
     * this exact controller flow. The same pattern was duplicated inline
     * (not through this trait) in five other controllers and had the same
     * failure mode in each.
     *
     * The filename is now generated here - timestamp + random token + the
     * already-whitelisted extension - and never contains anything the
     * client supplied, so no client-controlled byte (colon, slash,
     * directory traversal, null byte, absurd length) ever reaches a
     * filesystem path. The previous "catch the move() failure, chmod the
     * directory, retry the identical call" never addressed an illegal-
     * character failure (the retry fails identically and that second
     * failure was never caught) - a real environment permissions problem
     * now surfaces once, to the caller, which handleFormSubmission()
     * already turns into a redirect-with-error instead of a raw 500.
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

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception("Invalid file type for {$fieldName}. Allowed types: " . implode(', ', $allowedTypes));
        }

        // Use direct file move to public/storage - no symlink needed
        $uploadPath = public_path('storage/' . $directory);

        // Try to create directory, but don't fail if it exists
        @mkdir($uploadPath, 0777, true);

        // Never the client's own filename - see docblock above.
        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $file->move($uploadPath, $filename);

        return $directory . '/' . $filename;
    }

    /**
     * Delete a file previously returned by handleFileUpload(), e.g. the old
     * avatar/image when a new one replaces it, or when the owning record is
     * deleted. $relativePath is the "directory/filename" string stored in
     * the database. Silently does nothing for an empty path or a file that
     * is already gone - this is cleanup, not a step that should ever fail
     * the request it's called from.
     */
    protected function deleteUploadedFile(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $fullPath = public_path('storage/' . $relativePath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
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