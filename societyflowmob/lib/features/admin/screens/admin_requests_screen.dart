import 'package:flutter/material.dart';
import '../../../core/services/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../providers/admin_provider.dart';
import 'admin_queue.dart';

/// The office's "Requests" tab: facility bookings waiting for a decision and service requests waiting to be
/// assigned or progressed. Every button calls the real admin endpoint and then re-reads the list.
class AdminRequestsScreen extends StatelessWidget {
  /// 0 = Bookings, 1 = Service requests.
  final int initialTab;
  const AdminRequestsScreen({super.key, this.initialTab = 0});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      initialIndex: initialTab.clamp(0, 1),
      child: Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: const Text('Requests'),
          bottom: const TabBar(
            labelColor: AppColors.primary,
            unselectedLabelColor: AppColors.textHint,
            indicatorColor: AppColors.primary,
            tabs: [Tab(text: 'Bookings'), Tab(text: 'Services')],
          ),
        ),
        body: TabBarView(children: [
          AdminQueue(
            fetch: () => AdminApi.bookings('pending'),
            emptyTitle: 'No bookings waiting',
            emptyHint: 'New facility booking requests from residents will appear here.',
            tile: (ctx, b, reload, say) => _BookingTile(item: b, reload: reload, say: say),
          ),
          AdminQueue(
            fetch: () => AdminApi.serviceRequests('open'),
            emptyTitle: 'No open service requests',
            emptyHint: 'Requests for plumbers, cleaners and other services will appear here.',
            tile: (ctx, r, reload, say) => _ServiceTile(item: r, reload: reload, say: say),
          ),
        ]),
      ),
    );
  }
}

/// Runs an admin action, shows the server's own message, and reloads either way.
Future<void> _run(Future<ApiResult> Function() call, Future<void> Function() reload, void Function(String, {bool error}) say) async {
  final r = await call();
  final serverMessage = r.data?['message']?.toString();
  say(r.success ? (serverMessage ?? 'Done.') : (r.message.isNotEmpty ? r.message : (serverMessage ?? 'That did not work.')), error: !r.success);
  await reload();
}

Future<String?> _askNote(BuildContext context, String title, String hint) {
  final c = TextEditingController();
  return showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: TextField(controller: c, maxLength: 500, maxLines: 3, decoration: InputDecoration(hintText: hint)),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Back')),
        TextButton(onPressed: () => Navigator.pop(ctx, c.text.trim()), child: const Text('Confirm')),
      ],
    ),
  );
}

String _who(Map<String, dynamic> item) {
  final r = item['resident'];
  final name = r is Map ? r['name']?.toString() : null;
  return name == null || name.isEmpty ? 'Resident' : name;
}

class _BookingTile extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function() reload;
  final void Function(String, {bool error}) say;
  const _BookingTile({required this.item, required this.reload, required this.say});

  @override
  Widget build(BuildContext context) {
    final id = (item['id'] as num).toInt();
    final actions = [for (final a in (item['actions'] as List? ?? const [])) a.toString()];
    final when = '${item['booking_date'] ?? ''}  ${item['start_time'] ?? ''}-${item['end_time'] ?? ''}';
    return QueueCard(
      title: item['facility']?.toString() ?? 'Facility',
      subtitle: '${_who(item)}${(item['property'] ?? '').toString().isEmpty ? '' : ' - ${item['property']}'}',
      lines: [when, if ((item['purpose'] ?? '').toString().isNotEmpty) item['purpose'].toString()],
      status: item['status']?.toString() ?? 'pending',
      actions: [
        if (actions.contains('approved'))
          QueueAction(key: Key('approve-$id'), label: 'Approve', filled: true, color: AppColors.secondary, onTap: () => _run(() => AdminApi.bookingStatus(id, 'approved'), reload, say)),
        if (actions.contains('rejected'))
          QueueAction(key: Key('reject-$id'), label: 'Reject', color: AppColors.error, onTap: () async {
            final note = await _askNote(context, 'Reject this booking?', 'Reason shown to the resident (optional)');
            if (note != null) await _run(() => AdminApi.bookingStatus(id, 'rejected', notes: note), reload, say);
          }),
        if (actions.contains('completed'))
          QueueAction(label: 'Complete', onTap: () => _run(() => AdminApi.bookingStatus(id, 'completed'), reload, say)),
        if (actions.contains('cancelled') && !actions.contains('rejected'))
          QueueAction(label: 'Cancel', color: AppColors.error, onTap: () async {
            final note = await _askNote(context, 'Cancel this booking?', 'Reason (optional)');
            if (note != null) await _run(() => AdminApi.bookingStatus(id, 'cancelled', notes: note), reload, say);
          }),
      ],
    );
  }
}

class _ServiceTile extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function() reload;
  final void Function(String, {bool error}) say;
  const _ServiceTile({required this.item, required this.reload, required this.say});

  Future<void> _assign(BuildContext context, int id, List providers) async {
    final picked = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: ListView(shrinkWrap: true, children: [
          const Padding(padding: EdgeInsets.fromLTRB(20, 4, 20, 8), child: Text('Assign to', style: AppTextStyles.h3)),
          for (final p in providers)
            ListTile(
              key: Key('provider-${(p as Map)['id']}'),
              title: Text(p['name']?.toString() ?? 'Provider'),
              subtitle: (p['phone'] ?? '').toString().isEmpty ? null : Text(p['phone'].toString()),
              onTap: () => Navigator.pop(ctx, (p['id'] as num).toInt()),
            ),
        ]),
      ),
    );
    if (picked != null) await _run(() => AdminApi.assignServiceRequest(id, picked), reload, say);
  }

  @override
  Widget build(BuildContext context) {
    final id = (item['id'] as num).toInt();
    final status = item['status']?.toString() ?? 'pending';
    final providers = (item['providers'] as List?) ?? const [];
    final provider = item['provider'] is Map ? (item['provider'] as Map)['name']?.toString() : null;
    return QueueCard(
      title: item['service']?.toString() ?? 'Service',
      subtitle: _who(item),
      lines: [
        if ((item['description'] ?? '').toString().isNotEmpty) item['description'].toString(),
        if (provider != null) 'Assigned to $provider',
      ],
      status: status,
      actions: [
        if (status == 'pending' && providers.isNotEmpty)
          QueueAction(key: Key('assign-$id'), label: 'Assign', filled: true, onTap: () => _assign(context, id, providers)),
        if (status == 'pending' && providers.isEmpty)
          const Text('No active provider for this service yet', style: AppTextStyles.caption),
        if (status == 'assigned') QueueAction(label: 'Start', onTap: () => _run(() => AdminApi.serviceRequestStatus(id, 'in_progress'), reload, say)),
        if (status == 'assigned' || status == 'in_progress')
          QueueAction(label: 'Complete', color: AppColors.secondary, onTap: () => _run(() => AdminApi.serviceRequestStatus(id, 'completed'), reload, say)),
        if (status != 'completed' && status != 'cancelled')
          QueueAction(label: 'Cancel', color: AppColors.error, onTap: () async {
            final note = await _askNote(context, 'Cancel this request?', 'Reason (optional)');
            if (note != null) await _run(() => AdminApi.serviceRequestStatus(id, 'cancelled', reason: note), reload, say);
          }),
      ],
    );
  }
}
