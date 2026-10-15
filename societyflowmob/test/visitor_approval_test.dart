import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/features/auth/models/user_model.dart';
import 'package:societyflow/features/auth/providers/auth_provider.dart';
import 'package:societyflow/features/visitors/models/visitor_model.dart';
import 'package:societyflow/features/visitors/providers/visitors_provider.dart';
import 'package:societyflow/features/visitors/screens/visitors_screen.dart';

/// Gate approval in the apps. The server decides what each signed-in person may do (`can_decide`, `can_override`,
/// `can_check_in`, `can_check_out`); the app shows exactly that and never offers a button the server would refuse:
/// the guard records a visitor and waits, the resident (or an administrator) allows or denies, the guard checks in
/// only after approval, and an administrator changes a decision only with a reason.

Map<String, dynamic> _json({
  int id = 1,
  String approval = 'pending',
  String entry = 'pending',
  bool? canDecide,
  bool? canOverride,
  bool? canCheckIn,
  bool? canCheckOut,
  String? decidedBy,
  String? reason,
  bool escalated = false,
  String name = 'Ravi Courier',
}) =>
    {
      'id': id,
      'visitor_name': name,
      'visitor_phone': '9876543210',
      'visitor_type': 'delivery',
      'approval_status': approval,
      'entry_status': entry,
      'purpose': 'Parcel',
      'flat': {'flat_number': '101', 'building': {'name': 'Tower A'}},
      'expected_entry_time': '2026-10-15T10:00:00+05:30',
      'created_at': '2026-10-15T10:00:00+05:30',
      'requested_by': 'Gate Guard',
      'decided_by': decidedBy,
      'decided_at': decidedBy == null ? null : '2026-10-15T10:02:00+05:30',
      'rejection_reason': reason,
      'escalated': escalated,
      if (canDecide != null) 'can_decide': canDecide,
      if (canOverride != null) 'can_override': canOverride,
      if (canCheckIn != null) 'can_check_in': canCheckIn,
      if (canCheckOut != null) 'can_check_out': canCheckOut,
    };

class _Fake extends VisitorsNotifier {
  final List<String> calls = [];
  Object? failWith;

  _Fake(List<Map<String, dynamic>> visitors) {
    final list = visitors.map(VisitorModel.fromJson).toList();
    state = VisitorsState(visitors: list, allVisitors: list);
  }

  @override
  Future<void> loadAll() async {}
  @override
  Future<void> loadVisitors() async {}

  Future<void> _record(String call) async {
    calls.add(call);
    if (failWith != null) throw failWith!;
  }

  @override
  Future<void> allowVisitor(int id) => _record('allow:$id');
  @override
  Future<void> denyVisitor(int id, {String? reason}) => _record('deny:$id:${reason ?? ''}');
  @override
  Future<void> overrideVisitor(int id, {required bool allow, required String reason}) => _record('override:$id:${allow ? 'allow' : 'deny'}:$reason');
  @override
  Future<void> checkIn(int id) => _record('checkin:$id');
  @override
  Future<void> checkOut(int id) => _record('checkout:$id');
}

class _FakeAuth extends AuthNotifier {
  _FakeAuth(String role) {
    state = AuthState(user: UserModel.fromJson({'id': 1, 'name': 'Tara', 'email': 't@x.in', 'role': role, 'society_name': 'Royal Eden'}));
  }
}

Widget _app(_Fake fake, String role) => ProviderScope(
      overrides: [
        visitorsProvider.overrideWith((ref) => fake),
        authProvider.overrideWith((ref) => _FakeAuth(role)),
      ],
      child: const MaterialApp(home: VisitorsScreen()),
    );

// the dialog's own field (the screen has a search box too)
final _dialogField = find.descendant(of: find.byType(AlertDialog), matching: find.byType(TextField));

void main() {
  group('VisitorModel', () {
    test('derives the five states the apps show from the two stored ones', () {
      expect(VisitorModel.fromJson(_json()).status, 'pending_approval');
      expect(VisitorModel.fromJson(_json(approval: 'allowed')).status, 'approved');
      expect(VisitorModel.fromJson(_json(approval: 'denied')).status, 'rejected');
      expect(VisitorModel.fromJson(_json(approval: 'allowed', entry: 'entered')).status, 'checked_in');
      expect(VisitorModel.fromJson(_json(approval: 'allowed', entry: 'exited')).status, 'checked_out');
      expect(VisitorModel.fromJson(_json()).statusLabel, 'Pending approval');
      expect(VisitorModel.fromJson(_json(escalated: true)).statusLabel, 'No answer yet');
    });

    test('what the server says the person may do wins over a guess from the status', () {
      final guardView = VisitorModel.fromJson(_json(canDecide: false, canCheckIn: false));
      expect(guardView.isPending, isTrue);
      expect(guardView.canDecide, isFalse, reason: 'the guard records, never decides');

      final ownerView = VisitorModel.fromJson(_json(canDecide: true, canCheckIn: false));
      expect(ownerView.canDecide, isTrue);

      final gateAfterApproval = VisitorModel.fromJson(_json(approval: 'allowed', canCheckIn: true, canDecide: false));
      expect(gateAfterApproval.canCheckIn, isTrue);
      expect(gateAfterApproval.canDecide, isFalse);
    });

    test('a payload without the flags (an older server) falls back to the status', () {
      final v = VisitorModel.fromJson(_json());
      expect(v.canDecide, isTrue);
      expect(v.canOverride, isFalse);
      expect(VisitorModel.fromJson(_json(approval: 'allowed')).canCheckIn, isTrue);
      expect(VisitorModel.fromJson(_json(approval: 'allowed', entry: 'entered')).canCheckOut, isTrue);
    });

    test('decision details are parsed and missing ones are harmless', () {
      final v = VisitorModel.fromJson(_json(approval: 'denied', decidedBy: 'Asha Owner', reason: 'Not expecting anyone'));
      expect(v.decidedBy, 'Asha Owner');
      expect(v.decidedAt, isNotNull);
      expect(v.rejectionReason, 'Not expecting anyone');
      expect(v.requestedBy, 'Gate Guard');

      final bare = VisitorModel.fromJson({'id': 2, 'visitor_name': 'x', 'visitor_phone': '1', 'visitor_type': 'guest', 'approval_status': 'pending', 'entry_status': 'pending'});
      expect(bare.decidedBy, isNull);
      expect(bare.escalated, isFalse);
    });
  });

  group('the gate (Guard)', () {
    testWidgets('a waiting visitor has no Allow or Deny, only a clear note not to let them in', (tester) async {
      final fake = _Fake([_json(canDecide: false, canCheckIn: false, canCheckOut: false)]);
      await tester.pumpWidget(_app(fake, 'Guard'));
      await tester.pump();

      expect(find.text('Ravi Courier'), findsOneWidget);
      expect(find.textContaining('Waiting for the resident to approve'), findsOneWidget);
      expect(find.text('Allow'), findsNothing);
      expect(find.text('Deny'), findsNothing);
      expect(find.text('Check-In'), findsNothing);
    });

    testWidgets('after approval the guard can check in, and only then', (tester) async {
      final fake = _Fake([_json(approval: 'allowed', canDecide: false, canCheckIn: true, canCheckOut: false, decidedBy: 'Asha Owner')]);
      await tester.pumpWidget(_app(fake, 'Guard'));
      await tester.pump();

      expect(find.text('Check-In'), findsOneWidget);
      await tester.tap(find.text('Check-In'));
      await tester.pump();
      expect(fake.calls, ['checkin:1']);
    });

    testWidgets('a rejected visitor shows the reason and no way in', (tester) async {
      final fake = _Fake([_json(approval: 'denied', canDecide: false, canCheckIn: false, decidedBy: 'Asha Owner', reason: 'Not expecting anyone')]);
      await tester.pumpWidget(_app(fake, 'Guard'));
      await tester.pump();

      expect(find.textContaining('Rejected: Not expecting anyone'), findsOneWidget);
      expect(find.text('Check-In'), findsNothing);
    });

    testWidgets('an escalated visitor says no answer yet, and still no way in', (tester) async {
      final fake = _Fake([_json(canDecide: false, canCheckIn: false, escalated: true)]);
      await tester.pumpWidget(_app(fake, 'Guard'));
      await tester.pump();

      expect(find.textContaining('No answer yet'), findsOneWidget);
      expect(find.text('Check-In'), findsNothing);
    });
  });

  group('the resident', () {
    testWidgets('Allow asks first and sends nothing until confirmed', (tester) async {
      final fake = _Fake([_json(canDecide: true, canCheckIn: false)]);
      await tester.pumpWidget(_app(fake, 'Apartment Owner'));
      await tester.pump();

      await tester.tap(find.text('Allow'));
      await tester.pumpAndSettle();
      expect(find.text('Allow Ravi Courier?'), findsOneWidget);
      expect(fake.calls, isEmpty, reason: 'nothing sent before the confirmation');

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(fake.calls, isEmpty);

      await tester.tap(find.text('Allow'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Allow entry'));
      await tester.pumpAndSettle();
      expect(fake.calls, ['allow:1']);
    });

    testWidgets('Deny takes an optional reason from a resident', (tester) async {
      final fake = _Fake([_json(canDecide: true)]);
      await tester.pumpWidget(_app(fake, 'Tenant'));
      await tester.pump();

      await tester.tap(find.text('Deny'));
      await tester.pumpAndSettle();
      expect(find.text('Reason (optional)'), findsOneWidget);
      await tester.enterText(_dialogField, 'Not expecting anyone');
      await tester.tap(find.text('Deny entry'));
      await tester.pumpAndSettle();

      expect(fake.calls, ['deny:1:Not expecting anyone']);
    });

    testWidgets('a decision made elsewhere first shows the server\'s message, not a success', (tester) async {
      final fake = _Fake([_json(canDecide: true)])..failWith = const VisitorActionException('This visitor was already allowed by Asha Spouse at 15 Oct, 10:02 AM.', 409);
      await tester.pumpWidget(_app(fake, 'Apartment Owner'));
      await tester.pump();

      await tester.tap(find.text('Allow'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Allow entry'));
      await tester.pumpAndSettle();

      expect(find.textContaining('already allowed by Asha Spouse'), findsOneWidget);
      expect(find.textContaining('Visitor allowed'), findsNothing);
    });
  });

  group('the administrator', () {
    testWidgets('can change a resident\'s rejection only with a reason, which is sent with it', (tester) async {
      final fake = _Fake([_json(approval: 'denied', canDecide: false, canOverride: true, decidedBy: 'Asha Owner', reason: 'Do not know him')]);
      await tester.pumpWidget(_app(fake, 'Admin'));
      await tester.pump();

      await tester.tap(find.text('Ravi Courier'));
      await tester.pumpAndSettle();
      expect(find.text('Change decision (administrator)'), findsOneWidget);
      await tester.tap(find.text('Change decision (administrator)'));
      await tester.pumpAndSettle();

      // no reason: refused in the dialog, nothing sent
      await tester.tap(find.text('Change decision'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Enter a reason'), findsOneWidget);
      expect(fake.calls, isEmpty);

      await tester.enterText(_dialogField, 'Owner confirmed by phone');
      await tester.tap(find.text('Change decision'));
      await tester.pumpAndSettle();
      expect(fake.calls, ['override:1:allow:Owner confirmed by phone']);
    });

    testWidgets('the guard and a resident are never offered the override', (tester) async {
      for (final role in ['Guard', 'Tenant']) {
        final fake = _Fake([_json(approval: 'denied', canDecide: false, canOverride: false, decidedBy: 'Asha Owner')]);
        await tester.pumpWidget(_app(fake, role));
        await tester.pump();
        await tester.tap(find.text('Ravi Courier'));
        await tester.pumpAndSettle();

        expect(find.text('Change decision (administrator)'), findsNothing, reason: role);
        await tester.pumpWidget(const SizedBox());
      }
    });
  });
}
