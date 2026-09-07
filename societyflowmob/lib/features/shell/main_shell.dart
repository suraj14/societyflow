import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/theme/app_theme.dart';
import '../home/screens/home_screen.dart';
import '../guard/screens/guard_home_screen.dart';
import '../visitors/screens/visitors_screen.dart';
import '../payments/screens/payments_screen.dart';
import '../complaints/screens/complaints_screen.dart';
import '../profile/screens/profile_screen.dart';
import '../notices/screens/notices_screen.dart';
import '../facilities/screens/facilities_screen.dart';
import '../services/screens/services_screen.dart';
import '../events/screens/events_screen.dart';
import '../auth/providers/auth_provider.dart';

// Global key so any screen can switch tabs or open feature screens
final mainShellKey = GlobalKey<_MainShellState>();

class MainShell extends ConsumerStatefulWidget {
  const MainShell({super.key});

  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell> {
  int _currentIndex = 0;

  // Feature tab indices (hidden from bottom nav, accessible via home cards)
  static const int _noticesIndex    = 5;
  static const int _facilitiesIndex = 6;
  static const int _servicesIndex   = 7;
  static const int _eventsIndex     = 8;

  bool get _isGuard {
    final role = ref.read(currentUserProvider)?.role ?? '';
    return role == 'Guard' || role == 'Staff';
  }

  List<Widget> get _screens {
    final homeScreen = _isGuard ? const GuardHomeScreen() : const HomeScreen();
    return [
      homeScreen,         // 0 — Home or Guard Dashboard
      const VisitorsScreen(),   // 1
      const PaymentsScreen(),   // 2
      const ComplaintsScreen(), // 3
      const ProfileScreen(),    // 4
      const NoticesScreen(),    // 5
      const FacilitiesScreen(), // 6
      const ServicesScreen(),   // 7
      const EventsScreen(),     // 8
    ];
  }

  void switchTab(int index) => setState(() => _currentIndex = index);

  // Called from home cards / guard quick actions
  void openNotices()    => switchTab(_noticesIndex);
  void openFacilities() => switchTab(_facilitiesIndex);
  void openServices()   => switchTab(_servicesIndex);
  void openEvents()     => switchTab(_eventsIndex);

  bool get _isFeatureTab => _currentIndex >= 5;

  @override
  Widget build(BuildContext context) {
    final isGuard = _isGuard;
    final screens = _screens;

    return Scaffold(
      body: IndexedStack(index: _currentIndex, children: screens),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 20, offset: const Offset(0, -4)),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _NavItem(
                  icon: isGuard ? Icons.shield_rounded : Icons.home_rounded,
                  label: isGuard ? 'Guard' : 'Home',
                  index: 0,
                  current: _isFeatureTab ? 0 : _currentIndex,
                  onTap: (i) => setState(() => _currentIndex = i),
                ),
                _NavItem(icon: Icons.people_alt_rounded,    label: 'Visitors', index: 1, current: _isFeatureTab ? 0 : _currentIndex, onTap: (i) => setState(() => _currentIndex = i)),
                // Guard doesn't use Bills/Tickets tabs but they remain for consistency
                // For guard, Bills → Services, Tickets → Notices
                if (isGuard) ...[
                  _NavItem(icon: Icons.build_rounded,           label: 'Services', index: 7, current: _currentIndex, onTap: (i) => setState(() => _currentIndex = i)),
                  _NavItem(icon: Icons.campaign_rounded,        label: 'Notices',  index: 5, current: _currentIndex, onTap: (i) => setState(() => _currentIndex = i)),
                ] else ...[
                  _NavItem(icon: Icons.receipt_long_rounded,    label: 'Bills',    index: 2, current: _isFeatureTab ? 0 : _currentIndex, onTap: (i) => setState(() => _currentIndex = i)),
                  _NavItem(icon: Icons.support_agent_rounded,   label: 'Tickets',  index: 3, current: _isFeatureTab ? 0 : _currentIndex, onTap: (i) => setState(() => _currentIndex = i)),
                ],
                _NavItem(icon: Icons.person_rounded,        label: 'Profile',  index: 4, current: _isFeatureTab ? 0 : _currentIndex, onTap: (i) => setState(() => _currentIndex = i)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final int index, current;
  final ValueChanged<int> onTap;

  const _NavItem({required this.icon, required this.label, required this.index, required this.current, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final isActive = index == current;
    return GestureDetector(
      onTap: () => onTap(index),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? AppColors.primary.withOpacity(0.1) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: isActive ? AppColors.primary : AppColors.textHint, size: 22),
            const SizedBox(height: 3),
            Text(label, style: TextStyle(fontSize: 10, fontWeight: isActive ? FontWeight.w700 : FontWeight.w400, color: isActive ? AppColors.primary : AppColors.textHint)),
          ],
        ),
      ),
    );
  }
}
