import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_view.dart';
import '../../../shared/widgets/status_badge.dart';
import '../providers/admin_provider.dart';

String _group(num v) => v.toStringAsFixed(v % 1 == 0 ? 0 : 2).replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => ',');
String _inr(num? v) => v == null ? '-' : '₹${_group(v)}';

/// The list a Quick overview card (or a Needs attention row) opens. The server builds each list from the card's own
/// rule (AdminListApiController), so the card's number and this list's total cannot disagree - the header shows the
/// server's total and, for money, the server's sum over the WHOLE set (not just the pages loaded).
///
/// Loading, empty, error and "not allowed" are each shown as what they are; a failed call never turns into an empty list.
class AdminListScreen extends StatefulWidget {
  final String title;
  final String path;
  final Map<String, String> query;
  final String emptyTitle;
  final String emptyHint;
  final IconData emptyIcon;
  final Widget Function(Map<String, dynamic> row) row;

  /// One line over the list, from the server's `meta` (total, summary).
  final String Function(int total, Map<String, dynamic> summary)? header;

  const AdminListScreen({
    super.key,
    required this.title,
    required this.path,
    this.query = const {},
    required this.emptyTitle,
    required this.emptyHint,
    this.emptyIcon = Icons.inbox_outlined,
    required this.row,
    this.header,
  });

  // ── the five card destinations (+ the two attention variants) ───────────

  static Widget units({String state = 'all'}) => AdminListScreen(
        key: Key('admin-list-units-$state'),
        title: switch (state) { 'occupied' => 'Occupied units', 'vacant' => 'Vacant units', _ => 'Properties' },
        path: 'units',
        query: {'state': state},
        emptyTitle: switch (state) { 'occupied' => 'No occupied units', 'vacant' => 'No vacant units', _ => 'No properties yet' },
        emptyHint: 'Units added to this society appear here.',
        emptyIcon: Icons.home_work_outlined,
        header: (n, _) => '$n ${n == 1 ? 'unit' : 'units'}',
        row: (r) => _Row(
          title: r['title']?.toString() ?? 'Unit',
          subtitle: [r['state_label'], r['occupant']].where((e) => e != null && e.toString().isNotEmpty).join(' - '),
          trailing: _Tag(r['state'] == 'vacant' ? 'Vacant' : 'Occupied', vacant: r['state'] == 'vacant'),
        ),
      );

  static Widget residents() => AdminListScreen(
        key: const Key('admin-list-residents'),
        title: 'Residents',
        path: 'residents',
        emptyTitle: 'No residents yet',
        emptyHint: 'Active owners and tenants appear here.',
        emptyIcon: Icons.groups_outlined,
        header: (n, s) => '$n ${n == 1 ? 'resident' : 'residents'}  (owners ${s['owners'] ?? 0}, tenants ${s['tenants'] ?? 0})',
        row: (r) => _Row(
          title: r['title']?.toString() ?? 'Resident',
          subtitle: [r['role'], r['subtitle']].where((e) => e != null && e.toString().isNotEmpty).join(' - '),
          trailing: (r['phone'] ?? '').toString().isEmpty ? null : Text(r['phone'].toString(), style: AppTextStyles.caption),
        ),
      );

  static Widget visitors({String scope = 'today'}) => AdminListScreen(
        key: Key('admin-list-visitors-$scope'),
        title: scope == 'pending' ? 'Visitors awaiting approval' : "Today's visitors",
        path: 'visitors',
        query: {'scope': scope},
        emptyTitle: scope == 'pending' ? 'Nobody is waiting' : 'No visitors today',
        emptyHint: scope == 'pending' ? 'Visitors who need a decision appear here.' : 'Visitors expected today appear here.',
        emptyIcon: Icons.people_alt_outlined,
        header: (n, _) => '$n ${scope == 'pending' ? 'waiting for approval' : 'expected today'}',
        row: (r) => _Row(
          title: r['title']?.toString() ?? 'Visitor',
          subtitle: [r['subtitle'], r['when']].where((e) => e != null && e.toString().isNotEmpty).join('\n'),
          trailing: StatusBadge(status: r['approval_status']?.toString() ?? 'pending'),
        ),
      );

  static Widget bills({String scope = 'outstanding'}) => AdminListScreen(
        key: Key('admin-list-bills-$scope'),
        title: scope == 'overdue' ? 'Overdue bills' : 'Pending dues',
        path: 'bills',
        query: {'scope': scope},
        emptyTitle: scope == 'overdue' ? 'No overdue bills' : 'No dues outstanding',
        emptyHint: 'Unpaid and part-paid bills appear here.',
        emptyIcon: Icons.account_balance_wallet_outlined,
        header: (n, s) => '$n ${n == 1 ? 'bill' : 'bills'} - ${_inr(s['amount'] as num?)} outstanding',
        row: (r) => _Row(
          title: r['title']?.toString() ?? 'Bill',
          subtitle: [r['subtitle'], if (r['due_date'] != null) 'Due ${r['due_date']}'].join('\n'),
          trailing: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(_inr(r['amount'] as num?), style: AppTextStyles.label),
            const SizedBox(height: 4),
            StatusBadge(status: r['status']?.toString() ?? 'pending'),
          ]),
        ),
      );

  static Widget collections() => AdminListScreen(
        key: const Key('admin-list-collections'),
        title: 'Collected this month',
        path: 'collections',
        emptyTitle: 'Nothing collected yet this month',
        emptyHint: 'Verified payments appear here. A payment a resident has only submitted is not counted until it is verified.',
        emptyIcon: Icons.payments_outlined,
        header: (n, s) => '${_inr(s['amount'] as num?)} in ${s['month'] ?? 'this month'} - $n ${n == 1 ? 'payment' : 'payments'}',
        row: (r) => _Row(
          title: r['title']?.toString() ?? 'Payment',
          subtitle: [r['subtitle'], [r['date'], r['payer']].where((e) => e != null && e.toString().isNotEmpty).join(' - ')].where((e) => e.toString().isNotEmpty).join('\n'),
          trailing: Text(_inr(r['amount'] as num?), style: AppTextStyles.label),
        ),
      );

  @override
  State<AdminListScreen> createState() => _AdminListScreenState();
}

class _AdminListScreenState extends State<AdminListScreen> {
  final List<Map<String, dynamic>> _rows = [];
  bool _loading = true, _loadingMore = false, _denied = false;
  String? _error;
  int _page = 1, _lastPage = 1, _total = 0;
  Map<String, dynamic> _summary = const {};

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _loading = _rows.isEmpty;
        _error = null;
      });
    } else {
      setState(() => _loadingMore = true);
    }
    final page = reset ? 1 : _page + 1;
    final r = await AdminApi.list(widget.path, query: widget.query, page: page);
    if (!mounted) return;
    if (r.success && r.data != null) {
      final meta = (r.data!['meta'] as Map?) ?? const {};
      final raw = r.data!['data'];
      setState(() {
        if (reset) _rows.clear();
        _rows.addAll([for (final i in (raw is List ? raw : const [])) Map<String, dynamic>.from(i as Map)]);
        _page = page;
        _lastPage = (meta['last_page'] as num?)?.toInt() ?? 1;
        _total = (meta['total'] as num?)?.toInt() ?? _rows.length;
        _summary = meta['summary'] is Map ? Map<String, dynamic>.from(meta['summary'] as Map) : const {};
        _denied = false;
        _error = null;
        _loading = false;
        _loadingMore = false;
      });
    } else {
      setState(() {
        _denied = r.statusCode == 403;
        _error = r.message.isNotEmpty ? r.message : 'Could not load this list.';
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    Widget body;
    if (_loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null && _rows.isEmpty) {
      body = _denied
          ? const EmptyState(icon: Icons.lock_outline_rounded, title: 'Not available for your account', subtitle: 'Only the society administrator can open this list.')
          : ErrorView(message: _error!, onRetry: () => _load(reset: true));
    } else if (_rows.isEmpty) {
      body = EmptyState(icon: widget.emptyIcon, title: widget.emptyTitle, subtitle: widget.emptyHint);
    } else {
      final header = widget.header?.call(_total, _summary);
      body = RefreshIndicator(
        onRefresh: () => _load(reset: true),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: [
            if (header != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Semantics(header: true, child: Text(header, key: const Key('admin-list-header'), style: AppTextStyles.label)),
              ),
            if (_error != null)
              Padding(padding: const EdgeInsets.only(bottom: 8), child: Text('$_error Showing what was loaded.', style: AppTextStyles.caption.copyWith(color: AppColors.error))),
            for (final r in _rows) ...[widget.row(r), const SizedBox(height: 8)],
            if (_page < _lastPage)
              Center(
                child: _loadingMore
                    ? const Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator())
                    : TextButton(key: const Key('admin-list-more'), onPressed: () => _load(), child: Text('Load more (${_rows.length} of $_total)')),
              ),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: Text(widget.title)),
      body: body,
    );
  }
}

class _Row extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget? trailing;
  const _Row({required this.title, required this.subtitle, this.trailing});

  @override
  Widget build(BuildContext context) => AppCard(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: AppTextStyles.label, maxLines: 2, overflow: TextOverflow.ellipsis),
              if (subtitle.isNotEmpty) ...[const SizedBox(height: 3), Text(subtitle, style: AppTextStyles.caption)],
            ]),
          ),
          if (trailing != null) ...[const SizedBox(width: 10), trailing!],
        ]),
      );
}

class _Tag extends StatelessWidget {
  final String text;
  final bool vacant;
  const _Tag(this.text, {this.vacant = false});

  @override
  Widget build(BuildContext context) {
    final color = vacant ? AppColors.textSecondary : AppColors.secondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(text, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }
}

/// Who is rostered today, where, and whether they are actually on duty. Rows come from the real roster; the duty state
/// is the server's (GuardShiftService::dutyBoard): "On duty" only when a check-in exists, never from the roster alone.
class AdminGuardShiftsScreen extends StatefulWidget {
  const AdminGuardShiftsScreen({super.key});

  @override
  State<AdminGuardShiftsScreen> createState() => _AdminGuardShiftsScreenState();
}

class _AdminGuardShiftsScreenState extends State<AdminGuardShiftsScreen> {
  bool _loading = true, _denied = false;
  String? _error;
  List<Map<String, dynamic>> _items = const [];
  Map<String, dynamic> _counts = const {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final r = await AdminApi.guardShifts();
    if (!mounted) return;
    if (r.success && r.data != null) {
      final meta = (r.data!['meta'] as Map?) ?? const {};
      setState(() {
        _items = [for (final i in ((r.data!['data'] as List?) ?? const [])) Map<String, dynamic>.from(i as Map)];
        _counts = meta['summary'] is Map ? Map<String, dynamic>.from(meta['summary'] as Map) : const {};
        _error = null;
        _denied = false;
        _loading = false;
      });
    } else {
      setState(() {
        _denied = r.statusCode == 403;
        _error = r.message.isNotEmpty ? r.message : 'Could not load the guard roster.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    Widget body;
    if (_loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null) {
      body = _denied
          ? const EmptyState(icon: Icons.lock_outline_rounded, title: 'Not available for your account', subtitle: 'Only the society administrator can see the guard roster.')
          : ErrorView(message: _error!, onRetry: _load);
    } else if (_items.isEmpty) {
      body = const EmptyState(icon: Icons.shield_outlined, title: 'No guard is rostered today', subtitle: 'Assign guards to shifts and gates on the web (Admin > Guard Roster).');
    } else {
      body = RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: [
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                '${_counts['on_duty'] ?? 0} on duty - ${_counts['not_checked_in'] ?? 0} not checked in - ${_counts['scheduled'] ?? 0} scheduled - ${_counts['completed'] ?? 0} completed',
                key: const Key('guard-shifts-counts'),
                style: AppTextStyles.label,
              ),
            ),
            for (final g in _items) ...[GuardDutyTile(item: g), const SizedBox(height: 8)],
          ],
        ),
      );
    }
    return Scaffold(backgroundColor: AppColors.background, appBar: AppBar(title: const Text('Guard shifts')), body: body);
  }
}

/// One rostered guard: name, gate, shift window, duty state, and anything wrong with the assignment.
class GuardDutyTile extends StatelessWidget {
  final Map<String, dynamic> item;
  const GuardDutyTile({super.key, required this.item});

  static Color colorFor(String state) => switch (state) {
        'on_duty' => AppColors.secondary,
        'not_checked_in' => AppColors.warning,
        'absent' || 'missed' => AppColors.error,
        'completed' => AppColors.info,
        _ => AppColors.textSecondary,
      };

  @override
  Widget build(BuildContext context) {
    final state = item['duty_state']?.toString() ?? 'scheduled';
    final color = colorFor(state);
    final window = '${item['start'] ?? ''}-${item['end'] ?? ''}${item['overnight'] == true ? ' (overnight)' : ''}';
    final where = [item['gate'], item['shift']].where((e) => e != null && e.toString().isNotEmpty).join(' - ');
    final checked = item['checked_in_at'] != null ? 'Checked in ${item['checked_in_at']}${item['checked_out_at'] != null ? ', out ${item['checked_out_at']}' : ''}' : null;
    return AppCard(
      padding: const EdgeInsets.all(12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(Icons.shield_rounded, color: color, size: 18),
          const SizedBox(width: 8),
          Expanded(child: Text(item['guard']?.toString() ?? 'Guard', style: AppTextStyles.label, overflow: TextOverflow.ellipsis)),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
            child: Text(item['duty_label']?.toString() ?? state, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700)),
          ),
        ]),
        const SizedBox(height: 4),
        Text([where, window].where((e) => e.isNotEmpty).join('  |  '), style: AppTextStyles.caption),
        if (checked != null) Text(checked, style: AppTextStyles.caption),
        if (item['issue'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text('${item['issue']} - fix it on the web (Admin > Guard Roster).', style: AppTextStyles.caption.copyWith(color: AppColors.warning, fontWeight: FontWeight.w600)),
          ),
      ]),
    );
  }
}
