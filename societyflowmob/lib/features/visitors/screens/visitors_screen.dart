import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../auth/providers/auth_provider.dart';
import '../providers/visitors_provider.dart';
import '../models/visitor_model.dart';

class VisitorsScreen extends ConsumerStatefulWidget {
  const VisitorsScreen({super.key});
  @override
  ConsumerState<VisitorsScreen> createState() => _VisitorsScreenState();
}

class _VisitorsScreenState extends ConsumerState<VisitorsScreen>
    with SingleTickerProviderStateMixin, AutoRefreshMixin {
  late TabController _tabController;
  final _searchCtrl = TextEditingController();

  bool get _isStaff {
    final role = ref.read(currentUserProvider)?.role ?? '';
    return ['Staff', 'Guard', 'Admin', 'Super Admin'].any((r) => role.contains(r));
  }

  @override
  int get refreshIntervalSeconds => 15;

  @override
  void onAutoRefresh() => ref.read(visitorsProvider.notifier).loadVisitors();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _isStaff ? 4 : 3, vsync: this);
    Future.microtask(() => ref.read(visitorsProvider.notifier).loadAll());
    startAutoRefresh();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(visitorsProvider);
    final isStaff = _isStaff;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(isStaff ? '🛡 Guard Visitor Log' : 'My Visitors'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.read(visitorsProvider.notifier).loadAll(),
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textHint,
          indicatorColor: AppColors.primary,
          isScrollable: true,
          tabs: isStaff
              ? const [Tab(text: 'All'), Tab(text: 'Pending'), Tab(text: 'Inside'), Tab(text: 'History')]
              : const [Tab(text: 'All'), Tab(text: 'Pending'), Tab(text: 'History')],
        ),
      ),
      body: Column(children: [
        // Stats banner for guard, pending alert for resident
        if (isStaff)
          _GuardStatsBanner(stats: state.stats)
        else
          _ResidentPendingBanner(pendingCount: state.visitors.where((v) => v.isPending).length),

        // Search
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
          child: TextField(
            controller: _searchCtrl,
            onChanged: (v) => ref.read(visitorsProvider.notifier).search(v),
            decoration: InputDecoration(
              hintText: 'Search by name, phone, flat...',
              prefixIcon: const Icon(Icons.search_rounded, color: AppColors.textHint),
              suffixIcon: _searchCtrl.text.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.clear_rounded, color: AppColors.textHint),
                      onPressed: () {
                        _searchCtrl.clear();
                        ref.read(visitorsProvider.notifier).search('');
                      },
                    )
                  : null,
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            ),
          ),
        ),

        // Error banner
        if (state.error != null)
          Container(
            margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: AppColors.error.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
            child: Row(children: [
              const Icon(Icons.error_outline, color: AppColors.error, size: 16),
              const SizedBox(width: 8),
              Expanded(child: Text(state.error!, style: const TextStyle(color: AppColors.error, fontSize: 12))),
              TextButton(onPressed: () => ref.read(visitorsProvider.notifier).loadAll(), child: const Text('Retry', style: TextStyle(fontSize: 12))),
            ]),
          ),

        // Tabs
        Expanded(
          child: state.isLoading
              ? const ListSkeleton()
              : TabBarView(
                  controller: _tabController,
                  children: isStaff
                      ? [
                          _buildList(state.visitors, isStaff),
                          _buildList(state.visitors.where((v) => v.isPending).toList(), isStaff),
                          _buildList(state.visitors.where((v) => v.hasEntered && !v.hasExited).toList(), isStaff),
                          _buildList(state.visitors.where((v) => v.hasExited || v.isDenied).toList(), isStaff),
                        ]
                      : [
                          _buildList(state.visitors, isStaff),
                          _buildList(state.visitors.where((v) => v.isPending).toList(), isStaff),
                          _buildList(state.visitors.where((v) => !v.isPending).toList(), isStaff),
                        ],
                ),
        ),
      ]),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showAddVisitorSheet(context),
        backgroundColor: AppColors.primary,
        icon: const Icon(Icons.person_add_rounded, color: Colors.white),
        label: Text(
          isStaff ? '+ New Entry' : '+ Invite Visitor',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }

  Widget _buildList(List<VisitorModel> visitors, bool isStaff) {
    return _VisitorList(
      visitors: visitors,
      isStaff: isStaff,
      onRefresh: () => ref.read(visitorsProvider.notifier).loadAll(),
      onTap: (v) => _showVisitorDetail(v),
    );
  }

  void _showAddVisitorSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddVisitorSheet(isStaff: _isStaff),
    );
  }

  void _showVisitorDetail(VisitorModel visitor) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _VisitorDetailSheet(visitor: visitor, isStaff: _isStaff),
    );
  }
}

// ── Resident pending banner ──────────────────────────────────────────────────

class _ResidentPendingBanner extends StatelessWidget {
  final int pendingCount;
  const _ResidentPendingBanner({required this.pendingCount});

  @override
  Widget build(BuildContext context) {
    if (pendingCount == 0) return const SizedBox.shrink();
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.warning.withOpacity(0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.warning.withOpacity(0.4)),
      ),
      child: Row(children: [
        const Icon(Icons.notifications_active_rounded, color: AppColors.warning, size: 18),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            '$pendingCount visitor${pendingCount > 1 ? 's' : ''} waiting for your approval',
            style: const TextStyle(color: AppColors.warning, fontSize: 13, fontWeight: FontWeight.w600),
          ),
        ),
      ]),
    );
  }
}

// ── Guard Stats Banner ───────────────────────────────────────────────────────

class _GuardStatsBanner extends StatelessWidget {
  final VisitorStats stats;
  const _GuardStatsBanner({required this.stats});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.surface,
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
      child: Row(children: [
        _Stat(label: 'Today', value: '${stats.todayTotal}', color: AppColors.primary),
        _Stat(label: 'Pending', value: '${stats.pending}', color: AppColors.warning),
        _Stat(label: 'Inside', value: '${stats.checkedIn}', color: AppColors.secondary),
        _Stat(label: 'Exited', value: '${stats.checkedOut}', color: AppColors.textHint),
      ]),
    );
  }
}

class _Stat extends StatelessWidget {
  final String label, value;
  final Color color;
  const _Stat({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(children: [
      Text(value, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: color)),
      Text(label, style: AppTextStyles.caption),
    ]),
  );
}

// ── Visitor List ─────────────────────────────────────────────────────────────

class _VisitorList extends ConsumerWidget {
  final List<VisitorModel> visitors;
  final bool isStaff;
  final Future<void> Function() onRefresh;
  final void Function(VisitorModel) onTap;

  const _VisitorList({
    required this.visitors,
    required this.isStaff,
    required this.onRefresh,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (visitors.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        color: AppColors.primary,
        child: const SingleChildScrollView(
          physics: AlwaysScrollableScrollPhysics(),
          child: SizedBox(
            height: 350,
            child: EmptyState(
              icon: Icons.people_alt_outlined,
              title: 'No visitors found',
              subtitle: 'Visitor entries will appear here',
            ),
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      color: AppColors.primary,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
        itemCount: visitors.length,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (_, i) => _VisitorCard(
          visitor: visitors[i],
          isStaff: isStaff,
          onTap: () => onTap(visitors[i]),
        ),
      ),
    );
  }
}

// ── Visitor Card ─────────────────────────────────────────────────────────────

class _VisitorCard extends ConsumerWidget {
  final VisitorModel visitor;
  final bool isStaff;
  final VoidCallback onTap;

  const _VisitorCard({required this.visitor, required this.isStaff, required this.onTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return AppCard(
      padding: const EdgeInsets.all(14),
      onTap: onTap,
      child: Column(children: [
        Row(children: [
          // Type icon
          Container(
            width: 46, height: 46,
            decoration: BoxDecoration(
              color: _typeColor(visitor.visitorType).withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(_typeIcon(visitor.visitorType), color: _typeColor(visitor.visitorType), size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(visitor.visitorName, style: AppTextStyles.label),
              const SizedBox(height: 2),
              Row(children: [
                const Icon(Icons.phone_outlined, size: 12, color: AppColors.textHint),
                const SizedBox(width: 3),
                Text(visitor.visitorPhone, style: AppTextStyles.caption),
                if (visitor.vehicleNumber != null && visitor.vehicleNumber!.isNotEmpty) ...[
                  const SizedBox(width: 8),
                  const Icon(Icons.directions_car_outlined, size: 12, color: AppColors.textHint),
                  const SizedBox(width: 3),
                  Text(visitor.vehicleNumber!, style: AppTextStyles.caption),
                ],
              ]),
            ]),
          ),
          // Status badges
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            StatusBadge(status: visitor.approvalStatus),
            if (visitor.hasEntered) ...[
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.secondary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  visitor.hasExited ? 'Exited' : 'Inside',
                  style: const TextStyle(color: AppColors.secondary, fontSize: 10, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ]),
        ]),
        const SizedBox(height: 8),
        Row(children: [
          const Icon(Icons.home_outlined, size: 13, color: AppColors.textHint),
          const SizedBox(width: 4),
          Expanded(child: Text(visitor.unitDisplay.isNotEmpty ? visitor.unitDisplay : 'N/A', style: AppTextStyles.caption, overflow: TextOverflow.ellipsis)),
          const Icon(Icons.access_time_rounded, size: 13, color: AppColors.textHint),
          const SizedBox(width: 4),
          Text(_fmtTime(visitor.expectedEntryTime), style: AppTextStyles.caption),
        ]),

        // Quick action buttons on card for pending visitors
        if (visitor.isPending || visitor.canCheckIn || visitor.canCheckOut) ...[
          const SizedBox(height: 10),
          const Divider(height: 1),
          const SizedBox(height: 8),
          _QuickActions(visitor: visitor, isStaff: isStaff),
        ],

        // Actual times
        if (visitor.actualEntryTime != null || visitor.actualExitTime != null) ...[
          const SizedBox(height: 6),
          Row(children: [
            if (visitor.actualEntryTime != null) ...[
              const Icon(Icons.login_rounded, size: 12, color: AppColors.secondary),
              const SizedBox(width: 3),
              Text('In: ${_fmtTime(visitor.actualEntryTime!)}', style: const TextStyle(fontSize: 11, color: AppColors.secondary)),
              const SizedBox(width: 10),
            ],
            if (visitor.actualExitTime != null) ...[
              const Icon(Icons.logout_rounded, size: 12, color: AppColors.textHint),
              const SizedBox(width: 3),
              Text('Out: ${_fmtTime(visitor.actualExitTime!)}', style: const TextStyle(fontSize: 11, color: AppColors.textHint)),
            ],
          ]),
        ],
      ]),
    );
  }

  String _fmtTime(DateTime t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  IconData _typeIcon(String t) {
    switch (t) {
      case 'delivery': return Icons.local_shipping_rounded;
      case 'cab': return Icons.local_taxi_rounded;
      case 'service': case 'maid': case 'cook': case 'driver':
      case 'electrician': case 'plumber': return Icons.build_rounded;
      case 'emergency': return Icons.emergency_rounded;
      default: return Icons.person_rounded;
    }
  }

  Color _typeColor(String t) {
    switch (t) {
      case 'delivery': return AppColors.warning;
      case 'cab': return AppColors.info;
      case 'emergency': return AppColors.error;
      case 'service': case 'maid': case 'cook': return AppColors.secondary;
      default: return AppColors.primary;
    }
  }
}

// ── Quick action buttons on card ─────────────────────────────────────────────

class _QuickActions extends ConsumerWidget {
  final VisitorModel visitor;
  final bool isStaff;
  const _QuickActions({required this.visitor, required this.isStaff});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final buttons = <Widget>[];

    if (visitor.isPending) {
      buttons.add(Expanded(
        child: OutlinedButton.icon(
          onPressed: () => _act(context, ref, () => ref.read(visitorsProvider.notifier).denyVisitor(visitor.id), '❌ Visitor denied'),
          icon: const Icon(Icons.close_rounded, size: 15, color: AppColors.error),
          label: const Text('Deny', style: TextStyle(color: AppColors.error, fontSize: 12)),
          style: OutlinedButton.styleFrom(
            side: const BorderSide(color: AppColors.error),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            padding: const EdgeInsets.symmetric(vertical: 6),
          ),
        ),
      ));
      buttons.add(const SizedBox(width: 8));
      buttons.add(Expanded(
        child: ElevatedButton.icon(
          onPressed: () => _act(context, ref, () => ref.read(visitorsProvider.notifier).allowVisitor(visitor.id), '✅ Visitor allowed'),
          icon: const Icon(Icons.check_rounded, size: 15, color: Colors.white),
          label: const Text('Allow', style: TextStyle(color: Colors.white, fontSize: 12)),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.secondary,
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            padding: const EdgeInsets.symmetric(vertical: 6),
          ),
        ),
      ));
    }

    if (isStaff && visitor.canCheckIn) {
      if (buttons.isNotEmpty) buttons.add(const SizedBox(width: 8));
      buttons.add(Expanded(
        child: ElevatedButton.icon(
          onPressed: () => _act(context, ref, () => ref.read(visitorsProvider.notifier).checkIn(visitor.id), '✅ Checked In'),
          icon: const Icon(Icons.login_rounded, size: 15, color: Colors.white),
          label: const Text('Check-In', style: TextStyle(color: Colors.white, fontSize: 12)),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            padding: const EdgeInsets.symmetric(vertical: 6),
          ),
        ),
      ));
    }

    if (isStaff && visitor.canCheckOut) {
      if (buttons.isNotEmpty) buttons.add(const SizedBox(width: 8));
      buttons.add(Expanded(
        child: ElevatedButton.icon(
          onPressed: () => _act(context, ref, () => ref.read(visitorsProvider.notifier).checkOut(visitor.id), '⬅ Checked Out'),
          icon: const Icon(Icons.logout_rounded, size: 15, color: Colors.white),
          label: const Text('Check-Out', style: TextStyle(color: Colors.white, fontSize: 12)),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.warning,
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            padding: const EdgeInsets.symmetric(vertical: 6),
          ),
        ),
      ));
    }

    if (buttons.isEmpty) return const SizedBox.shrink();
    return Row(children: buttons);
  }

  Future<void> _act(BuildContext ctx, WidgetRef ref, Future<void> Function() fn, String msg) async {
    try {
      await fn();
      if (ctx.mounted) {
        ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(
          content: Text(msg),
          backgroundColor: AppColors.secondary,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ));
      }
    } catch (e) {
      if (ctx.mounted) {
        ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
      }
    }
  }
}

// ── Visitor Detail Sheet ─────────────────────────────────────────────────────

class _VisitorDetailSheet extends ConsumerStatefulWidget {
  final VisitorModel visitor;
  final bool isStaff;
  const _VisitorDetailSheet({required this.visitor, required this.isStaff});

  @override
  ConsumerState<_VisitorDetailSheet> createState() => _VisitorDetailSheetState();
}

class _VisitorDetailSheetState extends ConsumerState<_VisitorDetailSheet> {
  late VisitorModel _visitor;
  bool _isActing = false;

  @override
  void initState() {
    super.initState();
    _visitor = widget.visitor;
  }

  Future<void> _act(Future<void> Function() fn, String msg) async {
    setState(() => _isActing = true);
    try {
      await fn();
      // Refresh the visitor from state
      final updated = ref.read(visitorsProvider).allVisitors.where((v) => v.id == _visitor.id).firstOrNull;
      if (updated != null && mounted) setState(() => _visitor = updated);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(msg),
        backgroundColor: AppColors.secondary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
      ));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final v = _visitor;
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.fromLTRB(20, 16, 20, MediaQuery.of(context).viewInsets.bottom + 28),
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Handle
          Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
          const SizedBox(height: 16),

          // Header
          Row(children: [
            Container(
              width: 52, height: 52,
              decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(14)),
              child: const Icon(Icons.person_rounded, color: AppColors.primary, size: 26),
            ),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(v.visitorName, style: AppTextStyles.h3),
              Text(ucFirst(v.visitorType), style: AppTextStyles.caption),
            ])),
            StatusBadge(status: v.approvalStatus),
          ]),
          const SizedBox(height: 20),

          // Details grid
          _DetailRow(Icons.phone_rounded, 'Phone', v.visitorPhone),
          if (v.unitDisplay.isNotEmpty) _DetailRow(Icons.home_rounded, 'Unit', v.unitDisplay),
          if (v.vehicleNumber != null && v.vehicleNumber!.isNotEmpty)
            _DetailRow(Icons.directions_car_rounded, 'Vehicle', v.vehicleNumber!),
          if (v.purpose != null && v.purpose!.isNotEmpty)
            _DetailRow(Icons.note_rounded, 'Purpose', v.purpose!),
          _DetailRow(Icons.schedule_rounded, 'Expected', _fmtDateTime(v.expectedEntryTime)),
          if (v.actualEntryTime != null) _DetailRow(Icons.login_rounded, 'Checked In', _fmtDateTime(v.actualEntryTime!), color: AppColors.secondary),
          if (v.actualExitTime != null) _DetailRow(Icons.logout_rounded, 'Checked Out', _fmtDateTime(v.actualExitTime!), color: AppColors.textHint),

          const SizedBox(height: 20),

          // Action buttons
          if (_isActing)
            const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()))
          else ...[
            // Pending — Allow / Deny
            if (v.isPending) ...[
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.warning.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.warning.withOpacity(0.3)),
                ),
                child: Column(children: [
                  Row(children: [
                    const Icon(Icons.pending_actions_rounded, color: AppColors.warning, size: 18),
                    const SizedBox(width: 8),
                    Expanded(child: Text(
                      widget.isStaff
                          ? 'Waiting for resident approval. You can also allow or deny below.'
                          : 'This visitor is waiting at the gate. Allow or deny entry.',
                      style: const TextStyle(color: AppColors.warning, fontSize: 12),
                    )),
                  ]),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(child: OutlinedButton.icon(
                      onPressed: () => _act(() => ref.read(visitorsProvider.notifier).denyVisitor(v.id), '❌ Visitor denied'),
                      icon: const Icon(Icons.close_rounded, size: 18, color: AppColors.error),
                      label: const Text('Deny Entry', style: TextStyle(color: AppColors.error)),
                      style: OutlinedButton.styleFrom(side: const BorderSide(color: AppColors.error), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)), padding: const EdgeInsets.symmetric(vertical: 12)),
                    )),
                    const SizedBox(width: 12),
                    Expanded(child: ElevatedButton.icon(
                      onPressed: () => _act(() => ref.read(visitorsProvider.notifier).allowVisitor(v.id), '✅ Visitor allowed'),
                      icon: const Icon(Icons.check_circle_rounded, size: 18, color: Colors.white),
                      label: const Text('Allow Entry', style: TextStyle(color: Colors.white)),
                      style: ElevatedButton.styleFrom(backgroundColor: AppColors.secondary, elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)), padding: const EdgeInsets.symmetric(vertical: 12)),
                    )),
                  ]),
                ]),
              ),
              const SizedBox(height: 12),
            ],

            // Guard Check-In
            if (widget.isStaff && v.canCheckIn)
              SizedBox(width: double.infinity, height: 48,
                child: ElevatedButton.icon(
                  onPressed: () => _act(() => ref.read(visitorsProvider.notifier).checkIn(v.id), '✅ Visitor checked in'),
                  icon: const Icon(Icons.login_rounded, color: Colors.white),
                  label: const Text('Check In Visitor', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                  style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                ),
              ),

            // Guard Check-Out
            if (widget.isStaff && v.canCheckOut) ...[
              const SizedBox(height: 8),
              SizedBox(width: double.infinity, height: 48,
                child: ElevatedButton.icon(
                  onPressed: () => _act(() => ref.read(visitorsProvider.notifier).checkOut(v.id), '⬅ Visitor checked out'),
                  icon: const Icon(Icons.logout_rounded, color: Colors.white),
                  label: const Text('Check Out Visitor', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                  style: ElevatedButton.styleFrom(backgroundColor: AppColors.warning, elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                ),
              ),
            ],

            // Already completed
            if (!v.isPending && !v.canCheckIn && !v.canCheckOut)
              Center(child: Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  v.isDenied ? '❌ Entry Denied' : v.hasExited ? '✅ Visit Completed' : '✅ Visitor Approved',
                  style: TextStyle(color: v.isDenied ? AppColors.error : AppColors.secondary, fontWeight: FontWeight.w600),
                ),
              )),
          ],
        ]),
      ),
    );
  }

  String _fmtDateTime(DateTime t) => '${t.day}/${t.month}/${t.year} ${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
  String ucFirst(String s) => s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);
}

class _DetailRow extends StatelessWidget {
  final IconData icon;
  final String label, value;
  final Color? color;
  const _DetailRow(this.icon, this.label, this.value, {this.color});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Row(children: [
      Icon(icon, size: 16, color: color ?? AppColors.textHint),
      const SizedBox(width: 10),
      Text('$label: ', style: const TextStyle(fontSize: 13, color: AppColors.textHint)),
      Expanded(child: Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: color ?? AppColors.textPrimary), overflow: TextOverflow.ellipsis)),
    ]),
  );
}

// ── Add Visitor Sheet ────────────────────────────────────────────────────────

class _AddVisitorSheet extends ConsumerStatefulWidget {
  final bool isStaff;
  const _AddVisitorSheet({required this.isStaff});
  @override
  ConsumerState<_AddVisitorSheet> createState() => _AddVisitorSheetState();
}

class _AddVisitorSheetState extends ConsumerState<_AddVisitorSheet> {
  final _formKey     = GlobalKey<FormState>();
  final _phoneCtrl   = TextEditingController();
  final _nameCtrl    = TextEditingController();
  final _vehicleCtrl = TextEditingController();
  final _purposeCtrl = TextEditingController();

  String _selectedType = 'guest';
  int? _selectedFlatId;
  bool _isLoading = false;
  bool _showMore = false;

  static const _types = [
    {'value': 'guest',       'label': 'Guest',       'icon': '👤'},
    {'value': 'delivery',    'label': 'Delivery',    'icon': '📦'},
    {'value': 'cab',         'label': 'Cab',         'icon': '🚕'},
    {'value': 'service',     'label': 'Service',     'icon': '🔧'},
    {'value': 'maid',        'label': 'Maid',        'icon': '🧹'},
    {'value': 'driver',      'label': 'Driver',      'icon': '🚗'},
    {'value': 'cook',        'label': 'Cook',        'icon': '👨‍🍳'},
    {'value': 'electrician', 'label': 'Electrician', 'icon': '⚡'},
    {'value': 'plumber',     'label': 'Plumber',     'icon': '🪠'},
    {'value': 'emergency',   'label': 'Emergency',   'icon': '🚨'},
    {'value': 'other',       'label': 'Other',       'icon': '👥'},
  ];

  @override
  void dispose() {
    _phoneCtrl.dispose(); _nameCtrl.dispose();
    _vehicleCtrl.dispose(); _purposeCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (widget.isStaff && _selectedFlatId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select a flat/villa'), backgroundColor: AppColors.error));
      return;
    }
    setState(() => _isLoading = true);
    try {
      await ref.read(visitorsProvider.notifier).addVisitor(
        phone: _phoneCtrl.text.trim(),
        visitorType: _selectedType,
        flatId: _selectedFlatId,
        name: _nameCtrl.text.trim(),
        vehicleNumber: _vehicleCtrl.text.trim(),
        purpose: _purposeCtrl.text.trim(),
      );
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(widget.isStaff
              ? '✅ Visitor registered. Resident notified for approval.'
              : '✅ Visitor registered successfully!'),
          backgroundColor: AppColors.secondary,
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 4),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final flats = ref.watch(visitorsProvider).flats;
    return Container(
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      padding: EdgeInsets.fromLTRB(20, 16, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            Text(widget.isStaff ? '🛡 Quick Visitor Entry' : 'Invite a Visitor', style: AppTextStyles.h3),
            const SizedBox(height: 4),
            Text(
              widget.isStaff ? 'Resident will be notified for approval' : 'Register a new visitor',
              style: AppTextStyles.caption,
            ),
            const SizedBox(height: 20),

            // Visitor type chips
            const Text('Visitor Type *', style: AppTextStyles.label),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: _types.map((t) {
              final selected = _selectedType == t['value'];
              return GestureDetector(
                onTap: () => setState(() => _selectedType = t['value']!),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: selected ? AppColors.primary : AppColors.background,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: selected ? AppColors.primary : AppColors.divider),
                  ),
                  child: Text('${t['icon']} ${t['label']}',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: selected ? Colors.white : AppColors.textSecondary)),
                ),
              );
            }).toList()),
            const SizedBox(height: 16),

            // Phone
            const Text('Mobile Number *', style: AppTextStyles.label),
            const SizedBox(height: 6),
            TextFormField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(
                hintText: 'Enter mobile number',
                prefixIcon: Icon(Icons.phone_rounded, color: AppColors.textHint),
              ),
              validator: (v) => (v?.isEmpty ?? true) ? 'Mobile number required' : null,
            ),
            const SizedBox(height: 12),

            // Flat picker (guard only)
            if (widget.isStaff) ...[
              const Text('Flat / Villa *', style: AppTextStyles.label),
              const SizedBox(height: 6),
              DropdownButtonFormField<int>(
                value: _selectedFlatId,
                hint: const Text('Select flat or villa'),
                isExpanded: true,
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.home_rounded, color: AppColors.textHint),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
                items: flats.map((f) => DropdownMenuItem(value: f.id, child: Text(f.display, overflow: TextOverflow.ellipsis))).toList(),
                onChanged: (v) => setState(() => _selectedFlatId = v),
              ),
              const SizedBox(height: 12),
            ],

            // Optional details toggle
            GestureDetector(
              onTap: () => setState(() => _showMore = !_showMore),
              child: Row(children: [
                Icon(_showMore ? Icons.expand_less_rounded : Icons.expand_more_rounded, color: AppColors.primary, size: 18),
                const SizedBox(width: 4),
                Text(_showMore ? 'Hide Details' : 'More Details (Optional)',
                  style: const TextStyle(color: AppColors.primary, fontSize: 13, fontWeight: FontWeight.w600)),
              ]),
            ),

            if (_showMore) ...[
              const SizedBox(height: 12),
              TextFormField(controller: _nameCtrl,
                decoration: const InputDecoration(hintText: 'Visitor name', prefixIcon: Icon(Icons.person_outlined, color: AppColors.textHint))),
              const SizedBox(height: 10),
              TextFormField(controller: _vehicleCtrl,
                decoration: const InputDecoration(hintText: 'Vehicle number (e.g. MH12AB1234)', prefixIcon: Icon(Icons.directions_car_outlined, color: AppColors.textHint))),
              const SizedBox(height: 10),
              TextFormField(controller: _purposeCtrl,
                decoration: const InputDecoration(hintText: 'Purpose of visit', prefixIcon: Icon(Icons.note_outlined, color: AppColors.textHint))),
            ],

            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity, height: 52,
              child: ElevatedButton.icon(
                onPressed: _isLoading ? null : _submit,
                icon: _isLoading
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.person_add_rounded, color: Colors.white),
                label: Text(widget.isStaff ? 'Register & Notify Resident' : 'Add Visitor',
                  style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
                style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}
