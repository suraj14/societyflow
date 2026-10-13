import 'package:flutter/material.dart';
import '../../../core/services/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../providers/admin_provider.dart';
import 'admin_queue.dart';

/// The office's "Tickets" tab: every resident complaint still open, with the real status transitions.
/// (Residents keep their own ComplaintsScreen - this is the society-wide queue.)
class AdminTicketsScreen extends StatefulWidget {
  /// 'unresolved' (open + in progress: what the Open tickets card counts), 'open' (new only: what the Needs attention row counts), ...
  final String initialFilter;
  const AdminTicketsScreen({super.key, this.initialFilter = 'unresolved'});

  @override
  State<AdminTicketsScreen> createState() => _AdminTicketsScreenState();
}

class _AdminTicketsScreenState extends State<AdminTicketsScreen> {
  static const _filters = {'unresolved': 'Open', 'open': 'New', 'resolved': 'Resolved', 'all': 'All'};
  late String _filter = _filters.containsKey(widget.initialFilter) ? widget.initialFilter : 'unresolved';

  Future<ApiResult> _fetch() => AdminApi.complaints(_filter);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Tickets')),
      body: Column(children: [
        SizedBox(
          height: 52,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            children: [
              for (final e in _filters.entries)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    key: Key('ticket-filter-${e.key}'),
                    label: Text(e.value),
                    selected: _filter == e.key,
                    onSelected: (_) => setState(() => _filter = e.key),
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: AdminQueue(
            key: ValueKey(_filter),
            fetch: _fetch,
            emptyTitle: _filter == 'unresolved' ? 'No open tickets' : (_filter == 'open' ? 'No new complaints' : 'Nothing here'),
            emptyHint: 'Complaints raised by residents appear here.',
            tile: (ctx, c, reload, say) => _TicketTile(item: c, reload: reload, say: say),
          ),
        ),
      ]),
    );
  }
}

class _TicketTile extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function() reload;
  final void Function(String, {bool error}) say;
  const _TicketTile({required this.item, required this.reload, required this.say});

  Future<void> _move(String status) async {
    final id = (item['id'] as num).toInt();
    final r = await AdminApi.complaintStatus(id, status);
    final m = r.data?['message']?.toString();
    say(r.success ? (m ?? 'Updated.') : (r.message.isNotEmpty ? r.message : (m ?? 'That did not work.')), error: !r.success);
    await reload();
  }

  @override
  Widget build(BuildContext context) {
    final status = item['status']?.toString() ?? 'open';
    final resident = item['resident'] is Map ? (item['resident'] as Map)['name']?.toString() : null;
    final id = (item['id'] as num).toInt();
    return QueueCard(
      title: item['title']?.toString() ?? 'Complaint',
      subtitle: [
        if ((item['complaint_number'] ?? '').toString().isNotEmpty) item['complaint_number'].toString(),
        if (resident != null) resident,
        if ((item['property'] ?? '').toString().isNotEmpty) item['property'].toString(),
      ].join(' - '),
      lines: [
        if ((item['category'] ?? '').toString().isNotEmpty) '${item['category']}${(item['priority'] ?? '').toString().isEmpty ? '' : '  (${item['priority']})'}',
        if ((item['description'] ?? '').toString().isNotEmpty) item['description'].toString(),
      ],
      status: status,
      actions: [
        if (status == 'open') QueueAction(key: Key('progress-$id'), label: 'Start work', filled: true, onTap: () => _move('in_progress')),
        if (status == 'open' || status == 'in_progress') QueueAction(key: Key('resolve-$id'), label: 'Resolve', color: AppColors.secondary, onTap: () => _move('resolved')),
        if (status == 'resolved') QueueAction(label: 'Close', onTap: () => _move('closed')),
        if (status == 'resolved' || status == 'closed') QueueAction(label: 'Reopen', color: AppColors.warning, onTap: () => _move('open')),
      ],
    );
  }
}
