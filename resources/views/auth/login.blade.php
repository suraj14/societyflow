<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - SocietyFlow</title>
    <!-- Tailwind CSS Direct from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-shadow {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .demo-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
        }
        .copy-btn:hover {
            transform: scale(1.1);
        }
        .copy-btn.copied {
            color: #10b981;
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <!-- Main Container -->
        <div class="w-full max-w-md">
            <!-- Logo & Brand -->
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
                        <!-- Building -->
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
                        <rect x="18" y="54" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="25" y="54" width="5" height="5" fill="url(#logoGradient)"/>
                        <rect x="32" y="54" width="5" height="5" fill="url(#logoGradient)"/>
                        <!-- House -->
                        <polygon points="70,45 50,30 50,45 45,45 45,80 70,80 70,45" fill="white" opacity="0.9"/>
                        <polygon points="70,45 50,30 90,30 90,45" fill="white" opacity="0.7"/>
                        <rect x="55" y="55" width="8" height="8" fill="url(#logoGradient)"/>
                        <rect x="55" y="67" width="8" height="13" fill="url(#logoGradient)"/>
                        <!-- Wave -->
                        <path d="M10,75 Q30,65 50,75 T90,75 L90,85 Q70,75 50,85 T10,85 Z" fill="white" opacity="0.3"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white">
                    <span class="text-white">Society</span><span class="text-purple-200">Flow</span>
                </h1>
                <p class="text-purple-200 mt-1">Society & Apartment Management System</p>
            </div>

            <!-- Login Card -->
            <div class="bg-white rounded-2xl card-shadow p-8">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Welcome Back</h2>
                    <p class="text-gray-500 mt-1">Sign in to your account</p>
                </div>

                <!-- Success/Error Messages -->
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        {{ session('error') }}
                    </div>
                @endif

                <!-- Login Form -->
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    
                    <!-- Email Field -->
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

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-gray-400 mr-1"></i> Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all pr-12 @error('password') border-red-500 @enderror"
                                   placeholder="Enter your password">
                            <button type="button" 
                                    onclick="togglePassword()" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-1"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   name="remember" 
                                   id="remember"
                                   class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm text-purple-600 hover:text-purple-800 font-medium">
                            Forgot password?
                        </a>
                    </div>

                    <!-- Login Button -->
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 focus:ring-4 focus:ring-purple-200 transition-all transform hover:scale-[1.02] active:scale-[0.98]">
                        <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500">or</span>
                    </div>
                </div>

                <!-- Register & Home Links -->
                <div class="text-center space-y-3">
                    <p class="text-gray-600">
                        Don't have an account? 
                        <a href="{{ route('register') }}" class="text-purple-600 hover:text-purple-800 font-semibold">
                            Create Account
                        </a>
                    </p>
                    <a href="/" class="inline-flex items-center text-gray-500 hover:text-gray-700 text-sm">
                        <i class="fas fa-home mr-1"></i> Go to Home
                    </a>
                </div>
            </div>

            <!-- Demo Credentials Card -->
            <div class="mt-6 bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20">
                <div class="flex items-center justify-center mb-4">
                    <i class="fas fa-key text-yellow-300 mr-2"></i>
                    <h3 class="text-white font-semibold">Demo Credentials</h3>
                </div>
                
                <div class="space-y-2">
                    <!-- Super Admin -->
                    <div class="demo-credential flex items-center justify-between bg-white/90 rounded-lg px-4 py-2 cursor-pointer hover:bg-white transition-all" 
                         onclick="fillCredentials('superadmin@societyflow.com', 'password')">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-crown text-sm"></i>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Super Admin</p>
                                <p class="text-xs text-gray-500">superadmin@societyflow.com</p>
                            </div>
                        </div>
                        <button type="button" class="copy-btn text-gray-400 hover:text-purple-600 transition-all" onclick="event.stopPropagation(); copyCredentials('superadmin@societyflow.com', 'password', this)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>

                    <!-- Society Admin -->
                    <div class="demo-credential flex items-center justify-between bg-white/90 rounded-lg px-4 py-2 cursor-pointer hover:bg-white transition-all" 
                         onclick="fillCredentials('admin@societyflow.com', 'password')">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-user-shield text-sm"></i>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Society Admin</p>
                                <p class="text-xs text-gray-500">admin@societyflow.com</p>
                            </div>
                        </div>
                        <button type="button" class="copy-btn text-gray-400 hover:text-purple-600 transition-all" onclick="event.stopPropagation(); copyCredentials('admin@societyflow.com', 'password', this)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>

                    <!-- Owner (Multi-Property) -->
                    <div class="demo-credential flex items-center justify-between bg-white/90 rounded-lg px-4 py-2 cursor-pointer hover:bg-white transition-all" 
                         onclick="fillCredentials('villaowner@societyflow.com', 'password')">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-home text-sm"></i>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Owner</p>
                                <p class="text-xs text-gray-500">villaowner@societyflow.com</p>
                            </div>
                        </div>
                        <button type="button" class="copy-btn text-gray-400 hover:text-purple-600 transition-all" onclick="event.stopPropagation(); copyCredentials('villaowner@societyflow.com', 'password', this)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>

                    <!-- Tenant -->
                    <div class="demo-credential flex items-center justify-between bg-white/90 rounded-lg px-4 py-2 cursor-pointer hover:bg-white transition-all" 
                         onclick="fillCredentials('tenant@societyflow.com', 'password')">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-user text-sm"></i>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Tenant</p>
                                <p class="text-xs text-gray-500">tenant@societyflow.com</p>
                            </div>
                        </div>
                        <button type="button" class="copy-btn text-gray-400 hover:text-purple-600 transition-all" onclick="event.stopPropagation(); copyCredentials('tenant@societyflow.com', 'password', this)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>

                    <!-- Staff -->
                    <div class="demo-credential flex items-center justify-between bg-white/90 rounded-lg px-4 py-2 cursor-pointer hover:bg-white transition-all" 
                         onclick="fillCredentials('staff@societyflow.com', 'password')">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-teal-100 text-teal-600 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-hard-hat text-sm"></i>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Staff</p>
                                <p class="text-xs text-gray-500">staff@societyflow.com</p>
                            </div>
                        </div>
                        <button type="button" class="copy-btn text-gray-400 hover:text-purple-600 transition-all" onclick="event.stopPropagation(); copyCredentials('staff@societyflow.com', 'password', this)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>

                    <!-- Accountant -->
                    <div class="demo-credential flex items-center justify-between bg-white/90 rounded-lg px-4 py-2 cursor-pointer hover:bg-white transition-all" 
                         onclick="fillCredentials('accountant@societyflow.com', 'password')">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-calculator text-sm"></i>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Accountant</p>
                                <p class="text-xs text-gray-500">accountant@societyflow.com</p>
                            </div>
                        </div>
                        <button type="button" class="copy-btn text-gray-400 hover:text-purple-600 transition-all" onclick="event.stopPropagation(); copyCredentials('accountant@societyflow.com', 'password', this)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <p class="text-center text-purple-200 text-xs mt-4">
                    <i class="fas fa-info-circle mr-1"></i> Click to auto-fill credentials
                </p>
            </div>

            <!-- Footer -->
            <div class="text-center mt-6 text-purple-200 text-sm">
                <p>&copy; {{ date('Y') }} SocietyFlow. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Prevent back button from showing cached page
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                location.reload(true);
            }
        });
    </script>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Fill credentials in form
        function fillCredentials(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            
            // Visual feedback
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            emailInput.classList.add('ring-2', 'ring-green-500');
            passwordInput.classList.add('ring-2', 'ring-green-500');
            
            setTimeout(() => {
                emailInput.classList.remove('ring-2', 'ring-green-500');
                passwordInput.classList.remove('ring-2', 'ring-green-500');
            }, 1000);
        }

        // Copy credentials to clipboard
        function copyCredentials(email, password, button) {
            const text = `Email: ${email}\nPassword: ${password}`;
            navigator.clipboard.writeText(text).then(() => {
                const icon = button.querySelector('i');
                icon.classList.remove('fa-copy');
                icon.classList.add('fa-check');
                button.classList.add('copied');
                
                setTimeout(() => {
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-copy');
                    button.classList.remove('copied');
                }, 2000);
            });
        }
    </script>
</body>
</html>

    <!-- CRITICAL: Auth Cache Clearing - Prevents login issues after logout -->
    <script src="{{ asset('js/auth-cache-clear.js') }}"></script>
