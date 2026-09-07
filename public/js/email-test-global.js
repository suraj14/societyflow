/**
 * Global Email Test Function
 * This ensures testEmail is always available regardless of page load order
 */

// Define the function globally
window.testEmail = function() {
    const testEmailInput = document.getElementById('test_email');
    const testEmail = testEmailInput ? testEmailInput.value.trim() : '';
    
    if (!testEmail) {
        const email = prompt('Enter test email address:');
        if (!email) return;
        
        sendTestEmailRequest(email);
    } else {
        if (!isValidEmail(testEmail)) {
            alert('Please enter a valid email address');
            testEmailInput.focus();
            return;
        }
        
        sendTestEmailRequest(testEmail);
    }
};

// Helper function to send the actual request
function sendTestEmailRequest(email) {
    const button = event.target;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    button.disabled = true;
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    const token = csrfToken ? csrfToken.getAttribute('content') : '';
    
    // Send test email
    fetch('/settings/email/test', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({
            test_email: email
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Test email sent successfully! Check your inbox.');
        } else {
            alert('❌ Failed to send test email: ' + data.message + (data.details ? '\n\n' + data.details : ''));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error sending test email. Please check your settings and try again.');
    })
    .finally(() => {
        // Restore button state
        if (button) {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

// Email validation helper
window.isValidEmail = function(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

// Ensure functions are available immediately
console.log('✅ Global email test functions loaded');