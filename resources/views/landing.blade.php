<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings['site_title'] ?? 'SocietyFlow' }} - {{ $settings['site_tagline'] ?? 'Modern Society Management' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .gradient-text { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-white">
    <!-- Navigation -->
    <nav class="fixed w-full bg-white/95 backdrop-blur-sm shadow-sm z-50" x-data="{ mobileMenu: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <!-- Logo -->
                    <a href="{{ route('landing') }}" class="flex items-center">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden">
                            <svg viewBox="0 0 100 100" class="w-full h-full">
                                <defs>
                                    <linearGradient id="navLogoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#667eea"/>
                                        <stop offset="100%" style="stop-color:#764ba2"/>
                                    </linearGradient>
                                </defs>
                                <rect x="0" y="0" width="100" height="100" rx="15" fill="url(#navLogoGradient)"/>
                                <rect x="12" y="22" width="28" height="58" fill="white" opacity="0.9"/>
                                <rect x="16" y="28" width="6" height="6" fill="url(#navLogoGradient)"/>
                                <rect x="24" y="28" width="6" height="6" fill="url(#navLogoGradient)"/>
                                <rect x="32" y="28" width="6" height="6" fill="url(#navLogoGradient)"/>
                                <rect x="16" y="38" width="6" height="6" fill="url(#navLogoGradient)"/>
                                <rect x="24" y="38" width="6" height="6" fill="url(#navLogoGradient)"/>
                                <rect x="32" y="38" width="6" height="6" fill="url(#navLogoGradient)"/>
                                <polygon points="75,45 52,28 52,45 46,45 46,80 75,80 75,45" fill="white" opacity="0.9"/>
                                <polygon points="75,45 52,28 92,28 92,45" fill="white" opacity="0.7"/>
                                <rect x="58" y="55" width="10" height="10" fill="url(#navLogoGradient)"/>
                                <path d="M8,72 Q28,62 50,72 T92,72 L92,85 Q72,75 50,85 T8,85 Z" fill="white" opacity="0.3"/>
                            </svg>
                        </div>
                        <span class="ml-2 text-xl font-bold text-gray-800">Society</span>
                        <span class="text-xl font-bold text-purple-600">Flow</span>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-600 hover:text-purple-600 transition-colors">Features</a>
                    <a href="#pricing" class="text-gray-600 hover:text-purple-600 transition-colors">Pricing</a>
                    <a href="#reviews" class="text-gray-600 hover:text-purple-600 transition-colors">Reviews</a>
                    <a href="#faq" class="text-gray-600 hover:text-purple-600 transition-colors">FAQ</a>
                    <a href="#contact" class="text-gray-600 hover:text-purple-600 transition-colors">Contact</a>
                    <a href="{{ route('login') }}" class="text-purple-600 hover:text-purple-700 font-medium">Login</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">Get Started</a>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button @click="mobileMenu = !mobileMenu" class="text-gray-600">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div x-show="mobileMenu" x-cloak class="md:hidden bg-white border-t">
            <div class="px-4 py-3 space-y-2">
                <a href="#features" class="block py-2 text-gray-600">Features</a>
                <a href="#pricing" class="block py-2 text-gray-600">Pricing</a>
                <a href="#reviews" class="block py-2 text-gray-600">Reviews</a>
                <a href="#faq" class="block py-2 text-gray-600">FAQ</a>
                <a href="#contact" class="block py-2 text-gray-600">Contact</a>
                <a href="{{ route('login') }}" class="block py-2 text-purple-600 font-medium">Login</a>
                <a href="{{ route('register') }}" class="block py-2 px-4 bg-purple-600 text-white rounded-lg text-center">Get Started</a>
            </div>
        </div>
    </nav>


    <!-- Hero Section -->
    <section class="pt-24 pb-16 gradient-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center">
                <div class="lg:w-1/2 text-center lg:text-left mb-10 lg:mb-0">
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white leading-tight">
                        {{ $settings['hero_title'] ?? 'Manage Multiple Societies, Villas & Apartments' }}
                    </h1>
                    <p class="mt-6 text-lg text-white/90 max-w-xl">
                        {{ $settings['hero_subtitle'] ?? 'Complete SaaS solution for managing residential societies, villa communities, townships, and apartment complexes from a single unified platform.' }}
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="{{ $settings['hero_button_url'] ?? route('register') }}" 
                           class="px-8 py-3 bg-white text-purple-600 rounded-lg font-semibold hover:bg-gray-100 transition-colors shadow-lg">
                            {{ $settings['hero_button_text'] ?? 'Start Free Trial' }}
                        </a>
                        <a href="#features" class="px-8 py-3 border-2 border-white text-white rounded-lg font-semibold hover:bg-white/10 transition-colors">
                            Explore Features
                        </a>
                    </div>
                    <div class="mt-8 flex items-center justify-center lg:justify-start space-x-6 text-white/80">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>14-Day Free Trial</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>No Credit Card</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>24/7 Support</span>
                        </div>
                    </div>
                </div>
                <div class="lg:w-1/2 lg:pl-12">
                    @if(isset($settings['hero_image']) && $settings['hero_image'])
                        <img src="{{ Storage::url($settings['hero_image']) }}" alt="Hero" class="rounded-2xl shadow-2xl">
                    @else
                        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 shadow-2xl">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white rounded-xl p-4 shadow">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-building text-purple-600"></i>
                                        </div>
                                        <span class="ml-3 font-semibold text-gray-800">Societies</span>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-800">500+</p>
                                </div>
                                <div class="bg-white rounded-xl p-4 shadow">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-users text-green-600"></i>
                                        </div>
                                        <span class="ml-3 font-semibold text-gray-800">Residents</span>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-800">50K+</p>
                                </div>
                                <div class="bg-white rounded-xl p-4 shadow">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-home text-blue-600"></i>
                                        </div>
                                        <span class="ml-3 font-semibold text-gray-800">Units</span>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-800">100K+</p>
                                </div>
                                <div class="bg-white rounded-xl p-4 shadow">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-star text-yellow-600"></i>
                                        </div>
                                        <span class="ml-3 font-semibold text-gray-800">Rating</span>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-800">4.9/5</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>


    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800">Powerful Features for Complete Management</h2>
                <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">Manage multiple societies, villas, townships, and apartments all from one unified platform</p>
            </div>

            @if($features->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($features as $feature)
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                        @if($feature->type === 'icon')
                            <i class="{{ $feature->icon }} text-2xl text-purple-600"></i>
                        @else
                            <img src="{{ Storage::url($feature->image) }}" alt="" class="w-10 h-10 object-cover rounded">
                        @endif
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">{{ $feature->title }}</h3>
                    <p class="text-gray-600">{{ $feature->description }}</p>
                </div>
                @endforeach
            </div>
            @else
            <!-- Default Features -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-building text-2xl text-purple-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Multi-Society Management</h3>
                    <p class="text-gray-600">Manage unlimited societies, buildings, villas, and townships from a single dashboard. Perfect for property management companies.</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-home text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Villa & Township Support</h3>
                    <p class="text-gray-600">Dedicated features for villa communities and townships with area-based management and owner portals.</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-credit-card text-2xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Billing & Payments</h3>
                    <p class="text-gray-600">Automated billing, online payments, utility bills tracking, and comprehensive financial reporting.</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-user-shield text-2xl text-yellow-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Visitor Management</h3>
                    <p class="text-gray-600">Pre-approve visitors, track entry/exit with photos, and maintain digital security logs for all properties.</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-exclamation-circle text-2xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Complaint Management</h3>
                    <p class="text-gray-600">Ticketing system for complaints with categories, attachments, and status tracking for quick resolution.</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-calendar-check text-2xl text-indigo-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Facility Booking</h3>
                    <p class="text-gray-600">Online booking for amenities like clubhouse, gym, pool, and sports facilities with approval workflows.</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-pink-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-bell text-2xl text-pink-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Notice Board</h3>
                    <p class="text-gray-600">Post announcements, notices, and important updates to all residents with read receipts and approval system.</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-cyan-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-wrench text-2xl text-cyan-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Service Providers</h3>
                    <p class="text-gray-600">Manage service providers, track attendance, and maintain service records for maintenance and operations.</p>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-orange-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-lock text-2xl text-orange-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Role-Based Access</h3>
                    <p class="text-gray-600">Granular permissions for Super Admin, Admin, Tenants, and Villa Owners with data isolation.</p>
                </div>
            </div>
            @endif
        </div>
    </section>


    <!-- User Roles & Demo Credentials Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800">Role-Based Access Control</h2>
                <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">Different roles with tailored features and permissions for each user type</p>
            </div>

            <!-- User Roles Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-16">
                <!-- Super Admin -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6 border border-purple-200">
                    <div class="w-12 h-12 bg-purple-600 text-white rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-crown text-lg"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Super Admin</h3>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <li class="flex items-start"><i class="fas fa-check text-purple-600 mr-2 mt-1"></i>Manage all societies</li>
                        <li class="flex items-start"><i class="fas fa-check text-purple-600 mr-2 mt-1"></i>Global settings</li>
                        <li class="flex items-start"><i class="fas fa-check text-purple-600 mr-2 mt-1"></i>Billing & packages</li>
                        <li class="flex items-start"><i class="fas fa-check text-purple-600 mr-2 mt-1"></i>Admin management</li>
                    </ul>
                </div>

                <!-- Admin -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200">
                    <div class="w-12 h-12 bg-blue-600 text-white rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-user-tie text-lg"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Admin</h3>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <li class="flex items-start"><i class="fas fa-check text-blue-600 mr-2 mt-1"></i>Society management</li>
                        <li class="flex items-start"><i class="fas fa-check text-blue-600 mr-2 mt-1"></i>User management</li>
                        <li class="flex items-start"><i class="fas fa-check text-blue-600 mr-2 mt-1"></i>Billing & payments</li>
                        <li class="flex items-start"><i class="fas fa-check text-blue-600 mr-2 mt-1"></i>Reports & analytics</li>
                    </ul>
                </div>

                <!-- Tenant -->
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-6 border border-yellow-200">
                    <div class="w-12 h-12 bg-yellow-600 text-white rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-key text-lg"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Tenant</h3>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <li class="flex items-start"><i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>View rent details</li>
                        <li class="flex items-start"><i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>Pay rent online</li>
                        <li class="flex items-start"><i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>Submit requests</li>
                        <li class="flex items-start"><i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>View notices</li>
                    </ul>
                </div>

                <!-- Villa/Apartment Owner -->
                <div class="bg-gradient-to-br from-pink-50 to-pink-100 rounded-xl p-6 border border-pink-200">
                    <div class="w-12 h-12 bg-pink-600 text-white rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-building text-lg"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Owner</h3>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <li class="flex items-start"><i class="fas fa-check text-pink-600 mr-2 mt-1"></i>Property management</li>
                        <li class="flex items-start"><i class="fas fa-check text-pink-600 mr-2 mt-1"></i>Tenant assignment</li>
                        <li class="flex items-start"><i class="fas fa-check text-pink-600 mr-2 mt-1"></i>Visitor management</li>
                        <li class="flex items-start"><i class="fas fa-check text-pink-600 mr-2 mt-1"></i>Billing overview</li>
                    </ul>
                </div>
            </div>

            <!-- Demo Credentials Section -->
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl p-8 text-white">
                <h3 class="text-2xl font-bold mb-8 text-center">Try SocietyFlow Now - Demo Credentials</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Super Admin -->
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-purple-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-crown text-white"></i>
                            </div>
                            <h4 class="ml-3 font-semibold text-lg">Super Admin</h4>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-white/70">Email:</p>
                                <p class="font-mono text-white break-all">superadmin@societyflow.com</p>
                            </div>
                            <div>
                                <p class="text-white/70">Password:</p>
                                <p class="font-mono text-white">password</p>
                            </div>
                            <p class="text-white/60 text-xs pt-2">Manage all societies and global settings</p>
                        </div>
                    </div>

                    <!-- Admin 1 -->
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-tie text-white"></i>
                            </div>
                            <h4 class="ml-3 font-semibold text-lg">Admin</h4>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-white/70">Email:</p>
                                <p class="font-mono text-white break-all">admin@societyflow.com</p>
                            </div>
                            <div>
                                <p class="text-white/70">Password:</p>
                                <p class="font-mono text-white">password</p>
                            </div>
                            <p class="text-white/60 text-xs pt-2">Manage single society</p>
                        </div>
                    </div>

                    <!-- Admin 2 -->
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-tie text-white"></i>
                            </div>
                            <h4 class="ml-3 font-semibold text-lg">Admin 2</h4>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-white/70">Email:</p>
                                <p class="font-mono text-white break-all">sahgunvilla@gmail.com</p>
                            </div>
                            <div>
                                <p class="text-white/70">Password:</p>
                                <p class="font-mono text-white">password</p>
                            </div>
                            <p class="text-white/60 text-xs pt-2">Villa community management</p>
                        </div>
                    </div>

                    <!-- Admin 3 -->
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-tie text-white"></i>
                            </div>
                            <h4 class="ml-3 font-semibold text-lg">Admin 3</h4>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-white/70">Email:</p>
                                <p class="font-mono text-white break-all">mohallal@gmail.com</p>
                            </div>
                            <div>
                                <p class="text-white/70">Password:</p>
                                <p class="font-mono text-white">password</p>
                            </div>
                            <p class="text-white/60 text-xs pt-2">Apartment complex management</p>
                        </div>
                    </div>

                    <!-- Tenant -->
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-yellow-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-key text-white"></i>
                            </div>
                            <h4 class="ml-3 font-semibold text-lg">Tenant</h4>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-white/70">Email:</p>
                                <p class="font-mono text-white break-all">tenant@example.com</p>
                            </div>
                            <div>
                                <p class="text-white/70">Password:</p>
                                <p class="font-mono text-white">password123</p>
                            </div>
                            <p class="text-white/60 text-xs pt-2">Rented property access</p>
                        </div>
                    </div>

                    <!-- Villa Owner -->
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-pink-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-home text-white"></i>
                            </div>
                            <h4 class="ml-3 font-semibold text-lg">Villa Owner</h4>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-white/70">Email:</p>
                                <p class="font-mono text-white break-all">urbanvilla@gmail.com</p>
                            </div>
                            <div>
                                <p class="text-white/70">Password:</p>
                                <p class="font-mono text-white">password123</p>
                            </div>
                            <p class="text-white/60 text-xs pt-2">Villa property management</p>
                        </div>
                    </div>

                    <!-- Apartment Owner -->
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-pink-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-building text-white"></i>
                            </div>
                            <h4 class="ml-3 font-semibold text-lg">Apt Owner</h4>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-white/70">Email:</p>
                                <p class="font-mono text-white break-all">ubapartment@gmail.com</p>
                            </div>
                            <div>
                                <p class="text-white/70">Password:</p>
                                <p class="font-mono text-white">password123</p>
                            </div>
                            <p class="text-white/60 text-xs pt-2">Apartment property management</p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <a href="{{ route('login') }}" class="inline-block px-8 py-3 bg-white text-purple-600 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                        Login with Demo Credentials
                    </a>
                </div>
            </div>
        </div>
    </section>
    <section id="pricing" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800">Simple, Transparent Pricing</h2>
                <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">Choose the plan that fits your society's needs</p>
            </div>

            @if($pricing->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ min($pricing->count(), 3) }} gap-8 max-w-5xl mx-auto">
                @foreach($pricing as $plan)
                <div class="bg-white rounded-2xl shadow-xl {{ $plan->is_popular ? 'ring-2 ring-purple-500 relative' : 'border border-gray-200' }}">
                    @if($plan->is_popular)
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                            <span class="bg-purple-600 text-white px-4 py-1 rounded-full text-sm font-medium">Most Popular</span>
                        </div>
                    @endif
                    <div class="p-8">
                        <h3 class="text-2xl font-bold text-gray-800">{{ $plan->name }}</h3>
                        <p class="text-gray-600 mt-2">{{ $plan->description }}</p>
                        <div class="mt-6">
                            <span class="text-4xl font-bold text-gray-800">${{ number_format($plan->monthly_price, 0) }}</span>
                            <span class="text-gray-500">/month</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">or ${{ number_format($plan->yearly_price, 0) }}/year (save {{ round((1 - ($plan->yearly_price / ($plan->monthly_price * 12))) * 100) }}%)</p>
                        
                        @if($plan->features)
                        <ul class="mt-8 space-y-4">
                            @foreach($plan->features as $feature)
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">{{ $feature }}</span>
                            </li>
                            @endforeach
                        </ul>
                        @endif
                        
                        <a href="{{ route('register') }}" 
                           class="mt-8 block w-full py-3 text-center rounded-lg font-semibold transition-colors {{ $plan->is_popular ? 'bg-purple-600 text-white hover:bg-purple-700' : 'bg-gray-100 text-gray-800 hover:bg-gray-200' }}">
                            Get Started
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <!-- Default Pricing -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8">
                    <h3 class="text-2xl font-bold text-gray-800">Starter</h3>
                    <p class="text-gray-600 mt-2">For small societies</p>
                    <div class="mt-6">
                        <span class="text-4xl font-bold text-gray-800">$29</span>
                        <span class="text-gray-500">/month</span>
                    </div>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>Up to 50 units</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>Basic features</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>Email support</li>
                    </ul>
                    <a href="{{ route('register') }}" class="mt-8 block w-full py-3 text-center bg-gray-100 text-gray-800 rounded-lg font-semibold hover:bg-gray-200">Get Started</a>
                </div>
                <div class="bg-white rounded-2xl shadow-xl ring-2 ring-purple-500 relative p-8">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <span class="bg-purple-600 text-white px-4 py-1 rounded-full text-sm font-medium">Most Popular</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800">Professional</h3>
                    <p class="text-gray-600 mt-2">For growing societies</p>
                    <div class="mt-6">
                        <span class="text-4xl font-bold text-gray-800">$79</span>
                        <span class="text-gray-500">/month</span>
                    </div>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>Up to 200 units</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>All features</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>Priority support</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>Custom branding</li>
                    </ul>
                    <a href="{{ route('register') }}" class="mt-8 block w-full py-3 text-center bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700">Get Started</a>
                </div>
                <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8">
                    <h3 class="text-2xl font-bold text-gray-800">Enterprise</h3>
                    <p class="text-gray-600 mt-2">For large societies</p>
                    <div class="mt-6">
                        <span class="text-4xl font-bold text-gray-800">$199</span>
                        <span class="text-gray-500">/month</span>
                    </div>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>Unlimited units</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>All features</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>24/7 support</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i>Dedicated manager</li>
                    </ul>
                    <a href="{{ route('register') }}" class="mt-8 block w-full py-3 text-center bg-gray-100 text-gray-800 rounded-lg font-semibold hover:bg-gray-200">Contact Sales</a>
                </div>
            </div>
            @endif
        </div>
    </section>


    <!-- Reviews Section -->
    <section id="reviews" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800">What Our Customers Say</h2>
                <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">Trusted by hundreds of societies worldwide</p>
            </div>

            @if($reviews->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($reviews as $review)
                <div class="bg-white rounded-xl p-6 shadow-lg">
                    <div class="flex mb-4">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300' }}"></i>
                        @endfor
                    </div>
                    <p class="text-gray-600 mb-6">"{{ $review->review }}"</p>
                    <div class="flex items-center">
                        @if($review->avatar)
                            <img src="{{ Storage::url($review->avatar) }}" alt="" class="w-12 h-12 rounded-full object-cover">
                        @else
                            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                                <span class="text-purple-600 font-semibold text-lg">{{ substr($review->name, 0, 1) }}</span>
                            </div>
                        @endif
                        <div class="ml-4">
                            <h4 class="font-semibold text-gray-800">{{ $review->name }}</h4>
                            <p class="text-sm text-gray-500">{{ $review->designation }}{{ $review->company ? ', ' . $review->company : '' }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <!-- Default Reviews -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="bg-white rounded-xl p-6 shadow-lg">
                    <div class="flex mb-4">
                        @for($i = 1; $i <= 5; $i++)<i class="fas fa-star text-yellow-400"></i>@endfor
                    </div>
                    <p class="text-gray-600 mb-6">"SocietyFlow has transformed how we manage our residential complex. The billing automation alone saves us hours every month."</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <span class="text-purple-600 font-semibold text-lg">R</span>
                        </div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-gray-800">Rajesh Kumar</h4>
                            <p class="text-sm text-gray-500">Society President, Green Valley</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg">
                    <div class="flex mb-4">
                        @for($i = 1; $i <= 5; $i++)<i class="fas fa-star text-yellow-400"></i>@endfor
                    </div>
                    <p class="text-gray-600 mb-6">"The visitor management system is excellent. Our security team loves it, and residents feel safer knowing who's entering the premises."</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <span class="text-green-600 font-semibold text-lg">P</span>
                        </div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-gray-800">Priya Sharma</h4>
                            <p class="text-sm text-gray-500">Secretary, Sunrise Apartments</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-lg">
                    <div class="flex mb-4">
                        @for($i = 1; $i <= 5; $i++)<i class="fas fa-star text-yellow-400"></i>@endfor
                    </div>
                    <p class="text-gray-600 mb-6">"Best investment we made for our villa community. The support team is responsive and the features keep getting better."</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <span class="text-blue-600 font-semibold text-lg">A</span>
                        </div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-gray-800">Amit Patel</h4>
                            <p class="text-sm text-gray-500">Manager, Palm Villas</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </section>


    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800">Frequently Asked Questions</h2>
                <p class="mt-4 text-lg text-gray-600">Got questions? We've got answers.</p>
            </div>

            <div class="space-y-4" x-data="{ openFaq: null }">
                @if($faqs->count() > 0)
                    @foreach($faqs as $index => $faq)
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button @click="openFaq = openFaq === {{ $index }} ? null : {{ $index }}" 
                                class="w-full px-6 py-4 text-left flex items-center justify-between bg-white hover:bg-gray-50 transition-colors">
                            <span class="font-semibold text-gray-800">{{ $faq->question }}</span>
                            <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="openFaq === {{ $index }} ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="openFaq === {{ $index }}" x-collapse x-cloak class="px-6 pb-4 text-gray-600">
                            {{ $faq->answer }}
                        </div>
                    </div>
                    @endforeach
                @else
                    <!-- Default FAQs -->
                    @php
                    $defaultFaqs = [
                        ['q' => 'How do I get started with SocietyFlow?', 'a' => 'Simply sign up for a free trial, add your society details, and start inviting residents. Our onboarding wizard will guide you through the setup process.'],
                        ['q' => 'Can I import existing resident data?', 'a' => 'Yes! You can import resident data via CSV/Excel files. Our support team can also help with data migration from other systems.'],
                        ['q' => 'Is my data secure?', 'a' => 'Absolutely. We use industry-standard encryption, regular backups, and comply with data protection regulations to keep your data safe.'],
                        ['q' => 'Can residents pay maintenance fees online?', 'a' => 'Yes, residents can pay via multiple payment methods including credit/debit cards, UPI, and net banking. All transactions are secure and instant.'],
                        ['q' => 'Do you offer customer support?', 'a' => 'Yes, we offer email support for all plans, priority support for Professional plans, and 24/7 dedicated support for Enterprise customers.'],
                    ];
                    @endphp
                    @foreach($defaultFaqs as $index => $faq)
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button @click="openFaq = openFaq === {{ $index }} ? null : {{ $index }}" 
                                class="w-full px-6 py-4 text-left flex items-center justify-between bg-white hover:bg-gray-50 transition-colors">
                            <span class="font-semibold text-gray-800">{{ $faq['q'] }}</span>
                            <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="openFaq === {{ $index }} ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="openFaq === {{ $index }}" x-collapse x-cloak class="px-6 pb-4 text-gray-600">
                            {{ $faq['a'] }}
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>


    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800">Get In Touch</h2>
                <p class="mt-4 text-lg text-gray-600">Have questions? We'd love to hear from you.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Contact Info -->
                <div>
                    <div class="bg-white rounded-xl p-8 shadow-lg">
                        <h3 class="text-xl font-semibold text-gray-800 mb-6">Contact Information</h3>
                        <div class="space-y-6">
                            @if(isset($settings['contact_email']) && $settings['contact_email'])
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-medium text-gray-800">Email</h4>
                                    <a href="mailto:{{ $settings['contact_email'] }}" class="text-purple-600 hover:text-purple-700">{{ $settings['contact_email'] }}</a>
                                </div>
                            </div>
                            @endif
                            
                            @if(isset($settings['contact_phone']) && $settings['contact_phone'])
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-medium text-gray-800">Phone</h4>
                                    <a href="tel:{{ $settings['contact_phone'] }}" class="text-gray-600">{{ $settings['contact_phone'] }}</a>
                                </div>
                            </div>
                            @endif
                            
                            @if(isset($settings['contact_address']) && $settings['contact_address'])
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-map-marker-alt text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-medium text-gray-800">Address</h4>
                                    <p class="text-gray-600">{{ $settings['contact_address'] }}</p>
                                </div>
                            </div>
                            @endif

                            @if(!isset($settings['contact_email']) && !isset($settings['contact_phone']) && !isset($settings['contact_address']))
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-medium text-gray-800">Email</h4>
                                    <a href="mailto:support@societyflow.com" class="text-purple-600 hover:text-purple-700">support@societyflow.com</a>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-medium text-gray-800">Phone</h4>
                                    <p class="text-gray-600">+1 (555) 123-4567</p>
                                </div>
                            </div>
                            @endif
                        </div>

                        <!-- Social Links -->
                        <div class="mt-8 pt-6 border-t">
                            <h4 class="font-medium text-gray-800 mb-4">Follow Us</h4>
                            <div class="flex space-x-4">
                                @if(isset($settings['social_facebook']) && $settings['social_facebook'])
                                <a href="{{ $settings['social_facebook'] }}" target="_blank" class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center hover:bg-blue-700 transition-colors">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                @endif
                                @if(isset($settings['social_twitter']) && $settings['social_twitter'])
                                <a href="{{ $settings['social_twitter'] }}" target="_blank" class="w-10 h-10 bg-blue-400 text-white rounded-lg flex items-center justify-center hover:bg-blue-500 transition-colors">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                @endif
                                @if(isset($settings['social_instagram']) && $settings['social_instagram'])
                                <a href="{{ $settings['social_instagram'] }}" target="_blank" class="w-10 h-10 bg-pink-600 text-white rounded-lg flex items-center justify-center hover:bg-pink-700 transition-colors">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                @endif
                                @if(isset($settings['social_linkedin']) && $settings['social_linkedin'])
                                <a href="{{ $settings['social_linkedin'] }}" target="_blank" class="w-10 h-10 bg-blue-700 text-white rounded-lg flex items-center justify-center hover:bg-blue-800 transition-colors">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                @endif
                                @if(!isset($settings['social_facebook']) && !isset($settings['social_twitter']))
                                <a href="#" class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center hover:bg-blue-700 transition-colors">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="#" class="w-10 h-10 bg-blue-400 text-white rounded-lg flex items-center justify-center hover:bg-blue-500 transition-colors">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="w-10 h-10 bg-pink-600 text-white rounded-lg flex items-center justify-center hover:bg-pink-700 transition-colors">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="#" class="w-10 h-10 bg-blue-700 text-white rounded-lg flex items-center justify-center hover:bg-blue-800 transition-colors">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="bg-white rounded-xl p-8 shadow-lg">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6">Send us a Message</h3>
                    <form action="#" method="POST" class="space-y-6">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Your Name</label>
                                <input type="text" name="name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" name="email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                            <input type="text" name="subject" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                            <textarea name="message" rows="4" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                        </div>
                        <button type="submit" class="w-full py-3 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700 transition-colors">
                            Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>


    <!-- CTA Section -->
    <section class="py-20 gradient-bg">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white">Ready to Transform Your Society Management?</h2>
            <p class="mt-4 text-lg text-white/90">Join hundreds of societies already using SocietyFlow</p>
            <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="px-8 py-3 bg-white text-purple-600 rounded-lg font-semibold hover:bg-gray-100 transition-colors shadow-lg">
                    Start Free Trial
                </a>
                <a href="#contact" class="px-8 py-3 border-2 border-white text-white rounded-lg font-semibold hover:bg-white/10 transition-colors">
                    Contact Sales
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden">
                            <svg viewBox="0 0 100 100" class="w-full h-full">
                                <defs>
                                    <linearGradient id="footerLogoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#667eea"/>
                                        <stop offset="100%" style="stop-color:#764ba2"/>
                                    </linearGradient>
                                </defs>
                                <rect x="0" y="0" width="100" height="100" rx="15" fill="url(#footerLogoGradient)"/>
                                <rect x="12" y="22" width="28" height="58" fill="white" opacity="0.9"/>
                                <polygon points="75,45 52,28 52,45 46,45 46,80 75,80 75,45" fill="white" opacity="0.9"/>
                            </svg>
                        </div>
                        <span class="ml-2 text-xl font-bold text-white">Society</span>
                        <span class="text-xl font-bold text-purple-400">Flow</span>
                    </div>
                    <p class="text-gray-400 max-w-md">Complete solution for managing residential societies, apartments, and villas. Simplify operations, improve communication, and enhance resident experience.</p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
                        <li><a href="#pricing" class="hover:text-white transition-colors">Pricing</a></li>
                        <li><a href="#reviews" class="hover:text-white transition-colors">Reviews</a></li>
                        <li><a href="#faq" class="hover:text-white transition-colors">FAQ</a></li>
                        <li><a href="#contact" class="hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-12 pt-8 border-t border-gray-800 text-center">
                <p>&copy; {{ date('Y') }} {{ $settings['site_title'] ?? 'SocietyFlow' }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</body>
</html>
