<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Sign in - SocietyFlow</title>
    <!-- Tailwind CSS Direct from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .brand-panel {
            background:
                radial-gradient(60rem 40rem at -10% -10%, rgba(167, 139, 250, .55), transparent 60%),
                radial-gradient(50rem 40rem at 110% 110%, rgba(56, 189, 248, .35), transparent 55%),
                linear-gradient(135deg, #4338ca 0%, #6d28d9 55%, #7c3aed 100%);
        }
        .soft-card { box-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 24px 48px -16px rgba(15, 23, 42, .18); }
        .field:focus { outline: none; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-2">

        <!-- Brand panel (desktop) -->
        <aside class="brand-panel hidden lg:flex flex-col justify-between p-12 text-white">
            <a href="/" class="inline-flex items-center gap-3 w-fit">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                    <i class="fas fa-building text-lg"></i>
                </span>
                <span class="text-2xl font-bold tracking-tight">Society<span class="text-violet-200">Flow</span></span>
            </a>

            <div class="max-w-md">
                <h2 class="text-4xl font-bold leading-tight tracking-tight">One calm place to run your whole community.</h2>
                <p class="mt-4 text-violet-100 text-lg">Bills and payments, visitors at the gate, notices, bookings and complaints - clear for residents, simple for the committee.</p>
                <ul class="mt-8 space-y-4 text-violet-50">
                    <li class="flex items-start gap-3"><span class="mt-0.5 inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg bg-white/15"><i class="fas fa-user-check text-sm"></i></span><span><strong class="font-semibold text-white">Visitor approval</strong><br><span class="text-violet-100 text-sm">The gate asks, residents decide, everything is recorded.</span></span></li>
                    <li class="flex items-start gap-3"><span class="mt-0.5 inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg bg-white/15"><i class="fas fa-indian-rupee-sign text-sm"></i></span><span><strong class="font-semibold text-white">Honest finances</strong><br><span class="text-violet-100 text-sm">What is billed, what is received and what is owed - never mixed up.</span></span></li>
                    <li class="flex items-start gap-3"><span class="mt-0.5 inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg bg-white/15"><i class="fas fa-shield-halved text-sm"></i></span><span><strong class="font-semibold text-white">Your society's data stays yours</strong><br><span class="text-violet-100 text-sm">Every society is fully separated from every other.</span></span></li>
                </ul>
            </div>

            <p class="text-sm text-violet-200">&copy; {{ date('Y') }} SocietyFlow. All rights reserved.</p>
        </aside>

        <!-- Sign-in -->
        <main class="flex items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-md">

                <!-- Brand (mobile) -->
                <a href="/" class="lg:hidden flex items-center justify-center gap-3 mb-8">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-lg">
                        <i class="fas fa-building text-lg"></i>
                    </span>
                    <span class="text-2xl font-bold tracking-tight text-slate-900">Society<span class="text-violet-600">Flow</span></span>
                </a>

                <div class="soft-card rounded-3xl bg-white p-7 sm:p-9 ring-1 ring-slate-200/70">
                    <div class="mb-7">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
                        <p class="mt-1 text-slate-500">Sign in to your account to continue.</p>
                    </div>

                    @if(session('success') || session('status'))
                        <div class="mb-5 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">
                            <i class="fas fa-circle-check mt-0.5"></i><span>{{ session('success') ?? session('status') }}</span>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mb-5 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                            <i class="fas fa-triangle-exclamation mt-0.5"></i><span>{{ session('error') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5" novalidate>
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="far fa-envelope"></i></span>
                                <input type="email" name="email" id="email" required autofocus autocomplete="username" inputmode="email"
                                       value="{{ old('email') }}" placeholder="you@example.com"
                                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                       class="field w-full rounded-xl border bg-white py-3 pl-11 pr-4 text-slate-900 placeholder-slate-400 transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100 {{ $errors->has('email') ? 'border-red-400' : 'border-slate-300' }}">
                            </div>
                            @error('email')
                                <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600" role="alert"><i class="fas fa-circle-exclamation"></i>{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                                <a href="{{ route('password.request') }}" class="text-sm font-medium text-violet-600 hover:text-violet-800">Forgot password?</a>
                            </div>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="Enter your password"
                                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                       class="field w-full rounded-xl border bg-white py-3 pl-11 pr-12 text-slate-900 placeholder-slate-400 transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100 {{ $errors->has('password') ? 'border-red-400' : 'border-slate-300' }}">
                                <button type="button" onclick="togglePassword()" aria-label="Show or hide password" aria-pressed="false" id="toggleBtn"
                                        class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-400 hover:text-slate-600">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600" role="alert"><i class="fas fa-circle-exclamation"></i>{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex cursor-pointer items-center gap-2.5">
                            <input type="checkbox" name="remember" id="remember" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                            <span class="text-sm text-slate-600">Keep me signed in on this device</span>
                        </label>

                        <button type="submit"
                                class="w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-3 font-semibold text-white shadow-lg shadow-violet-500/25 transition hover:from-indigo-700 hover:to-violet-700 focus:outline-none focus:ring-4 focus:ring-violet-200 active:scale-[.99]">
                            Sign in <i class="fas fa-arrow-right ml-1.5 text-sm"></i>
                        </button>
                    </form>

                    <div class="mt-7 border-t border-slate-100 pt-6 text-center text-sm text-slate-600">
                        New to SocietyFlow?
                        <a href="{{ route('register') }}" class="font-semibold text-violet-600 hover:text-violet-800">Create an account</a>
                    </div>
                </div>

                <p class="mt-6 text-center text-sm text-slate-500">
                    <a href="/" class="inline-flex items-center gap-1.5 hover:text-slate-700"><i class="fas fa-arrow-left text-xs"></i> Back to home</a>
                </p>
                <p class="mt-6 text-center text-xs text-slate-400 lg:hidden">&copy; {{ date('Y') }} SocietyFlow. All rights reserved.</p>
            </div>
        </main>
    </div>

    <script>
        // Prevent back button from showing cached page
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                location.reload(true);
            }
        });

        // Toggle password visibility
        function togglePassword() {
            var input = document.getElementById('password');
            var icon = document.getElementById('toggleIcon');
            var btn = document.getElementById('toggleBtn');
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        }
    </script>

    <!-- CRITICAL: Auth Cache Clearing - Prevents login issues after logout -->
    <script src="{{ asset('js/auth-cache-clear.js') }}"></script>
</body>
</html>
