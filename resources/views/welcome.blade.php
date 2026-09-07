<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">SocietyFlow</h1>
                <p class="text-gray-600 mb-6">Complete SaaS Society & Apartment Management System</p>
                
                <div class="space-y-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-blue-900">🏢 Society Management</h3>
                        <p class="text-blue-700 text-sm">Manage buildings, flats, and residents</p>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-green-900">💰 Financial Management</h3>
                        <p class="text-green-700 text-sm">Track payments, expenses, and maintenance bills</p>
                    </div>
                    
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-purple-900">🔧 Facility Booking</h3>
                        <p class="text-purple-700 text-sm">Book common facilities and manage staff</p>
                    </div>
                    
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-orange-900">📋 Complaint Management</h3>
                        <p class="text-orange-700 text-sm">Handle resident complaints and notices</p>
                    </div>
                </div>
                
                <div class="mt-8">
                    <a href="/dashboard" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Go to Dashboard
                    </a>
                </div>
                
                <div class="mt-4 text-sm text-gray-500">
                    Server running on: <strong>http://127.0.0.1:8000</strong>
                </div>
            </div>
        </div>
    </div>
</body>
</html>