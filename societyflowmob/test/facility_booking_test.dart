import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/core/utils/date_text.dart';
import 'package:societyflow/features/facilities/models/facility_model.dart';
import 'package:societyflow/features/facilities/providers/facilities_provider.dart';
import 'package:societyflow/features/facilities/screens/facilities_screen.dart';
import 'package:societyflow/features/facilities/screens/facility_booking_screen.dart';

/// Mobile facility booking. The old sheet offered a hard-coded 06:00-22:00
/// grid for every facility, never showed what was taken, ignored the facility's
/// own days/hours, showed "₹/hr" for a flat charge, and printed
/// "Exception: ..." for every server message.

// Saturday 10 Oct 2026; the facility is closed on Sundays.
final _today = DateTime(2026, 10, 10);

const _facility = FacilityModel(
  id: 7,
  name: 'Clubhouse',
  charges: 500,
  status: 'active',
  openTime: '08:00',
  closeTime: '20:00',
  availableDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
  advanceBookingDays: 14,
  maxBookingHours: 3,
  requiresApproval: true,
);

FacilityAvailability _day({List<String> booked = const [], bool bookable = true, String? message}) {
  final slots = <FacilitySlot>[];
  for (var h = 8; h < 20; h++) {
    final start = DateText.hhmm(h * 60);
    final isBooked = booked.contains(start);
    // From this hour, how far can a booking run before the next taken hour?
    var run = 0;
    while (!isBooked && run < 3 && h + run < 20 && !booked.contains(DateText.hhmm((h + run) * 60))) {
      run++;
    }
    slots.add(FacilitySlot(
      start: start,
      end: DateText.hhmm((h + 1) * 60),
      available: !isBooked && bookable,
      maxHours: bookable ? run : 0,
      reason: isBooked ? 'booked' : null,
    ));
  }
  return FacilityAvailability(
    date: '2026-10-10',
    open: '08:00',
    close: '20:00',
    maxHours: 3,
    charge: 500,
    requiresApproval: true,
    bookable: bookable,
    message: message,
    slots: slots,
  );
}

FacilityBookingModel _booking({int id = 1, String status = 'pending', int daysFromNow = 5, bool canCancel = true}) {
  final d = DateTime.now().add(Duration(days: daysFromNow));
  return FacilityBookingModel(
    id: id,
    bookingNumber: 'BKG-0001',
    facilityName: 'Clubhouse',
    bookingDate: DateText.iso(d),
    startTime: '10:00',
    endTime: '12:00',
    amount: 500,
    status: status,
    canCancel: canCancel,
    createdAt: DateTime.now(),
  );
}

class _Fake extends FacilitiesNotifier {
  final List<String> asked = [];
  final List<Map<String, Object?>> booked = [];
  final List<(int, String?)> cancelled = [];
  FacilityAvailability Function(String date) availabilityFor;
  Object? bookError;

  _Fake({
    List<FacilityModel> facilities = const [],
    List<FacilityBookingModel> bookings = const [],
    FacilityAvailability Function(String)? availabilityFor,
  }) : availabilityFor = availabilityFor ?? ((_) => _day()) {
    state = FacilitiesState(facilities: facilities, bookings: bookings);
  }

  @override
  Future<void> loadFacilities() async {}
  @override
  Future<void> loadBookings() async {}

  @override
  Future<FacilityAvailability> availability({required int facilityId, required String date}) async {
    asked.add(date);
    return availabilityFor(date);
  }

  @override
  Future<FacilityBookingModel> bookFacility({
    required int facilityId,
    required String bookingDate,
    required String startTime,
    required String endTime,
    String? purpose,
    int? expectedGuests,
  }) async {
    booked.add({'date': bookingDate, 'start': startTime, 'end': endTime, 'purpose': purpose, 'guests': expectedGuests});
    if (bookError != null) throw bookError!;
    return FacilityBookingModel(
      id: 99,
      bookingNumber: 'BKG-0099',
      facilityName: 'Clubhouse',
      bookingDate: bookingDate,
      startTime: startTime.substring(0, 5),
      endTime: endTime.substring(0, 5),
      amount: 500,
      status: 'pending',
      createdAt: DateTime.now(),
    );
  }

  @override
  Future<void> cancelBooking(int id, {String? reason}) async {
    cancelled.add((id, reason));
    state = state.copyWith(
      bookings: state.bookings.map((b) => b.id == id ? b.copyWith(status: 'cancelled', canCancel: false) : b).toList(),
    );
  }
}

Widget _app(Widget home, _Fake fake) => ProviderScope(
      overrides: [facilitiesProvider.overrideWith((ref) => fake)],
      child: MaterialApp(home: home),
    );

/// [small] is an iPhone SE-class 320x568; otherwise a typical 360x800 Android.
Future<void> _phone(WidgetTester tester, {double keyboard = 0, double textScale = 1, bool small = false}) async {
  tester.view.physicalSize = small ? const Size(640, 1136) : const Size(1080, 2400);
  tester.view.devicePixelRatio = small ? 2.0 : 3.0;
  tester.view.viewInsets = FakeViewPadding(bottom: keyboard * (small ? 2.0 : 3.0));
  tester.platformDispatcher.textScaleFactorTestValue = textScale;
  addTearDown(() {
    tester.view.resetPhysicalSize();
    tester.view.resetDevicePixelRatio();
    tester.view.resetViewInsets();
    tester.platformDispatcher.clearTextScaleFactorTestValue();
  });
}

/// A widget test on a typical phone-sized surface (the default 800x600 puts
/// half the booking screen off-screen).
void phoneTest(String name, Future<void> Function(WidgetTester tester) body) => testWidgets(name, (tester) async {
      await _phone(tester);
      await body(tester);
    });

void main() {
  group('models', () {
    test('a facility parses its rules and tolerates missing ones', () {
      final f = FacilityModel.fromJson({
        'id': 3,
        'name': 'Pool',
        'charges': '250.50',
        'open_time': '06:00',
        'close_time': '20:00',
        'available_days': ['Monday', 'SATURDAY'],
        'advance_booking_days': 7,
        'max_booking_hours': 2,
        'requires_approval': false,
      });
      expect(f.charges, 250.5);
      expect(f.availableDays, ['monday', 'saturday']);
      expect(f.requiresApproval, isFalse);
      expect(f.priceLabel, '₹250.50 per booking');
      expect(f.hoursLabel, '6:00 AM - 8:00 PM');
      expect(f.opensOn(DateTime(2026, 10, 10)), isTrue, reason: 'a Saturday');
      expect(f.opensOn(DateTime(2026, 10, 11)), isFalse, reason: 'a Sunday');

      final bare = FacilityModel.fromJson({'id': 4, 'name': 'Hall'});
      expect(bare.priceLabel, 'Free');
      expect(bare.availableDays, isEmpty);
      expect(bare.opensOn(DateTime(2026, 10, 11)), isTrue, reason: 'no day list means every day');
      expect(bare.requiresApproval, isTrue);
    });

    test('a booking is upcoming until it ends, and only while it is alive', () {
      final now = DateTime(2026, 10, 10, 11, 0);
      FacilityBookingModel at(String date, String status, {String start = '10:00', String end = '12:00'}) =>
          FacilityBookingModel(id: 1, bookingDate: date, startTime: start, endTime: end, status: status, createdAt: now);

      expect(at('2026-10-10', 'approved').isUpcoming(now), isTrue, reason: 'under way');
      expect(at('2026-10-10', 'approved', start: '08:00', end: '09:00').isUpcoming(now), isFalse, reason: 'ended this morning');
      expect(at('2026-10-12', 'pending').isUpcoming(now), isTrue);
      expect(at('2026-10-12', 'cancelled').isUpcoming(now), isFalse);
      expect(at('2026-10-12', 'rejected').isUpcoming(now), isFalse);
      expect(at('2026-10-09', 'pending').isUpcoming(now), isFalse);
    });

    test('server times are the plain clock the API now sends', () {
      final b = FacilityBookingModel.fromJson({
        'id': 5,
        'facility': {'id': 1, 'name': 'Pool'},
        'booking_date': '2026-10-12',
        'start_time': '09:30',
        'end_time': '10:30',
        'status': 'approved',
        'created_at': '2026-10-10T04:30:00+00:00',
        'amount': 120,
        'can_cancel': true,
      });
      expect(b.facilityName, 'Pool');
      expect(DateText.range(b.startTime, b.endTime), '9:30 AM - 10:30 AM');
      expect(b.canCancel, isTrue);
    });

    test('availability parses slots and survives gaps', () {
      final a = FacilityAvailability.fromJson({
        'date': '2026-10-10',
        'bookable': false,
        'message': 'Closed',
        'slots': [
          {'start': '08:00', 'end': '09:00', 'available': true, 'max_hours': 2},
          'junk',
        ],
      });
      expect(a.bookable, isFalse);
      expect(a.slots, hasLength(1));
      expect(a.hasFreeSlot, isTrue);
    });
  });

  group('FacilityBookingScreen', () {
    phoneTest('shows the day from the server, with taken hours greyed and not tappable', (tester) async {
      final fake = _Fake(availabilityFor: (_) => _day(booked: ['10:00', '11:00']));
      await tester.pumpWidget(_app(FacilityBookingScreen(facility: _facility, today: _today), fake));
      await tester.pumpAndSettle();

      expect(fake.asked, ['2026-10-10'], reason: 'the first open day is loaded on open');
      expect(find.text('Open 8:00 AM - 8:00 PM'), findsOneWidget);
      expect(find.text('₹500 per booking'), findsOneWidget);
      expect(find.byKey(const Key('slot-09:00')), findsOneWidget);
      expect(find.byKey(const Key('slot-10:00')), findsOneWidget);

      await tester.ensureVisible(find.byKey(const Key('slot-10:00')));
      await tester.tap(find.byKey(const Key('slot-10:00')));
      await tester.pump();
      expect(find.byKey(const Key('booking-summary')), findsNothing, reason: 'a taken hour cannot be chosen');

      // Confirm stays disabled until a start time is chosen.
      expect(tester.widget<ElevatedButton>(find.byType(ElevatedButton).last).onPressed, isNull);
    });

    phoneTest('a Sunday is closed for this facility and cannot be chosen', (tester) async {
      final fake = _Fake();
      await tester.pumpWidget(_app(FacilityBookingScreen(facility: _facility, today: _today), fake));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('date-2026-10-11')));
      await tester.pumpAndSettle();
      expect(fake.asked, ['2026-10-10'], reason: 'no request was made for the closed day');
      expect(find.text('Closed'), findsWidgets);

      await tester.tap(find.byKey(const Key('date-2026-10-12')));
      await tester.pumpAndSettle();
      expect(fake.asked, ['2026-10-10', '2026-10-12']);
    });

    phoneTest('choosing a start and a length books exactly that range and confirms it', (tester) async {
      final fake = _Fake(availabilityFor: (_) => _day(booked: ['14:00']));
      FacilityBookingModel? result;
      await tester.pumpWidget(_app(
        Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: ElevatedButton(
                onPressed: () async => result = await Navigator.of(context).push<FacilityBookingModel>(
                  MaterialPageRoute(builder: (_) => FacilityBookingScreen(facility: _facility, today: _today)),
                ),
                child: const Text('open'),
              ),
            ),
          ),
        ),
        fake,
      ));
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.byKey(const Key('slot-10:00')));
      await tester.tap(find.byKey(const Key('slot-10:00')));
      await tester.pump();
      expect(find.byKey(const Key('duration-3')), findsOneWidget, reason: 'up to the facility maximum');
      expect(find.byKey(const Key('duration-4')), findsNothing);
      await tester.ensureVisible(find.byKey(const Key('duration-2')));
      await tester.tap(find.byKey(const Key('duration-2')));
      await tester.pump();
      expect(find.text('10:00 AM - 12:00 PM'), findsOneWidget);
      expect(find.text('Amount: ₹500'), findsOneWidget);

      await tester.ensureVisible(find.byKey(const Key('booking-purpose')));
      await tester.enterText(find.byKey(const Key('booking-purpose')), 'Birthday');
      await tester.ensureVisible(find.byKey(const Key('booking-guests')));
      await tester.enterText(find.byKey(const Key('booking-guests')), '25');
      await tester.ensureVisible(find.text('Request booking'));
      await tester.tap(find.text('Request booking'));
      await tester.pumpAndSettle();

      expect(fake.booked, [
        {'date': '2026-10-10', 'start': '10:00:00', 'end': '12:00:00', 'purpose': 'Birthday', 'guests': 25},
      ]);
      expect(find.byKey(const Key('booking-confirmation')), findsOneWidget);
      expect(find.text('Request sent'), findsOneWidget);
      expect(find.text('Booking no. BKG-0099'), findsOneWidget);

      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      expect(result?.id, 99, reason: 'the new booking is handed back so My Bookings can show it');
    });

    phoneTest("a slot taken meanwhile shows the server's words, not \"Exception:\", and refreshes the day", (tester) async {
      final fake = _Fake()..bookError = const FacilityException('This time slot is already booked. Please choose a different time.');
      await tester.pumpWidget(_app(FacilityBookingScreen(facility: _facility, today: _today), fake));
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.byKey(const Key('slot-09:00')));
      await tester.tap(find.byKey(const Key('slot-09:00')));
      await tester.pump();
      await tester.ensureVisible(find.text('Request booking'));
      await tester.tap(find.text('Request booking'));
      await tester.pumpAndSettle();

      expect(find.text('This time slot is already booked. Please choose a different time.'), findsOneWidget);
      expect(find.textContaining('Exception'), findsNothing);
      expect(fake.asked, ['2026-10-10', '2026-10-10'], reason: 'the day was reloaded to show what is taken now');
    });

    phoneTest('a day the server says is not bookable explains why instead of showing times', (tester) async {
      final fake = _Fake(availabilityFor: (_) => _day(bookable: false, message: 'Clubhouse can be booked up to 14 days in advance.'));
      await tester.pumpWidget(_app(FacilityBookingScreen(facility: _facility, today: _today), fake));
      await tester.pumpAndSettle();

      expect(find.text('Clubhouse can be booked up to 14 days in advance.'), findsOneWidget);
      expect(find.byKey(const Key('slot-09:00')), findsNothing);
    });

    phoneTest('a failed load says so and can be retried', (tester) async {
      var calls = 0;
      final fake = _Fake(availabilityFor: (_) {
        calls++;
        if (calls == 1) throw const FacilityException('Cannot reach server.');
        return _day();
      });
      await tester.pumpWidget(_app(FacilityBookingScreen(facility: _facility, today: _today), fake));
      await tester.pumpAndSettle();
      expect(find.text('Cannot reach server.'), findsOneWidget);

      await tester.tap(find.text('Retry'));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('slot-09:00')), findsOneWidget);
    });

    testWidgets('no overflow on a small phone with the keyboard open and large text', (tester) async {
      await _phone(tester, keyboard: 300, textScale: 1.3, small: true);
      final fake = _Fake();
      await tester.pumpWidget(_app(FacilityBookingScreen(facility: _facility, today: _today), fake));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('slot-09:00')));
      await tester.tap(find.byKey(const Key('slot-09:00')));
      await tester.pump();

      expect(tester.takeException(), isNull, reason: 'a RenderFlex overflow throws here');
    });
  });

  group('FacilitiesScreen', () {
    phoneTest('lists facilities with a flat price, hours and a Book button', (tester) async {
      await tester.pumpWidget(_app(const FacilitiesScreen(), _Fake(facilities: const [_facility])));
      await tester.pump();

      expect(find.text('Clubhouse'), findsOneWidget);
      expect(find.text('₹500 per booking'), findsOneWidget);
      expect(find.textContaining('/hr'), findsNothing, reason: 'the charge is per booking');
      expect(find.text('8:00 AM - 8:00 PM'), findsOneWidget);
      expect(find.text('Needs approval'), findsOneWidget);
      expect(find.byKey(const Key('book-7')), findsOneWidget);
    });

    phoneTest('a society with no facilities explains it instead of showing a blank list', (tester) async {
      await tester.pumpWidget(_app(const FacilitiesScreen(), _Fake()));
      await tester.pump();
      expect(find.text('No facilities yet'), findsOneWidget);
      expect(find.textContaining('Please ask the society office'), findsOneWidget);
    });

    phoneTest('My Bookings splits upcoming from past and cancels with a reason', (tester) async {
      final fake = _Fake(bookings: [
        _booking(id: 1, status: 'approved', daysFromNow: 3),
        _booking(id: 2, status: 'cancelled', daysFromNow: 2, canCancel: false),
        _booking(id: 3, status: 'approved', daysFromNow: -4, canCancel: false),
      ]);
      await tester.pumpWidget(_app(const FacilitiesScreen(), fake));
      await tester.pump();
      await tester.tap(find.text('My Bookings'));
      await tester.pumpAndSettle();

      expect(find.text('UPCOMING'), findsOneWidget);
      expect(find.text('PAST & CANCELLED'), findsOneWidget);
      expect(find.byKey(const Key('cancel-1')), findsOneWidget);
      expect(find.byKey(const Key('cancel-2')), findsNothing, reason: 'already cancelled');
      expect(find.byKey(const Key('cancel-3')), findsNothing, reason: 'already happened');
      expect(find.text('BKG-0001 · ₹500'), findsWidgets);

      await tester.tap(find.byKey(const Key('cancel-1')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('cancel-reason')), 'Plans changed');
      await tester.tap(find.byKey(const Key('confirm-cancel')));
      await tester.pumpAndSettle();

      expect(fake.cancelled, [(1, 'Plans changed')]);
      expect(find.text('Booking cancelled.'), findsOneWidget);
      expect(find.byKey(const Key('cancel-1')), findsNothing);
    });

    phoneTest('keeping the booking cancels nothing', (tester) async {
      final fake = _Fake(bookings: [_booking(id: 1, status: 'pending')]);
      await tester.pumpWidget(_app(const FacilitiesScreen(), fake));
      await tester.pump();
      await tester.tap(find.text('My Bookings'));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('cancel-1')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Keep booking'));
      await tester.pumpAndSettle();

      expect(fake.cancelled, isEmpty);
    });

    phoneTest('no bookings yet says how to make one', (tester) async {
      await tester.pumpWidget(_app(const FacilitiesScreen(), _Fake(facilities: const [_facility])));
      await tester.pump();
      await tester.tap(find.text('My Bookings'));
      await tester.pumpAndSettle();
      expect(find.text('No bookings yet'), findsOneWidget);
    });
  });
}
