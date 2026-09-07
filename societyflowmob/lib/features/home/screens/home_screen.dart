import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../auth/providers/auth_provider.dart';
import '../../notices/screens/notices_screen.dart';
import '../../facilities/screens/facilities_screen.dart';
import '../../services/screens/services_screen.dart';
import '../../events/screens/events_screen.dart';
import '../../shell/main_shell.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../providers/home_provider.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});
  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> with AutoRefreshMixin {
  @override
  void onAutoRefresh() => ref.read(homeProvider.notifier).loadDashboard();

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(homeProvider.notifier).loadDashboard());
    startAutoRefresh();
  }

  @override
  Widget build(BuildContext context) {
    final user  = ref.watch(currentUserProvider);
    final state = ref.watch(homeProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        onRefresh: () => ref.read(homeProvider.notifier).loadDashboard(),
        color: AppColors.primary,
        child: CustomScrollView(
          slivers: [
            SliverAppBar(
              floating: true,
              backgroundColor: AppColors.surface,
              elevation: 0,
              title: Row(children: [
                Container(width: 36, height: 36, decoration: BoxDecoration(gradient: AppColors.primaryGradient, borderRadius: BorderRadius.circular(10)), child: const Icon(Icons.apartment_rounded, color: Colors.white, size: 18)),
                const SizedBox(width: 10),
                const Text('SocietyFlow', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textPrimary, letterSpacing: -0.3)),
              ]),
            ),
            if (state.isLoading)
              const SliverToBoxAdapter(child: DashboardSkeleton())
            else if (state.error != null && state.pendingBills == 0)
              SliverFillRemaining(
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(32),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.wifi_off_rounded, size: 56, color: AppColors.textHint),
                        const SizedBox(height: 16),
                        Text(state.error!, style: AppTextStyles.body2, textAlign: TextAlign.center),
                        const SizedBox(height: 24),
                        ElevatedButton.icon(onPressed: () => ref.read(homeProvider.notifier).loadDashboard(), icon: const Icon(Icons.refresh_rounded), label: const Text('Retry')),
                      ],
                    ),
                  ),
                ),
              )
            else ...[
              SliverToBoxAdapter(child: _welcomeCard(user)),
              SliverToBoxAdapter(child: _quickStats(state)),
              SliverToBoxAdapter(child: _noticeBoard(state)),
              SliverToBoxAdapter(child: _featureCards(context)),
              SliverToBoxAdapter(child: _recentActivity(state)),
              const SliverToBoxAdapter(child: SizedBox(height: 100)),
            ],
          ],
        ),
      ),
    );
  }

  Widget _welcomeCard(user) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
    child: GradientCard(
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
          Text('Good ${_greeting()}! 👋', style: TextStyle(fontSize: 12, color: Colors.white.withOpacity(0.8))),
          const SizedBox(height: 4),
          Text(
            user?.name ?? 'Resident',
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: -0.3),
            maxLines: 1, overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), borderRadius: BorderRadius.circular(20)),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.home_outlined, color: Colors.white, size: 12),
              const SizedBox(width: 4),
              Flexible(child: Text(user?.displayUnit ?? 'Flat A-101', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w500), overflow: TextOverflow.ellipsis)),
            ]),
          ),
        ])),
        const SizedBox(width: 12),
        Container(
          width: 60, height: 60,
          decoration: BoxDecoration(color: Colors.white.withOpacity(0.15), shape: BoxShape.circle),
          child: const Icon(Icons.person_rounded, color: Colors.white, size: 30),
        ),
      ]),
    ),
  );

  Widget _quickStats(HomeState s) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Quick Overview', style: AppTextStyles.h3),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: StatCard(title: 'Pending Bills',   value: '${s.pendingBills}',   icon: Icons.receipt_long_rounded,  color: AppColors.warning)),
        const SizedBox(width: 12),
        Expanded(child: StatCard(title: 'Visitors Today',  value: '${s.visitorsToday}',  icon: Icons.people_alt_rounded,    color: AppColors.info)),
      ]),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: StatCard(title: 'Open Complaints', value: '${s.openComplaints}', icon: Icons.support_agent_rounded, color: AppColors.error)),
        const SizedBox(width: 12),
        Expanded(child: StatCard(title: 'Notices',         value: '${s.activeNotices}',  icon: Icons.campaign_rounded,      color: AppColors.secondary)),
      ]),
    ]),
  );

  Widget _noticeBoard(HomeState s) {
    if (s.notices.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          const Text('Notice Board', style: AppTextStyles.h3),
          TextButton(onPressed: () => mainShellKey.currentState?.openNotices(), child: const Text('View All', style: TextStyle(color: AppColors.primary, fontSize: 12))),
        ]),
        const SizedBox(height: 8),
        SizedBox(
          height: 88,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: s.notices.length,
            separatorBuilder: (_, __) => const SizedBox(width: 10),
            itemBuilder: (_, i) => Container(
              width: 220,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.06),
                borderRadius: BorderRadius.circular(AppRadius.lg),
                border: Border.all(color: AppColors.primary.withOpacity(0.15)),
              ),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                Row(children: [
                  const Icon(Icons.campaign_rounded, color: AppColors.primary, size: 14),
                  const SizedBox(width: 5),
                  const Text('Notice', style: TextStyle(color: AppColors.primary, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.3)),
                ]),
                const SizedBox(height: 5),
                Text(s.notices[i]['title'] ?? '', style: AppTextStyles.label.copyWith(fontSize: 12), maxLines: 2, overflow: TextOverflow.ellipsis),
              ]),
            ),
          ),
        ),
      ]),
    );
  }

  Widget _recentActivity(HomeState s) {
    if (s.recentActivities.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Recent Activity', style: AppTextStyles.h3),
        const SizedBox(height: 12),
        ...s.recentActivities.map((a) => Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: AppCard(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              Container(width: 40, height: 40, decoration: BoxDecoration(color: _actColor(a['type']).withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(_actIcon(a['type']), color: _actColor(a['type']), size: 18)),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(a['title'] ?? '', style: AppTextStyles.label),
                const SizedBox(height: 2),
                Text(a['time'] ?? '', style: AppTextStyles.caption),
              ])),
              if (a['status'] != null) StatusBadge(status: a['status']),
            ]),
          ),
        )),
      ]),
    );
  }

  // ── Feature Cards (Notices, Facilities, Services, Events) ────────────────
  // These screens are accessible from here since they're removed from bottom nav

  Widget _featureCards(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('More Features', style: AppTextStyles.h3),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: _FeatureCard(
          icon: Icons.campaign_rounded,
          label: 'Notices',
          color: AppColors.primary,
          onTap: () => mainShellKey.currentState?.openNotices(),
        )),
        const SizedBox(width: 12),
        Expanded(child: _FeatureCard(
          icon: Icons.meeting_room_rounded,
          label: 'Facilities',
          color: AppColors.info,
          onTap: () => mainShellKey.currentState?.openFacilities(),
        )),
      ]),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: _FeatureCard(
          icon: Icons.build_rounded,
          label: 'Services',
          color: AppColors.secondary,
          onTap: () => mainShellKey.currentState?.openServices(),
        )),
        const SizedBox(width: 12),
        Expanded(child: _FeatureCard(
          icon: Icons.event_rounded,
          label: 'Events',
          color: AppColors.warning,
          onTap: () => mainShellKey.currentState?.openEvents(),
        )),
      ]),
    ]),
  );

  String _greeting() { final h = DateTime.now().hour; if (h < 12) return 'Morning'; if (h < 17) return 'Afternoon'; return 'Evening'; }
  IconData _actIcon(String? t) { switch (t) { case 'payment': return Icons.receipt_long_rounded; case 'visitor': return Icons.people_alt_rounded; case 'complaint': return Icons.support_agent_rounded; default: return Icons.notifications_rounded; } }
  Color _actColor(String? t) { switch (t) { case 'payment': return AppColors.secondary; case 'visitor': return AppColors.info; case 'complaint': return AppColors.error; default: return AppColors.primary; } }
}

// ── Feature Card Widget ───────────────────────────────────────────────────────

class _FeatureCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _FeatureCard({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 14),
        decoration: BoxDecoration(
          color: color.withOpacity(0.07),
          borderRadius: BorderRadius.circular(AppRadius.lg),
          border: Border.all(color: color.withOpacity(0.18)),
        ),
        child: Row(children: [
          Container(
            width: 38, height: 38,
            decoration: BoxDecoration(
              color: color.withOpacity(0.15),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 19),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              label,
              style: AppTextStyles.label.copyWith(fontSize: 13),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          Icon(Icons.arrow_forward_ios_rounded, size: 12, color: color.withOpacity(0.5)),
        ]),
      ),
    );
  }
}
