import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../../../shared/widgets/app_card.dart';
import '../../auth/providers/auth_provider.dart';
import '../../visitors/providers/visitors_provider.dart';
import '../models/guard_shift_model.dart';
import '../providers/guard_shift_provider.dart';
import 'today_shift_card.dart';
import '../../../core/navigation/app_destinations.dart';
import '../../shell/main_shell.dart';
import '../../support/screens/emergency_contacts_sheet.dart';
import 'guard_scan_screen.dart';

class GuardHomeScreen extends ConsumerStatefulWidget {
  const GuardHomeScreen({super.key});
  @override
  ConsumerState<GuardHomeScreen> createState() => _GuardHomeScreenState();
}

class _GuardHomeScreenState extends ConsumerState<GuardHomeScreen> with AutoRefreshMixin {
  @override
  int get refreshIntervalSeconds => 20;

  Future<void> _reload() async {
    await Future.wait([
      ref.read(visitorsProvider.notifier).loadAll(),
      ref.read(guardShiftProvider.notifier).load(),
    ]);
  }

  @override
  void onAutoRefresh() => _reload();

  @override
  void initState() {
    super.initState();
    Future.microtask(_reload);
    startAutoRefresh();
  }

  @override
  Widget build(BuildContext context) {
    final user  = ref.watch(currentUserProvider);
    final stats = ref.watch(visitorsProvider).stats;
    final overview = ref.watch(guardShiftProvider.select((s) => s.overview));

    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        onRefresh: _reload,
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
                  onPressed: _reload,
                ),
              ],
            ),

            // Welcome strip
            SliverToBoxAdapter(child: _welcomeStrip(user, overview)),

            // Today's shift: gate, shift, timing and status from the roster
            const SliverToBoxAdapter(child: TodayShiftCard()),

            // Stats grid
            SliverToBoxAdapter(child: _statsGrid(stats)),

            // Scan QR — the primary action ("scan, verify, move on"), not
            // buried among equally-sized quick actions.
            SliverToBoxAdapter(child: _scanButton(context)),

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

  Widget _welcomeStrip(user, GuardShiftOverview? overview) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
    child: GradientCard(
      child: Row(children: [
        const Icon(Icons.shield_rounded, color: Colors.white, size: 36),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Welcome, ${user?.name ?? 'Guard'}!', style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(_greeting(), style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 12)),
        ])),
        if (overview != null)
          Container(
            key: const Key('duty-badge'),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
            child: Text(overview.dutyLabel!, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
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

  Widget _scanButton(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
    child: SizedBox(
      height: 64,
      child: ElevatedButton.icon(
        onPressed: () async {
          await Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const GuardScanScreen()),
          );
          // A pass/guest scanned in or out changes the counters and the
          // "Currently Inside" list the moment the guard returns here.
          ref.read(visitorsProvider.notifier).loadAll();
        },
        icon: const Icon(Icons.qr_code_scanner_rounded, size: 28),
        label: const Text('SCAN QR', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          elevation: 3,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        ),
      ),
    ),
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
        const Expanded(child: SizedBox()),
        const SizedBox(width: 10),
        const Expanded(child: SizedBox()),
      ]),
    ]),
  );

  Widget _infoModules(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Modules', style: AppTextStyles.h3),
      const SizedBox(height: 12),
      // Services and Notices are bottom-bar tabs for a guard, so they are not repeated here (AppNav); Events shows
      // only when the society has switched the module on.
      if (AppNav.shortcuts(ref.watch(currentUserProvider)).contains(Dest.events)) ...[
        _ModuleRow(
          key: const Key('guard-module-events'),
          icon: Icons.event_rounded,
          label: 'Events',
          subtitle: 'Upcoming society events',
          color: AppColors.warning,
          onTap: () => mainShellKey.currentState?.openEvents(),
        ),
        const SizedBox(height: 10),
      ],
      _ModuleRow(
        icon: Icons.info_outline_rounded,
        label: 'Society Info',
        subtitle: 'Society details, committee and gate rules',
        color: AppColors.info,
        onTap: () => Navigator.of(context).pushNamed('/society-documents'),
      ),
      const SizedBox(height: 10),
      _ModuleRow(
        icon: Icons.phone_rounded,
        label: 'Emergency Contacts',
        subtitle: 'Quick dial for emergencies',
        color: AppColors.error,
        onTap: () => showEmergencyContacts(context),
      ),
    ]),
  );


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
        decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
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
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.2)),
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
  const _ModuleRow({super.key, required this.icon, required this.label, required this.subtitle, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) => AppCard(
    padding: const EdgeInsets.all(14),
    onTap: onTap,
    child: Row(children: [
      Container(width: 42, height: 42, decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: color, size: 20)),
      const SizedBox(width: 14),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: AppTextStyles.label),
        const SizedBox(height: 2),
        Text(subtitle, style: AppTextStyles.caption),
      ])),
      const Icon(Icons.arrow_forward_ios_rounded, size: 13, color: AppColors.textHint),
    ]),
  );
}
