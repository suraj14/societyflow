<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password - SocietyFlow</title>
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
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-lock text-purple-600 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Forgot Password?</h2>
                    <p class="text-gray-500 mt-2">No worries! Enter your email and we'll send you a reset link.</p>
                </div>

                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        {{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                    @csrf
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope text-gray-400 mr-1"></i> Email Address
                        </label>
                        <input type="email" 
                               name="email" 
                               id="email" 
                               required 
                               autofocus
                               value="{{ old('email') }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all @error('email') border-red-500 @enderror"
                               placeholder="Enter your email">
                        @error('email')
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-1"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 focus:ring-4 focus:ring-purple-200 transition-all">
                        <i class="fas fa-paper-plane mr-2"></i> Send Reset Link
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="text-purple-600 hover:text-purple-800 font-medium">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Login
                    </a>
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
