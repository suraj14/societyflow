import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/navigation/app_destinations.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/error_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../access_passes/screens/access_passes_screen.dart';
import '../../auth/providers/auth_provider.dart';
import '../../home/providers/home_provider.dart';
import '../../shell/main_shell.dart';
import '../providers/admin_provider.dart';
import 'admin_requests_screen.dart';

/// The society office on a phone: what needs a decision first, the society at a glance, the handful of
/// operations used every day, and what is new. Everything comes from `/admin/dashboard` (and the notice feed);
/// a failed call shows an error with Retry - it never shows zeros that look like real figures.
class AdminHomeScreen extends ConsumerStatefulWidget {
  const AdminHomeScreen({super.key});

  @override
  ConsumerState<AdminHomeScreen> createState() => _AdminHomeScreenState();
}

class _AdminHomeScreenState extends ConsumerState<AdminHomeScreen> with AutoRefreshMixin {
  Future<void> _reload() async {
    await Future.wait([
      ref.read(adminDashboardProvider.notifier).load(),
      ref.read(homeProvider.notifier).loadDashboard(),
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

  void _go(Dest d) {
    if (d == Dest.accessPasses) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => const AccessPassesScreen()));
    } else {
      mainShellKey.currentState?.openDest(d);
    }
  }

  void _openAttention(AdminAttention a) {
    switch (a.key) {
      case 'bookings':
        Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminRequestsScreen(initialTab: 0)));
      case 'service_requests':
        Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminRequestsScreen(initialTab: 1)));
      case 'complaints':
        _go(Dest.tickets);
      case 'visitors':
        _go(Dest.visitors);
      default:
        break; // e.g. overdue bills: there is no mobile bills list for the office, so the row is information only
    }
  }

  bool _opensSomething(String key) => const {'bookings', 'service_requests', 'complaints', 'visitors'}.contains(key);

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(currentUserProvider);
    final s = ref.watch(adminDashboardProvider);
    final notices = ref.watch(homeProvider.select((h) => h.notices));
    final shortcuts = AppNav.shortcuts(user, promoted: {if (notices.isNotEmpty) Dest.notices});

    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        onRefresh: _reload,
        color: AppColors.primary,
        child: CustomScrollView(slivers: [
          SliverAppBar(
            floating: true,
            backgroundColor: AppColors.surface,
            elevation: 0,
            title: Row(children: [
              Container(width: 36, height: 36, decoration: BoxDecoration(gradient: AppColors.primaryGradient, borderRadius: BorderRadius.circular(10)), child: const Icon(Icons.apartment_rounded, color: Colors.white, size: 18)),
              const SizedBox(width: 10),
              const Text('SocietyFlow', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textPrimary, letterSpacing: -0.3)),
            ]),
            actions: [
              IconButton(tooltip: 'Profile', icon: const Icon(Icons.account_circle_outlined, color: AppColors.textPrimary), onPressed: () => _go(Dest.profile)),
            ],
          ),
          if (s.isLoading && !s.loaded)
            const SliverToBoxAdapter(child: DashboardSkeleton())
          else if (s.error != null && !s.loaded)
            SliverFillRemaining(hasScrollBody: false, child: ErrorView(message: s.error!, onRetry: _reload))
          else ...[
            SliverToBoxAdapter(child: _greeting(user?.name, s.societyName ?? user?.societyName)),
            if (s.error != null) SliverToBoxAdapter(child: _staleBanner(s.error!)),
            SliverToBoxAdapter(child: _attention(s)),
            SliverToBoxAdapter(child: _overview(s)),
            if (shortcuts.isNotEmpty) SliverToBoxAdapter(child: _quickActions(shortcuts)),
            if (notices.isNotEmpty) SliverToBoxAdapter(child: _latestNotices(notices)),
            if (s.guardsOnDuty.isNotEmpty) SliverToBoxAdapter(child: _guards(s)),
            const SliverToBoxAdapter(child: SizedBox(height: 100)),
          ],
        ]),
      ),
    );
  }

  Widget _greeting(String? name, String? society) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
        child: GradientCard(
          child: Row(children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Society office', style: TextStyle(fontSize: 12, color: Colors.white.withValues(alpha: 0.8))),
                const SizedBox(height: 4),
                Text(name ?? 'Admin', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Colors.white), maxLines: 1, overflow: TextOverflow.ellipsis),
                if (society != null && society.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Row(children: [
                    const Icon(Icons.apartment_outlined, color: Colors.white, size: 13),
                    const SizedBox(width: 4),
                    Flexible(child: Text(society, style: const TextStyle(color: Colors.white, fontSize: 12), overflow: TextOverflow.ellipsis)),
                  ]),
                ],
              ]),
            ),
          ]),
        ),
      );

  Widget _staleBanner(String message) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: AppColors.warning.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(AppRadius.md)),
          child: Row(children: [
            const Icon(Icons.warning_amber_rounded, size: 18, color: AppColors.warning),
            const SizedBox(width: 8),
            Expanded(child: Text('$message Showing the last data we received.', style: AppTextStyles.caption)),
            TextButton(onPressed: _reload, child: const Text('Retry')),
          ]),
        ),
      );

  Widget _section(String title, Widget child) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: AppTextStyles.h3), const SizedBox(height: 12), child]),
      );

  Widget _attention(AdminDashboardState s) {
    if (s.attention.isEmpty) {
      return _section(
        'Needs attention',
        const AppCard(
          padding: EdgeInsets.all(14),
          child: Row(children: [
            Icon(Icons.check_circle_rounded, color: AppColors.secondary),
            SizedBox(width: 10),
            Expanded(child: Text('All clear - nothing is waiting for you.', style: AppTextStyles.body2)),
          ]),
        ),
      );
    }
    return _section(
      'Needs attention',
      Column(children: [
        for (final a in s.attention)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: AppCard(
              onTap: _opensSomething(a.key) ? () => _openAttention(a) : null,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              child: Row(children: [
                Container(
                  constraints: const BoxConstraints(minWidth: 34),
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(color: AppColors.warning.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(10)),
                  child: Text('${a.count}', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.warning)),
                ),
                const SizedBox(width: 12),
                Expanded(child: Text(a.label, style: AppTextStyles.label)),
                if (_opensSomething(a.key)) const Icon(Icons.chevron_right_rounded, color: AppColors.textHint),
              ]),
            ),
          ),
      ]),
    );
  }

  String _n(num? v) => v == null ? '-' : v.toStringAsFixed(v % 1 == 0 ? 0 : 2).replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => ',');
  String _money(num? v) => v == null ? '-' : '₹${_n(v)}';

  Widget _overview(AdminDashboardState s) {
    Widget tile(String title, String value, IconData icon, Color color, {VoidCallback? onTap}) => Expanded(
          child: AppCard(
            onTap: onTap,
            padding: const EdgeInsets.all(12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(icon, size: 18, color: color),
              const SizedBox(height: 8),
              FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textPrimary))),
              const SizedBox(height: 2),
              Text(title, style: AppTextStyles.caption, maxLines: 2, overflow: TextOverflow.ellipsis),
            ]),
          ),
        );

    return _section(
      'Quick overview',
      Column(children: [
        Row(children: [
          tile('Properties', _n(s.kpi('units')), Icons.home_work_rounded, AppColors.info),
          const SizedBox(width: 10),
          tile('Occupied', _n(s.kpi('occupied_units')), Icons.meeting_room_rounded, AppColors.secondary),
          const SizedBox(width: 10),
          tile('Vacant', _n(s.kpi('vacant_units')), Icons.door_front_door_outlined, AppColors.textSecondary),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          tile('Residents', _n(s.kpi('residents')), Icons.groups_rounded, AppColors.primary),
          const SizedBox(width: 10),
          tile('Visitors today', _n(s.kpi('visitors_today')), Icons.people_alt_rounded, AppColors.info, onTap: () => _go(Dest.visitors)),
          const SizedBox(width: 10),
          tile('Open tickets', _n(s.kpi('open_tickets')), Icons.support_agent_rounded, AppColors.error, onTap: () => _go(Dest.tickets)),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          tile('Pending dues', _money(s.kpi('pending_dues')), Icons.account_balance_wallet_outlined, AppColors.warning),
          const SizedBox(width: 10),
          tile('Collected this month', _money(s.kpi('monthly_revenue')), Icons.payments_outlined, AppColors.secondary),
        ]),
      ]),
    );
  }

  Widget _quickActions(List<Dest> shortcuts) => _section(
        'Quick actions',
        GridView.count(
          crossAxisCount: 3,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.15,
          children: [
            for (final d in shortcuts)
              AppCard(
                key: Key('quick-${d.name}'),
                onTap: () => _go(d),
                padding: const EdgeInsets.all(10),
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(AppNav.all[d]!.icon, color: AppNav.all[d]!.color, size: 24),
                  const SizedBox(height: 6),
                  Text(AppNav.all[d]!.label, style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.w600), textAlign: TextAlign.center, maxLines: 2),
                ]),
              ),
          ],
        ),
      );

  Widget _latestNotices(List<Map<String, dynamic>> notices) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            const Expanded(child: Text('Latest notices', style: AppTextStyles.h3, maxLines: 1, overflow: TextOverflow.ellipsis)),
            TextButton(onPressed: () => _go(Dest.notices), child: const Text('View all', style: TextStyle(color: AppColors.primary, fontSize: 12))),
          ]),
          for (final n in notices.take(3))
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: AppCard(
                onTap: () => _go(Dest.notices),
                padding: const EdgeInsets.all(12),
                child: Row(children: [
                  const Icon(Icons.campaign_rounded, color: AppColors.primary, size: 18),
                  const SizedBox(width: 10),
                  Expanded(child: Text((n['title'] ?? '').toString(), style: AppTextStyles.label, maxLines: 2, overflow: TextOverflow.ellipsis)),
                ]),
              ),
            ),
        ]),
      );

  Widget _guards(AdminDashboardState s) => _section(
        'Guards on duty',
        Column(children: [
          for (final g in s.guardsOnDuty)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: AppCard(
                padding: const EdgeInsets.all(12),
                child: Row(children: [
                  const Icon(Icons.shield_rounded, color: AppColors.secondary, size: 18),
                  const SizedBox(width: 10),
                  Expanded(child: Text((g['guard'] ?? 'Guard').toString(), style: AppTextStyles.label, overflow: TextOverflow.ellipsis)),
                  Flexible(child: Text([if (g['gate'] != null) g['gate'], if (g['since'] != null) 'since ${g['since']}'].join('  '), style: AppTextStyles.caption, overflow: TextOverflow.ellipsis, textAlign: TextAlign.end)),
                ]),
              ),
            ),
        ]),
      );
}
