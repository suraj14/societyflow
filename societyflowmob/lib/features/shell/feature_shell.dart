import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';

/// Wraps any feature screen (Notices, Facilities, Services, Events)
/// with the same bottom navigation bar as MainShell.
/// Use this instead of Navigator.push() for feature screens.
class FeatureShell extends StatefulWidget {
  final Widget child;
  final String title;

  const FeatureShell({super.key, required this.child, required this.title});

  @override
  State<FeatureShell> createState() => _FeatureShellState();
}

class _FeatureShellState extends State<FeatureShell> {
  // Home tab is always index 0 in the main shell
  // When user taps a bottom nav item here, we pop back to MainShell
  // and switch to the correct tab

  void _onNavTap(BuildContext context, int index) {
    // Pop back to MainShell and switch tab
    Navigator.of(context).popUntil((route) => route.isFirst);
    // Use a small delay to let the pop complete, then switch tab
    // We pass the index via a global notifier
    _MainShellTabNotifier.instance.switchTo(index);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: widget.child,
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 20,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _NavItem(icon: Icons.home_rounded,          label: 'Home',     index: 0, onTap: (i) => _onNavTap(context, i)),
                _NavItem(icon: Icons.people_alt_rounded,    label: 'Visitors', index: 1, onTap: (i) => _onNavTap(context, i)),
                _NavItem(icon: Icons.receipt_long_rounded,  label: 'Bills',    index: 2, onTap: (i) => _onNavTap(context, i)),
                _NavItem(icon: Icons.support_agent_rounded, label: 'Tickets',  index: 3, onTap: (i) => _onNavTap(context, i)),
                _NavItem(icon: Icons.person_rounded,        label: 'Profile',  index: 4, onTap: (i) => _onNavTap(context, i)),
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
  final int index;
  final ValueChanged<int> onTap;

  const _NavItem({required this.icon, required this.label, required this.index, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => onTap(index),
      behavior: HitTestBehavior.opaque,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: AppColors.textHint, size: 22),
            const SizedBox(height: 3),
            Text(label, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w400, color: AppColors.textHint)),
          ],
        ),
      ),
    );
  }
}

/// Simple notifier to communicate tab switch from FeatureShell back to MainShell
class _MainShellTabNotifier {
  static final _MainShellTabNotifier instance = _MainShellTabNotifier._();
  _MainShellTabNotifier._();

  int _pendingTab = 0;
  int get pendingTab => _pendingTab;

  void switchTo(int index) => _pendingTab = index;
}
