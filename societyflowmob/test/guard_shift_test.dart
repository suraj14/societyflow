import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/features/guard/models/guard_shift_model.dart';
import 'package:societyflow/features/guard/providers/guard_shift_provider.dart';
import 'package:societyflow/features/guard/screens/today_shift_card.dart';

/// The Guard app's "Today's Shift". The roster existed only on the web: a guard
/// opening the app saw nothing about their gate, shift or timing, and the home
/// screen said "On Duty" for every guard at every hour.

Map<String, dynamic> _json({
  String phase = 'active',
  String label = 'Active',
  bool canStart = false,
  bool canEnd = false,
  String? checkedIn,
  String? checkedOut,
  bool late = false,
  Map<String, dynamic>? handover,
  String name = 'Day Shift',
  String gate = 'Main Gate',
  String start = '09:00',
  String end = '18:00',
  int id = 1,
  String date = '2026-10-10',
}) =>
    {
      'id': id,
      'shift_name': name,
      'gate_name': gate,
      'date': date,
      'start_time': start,
      'end_time': end,
      'overnight': false,
      'status': 'scheduled',
      'phase': phase,
      'phase_label': label,
      'late': late,
      'checked_in_at': checkedIn,
      'checked_out_at': checkedOut,
      'can_start': canStart,
      'can_end': canEnd,
      'handover': handover,
    };

GuardShiftOverview _overview({Map<String, dynamic>? current, List<Map<String, dynamic>> today = const [], Map<String, dynamic>? next}) =>
    GuardShiftOverview.fromJson({'current': current, 'today': today, 'next': next, 'upcoming': next == null ? [] : [next]});

class _Fake extends GuardShiftNotifier {
  final List<String> calls = [];
  Object? failWith;
  GuardShiftOverview Function()? afterAction;

  _Fake({GuardShiftOverview? overview, String? error}) {
    state = GuardShiftState(overview: overview, error: error);
  }

  @override
  Future<void> load() async {}

  @override
  Future<void> start() async => _record('start');
  @override
  Future<void> end() async => _record('end');

  @override
  Future<void> saveHandover({String? notes, String? pendingVisitors, String? activePasses}) async =>
      _record('handover:$notes|$pendingVisitors|$activePasses');

  Future<void> _record(String c) async {
    calls.add(c);
    if (failWith != null) throw failWith!;
    final next = afterAction?.call();
    if (next != null) state = state.copyWith(overview: next);
  }
}

Widget _app(_Fake fake) => ProviderScope(
      overrides: [guardShiftProvider.overrideWith((ref) => fake)],
      child: const MaterialApp(home: Scaffold(body: SingleChildScrollView(child: TodayShiftCard()))),
    );

void main() {
  group('models', () {
    test('a shift parses and survives missing fields', () {
      final s = GuardShift.fromJson(_json(canStart: true, checkedIn: '2026-10-10T09:05:00+05:30'));
      expect(s.gateName, 'Main Gate');
      expect(s.canStart, isTrue);
      expect(s.onDuty, isTrue, reason: 'checked in and not out');
      expect(GuardShift.clockOf(s.checkedInAt), '09:05', reason: 'the society clock digits, not converted');

      final bare = GuardShift.fromJson({});
      expect(bare.shiftName, 'Shift');
      expect(bare.canStart, isFalse);
      expect(bare.onDuty, isFalse);
    });

    test('the featured shift is the current one, else the next today, else the last', () {
      final upcoming = _json(id: 2, phase: 'upcoming', label: 'Upcoming', start: '16:00', end: '23:00');
      final ended = _json(id: 1, phase: 'ended', label: 'Completed');

      expect(_overview(current: _json(id: 9), today: [ended, upcoming]).featured?.id, 9);
      expect(_overview(today: [ended, upcoming]).featured?.id, 2);
      expect(_overview(today: [ended]).featured?.id, 1);
      expect(_overview().featured, isNull);
    });

    test('the duty badge tells the truth', () {
      expect(_overview().dutyLabel, 'No shift today');
      expect(_overview(current: _json(canStart: true), today: [_json()]).dutyLabel, 'Not started');
      expect(_overview(current: _json(canEnd: true, checkedIn: '2026-10-10T09:05:00+05:30'), today: [_json()]).dutyLabel, 'On Duty');
      expect(_overview(today: [_json(phase: 'upcoming', label: 'Upcoming', start: '16:00')]).dutyLabel, 'Starts 4:00 PM');
      expect(_overview(today: [_json(phase: 'ended', label: 'Completed')]).dutyLabel, 'Off duty');
    });
  });

  group('TodayShiftCard', () {
    testWidgets('an active shift shows gate, shift, time and status, and can be started', (tester) async {
      final fake = _Fake(overview: _overview(current: _json(canStart: true), today: [_json(canStart: true)]));
      await tester.pumpWidget(_app(fake));

      expect(find.text("Today's Shift"), findsOneWidget);
      expect(find.text('Main Gate'), findsOneWidget);
      expect(find.text('Day Shift'), findsOneWidget);
      expect(find.text('9:00 AM - 6:00 PM'), findsOneWidget);
      expect(find.text('Status: Active'), findsOneWidget);
      expect(find.byKey(const Key('shift-end')), findsNothing);

      await tester.tap(find.byKey(const Key('shift-start')));
      await tester.pump();
      expect(fake.calls, ['start']);
    });

    testWidgets('after starting, the card shows the start time and offers End shift', (tester) async {
      final started = _json(canEnd: true, checkedIn: '2026-10-10T09:05:00+05:30');
      final fake = _Fake(overview: _overview(current: _json(canStart: true), today: [_json(canStart: true)]))
        ..afterAction = () => _overview(current: started, today: [started]);
      await tester.pumpWidget(_app(fake));

      await tester.tap(find.byKey(const Key('shift-start')));
      await tester.pumpAndSettle();

      expect(find.text('Started 9:05 AM'), findsOneWidget);
      expect(find.byKey(const Key('shift-start')), findsNothing);
      expect(find.byKey(const Key('shift-end')), findsOneWidget);
    });

    testWidgets('ending asks first, and "Not yet" ends nothing', (tester) async {
      final started = _json(canEnd: true, checkedIn: '2026-10-10T09:05:00+05:30');
      final fake = _Fake(overview: _overview(current: started, today: [started]));
      await tester.pumpWidget(_app(fake));

      await tester.tap(find.byKey(const Key('shift-end')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Not yet'));
      await tester.pumpAndSettle();
      expect(fake.calls, isEmpty);

      await tester.tap(find.byKey(const Key('shift-end')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('confirm-end')));
      await tester.pumpAndSettle();
      expect(fake.calls, ['end']);
      expect(find.text('Shift ended.'), findsOneWidget);
    });

    testWidgets('handover notes are sent as typed', (tester) async {
      final started = _json(canEnd: true, checkedIn: '2026-10-10T09:05:00+05:30');
      final fake = _Fake(overview: _overview(current: started, today: [started]));
      await tester.pumpWidget(_app(fake));

      await tester.tap(find.byKey(const Key('shift-handover')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('handover-notes')), 'Gate lock is stiff');
      await tester.enterText(find.byKey(const Key('handover-visitors')), '2 waiting');
      await tester.tap(find.byKey(const Key('handover-save')));
      await tester.pumpAndSettle();

      expect(fake.calls, ['handover:Gate lock is stiff|2 waiting|']);
      expect(find.text('Handover notes saved.'), findsOneWidget);
    });

    testWidgets('a refused action shows the server\'s reason', (tester) async {
      final fake = _Fake(overview: _overview(current: _json(canStart: true), today: [_json(canStart: true)]))
        ..failWith = const GuardShiftException('This shift has already been started.');
      await tester.pumpWidget(_app(fake));

      await tester.tap(find.byKey(const Key('shift-start')));
      await tester.pumpAndSettle();

      expect(find.text('This shift has already been started.'), findsOneWidget);
      expect(find.textContaining('Exception'), findsNothing);
    });

    testWidgets('an upcoming shift says so and has no Start button', (tester) async {
      final fake = _Fake(overview: _overview(today: [_json(phase: 'upcoming', label: 'Upcoming', start: '16:00', end: '23:00', name: 'Evening Shift')]));
      await tester.pumpWidget(_app(fake));

      expect(find.text('Evening Shift'), findsOneWidget);
      expect(find.text('Status: Upcoming'), findsOneWidget);
      expect(find.byKey(const Key('shift-start')), findsNothing);
    });

    testWidgets('no shift today says so plainly and names the next one', (tester) async {
      final fake = _Fake(overview: _overview(next: _json(date: '2026-10-12', name: 'Day Shift', gate: 'Back Gate')));
      await tester.pumpWidget(_app(fake));

      expect(find.byKey(const Key('shift-none')), findsOneWidget);
      expect(find.text('No shift assigned today'), findsOneWidget);
      expect(find.textContaining('Next: Mon, 12 Oct 2026 · Day Shift · Back Gate · 9:00 AM - 6:00 PM'), findsOneWidget);
    });

    testWidgets('no shift and nothing coming says the office has not rostered them', (tester) async {
      await tester.pumpWidget(_app(_Fake(overview: _overview())));
      expect(find.text('No shift assigned today'), findsOneWidget);
      expect(find.textContaining('has not rostered you'), findsOneWidget);
    });

    testWidgets('a failed first load says so and can be retried', (tester) async {
      await tester.pumpWidget(_app(_Fake(error: 'Cannot reach server.')));
      expect(find.text('Cannot reach server.'), findsOneWidget);
      expect(find.byKey(const Key('shift-retry')), findsOneWidget);
    });

    testWidgets('a late start is called out', (tester) async {
      final started = _json(canEnd: true, late: true, checkedIn: '2026-10-10T10:00:00+05:30');
      await tester.pumpWidget(_app(_Fake(overview: _overview(current: started, today: [started]))));
      expect(find.text('Late start'), findsOneWidget);
    });

    testWidgets('no overflow with long names on a small phone at large text', (tester) async {
      tester.view.physicalSize = const Size(640, 1136);
      tester.view.devicePixelRatio = 2.0;
      tester.platformDispatcher.textScaleFactorTestValue = 1.3;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
        tester.platformDispatcher.clearTextScaleFactorTestValue();
      });
      final s = _json(canEnd: true, checkedIn: '2026-10-10T09:05:00+05:30', name: 'Early Morning Perimeter Patrol Shift', gate: 'North East Service Entrance Gate');
      await tester.pumpWidget(_app(_Fake(overview: _overview(current: s, today: [s, _json(id: 2, phase: 'upcoming', label: 'Upcoming', name: 'Evening Patrol')]))));

      expect(tester.takeException(), isNull, reason: 'a RenderFlex overflow throws here');
    });
  });
}
