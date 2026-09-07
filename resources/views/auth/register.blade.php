<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register - SocietyFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl shadow-lg mb-4 p-2">
                    <svg viewBox="0 0 100 100" class="w-full h-full">
                        <defs>
                            <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea"/>
                                <stop offset="100%" style="stop-color:#764ba2"/>
                            </linearGradient>
                        </defs>
                        <rect x="5" y="5" width="90" height="90" rx="15" fill="url(#logoGradient)"/>
                        <rect x="15" y="25" width="25" height="55" fill="white" opacity="0.9"/>
                        <rect x="18" y="30" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="25" y="30" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="32" y="30" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="18" y="38" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="25" y="38" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="32" y="38" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="18" y="46" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="25" y="46" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="32" y="46" width="5" height="5" fill="url(#logoGradient)"/>
                        <polygon points="70,45 50,30 50,45 45,45 45,80 70,80 70,45" fill="white" opacity="0.9"/>
                        <polygon points="70,45 50,30 90,30 90,45" fill="white" opacity="0.7"/>
                        <rect x="55" y="55" width="8" height="8" fill="url(#logoGradient)"/>
                        <rect x="55" y="67" width="8" height="13" fill="url(#logoGradient)"/>
                        <path d="M10,75 Q30,65 50,75 T90,75 L90,85 Q70,75 50,85 T10,85 Z" fill="white" opacity="0.3"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white">
                    <span class="text-white">Society</span><span class="text-purple-200">Flow</span>
                </h1>
                <p class="text-purple-200 mt-1">Society & Apartment Management System</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Create Account</h2>
                    <p class="text-gray-500 mt-1">Register your society today</p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                        <div>
                            <p class="text-sm text-blue-800">
                                <strong>Note:</strong> Society registration requires admin approval. 
                                You'll receive an email once your account is activated.
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('register.submit') }}" class="space-y-5">
                    @csrf
                    
                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-red-600 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h4>
                                    <ul class="text-sm text-red-700 list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-building text-gray-400 mr-1"></i> Society Name
                        </label>
                        <input type="text" name="society_name" required value="{{ old('society_name') }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 @error('society_name') border-red-500 @enderror"
                               placeholder="Enter society name">
                        @error('society_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-gray-400 mr-1"></i> Admin Name
                        </label>
                        <input type="text" name="name" required value="{{ old('name') }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 @error('name') border-red-500 @enderror"
                               placeholder="Enter your name">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope text-gray-400 mr-1"></i> Email Address
                        </label>
                        <input type="email" name="email" required value="{{ old('email') }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 @error('email') border-red-500 @enderror"
                               placeholder="Enter your email">
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone text-gray-400 mr-1"></i> Phone Number
                        </label>
                        <input type="tel" name="phone" required value="{{ old('phone') }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 @error('phone') border-red-500 @enderror"
                               placeholder="Enter phone number">
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-gray-400 mr-1"></i> Password
                        </label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 @error('password') border-red-500 @enderror"
                               placeholder="Create a password">
                        @error('password')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-gray-400 mr-1"></i> Confirm Password
                        </label>
                        <input type="password" name="password_confirmation" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                               placeholder="Confirm your password">
                    </div>

                    <div class="flex items-start">
                        <input type="checkbox" name="terms" required {{ old('terms') ? 'checked' : '' }}
                               class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500 mt-1 @error('terms') border-red-500 @enderror">
                        <label class="ml-2 text-sm text-gray-600">
                            I agree to the <a href="#" class="text-purple-600 hover:underline">Terms of Service</a> 
                            and <a href="#" class="text-purple-600 hover:underline">Privacy Policy</a>
                        </label>
                        @error('terms')
                            <p class="text-red-500 text-sm mt-1 ml-6">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 focus:ring-4 focus:ring-purple-200 transition-all">
                        <i class="fas fa-user-plus mr-2"></i> Create Account
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="{{ route('login') }}" class="text-purple-600 hover:text-purple-800 font-semibold">Sign In</a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-6 text-purple-200 text-sm">
                <p>&copy; {{ date('Y') }} SocietyFlow. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
