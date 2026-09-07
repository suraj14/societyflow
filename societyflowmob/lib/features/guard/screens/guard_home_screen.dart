import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../../../shared/widgets/app_card.dart';
import '../../auth/providers/auth_provider.dart';
import '../../visitors/providers/visitors_provider.dart';
import '../../notices/screens/notices_screen.dart';
import '../../services/screens/services_screen.dart';
import '../../events/screens/events_screen.dart';
import '../../shell/main_shell.dart';

class GuardHomeScreen extends ConsumerStatefulWidget {
  const GuardHomeScreen({super.key});
  @override
  ConsumerState<GuardHomeScreen> createState() => _GuardHomeScreenState();
}

class _GuardHomeScreenState extends ConsumerState<GuardHomeScreen> with AutoRefreshMixin {
  @override
  int get refreshIntervalSeconds => 20;

  @override
  void onAutoRefresh() => ref.read(visitorsProvider.notifier).loadAll();

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(visitorsProvider.notifier).loadAll());
    startAutoRefresh();
  }

  @override
  Widget build(BuildContext context) {
    final user  = ref.watch(currentUserProvider);
    final stats = ref.watch(visitorsProvider).stats;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        onRefresh: () => ref.read(visitorsProvider.notifier).loadAll(),
        color: AppColors.primary,
        child: CustomScrollView(
          slivers: [
            // Guard header
            SliverAppBar(
              floating: true,
              backgroundColor: AppColors.surface,
              elevation: 0,
              title: Row(children: [
                Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    gradient: AppColors.primaryGradient,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.security_rounded, color: Colors.white, size: 18),
                ),
                const SizedBox(width: 10),
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Guard Panel', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppColors.textPrimary)),
                  Text(user?.societyName ?? 'Society', style: const TextStyle(fontSize: 11, color: AppColors.textHint)),
                ]),
              ]),
              actions: [
                IconButton(
                  icon: const Icon(Icons.refresh_rounded, color: AppColors.textHint),
                  onPressed: () => ref.read(visitorsProvider.notifier).loadAll(),
                ),
              ],
            ),

            // Welcome strip
            SliverToBoxAdapter(child: _welcomeStrip(user)),

            // Stats grid
            SliverToBoxAdapter(child: _statsGrid(stats)),

            // Quick actions
            SliverToBoxAdapter(child: _quickActions(context)),

            // Info modules
            SliverToBoxAdapter(child: _infoModules(context)),

            const SliverToBoxAdapter(child: SizedBox(height: 100)),
          ],
        ),
      ),
    );
  }

  Widget _welcomeStrip(user) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
    child: GradientCard(
      child: Row(children: [
        const Icon(Icons.shield_rounded, color: Colors.white, size: 36),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Welcome, ${user?.name ?? 'Guard'}!', style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(_greeting(), style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 12)),
        ])),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), borderRadius: BorderRadius.circular(20)),
          child: const Text('On Duty', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
        ),
      ]),
    ),
  );

  Widget _statsGrid(stats) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text("Today's Summary", style: AppTextStyles.h3),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: _StatTile(label: "Today's\nVisitors", value: '${stats.todayTotal}', icon: Icons.groups_rounded, color: AppColors.primary)),
        const SizedBox(width: 12),
        Expanded(child: _StatTile(label: "Pending\nApproval", value: '${stats.pending}', icon: Icons.pending_rounded, color: AppColors.warning)),
      ]),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: _StatTile(label: "Currently\nInside", value: '${stats.checkedIn}', icon: Icons.login_rounded, color: AppColors.secondary)),
        const SizedBox(width: 12),
        Expanded(child: _StatTile(label: "Checked\nOut", value: '${stats.checkedOut}', icon: Icons.logout_rounded, color: AppColors.textHint)),
      ]),
    ]),
  );

  Widget _quickActions(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Quick Actions', style: AppTextStyles.h3),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: _ActionButton(
          icon: Icons.person_add_rounded,
          label: 'New Visitor',
          color: AppColors.primary,
          onTap: () {
            mainShellKey.currentState?.switchTab(1);
            // Give time to switch then open the add sheet
            Future.delayed(const Duration(milliseconds: 300), () {
              mainShellKey.currentState?.switchTab(1);
            });
          },
        )),
        const SizedBox(width: 10),
        Expanded(child: _ActionButton(
          icon: Icons.login_rounded,
          label: 'Check-In',
          color: AppColors.secondary,
          onTap: () => mainShellKey.currentState?.switchTab(1),
        )),
        const SizedBox(width: 10),
        Expanded(child: _ActionButton(
          icon: Icons.logout_rounded,
          label: 'Check-Out',
          color: AppColors.warning,
          onTap: () => mainShellKey.currentState?.switchTab(1),
        )),
      ]),
      const SizedBox(height: 10),
      Row(children: [
        Expanded(child: _ActionButton(
          icon: Icons.list_alt_rounded,
          label: 'Visitor Log',
          color: AppColors.info,
          onTap: () => mainShellKey.currentState?.switchTab(1),
        )),
        const SizedBox(width: 10),
        Expanded(child: _ActionButton(
          icon: Icons.campaign_rounded,
          label: 'Notices',
          color: AppColors.primary,
          onTap: () => mainShellKey.currentState?.openNotices(),
        )),
        const SizedBox(width: 10),
        Expanded(child: _ActionButton(
          icon: Icons.event_rounded,
          label: 'Events',
          color: AppColors.secondary,
          onTap: () => mainShellKey.currentState?.openEvents(),
        )),
      ]),
    ]),
  );

  Widget _infoModules(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Modules', style: AppTextStyles.h3),
      const SizedBox(height: 12),
      _ModuleRow(
        icon: Icons.build_rounded,
        label: 'Services Directory',
        subtitle: 'View service providers & contact info',
        color: AppColors.secondary,
        onTap: () => mainShellKey.currentState?.openServices(),
      ),
      const SizedBox(height: 10),
      _ModuleRow(
        icon: Icons.campaign_rounded,
        label: 'Notice Board',
        subtitle: 'Society announcements & notices',
        color: AppColors.primary,
        onTap: () => mainShellKey.currentState?.openNotices(),
      ),
      const SizedBox(height: 10),
      _ModuleRow(
        icon: Icons.event_rounded,
        label: 'Events',
        subtitle: 'Upcoming society events',
        color: AppColors.warning,
        onTap: () => mainShellKey.currentState?.openEvents(),
      ),
      const SizedBox(height: 10),
      _ModuleRow(
        icon: Icons.phone_rounded,
        label: 'Emergency Contacts',
        subtitle: 'Quick dial for emergencies',
        color: AppColors.error,
        onTap: () => _showEmergencyContacts(context),
      ),
    ]),
  );

  void _showEmergencyContacts(BuildContext context) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => const _EmergencySheet(),
    );
  }

  String _greeting() {
    final h = DateTime.now().hour;
    final time = h < 12 ? 'Morning' : h < 17 ? 'Afternoon' : 'Evening';
    return 'Good $time — Stay safe on duty';
  }
}

// ── Helper widgets ────────────────────────────────────────────────────────────

class _StatTile extends StatelessWidget {
  final String label, value;
  final IconData icon;
  final Color color;
  const _StatTile({required this.label, required this.value, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) => AppCard(
    padding: const EdgeInsets.all(16),
    child: Row(children: [
      Container(
        width: 42, height: 42,
        decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: color, size: 20),
      ),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(value, style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: color)),
        Text(label, style: const TextStyle(fontSize: 11, color: AppColors.textHint, height: 1.2)),
      ])),
    ]),
  );
}

class _ActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _ActionButton({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withOpacity(0.2)),
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: color, size: 24),
        const SizedBox(height: 6),
        Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600), textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
      ]),
    ),
  );
}

class _ModuleRow extends StatelessWidget {
  final IconData icon;
  final String label, subtitle;
  final Color color;
  final VoidCallback onTap;
  const _ModuleRow({required this.icon, required this.label, required this.subtitle, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) => AppCard(
    padding: const EdgeInsets.all(14),
    onTap: onTap,
    child: Row(children: [
      Container(width: 42, height: 42, decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: color, size: 20)),
      const SizedBox(width: 14),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: AppTextStyles.label),
        const SizedBox(height: 2),
        Text(subtitle, style: AppTextStyles.caption),
      ])),
      Icon(Icons.arrow_forward_ios_rounded, size: 13, color: AppColors.textHint),
    ]),
  );
}

class _EmergencySheet extends StatelessWidget {
  const _EmergencySheet();

  static const _contacts = [
    {'label': 'Society Manager',    'phone': '1800-XXX-XXX', 'icon': Icons.manage_accounts_rounded, 'color': 0xFF2196F3},
    {'label': 'Security Supervisor','phone': '1800-XXX-XXX', 'icon': Icons.security_rounded,          'color': 0xFF4CAF50},
    {'label': 'Maintenance Team',   'phone': '1800-XXX-XXX', 'icon': Icons.build_rounded,             'color': 0xFFFF9800},
    {'label': 'Police',             'phone': '100',           'icon': Icons.local_police_rounded,      'color': 0xFF3F51B5},
    {'label': 'Ambulance',          'phone': '108',           'icon': Icons.emergency_rounded,         'color': 0xFFF44336},
    {'label': 'Fire Department',    'phone': '101',           'icon': Icons.local_fire_department_rounded, 'color': 0xFFFF5722},
  ];

  @override
  Widget build(BuildContext context) => Container(
    decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
    padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16), decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2))),
      const Text('☎ Emergency Contacts', style: AppTextStyles.h3),
      const SizedBox(height: 16),
      ..._contacts.map((c) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: AppCard(
          padding: const EdgeInsets.all(14),
          child: Row(children: [
            Container(width: 40, height: 40, decoration: BoxDecoration(color: Color(c['color'] as int).withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(c['icon'] as IconData, color: Color(c['color'] as int), size: 20)),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(c['label'] as String, style: AppTextStyles.label),
              Text(c['phone'] as String, style: AppTextStyles.caption),
            ])),
            Icon(Icons.call_rounded, color: Color(c['color'] as int), size: 22),
          ]),
        ),
      )),
    ]),
  );
}
