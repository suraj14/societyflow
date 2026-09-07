import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/storage_service.dart';
import '../../core/services/server_discovery.dart';
import '../auth/providers/auth_provider.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _fadeAnim;
  late Animation<double> _scaleAnim;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200));
    _fadeAnim  = Tween<double>(begin: 0, end: 1).animate(CurvedAnimation(parent: _controller, curve: const Interval(0, 0.6, curve: Curves.easeOut)));
    _scaleAnim = Tween<double>(begin: 0.75, end: 1).animate(CurvedAnimation(parent: _controller, curve: const Interval(0, 0.7, curve: Curves.elasticOut)));
    _controller.forward();
    _checkAuth();
  }

  Future<void> _checkAuth() async {
    // Run server discovery and auth check in parallel — both must finish
    // before we navigate. This ensures the correct IP is cached before
    // any API call is made, even if DHCP assigned a new IP today.
    await Future.wait([
      Future.delayed(const Duration(milliseconds: 1800)), // min splash time
      ServerDiscovery.resolveBaseUrl(),                   // BLOCKING discovery
    ]);

    if (!mounted) return;

    final isLoggedIn = await StorageService.isLoggedIn();
    if (isLoggedIn) await ref.read(authProvider.notifier).loadSavedUser();
    if (mounted) Navigator.pushReplacementNamed(context, isLoggedIn ? '/home' : '/login');
  }

  @override
  void dispose() { _controller.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppColors.splashGradient),
        child: SafeArea(
          child: Center(
            child: AnimatedBuilder(
              animation: _controller,
              builder: (_, __) => FadeTransition(
                opacity: _fadeAnim,
                child: ScaleTransition(
                  scale: _scaleAnim,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 96, height: 96,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(28),
                          border: Border.all(color: Colors.white.withOpacity(0.3), width: 1.5),
                        ),
                        child: const Icon(Icons.apartment_rounded, size: 48, color: Colors.white),
                      ),
                      const SizedBox(height: 24),
                      const Text('SocietyFlow', style: TextStyle(fontSize: 34, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: -1)),
                      const SizedBox(height: 8),
                      Text('Smart Society Management', style: TextStyle(fontSize: 15, color: Colors.white.withOpacity(0.8))),
                      const SizedBox(height: 64),
                      SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 2.5, valueColor: AlwaysStoppedAnimation<Color>(Colors.white.withOpacity(0.7)))),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
