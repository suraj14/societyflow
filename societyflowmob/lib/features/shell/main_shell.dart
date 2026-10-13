import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/navigation/app_destinations.dart';
import '../../core/theme/app_theme.dart';
import '../admin/screens/admin_home_screen.dart';
import '../admin/screens/admin_requests_screen.dart';
import '../admin/screens/admin_tickets_screen.dart';
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

  /// Which kind of account this is, from the server's capabilities (see AppNav) - not from the first role name.
  /// Staff is deliberately not a gate account: their endpoints reject the scanner (no dedicated Staff screen
  /// exists yet; they get the generic home).
  bool get _isGateOnly => AppNav.isGateOnly(ref.read(currentUserProvider));
  bool get _isOffice => AppNav.isOffice(ref.read(currentUserProvider));

  List<Widget> get _screens => [
        _isGateOnly ? const GuardHomeScreen() : (_isOffice ? const AdminHomeScreen() : const HomeScreen()), // 0
        const VisitorsScreen(),                                             // 1
        const PaymentsScreen(),                                             // 2
        _isOffice ? const AdminTicketsScreen() : const ComplaintsScreen(),  // 3
        const ProfileScreen(),                                              // 4
        const NoticesScreen(),                                              // 5
        const FacilitiesScreen(),                                           // 6
        const ServicesScreen(),                                             // 7
        const EventsScreen(),                                               // 8
        const AdminRequestsScreen(),                                        // 9
      ];

  void switchTab(int index) => setState(() {
        _currentIndex = index;
        _visited.add(index);
      });

  /// Tabs are built the first time they are opened, not all at login: every
  /// screen loads its data in initState, so building all of them fired a burst of API
  /// calls at startup (and resident-only ones for guards).
  final Set<int> _visited = {0};

  /// Open any destination from anywhere (home cards, guard shortcuts, admin attention rows).
  void openDest(Dest d) {
    final i = AppNav.tabIndex[d];
    if (i == null) return;
    final user = ref.read(currentUserProvider);

    // A destination that is NOT on this account's bottom bar (Notices, Facilities, Services, Events ... opened from a
    // card) is pushed as its own route: the app bar then shows a back arrow and Android Back returns to where the user
    // came from. Switching to a hidden tab left those screens with no visible way back.
    if (d != Dest.home && !AppNav.bottomBar(user).contains(d) && navigator != null) {
      navigator!.push(MaterialPageRoute(builder: (_) => _screens[i]));
      return;
    }
    switchTab(i);
  }

  NavigatorState? get navigator => mounted ? Navigator.maybeOf(context) : null;

  // Kept for the existing callers.
  void openNotices()    => openDest(Dest.notices);
  void openFacilities() => openDest(Dest.facilities);
  void openServices()   => openDest(Dest.services);
  void openEvents()     => openDest(Dest.events);
  void openComplaints() => openDest(Dest.tickets);

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(currentUserProvider);
    final bar = AppNav.bottomBar(user);
    final barIndexes = {for (final d in bar) AppNav.tabIndex[d]};
    final screens = _screens;
    // A tab that is not on the bar (a feature opened from a card) keeps "Home" lit.
    final lit = barIndexes.contains(_currentIndex) ? _currentIndex : 0;

    return Scaffold(
      body: PopScope(
        // Android Back on any tab other than Home goes Home instead of
        // closing the app.
        canPop: _currentIndex == 0,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) switchTab(0);
        },
        child: IndexedStack(
          index: _currentIndex,
          children: [
            for (var i = 0; i < screens.length; i++) _visited.contains(i) ? screens[i] : const SizedBox.shrink(),
          ],
        ),
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 20, offset: const Offset(0, -4)),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                // Equal shares of the width: five items overflowed a narrow phone (or a large system font) when each kept its own minimum width.
                for (final d in bar)
                  Expanded(
                    child: _NavItem(
                      key: Key('nav-${d.name}'),
                      icon: d == Dest.home && _isGateOnly ? Icons.shield_rounded : AppNav.all[d]!.icon,
                      label: d == Dest.home && _isGateOnly ? 'Guard' : AppNav.all[d]!.label,
                      index: AppNav.tabIndex[d]!,
                      current: lit,
                      onTap: switchTab,
                    ),
                  ),
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

  const _NavItem({super.key, required this.icon, required this.label, required this.index, required this.current, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final isActive = index == current;
    return GestureDetector(
      onTap: () => onTap(index),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        constraints: const BoxConstraints(minHeight: 48),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? AppColors.primary.withValues(alpha: 0.1) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: isActive ? AppColors.primary : AppColors.textHint, size: 22),
            const SizedBox(height: 3),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 10, fontWeight: isActive ? FontWeight.w700 : FontWeight.w400, color: isActive ? AppColors.primary : AppColors.textHint)),
          ],
        ),
      ),
    );
  }
}
