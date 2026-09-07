/**
 * CRITICAL: Global Form Handler - Ensures ALL forms work correctly
 * Enhanced with robust CSRF token handling and session management
 * This script MUST be loaded AFTER csrf-token-manager.js
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Global Form Handler: Initializing...');
    
    // Wait for CSRF manager to be ready
    const waitForCsrfManager = setInterval(() => {
        if (window.csrfManager) {
            clearInterval(waitForCsrfManager);
            initializeFormHandler();
        }
    }, 100);

    function initializeFormHandler() {
        // ============================================================================
        // FORM SUBMISSION HANDLER
        // ============================================================================
        
        document.addEventListener('submit', function(e) {
            const form = e.target;
            
            // Skip if not a form
            if (form.tagName !== 'FORM') return;
            
            // Skip if form has custom handler
            if (form.id === 'tenant-create-form' || form.id === 'tenant-edit-form' || 
                form.id === 'rent-create-form' || form.id === 'rent-edit-form') {
                console.log('📝 Form with custom handler detected - skipping global handler');
                return;
            }
            
            console.log('📝 Form submission detected:', form.action);
            
            // Ensure CSRF token is present in form
            let csrfInput = form.querySelector('input[name="_token"]');
            if (!csrfInput) {
                csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                form.appendChild(csrfInput);
            }
            
            // Update token from manager
            csrfInput.value = window.csrfToken;
            console.log('✅ CSRF token injected into form');
            
            // Prevent double submission
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                if (submitButton.disabled) {
                    console.log('⚠️ Form already submitting, preventing double submission');
                    e.preventDefault();
                    return;
                }
                
                // Disable submit button and show loading state
                submitButton.disabled = true;
                const originalText = submitButton.innerHTML;
                submitButton.setAttribute('data-original-text', originalText);
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                
                // Re-enable after 20 seconds as failsafe
                setTimeout(() => {
                    if (submitButton.disabled) {
                        submitButton.disabled = false;
                        const storedOriginalText = submitButton.getAttribute('data-original-text');
                        if (storedOriginalText) {
                            submitButton.innerHTML = storedOriginalText;
                        }
                        console.log('⚠️ Form submission timeout - re-enabled submit button');
                    }
                }, 20000);
            }
            
            // Validate required fields
            const requiredFields = form.querySelectorAll('[required]');
            let hasErrors = false;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('border-red-500', 'bg-red-50');
                    hasErrors = true;
                    
                    let errorMsg = field.parentElement.querySelector('.field-error');
                    if (!errorMsg) {
                        errorMsg = document.createElement('p');
                        errorMsg.className = 'field-error text-red-500 text-sm mt-1';
                        field.parentElement.appendChild(errorMsg);
                    }
                    errorMsg.textContent = 'This field is required';
                } else {
                    field.classList.remove('border-red-500', 'bg-red-50');
                    const errorMsg = field.parentElement.querySelector('.field-error');
                    if (errorMsg) errorMsg.remove();
                }
            });
            
            if (hasErrors) {
                console.log('❌ Form validation failed');
                e.preventDefault();
                
                if (submitButton) {
                    submitButton.disabled = false;
                    const storedOriginalText = submitButton.getAttribute('data-original-text');
                    if (storedOriginalText) {
                        submitButton.innerHTML = storedOriginalText;
                    }
                }
                
                showErrorMessage('Please fill in all required fields');
                return;
            }
            
            console.log('✅ Form validation passed, submitting...');
        });
        
        // Auto-clear validation errors on input
        document.addEventListener('input', function(e) {
            const field = e.target;
            if (field.classList.contains('border-red-500')) {
                field.classList.remove('border-red-500', 'bg-red-50');
                const errorMsg = field.parentElement.querySelector('.field-error');
                if (errorMsg) errorMsg.remove();
            }
        });
        
        // Global error handler for unhandled promise rejections
        window.addEventListener('unhandledrejection', function(event) {
            console.error('❌ Unhandled promise rejection:', event.reason);
            
            // Check if it's a 419 error
            if (event.reason?.status === 419) {
                showErrorMessage('Session expired. Please refresh the page and try again.');
                if (window.csrfManager) {
                    window.csrfManager.refreshToken();
                }
            } else {
                showErrorMessage('An unexpected error occurred. Please try again.');
            }
        });
        
        // ============================================================================
        // MESSAGE DISPLAY FUNCTIONS
        // ============================================================================
        
        window.showErrorMessage = function(message) {
            console.log('🚨 Showing error:', message);
            
            let errorContainer = document.querySelector('.global-error-container');
            
            if (!errorContainer) {
                errorContainer = document.createElement('div');
                errorContainer.className = 'global-error-container fixed top-4 right-4 z-50';
                document.body.appendChild(errorContainer);
            }
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg shadow-lg max-w-md toast-enter';
            errorDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <p class="font-medium">${message}</p>
                    <button class="ml-auto text-red-500 hover:text-red-700" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            errorContainer.appendChild(errorDiv);
            
            setTimeout(() => {
                if (errorDiv.parentElement) {
                    errorDiv.classList.add('toast-exit');
                    setTimeout(() => errorDiv.remove(), 300);
                }
            }, 5000);
        };
        
        window.showSuccessMessage = function(message) {
            console.log('✅ Showing success:', message);
            
            let successContainer = document.querySelector('.global-success-container');
            
            if (!successContainer) {
                successContainer = document.createElement('div');
                successContainer.className = 'global-success-container fixed top-4 right-4 z-50';
                document.body.appendChild(successContainer);
            }
            
            const successDiv = document.createElement('div');
            successDiv.className = 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-lg shadow-lg max-w-md toast-enter';
            successDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3"></i>
                    <p class="font-medium">${message}</p>
                    <button class="ml-auto text-green-500 hover:text-green-700" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            successContainer.appendChild(successDiv);
            
            setTimeout(() => {
                if (successDiv.parentElement) {
                    successDiv.classList.add('toast-exit');
                    setTimeout(() => successDiv.remove(), 300);
                }
            }, 3000);
        };
        
        window.validateForm = function(form) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('border-red-500', 'bg-red-50');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-500', 'bg-red-50');
                }
            });
            
            return isValid;
        };
        
        // ============================================================================
        // SESSION MESSAGE DISPLAY
        // ============================================================================
        
        const showSessionMessages = function() {
            const successMessage = document.querySelector('[data-success-message]');
            if (successMessage) {
                showSuccessMessage(successMessage.dataset.successMessage);
                successMessage.remove();
            }
            
            const errorMessage = document.querySelector('[data-error-message]');
            if (errorMessage) {
                showErrorMessage(errorMessage.dataset.errorMessage);
                errorMessage.remove();
            }
            
            const validationErrors = document.querySelectorAll('[data-validation-error]');
            validationErrors.forEach(error => {
                showErrorMessage(error.dataset.validationError);
                error.remove();
            });
        };
        
        showSessionMessages();
        
        console.log('✅ Global Form Handler: Initialized successfully');
    }
});