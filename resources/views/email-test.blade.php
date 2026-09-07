<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Email Test - SocietyFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-md w-full">
            <h1 class="text-2xl font-bold text-gray-900 mb-6 text-center">
                <i class="fas fa-envelope text-blue-600 mr-2"></i>
                Email Test
            </h1>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test Email Address</label>
                    <input type="email" id="test_email" placeholder="Enter email address" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <button onclick="testEmail()" 
                        class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    <i class="fas fa-paper-plane mr-2"></i>Send Test Email
                </button>
                
                <div id="result" class="hidden p-4 rounded-lg"></div>
                
                <div class="text-center">
                    <a href="{{ route('settings.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function testEmail() {
        const testEmailInput = document.getElementById('test_email');
        const testEmail = testEmailInput.value.trim();
        const resultDiv = document.getElementById('result');
        
        if (!testEmail) {
            showResult('Please enter a test email address', 'error');
            testEmailInput.focus();
            return;
        }
        
        if (!isValidEmail(testEmail)) {
            showResult('Please enter a valid email address', 'error');
            testEmailInput.focus();
            return;
        }
        
        const button = event.target;
        const originalText = button.innerHTML;
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
        button.disabled = true;
        
        // Send test email
        fetch('{{ route("settings.email.test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                test_email: testEmail
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showResult('✅ Test email sent successfully! Check your inbox.', 'success');
            } else {
                showResult('❌ Failed to send test email: ' + data.message + (data.details ? '\n\n' + data.details : ''), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showResult('❌ Error sending test email. Please check your settings and try again.', 'error');
        })
        .finally(() => {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showResult(message, type) {
        const resultDiv = document.getElementById('result');
        resultDiv.className = 'p-4 rounded-lg ' + (type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200');
        resultDiv.textContent = message;
        resultDiv.classList.remove('hidden');
        
        // Auto-hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                resultDiv.classList.add('hidden');
            }, 5000);
        }
    }
    </script>
</body>
</html>