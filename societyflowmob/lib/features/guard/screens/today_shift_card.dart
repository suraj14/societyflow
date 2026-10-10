import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_text.dart';
import '../../../shared/widgets/app_card.dart';
import '../models/guard_shift_model.dart';
import '../providers/guard_shift_provider.dart';

/// "Today's Shift" at the top of the guard's home: which gate, which shift,
/// when, and whether it is on - all from the roster the office keeps, never
/// hard-coded. Says plainly when there is no shift today, and what the next
/// one is.
class TodayShiftCard extends ConsumerWidget {
  const TodayShiftCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(guardShiftProvider);
    final overview = state.overview;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      child: AppCard(
        key: const Key('shift-card'),
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Icon(Icons.schedule_rounded, size: 18, color: AppColors.primary),
            const SizedBox(width: 8),
            const Expanded(child: Text("Today's Shift", style: AppTextStyles.label)),
            if (state.isLoading) const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
          ]),
          const SizedBox(height: 12),
          if (overview == null && state.error != null)
            _LoadError(message: state.error!, onRetry: () => ref.read(guardShiftProvider.notifier).load())
          else if (overview == null)
            const Text('Loading your shift…', style: AppTextStyles.body2)
          else if (overview.featured == null)
            _NoShift(next: overview.next)
          else
            _ShiftBody(shift: overview.featured!, others: overview.today.where((s) => s.id != overview.featured!.id).toList(), next: overview.next),
        ]),
      ),
    );
  }
}

class _LoadError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _LoadError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Row(children: [
        Expanded(child: Text(message, style: AppTextStyles.body2.copyWith(color: AppColors.error))),
        TextButton(key: const Key('shift-retry'), onPressed: onRetry, child: const Text('Retry')),
      ]);
}

class _NoShift extends StatelessWidget {
  final GuardShift? next;
  const _NoShift({this.next});

  @override
  Widget build(BuildContext context) {
    final d = next == null ? null : DateText.parseDate(next!.date);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('No shift assigned today', key: Key('shift-none'), style: AppTextStyles.h3),
      const SizedBox(height: 4),
      Text(
        next == null
            ? 'The society office has not rostered you for today.'
            : 'Next: ${d == null ? next!.date : DateText.date(d)} · ${next!.shiftName} · ${next!.gateName} · ${DateText.range(next!.startTime, next!.endTime)}',
        style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary),
      ),
    ]);
  }
}

class _ShiftBody extends ConsumerWidget {
  final GuardShift shift;
  final List<GuardShift> others;
  final GuardShift? next;
  const _ShiftBody({required this.shift, required this.others, this.next});

  Color get _color => switch (shift.phase) {
        'active' => AppColors.secondary,
        'upcoming' => AppColors.info,
        'missed' => AppColors.error,
        _ => AppColors.textHint,
      };

  Future<void> _run(BuildContext context, Future<void> Function() action, String done) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      await action();
      messenger.showSnackBar(SnackBar(content: Text(done)));
    } catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final busy = ref.watch(guardShiftProvider.select((s) => s.busy));
    final notifier = ref.read(guardShiftProvider.notifier);
    final inAt = GuardShift.clockOf(shift.checkedInAt);
    final outAt = GuardShift.clockOf(shift.checkedOutAt);

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        const Icon(Icons.door_front_door_rounded, size: 18, color: AppColors.textSecondary),
        const SizedBox(width: 6),
        Expanded(child: Text(shift.gateName, key: const Key('shift-gate'), style: AppTextStyles.h3, overflow: TextOverflow.ellipsis)),
      ]),
      const SizedBox(height: 4),
      Text(shift.shiftName, key: const Key('shift-name'), style: AppTextStyles.body1.copyWith(fontWeight: FontWeight.w600)),
      const SizedBox(height: 2),
      Text(
        '${DateText.range(shift.startTime, shift.endTime)}${shift.overnight ? '  (overnight)' : ''}',
        key: const Key('shift-time'),
        style: AppTextStyles.body2,
      ),
      const SizedBox(height: 10),
      Row(children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(color: _color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
          child: Text('Status: ${shift.phaseLabel}', key: const Key('shift-status'), style: TextStyle(color: _color, fontSize: 12, fontWeight: FontWeight.w700)),
        ),
        if (shift.late) ...[
          const SizedBox(width: 8),
          const Text('Late start', style: TextStyle(color: AppColors.warning, fontSize: 12, fontWeight: FontWeight.w600)),
        ],
      ]),
      if (inAt != null || outAt != null) ...[
        const SizedBox(height: 6),
        Text(
          [if (inAt != null) 'Started ${DateText.clock(inAt)}', if (outAt != null) 'Ended ${DateText.clock(outAt)}'].join(' · '),
          style: AppTextStyles.caption,
        ),
      ],
      if (shift.canStart || shift.canEnd) ...[
        const SizedBox(height: 12),
        Row(children: [
          if (shift.canStart)
            Expanded(
              child: ElevatedButton.icon(
                key: const Key('shift-start'),
                onPressed: busy ? null : () => _run(context, notifier.start, 'Shift started.'),
                icon: const Icon(Icons.play_arrow_rounded),
                label: const Text('Start shift'),
                style: ElevatedButton.styleFrom(backgroundColor: AppColors.secondary, foregroundColor: Colors.white, minimumSize: const Size(0, 46)),
              ),
            ),
          if (shift.canEnd) ...[
            Expanded(
              child: OutlinedButton.icon(
                key: const Key('shift-end'),
                onPressed: busy ? null : () => _confirmEnd(context, notifier),
                icon: const Icon(Icons.stop_rounded),
                label: const Text('End shift'),
                style: OutlinedButton.styleFrom(minimumSize: const Size(0, 46)),
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              key: const Key('shift-handover'),
              tooltip: shift.hasHandover ? 'Handover notes saved' : 'Handover notes',
              onPressed: busy || shift.hasHandover ? null : () => _handover(context, notifier),
              icon: Icon(shift.hasHandover ? Icons.assignment_turned_in_rounded : Icons.edit_note_rounded),
            ),
          ],
        ]),
      ],
      if (others.isNotEmpty) ...[
        const Divider(height: 24),
        for (final o in others)
          Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Text('${o.shiftName} · ${o.gateName} · ${DateText.range(o.startTime, o.endTime)} (${o.phaseLabel})', style: AppTextStyles.caption),
          ),
      ],
      if (next != null && next!.id != shift.id) ...[
        const SizedBox(height: 8),
        Text('Next: ${_nextLine(next!)}', style: AppTextStyles.caption),
      ],
    ]);
  }

  static String _nextLine(GuardShift n) {
    final d = DateText.parseDate(n.date);
    return '${d == null ? n.date : DateText.dayMonth(d)} · ${n.shiftName} · ${n.gateName} · ${DateText.range(n.startTime, n.endTime)}';
  }

  Future<void> _confirmEnd(BuildContext context, GuardShiftNotifier notifier) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('End your shift?'),
        content: const Text('This records the time you finished. Save handover notes first if the next guard needs to know something.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Not yet')),
          TextButton(key: const Key('confirm-end'), onPressed: () => Navigator.pop(context, true), child: const Text('End shift')),
        ],
      ),
    );
    if (ok == true && context.mounted) await _run(context, notifier.end, 'Shift ended.');
  }

  Future<void> _handover(BuildContext context, GuardShiftNotifier notifier) async {
    final notes = await showDialog<_HandoverNotes>(context: context, builder: (_) => const _HandoverDialog());
    if (notes != null && context.mounted) {
      await _run(
        context,
        () => notifier.saveHandover(notes: notes.notes, pendingVisitors: notes.pendingVisitors, activePasses: notes.activePasses),
        'Handover notes saved.',
      );
    }
  }
}

class _HandoverNotes {
  final String notes;
  final String pendingVisitors;
  final String activePasses;
  const _HandoverNotes(this.notes, this.pendingVisitors, this.activePasses);
}

/// Notes for the guard coming on after you. Owns its controllers so they are
/// disposed with the dialog.
class _HandoverDialog extends StatefulWidget {
  const _HandoverDialog();

  @override
  State<_HandoverDialog> createState() => _HandoverDialogState();
}

class _HandoverDialogState extends State<_HandoverDialog> {
  final _notes = TextEditingController();
  final _visitors = TextEditingController();
  final _passes = TextEditingController();

  @override
  void dispose() {
    _notes.dispose();
    _visitors.dispose();
    _passes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Handover notes'),
      content: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(key: const Key('handover-notes'), controller: _notes, maxLines: 3, maxLength: 2000, decoration: const InputDecoration(labelText: 'Anything the next guard should know', border: OutlineInputBorder())),
          const SizedBox(height: 8),
          TextField(key: const Key('handover-visitors'), controller: _visitors, maxLines: 2, maxLength: 1000, decoration: const InputDecoration(labelText: 'Visitors still waiting', border: OutlineInputBorder())),
          const SizedBox(height: 8),
          TextField(key: const Key('handover-passes'), controller: _passes, maxLines: 2, maxLength: 1000, decoration: const InputDecoration(labelText: 'Passes to watch', border: OutlineInputBorder())),
        ]),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        TextButton(
          key: const Key('handover-save'),
          onPressed: () => Navigator.pop(context, _HandoverNotes(_notes.text.trim(), _visitors.text.trim(), _passes.text.trim())),
          child: const Text('Save'),
        ),
      ],
    );
  }
}
