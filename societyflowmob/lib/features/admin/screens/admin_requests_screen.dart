import 'package:flutter/material.dart';
import '../../../core/services/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../providers/admin_provider.dart';
import 'admin_queue.dart';

/// The office's "Bookings" screen: facility bookings waiting for a decision. Every button calls the real admin endpoint and
/// then re-reads the list. (Service requests are no longer a workflow in the app - residents contact providers directly
/// from Services - so there is no second tab.)
class AdminRequestsScreen extends StatelessWidget {
  const AdminRequestsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Bookings')),
      body: AdminQueue(
        fetch: () => AdminApi.bookings('pending'),
        emptyTitle: 'No bookings waiting',
        emptyHint: 'New facility booking requests from residents will appear here.',
        tile: (ctx, b, reload, say) => _BookingTile(item: b, reload: reload, say: say),
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
