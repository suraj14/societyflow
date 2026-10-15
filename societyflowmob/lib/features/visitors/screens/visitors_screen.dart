import 'dart:io';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/network_image_widget.dart';
import '../../auth/providers/auth_provider.dart';
import '../../../core/services/document_scan_service.dart';
import '../providers/visitors_provider.dart';
import '../models/id_document.dart';
import '../models/visitor_lookup.dart';
import '../models/visitor_model.dart';
import 'document_scan_screen.dart';

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
  int get refreshIntervalSeconds => 10; // a decision made elsewhere shows within seconds

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
            decoration: BoxDecoration(color: AppColors.error.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
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
        // See the note on the complaints FAB: tabs stay mounted together in
        // the shell's IndexedStack, so every FAB needs a distinct hero tag.
        heroTag: 'fab-visitors',
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
        color: AppColors.warning.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.4)),
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
              color: _typeColor(visitor.visitorType).withValues(alpha: 0.1),
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
                  color: AppColors.secondary.withValues(alpha: 0.1),
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

        // What the signed-in person may do now (the server's answer, not a guess from the status). Gate staff get
        // no Allow / Deny - they record the visitor and wait for the resident - only a clear note not to let them in.
        if (visitor.canDecide || (isStaff && (visitor.canCheckIn || visitor.canCheckOut))) ...[
          const SizedBox(height: 10),
          const Divider(height: 1),
          const SizedBox(height: 8),
          _QuickActions(visitor: visitor, isStaff: isStaff),
        ] else if (isStaff && visitor.status == 'pending_approval') ...[
          const SizedBox(height: 10),
          const Divider(height: 1),
          const SizedBox(height: 8),
          _WaitingNote(visitor: visitor),
        ] else if (visitor.isDenied && (visitor.rejectionReason ?? '').isNotEmpty) ...[
          const SizedBox(height: 8),
          Text('Rejected: ${visitor.rejectionReason}', style: const TextStyle(fontSize: 12, color: AppColors.error)),
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

// ── Decisions: confirm before they are sent ──────────────────────────────────
//
// What the signed-in person may do comes from the server (`can_decide`, `can_override`, `can_check_in`,
// `can_check_out` on every visitor), so no button is shown that the server would refuse. Allow and Deny
// each ask first: a resident's decision lets a stranger in, or turns them away, and the guard acts on it.

bool _isAdminRole(WidgetRef ref) => (ref.read(currentUserProvider)?.role ?? '').contains('Admin');

Future<bool> _confirmAllow(BuildContext context, VisitorModel v) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text('Allow ${v.visitorName}?'),
      content: Text(
        '${v.visitorName} will be allowed to enter'
        '${v.unitDisplay.isNotEmpty ? ' ${v.unitDisplay}' : ''}. The guard is told straight away.',
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancel')),
        ElevatedButton(
          onPressed: () => Navigator.pop(c, true),
          style: ElevatedButton.styleFrom(backgroundColor: AppColors.secondary),
          child: const Text('Allow entry', style: TextStyle(color: Colors.white)),
        ),
      ],
    ),
  );
  return ok ?? false;
}

/// The reason for turning a visitor away. Optional for a resident, required for an administrator.
/// Null when they cancel.
Future<String?> _askDenyReason(BuildContext context, VisitorModel v, {required bool reasonRequired}) async {
  final ctrl = TextEditingController();
  String? error;
  final reason = await showDialog<String>(
    context: context,
    builder: (c) => StatefulBuilder(
      builder: (c, setDialog) => AlertDialog(
        title: Text('Turn away ${v.visitorName}?'),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('The guard is told not to let them in.'),
          const SizedBox(height: 12),
          TextField(
            controller: ctrl,
            maxLength: 250,
            decoration: InputDecoration(
              labelText: reasonRequired ? 'Reason (required)' : 'Reason (optional)',
              errorText: error,
            ),
          ),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              if (reasonRequired && ctrl.text.trim().length < 3) {
                setDialog(() => error = 'Enter the reason');
                return;
              }
              Navigator.pop(c, ctrl.text.trim());
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error),
            child: const Text('Deny entry', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    ),
  );
  // No ctrl.dispose() here: the dialog is still animating out when showDialog returns and its field would be
  // reading a disposed controller. A TextEditingController holds no native resources; it is collected with the dialog.
  return reason;
}

/// An administrator changing a decision: which way, and why. The reason is mandatory (the server needs five
/// characters) because the residents and the guard are told and the change is kept in the visitor's history.
/// Null when they cancel.
Future<({bool allow, String reason})?> _askOverride(BuildContext context, VisitorModel v) async {
  final ctrl = TextEditingController();
  var allow = !v.isAllowed; // the opposite of what stands now
  String? error;
  final result = await showDialog<({bool allow, String reason})>(
    context: context,
    builder: (c) => StatefulBuilder(
      builder: (c, setDialog) => AlertDialog(
        title: const Text('Change the decision'),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(
            v.isDenied
                ? '${v.decidedBy ?? 'A resident'} turned ${v.visitorName} away${(v.rejectionReason ?? '').isNotEmpty ? ': ${v.rejectionReason}' : ''}.'
                : '${v.visitorName} is ${v.isAllowed ? 'allowed' : 'waiting for an answer'}.',
            style: const TextStyle(fontSize: 13),
          ),
          const SizedBox(height: 8),
          // the decision that already stands cannot be chosen again
          SegmentedButton<bool>(
            segments: [
              ButtonSegment(value: true, label: const Text('Allow entry'), enabled: !v.isAllowed),
              ButtonSegment(value: false, label: const Text('Deny entry'), enabled: !v.isDenied),
            ],
            selected: {allow},
            onSelectionChanged: (sel) => setDialog(() => allow = sel.first),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: ctrl,
            maxLength: 250,
            decoration: InputDecoration(labelText: 'Reason (required)', errorText: error),
          ),
          const Text('The residents and the guard are told, and this is kept in the visitor\'s history.',
              style: TextStyle(fontSize: 12, color: AppColors.textHint)),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              if (ctrl.text.trim().length < 5) {
                setDialog(() => error = 'Enter a reason (at least 5 characters)');
                return;
              }
              Navigator.pop(c, (allow: allow, reason: ctrl.text.trim()));
            },
            child: const Text('Change decision'),
          ),
        ],
      ),
    ),
  );
  return result; // (controller left to be collected with the dialog, see _askDenyReason)
}

/// Runs one visitor action and says what the server answered, in its own words.
Future<void> _runVisitorAction(BuildContext context, Future<void> Function() action, String success) async {
  final messenger = ScaffoldMessenger.of(context);
  try {
    await action();
    messenger.showSnackBar(SnackBar(
      content: Text(success),
      backgroundColor: AppColors.secondary,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      margin: const EdgeInsets.all(16),
    ));
  } catch (e) {
    messenger.showSnackBar(SnackBar(
      content: Text(e.toString()),
      backgroundColor: AppColors.error,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      margin: const EdgeInsets.all(16),
    ));
  }
}

// ── Gate staff: nothing to press while the residents decide ──────────────────

class _WaitingNote extends StatelessWidget {
  final VisitorModel visitor;
  const _WaitingNote({required this.visitor});

  @override
  Widget build(BuildContext context) => Row(children: [
        const Icon(Icons.hourglass_top_rounded, size: 14, color: AppColors.warning),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            visitor.escalated
                ? 'No answer yet - an administrator has been told. Do not let them in.'
                : 'Waiting for the resident to approve. Do not let them in yet.',
            style: const TextStyle(fontSize: 12, color: AppColors.warning, fontWeight: FontWeight.w600),
          ),
        ),
      ]);
}

// ── Quick action buttons on card ─────────────────────────────────────────────

class _QuickActions extends ConsumerWidget {
  final VisitorModel visitor;
  final bool isStaff;
  const _QuickActions({required this.visitor, required this.isStaff});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final buttons = <Widget>[];
    final notifier = ref.read(visitorsProvider.notifier);

    if (visitor.canDecide) {
      buttons.add(Expanded(
        child: OutlinedButton.icon(
          onPressed: () async {
            final reason = await _askDenyReason(context, visitor, reasonRequired: _isAdminRole(ref));
            if (reason == null || !context.mounted) return;
            await _runVisitorAction(context, () => notifier.denyVisitor(visitor.id, reason: reason), '❌ Visitor denied');
          },
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
          onPressed: () async {
            if (!await _confirmAllow(context, visitor) || !context.mounted) return;
            await _runVisitorAction(context, () => notifier.allowVisitor(visitor.id), '✅ Visitor allowed');
          },
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
          onPressed: () => _runVisitorAction(context, () => notifier.checkIn(visitor.id), '✅ Checked In'),
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
          onPressed: () => _runVisitorAction(context, () => notifier.checkOut(visitor.id), '⬅ Checked Out'),
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

  /// The visitor as the server holds it now (the list is replaced by the server's answer after every action,
  /// and refreshed every few seconds), so this sheet follows a decision made elsewhere too.
  VisitorModel get _live => ref.watch(visitorsProvider).allVisitors.where((v) => v.id == _visitor.id).firstOrNull ?? _visitor;

  Future<void> _act(Future<void> Function() fn, String msg) async {
    setState(() => _isActing = true);
    await _runVisitorAction(context, fn, msg);
    if (mounted) setState(() => _isActing = false);
  }

  @override
  Widget build(BuildContext context) {
    final v = _live;
    final notifier = ref.read(visitorsProvider.notifier);
    final isAdmin = _isAdminRole(ref);

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

          // Header: the visitor's photo when the guard took one, so the resident sees who is waiting
          Row(children: [
            if ((v.photo ?? '').isNotEmpty)
              NetImage(url: v.photo, width: 64, height: 64, borderRadius: BorderRadius.circular(14), fallbackIcon: Icons.person_rounded)
            else
              Container(
                width: 52, height: 52,
                decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(14)),
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
          _DetailRow(Icons.schedule_rounded, 'Arrived', _fmtDateTime(v.expectedEntryTime)),
          if ((v.requestedBy ?? '').isNotEmpty) _DetailRow(Icons.shield_outlined, 'Logged by', v.requestedBy!),
          if ((v.decidedBy ?? '').isNotEmpty && v.decidedAt != null)
            _DetailRow(
              v.isDenied ? Icons.cancel_outlined : Icons.verified_outlined,
              v.isDenied ? 'Denied by' : 'Allowed by',
              '${v.decidedBy}${v.decisionRole == 'override' ? ' (administrator override)' : ''} · ${_fmtDateTime(v.decidedAt!.toLocal())}',
              color: v.isDenied ? AppColors.error : AppColors.secondary,
            ),
          if (v.isDenied && (v.rejectionReason ?? '').isNotEmpty) _DetailRow(Icons.comment_outlined, 'Reason', v.rejectionReason!, color: AppColors.error),
          if ((v.overrideReason ?? '').isNotEmpty) _DetailRow(Icons.scale_rounded, 'Override reason', v.overrideReason!),
          if (v.actualEntryTime != null) _DetailRow(Icons.login_rounded, 'Checked In', _fmtDateTime(v.actualEntryTime!), color: AppColors.secondary),
          if (v.actualExitTime != null) _DetailRow(Icons.logout_rounded, 'Checked Out', _fmtDateTime(v.actualExitTime!), color: AppColors.textHint),

          const SizedBox(height: 20),

          // Action buttons
          if (_isActing)
            const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()))
          else ...[
            // Waiting - the resident (or an administrator) decides; the guard only waits
            if (v.isPending && v.entryStatus == 'pending') ...[
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.warning.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.warning.withValues(alpha: 0.3)),
                ),
                child: Column(children: [
                  Row(children: [
                    const Icon(Icons.pending_actions_rounded, color: AppColors.warning, size: 18),
                    const SizedBox(width: 8),
                    Expanded(child: Text(
                      v.canDecide
                          ? 'This visitor is waiting at the gate. Allow or deny entry.'
                          : v.escalated
                              ? 'No answer yet - an administrator has been told. Do not let them in.'
                              : 'Waiting for the resident to approve. Do not let them in yet.',
                      style: const TextStyle(color: AppColors.warning, fontSize: 12),
                    )),
                  ]),
                  if (v.canDecide) ...[
                    const SizedBox(height: 12),
                    Row(children: [
                      Expanded(child: OutlinedButton.icon(
                        onPressed: () async {
                          final reason = await _askDenyReason(context, v, reasonRequired: isAdmin);
                          if (reason == null || !mounted) return;
                          await _act(() => notifier.denyVisitor(v.id, reason: reason), '❌ Visitor denied');
                        },
                        icon: const Icon(Icons.close_rounded, size: 18, color: AppColors.error),
                        label: const Text('Deny Entry', style: TextStyle(color: AppColors.error)),
                        style: OutlinedButton.styleFrom(side: const BorderSide(color: AppColors.error), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)), padding: const EdgeInsets.symmetric(vertical: 12)),
                      )),
                      const SizedBox(width: 12),
                      Expanded(child: ElevatedButton.icon(
                        onPressed: () async {
                          if (!await _confirmAllow(context, v) || !mounted) return;
                          await _act(() => notifier.allowVisitor(v.id), '✅ Visitor allowed');
                        },
                        icon: const Icon(Icons.check_circle_rounded, size: 18, color: Colors.white),
                        label: const Text('Allow Entry', style: TextStyle(color: Colors.white)),
                        style: ElevatedButton.styleFrom(backgroundColor: AppColors.secondary, elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)), padding: const EdgeInsets.symmetric(vertical: 12)),
                      )),
                    ]),
                  ],
                ]),
              ),
              const SizedBox(height: 12),
            ],

            // Guard Check-In: only after the visitor was allowed
            if (widget.isStaff && v.canCheckIn)
              SizedBox(width: double.infinity, height: 48,
                child: ElevatedButton.icon(
                  onPressed: () => _act(() => notifier.checkIn(v.id), '✅ Visitor checked in'),
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
                  onPressed: () => _act(() => notifier.checkOut(v.id), '⬅ Visitor checked out'),
                  icon: const Icon(Icons.logout_rounded, color: Colors.white),
                  label: const Text('Check Out Visitor', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                  style: ElevatedButton.styleFrom(backgroundColor: AppColors.warning, elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                ),
              ),
            ],

            // Administrator: change a decision, with a reason. Never silent - the residents and the guard are told.
            if (v.canOverride && !(v.isPending && v.canDecide)) ...[
              const SizedBox(height: 8),
              SizedBox(width: double.infinity, height: 46,
                child: OutlinedButton.icon(
                  onPressed: () async {
                    final choice = await _askOverride(context, v);
                    if (choice == null || !mounted) return;
                    await _act(() => notifier.overrideVisitor(v.id, allow: choice.allow, reason: choice.reason), 'Decision changed. The residents and the guard have been told.');
                  },
                  icon: const Icon(Icons.scale_rounded, size: 18, color: AppColors.primary),
                  label: const Text('Change decision (administrator)', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w600)),
                  style: OutlinedButton.styleFrom(side: const BorderSide(color: AppColors.primary), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                ),
              ),
            ],

            // Outcome line when there is nothing left to press
            if (!v.isPending && !v.canCheckIn && !v.canCheckOut)
              Center(child: Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  v.isDenied ? '❌ Entry Denied' : v.hasExited ? '✅ Visit Completed' : v.hasEntered ? '✅ Visitor Inside' : '✅ Visitor Approved',
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

  /// Identifies this submission. A tap that seemed to do nothing, or a request that timed out, is sent again with the
  /// SAME id, so the server returns the visitor it already has instead of creating a second one and telling the
  /// residents twice.
  final String _clientRequestId = '${DateTime.now().microsecondsSinceEpoch}-${Random().nextInt(1 << 32)}';

  /// A photo of the visitor taken at the gate (guard and staff), shown to the resident when they decide.
  String? _photoPath;

  /// What a scanned ID gave us, if the guard scanned one. Held so the ID
  /// proof travels with the visitor and the capture can be cleaned up.
  ScannedIdentity? _scanned;
  final FocusNode _nameFocus = FocusNode();

  /// What the gate already knows about the number being typed, and which
  /// number that answer was for - so the lookup runs once per number
  /// rather than on every keystroke.
  VisitorLookup? _lookup;
  String? _lookedUpPhone;
  bool _dismissedLookup = false;

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
    _nameFocus.dispose();
    // The captured page does not outlive the form that used it.
    DocumentScanService.discardCapture(_scanned?.imagePath);
    super.dispose();
  }

  /// Ask the gate whether this number has been here before.
  ///
  /// Runs as soon as a full ten digits are present. A null answer (offline,
  /// an error, a resident account) simply leaves the form alone: the lookup
  /// is a convenience, never a gate on registering someone.
  Future<void> _maybeLookup(String raw) async {
    final digits = raw.replaceAll(RegExp(r'\D'), '');

    if (digits.length < 10) {
      if (_lookup != null) {
        setState(() {
          _lookup = null;
          _lookedUpPhone = null;
        });
      }
      return;
    }
    if (digits == _lookedUpPhone) return;

    _lookedUpPhone = digits;
    final found = await ref.read(visitorsProvider.notifier).lookupByPhone(digits);
    if (!mounted || digits != _lookedUpPhone) return;

    setState(() {
      _lookup = (found?.found ?? false) ? found : null;
      _dismissedLookup = false;
    });
  }

  /// Reuse what is on file: the name and visitor type carry over, the
  /// destination does not - who they are visiting is a fact about *this*
  /// visit, so the guard still chooses it.
  void _useExisting({required bool forEditing}) {
    final existing = _lookup?.visitor;
    if (existing == null) return;

    setState(() {
      _nameCtrl.text = existing.visitorName;
      if (_types.any((t) => t['value'] == existing.visitorType)) {
        _selectedType = existing.visitorType;
      }
      _showMore = forEditing;
      _dismissedLookup = true;
    });
    if (forEditing) _nameFocus.requestFocus();
  }

  /// Let an already-approved visitor straight in, instead of creating a
  /// second record for the same person.
  Future<void> _checkInExisting() async {
    final existing = _lookup?.visitor;
    if (existing == null) return;

    setState(() => _isLoading = true);
    try {
      await ref.read(visitorsProvider.notifier).checkIn(existing.id);
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('\u2705 ${existing.visitorName} checked in.'),
        backgroundColor: AppColors.secondary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
      ));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  /// Open the ID scanner, then fill in what it managed to read.
  ///
  /// The review step lives inside [DocumentScanScreen], so anything arriving
  /// here has already been seen and accepted by the guard. A `null` result
  /// means they cancelled or chose to type instead - this sheet is the
  /// manual form, so there is nothing to undo.
  Future<void> _scanDocument() async {
    final scanned = await Navigator.of(context).push<ScannedIdentity>(
      MaterialPageRoute(builder: (_) => const DocumentScanScreen()),
    );
    if (!mounted || scanned == null) return;

    setState(() {
      _scanned = scanned;
      if ((scanned.name ?? '').isNotEmpty) _nameCtrl.text = scanned.name!;
      // Open the details section so the filled-in name is visible rather
      // than hidden behind "More Details".
      _showMore = true;
    });
  }

  void _enterManually() {
    setState(() => _showMore = true);
    _nameFocus.requestFocus();
  }

  void _clearScan() {
    DocumentScanService.discardCapture(_scanned?.imagePath);
    setState(() => _scanned = null);
  }

  Future<void> _takePhoto() async {
    try {
      final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 70, maxWidth: 1280);
      if (picked != null && mounted) setState(() => _photoPath = picked.path);
    } catch (_) {
      // no camera / permission refused: the visitor can still be registered without a photo
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Could not open the camera. You can register the visitor without a photo.')));
      }
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (widget.isStaff && _selectedFlatId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select a flat/villa'), backgroundColor: AppColors.error));
      return;
    }
    setState(() => _isLoading = true);
    try {
      final added = await ref.read(visitorsProvider.notifier).addVisitor(
        clientRequestId: _clientRequestId,
        photoPath: _photoPath,
        phone: _phoneCtrl.text.trim(),
        visitorType: _selectedType,
        flatId: _selectedFlatId,
        name: _nameCtrl.text.trim(),
        vehicleNumber: _vehicleCtrl.text.trim(),
        purpose: _purposeCtrl.text.trim(),
        idProof: _scanned?.documentType.apiValue,
        // Already redacted for Aadhaar by the scan flow; the API redacts
        // again on arrival rather than trusting this client.
        idNumber: _scanned?.documentReference,
      );
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          // the server's own words: it says when nobody is linked to the unit and the administrators were asked instead
          content: Text((added.residentsLinked ? '✅ ' : '⚠ ') + (added.message.isNotEmpty
              ? added.message
              : (widget.isStaff ? 'Visitor registered. Resident notified for approval.' : 'Visitor registered successfully!'))),
          backgroundColor: added.residentsLinked ? AppColors.secondary : AppColors.warning,
          behavior: SnackBarBehavior.floating,
          duration: Duration(seconds: added.residentsLinked ? 4 : 8),
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
    // The society decides the wording: a villa township should not read
    // "Flat / Villa", nor an apartment society "Villa" (brief §12).
    final propertyNoun = ref.watch(currentUserProvider)?.society.propertyNoun ?? 'Property';
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

            // Scan ID / Document - guard and staff only. It sits at the top
            // because at a gate the usual move is "take the card, scan it";
            // typing is the fallback, not the default. A resident inviting
            // their own guest has no business scanning anyone's ID.
            if (widget.isStaff) ...[
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton.icon(
                  onPressed: _isLoading ? null : _scanDocument,
                  icon: const Icon(Icons.document_scanner_outlined, size: 20, color: Colors.white),
                  label: const Text('Scan ID / Document',
                    style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                height: 46,
                child: OutlinedButton.icon(
                  onPressed: _isLoading ? null : _enterManually,
                  icon: const Icon(Icons.keyboard_rounded, size: 18, color: AppColors.primary),
                  label: const Text('Enter Manually',
                    style: TextStyle(color: AppColors.primary, fontSize: 14, fontWeight: FontWeight.w600)),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: AppColors.primary),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
              if (_scanned != null) _scannedIdChip(),
              _photoRow(),
              const SizedBox(height: 18),
            ],

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
              onChanged: widget.isStaff ? _maybeLookup : null,
            ),
            if (widget.isStaff && _lookup != null && !_dismissedLookup) _existingVisitorCard(),
            const SizedBox(height: 12),

            // Flat picker (guard only)
            if (widget.isStaff) ...[
              Text('$propertyNoun *', style: AppTextStyles.label),
              const SizedBox(height: 6),
              DropdownButtonFormField<int>(
                value: _selectedFlatId,
                hint: Text('Select ${propertyNoun.toLowerCase()}'),
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
              TextFormField(controller: _nameCtrl, focusNode: _nameFocus,
                textCapitalization: TextCapitalization.words,
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

  /// A photo of the visitor, taken at the gate, so the resident sees who is waiting before they decide.
  Widget _photoRow() => Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Row(children: [
          if (_photoPath != null)
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.file(File(_photoPath!), width: 44, height: 44, fit: BoxFit.cover),
            ),
          if (_photoPath != null) const SizedBox(width: 10),
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _isLoading ? null : _takePhoto,
              icon: const Icon(Icons.photo_camera_outlined, size: 18, color: AppColors.primary),
              label: Text(_photoPath == null ? 'Take visitor photo (optional)' : 'Retake photo',
                  style: const TextStyle(color: AppColors.primary, fontSize: 13, fontWeight: FontWeight.w600)),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: AppColors.primary),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
        ]),
      );

  /// "Existing visitor found" - the duplicate the gate would otherwise
  /// create. Shown the moment a known number is typed, with the three things
  /// a guard actually wants to do about it.
  Widget _existingVisitorCard() {
    final lookup = _lookup!;
    final existing = lookup.visitor;

    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.info.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.info.withValues(alpha: 0.35)),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Icon(Icons.history_rounded, size: 16, color: AppColors.info),
            const SizedBox(width: 8),
            const Expanded(
              child: Text('Existing visitor found',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
            ),
            GestureDetector(
              onTap: () => setState(() => _dismissedLookup = true),
              child: const Icon(Icons.close_rounded, size: 16, color: AppColors.textHint),
            ),
          ]),
          const SizedBox(height: 6),
          if (existing != null)
            Text(
              [
                existing.visitorName,
                existing.visitorType,
                if ((existing.flatNumber ?? '').isNotEmpty) 'last visited ${existing.flatNumber}',
              ].join(' • '),
              style: AppTextStyles.caption,
            ),
          if (lookup.hasPass)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'Active pass: ${lookup.passPersonName}'
                '${(lookup.passCategory ?? '').isNotEmpty ? ' (${lookup.passCategory})' : ''}'
                '${lookup.passCurrentlyInside ? ' - currently inside' : ''}',
                style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: AppColors.secondary),
              ),
            ),
          if (existing != null) ...[
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [
              _lookupAction('Use Existing', Icons.person_rounded,
                  () => _useExisting(forEditing: false)),
              _lookupAction('Update Details', Icons.edit_rounded,
                  () => _useExisting(forEditing: true)),
              if (lookup.canCheckInExisting)
                _lookupAction('Check In', Icons.login_rounded, _checkInExisting, primary: true),
            ]),
          ],
        ]),
      ),
    );
  }

  Widget _lookupAction(String label, IconData icon, VoidCallback onTap, {bool primary = false}) =>
    GestureDetector(
      onTap: _isLoading ? null : onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
        decoration: BoxDecoration(
          color: primary ? AppColors.secondary : Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: primary ? AppColors.secondary : AppColors.divider),
        ),
        child: Row(children: [
          Icon(icon, size: 14, color: primary ? Colors.white : AppColors.textSecondary),
          const SizedBox(width: 5),
          Text(label, style: TextStyle(
            fontSize: 12, fontWeight: FontWeight.w600,
            color: primary ? Colors.white : AppColors.textSecondary)),
        ]),
      ),
    );

  /// What the scan contributed, in one line the guard can check and remove.
  /// Only the redacted reference is ever shown here - the same string that
  /// will be stored.
  Widget _scannedIdChip() => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.secondary.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.secondary.withValues(alpha: 0.35)),
      ),
      child: Row(children: [
        const Icon(Icons.badge_outlined, size: 16, color: AppColors.secondary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            [
              _scanned!.documentType.label,
              if ((_scanned!.documentReference ?? '').isNotEmpty) _scanned!.documentReference!,
            ].join(' • '),
            style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.textSecondary),
          ),
        ),
        GestureDetector(
          onTap: _clearScan,
          child: const Icon(Icons.close_rounded, size: 16, color: AppColors.textHint),
        ),
      ]),
    ),
  );
}
