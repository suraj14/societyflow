import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/features/auth/models/user_model.dart';
import 'package:societyflow/features/events/models/event_model.dart';
import 'package:societyflow/features/facilities/models/facility_model.dart';
import 'package:societyflow/features/payments/models/payment_model.dart';
import 'package:societyflow/features/visitors/models/visitor_model.dart';

/// Parsing tests for the JSON the API actually sends.
///
/// This is where the backend/mobile contract broke: the events and
/// facilities endpoints were serialising null for title, date, venue,
/// charges and opening hours because the controllers read column names that
/// do not exist. The app parsed that JSON without complaint and rendered
/// empty rows, so nothing surfaced the problem on either side.
///
/// These fixtures mirror the corrected API responses. If a payload key is
/// renamed on the server, these fail rather than the app silently showing
/// blanks.
void main() {
  group('EventModel', () {
    test('parses a fully populated payload', () {
      final event = EventModel.fromJson({
        'id': 7,
        'title': 'Diwali Celebration',
        'description': 'Lights and food',
        'event_date': '2026-11-08',
        'start_time': '18:30',
        'end_time': '21:30',
        'venue': 'Central Lawn',
        'status': 'upcoming',
        'image': null,
        'created_at': '2026-09-08T10:00:00+00:00',
      });

      expect(event.id, 7);
      expect(event.title, 'Diwali Celebration');
      expect(event.venue, 'Central Lawn');
      expect(event.eventDate, '2026-11-08');
      expect(event.startTime, '18:30');
      expect(event.isUpcoming, isTrue);
    });

    test('survives a payload with nulls without throwing', () {
      final event = EventModel.fromJson({
        'id': 8,
        'title': null,
        'description': null,
        'event_date': null,
        'venue': null,
        'status': null,
        'created_at': '2026-09-08T10:00:00+00:00',
      });

      expect(event.title, '');
      expect(event.venue, isNull);
      expect(event.status, 'upcoming');
    });

    test('maps the status vocabulary the server sends', () {
      for (final entry in {
        'upcoming': 'Upcoming',
        'ongoing': 'Ongoing',
        'completed': 'Completed',
      }.entries) {
        final event = EventModel.fromJson({
          'id': 1,
          'title': 'x',
          'status': entry.key,
          'created_at': '2026-09-08T10:00:00+00:00',
        });
        expect(event.statusLabel, entry.value);
      }
    });
  });

  group('FacilityModel', () {
    test('parses charges and opening hours', () {
      final facility = FacilityModel.fromJson({
        'id': 3,
        'name': 'Clubhouse',
        'description': 'Party hall',
        'capacity': 80,
        'charges': 750.5,
        'image': null,
        'status': 'active',
        'open_time': '06:00',
        'close_time': '22:00',
      });

      expect(facility.name, 'Clubhouse');
      expect(facility.charges, 750.5);
      expect(facility.capacity, 80);
      expect(facility.openTime, '06:00');
      expect(facility.closeTime, '22:00');
    });

    test('defaults charges to zero when absent', () {
      final facility = FacilityModel.fromJson({
        'id': 4,
        'name': 'Garden',
        'status': 'active',
      });

      expect(facility.charges, 0);
      expect(facility.openTime, isNull);
    });

    test('accepts charges sent as a string', () {
      // Laravel serialises decimal casts as strings.
      final facility = FacilityModel.fromJson({
        'id': 5,
        'name': 'Pool',
        'charges': '250.00',
        'status': 'active',
      });

      expect(facility.charges, 250.0);
    });
  });

  group('PaymentModel / BillModel', () {
    test('parses a payment with a null date', () {
      final payment = PaymentModel.fromJson({
        'id': 1,
        'amount': 1500.0,
        'bill_type': 'Maintenance',
        'payment_method': 'cash',
        'status': 'pending',
        'payment_date': null,
        'due_date': '2026-09-30',
      });

      expect(payment.amount, 1500.0);
      expect(payment.paymentDate, isNull);
      expect(payment.dueDate, isNotNull);
    });

    test('parses bill amounts sent as strings', () {
      final bill = BillModel.fromJson({
        'id': 2,
        'bill_number': 'MB0012026000001',
        'total_amount': '2000.00',
        'paid_amount': '500.00',
        'balance_amount': '1500.00',
        'status': 'partial',
        'month': 'January',
        'year': 2026,
        'due_date': '2026-01-31',
        'bill_type': 'maintenance',
      });

      expect(bill.totalAmount, 2000.0);
      expect(bill.balanceAmount, 1500.0);
      expect(bill.isPaid, isFalse);
    });
  });

  group('UserModel', () {
    test('parses a villa owner payload', () {
      final user = UserModel.fromJson({
        'id': 11,
        'name': 'Asha Verma',
        'email': 'asha@example.com',
        'phone': '9876500000',
        'role': 'Villa Owner',
        'status': 'active',
        'villa_number': 'V-1',
        'villa_area': 'Palm Grove',
        'society_name': 'Green Acres',
        'is_owner': true,
        'is_tenant': false,
      });

      expect(user.name, 'Asha Verma');
      expect(user.role, 'Villa Owner');
      expect(user.isOwner, isTrue);
      expect(user.isTenant, isFalse);
    });
  });

  group('VisitorModel', () {
    test('parses every visitor type the API accepts', () {
      // The server validated 11 types while the column allowed 5; these are
      // the values the app can now receive.
      const types = [
        'guest', 'delivery', 'cab', 'service', 'maid', 'driver',
        'cook', 'electrician', 'plumber', 'emergency', 'other',
      ];

      for (final type in types) {
        final visitor = VisitorModel.fromJson({
          'id': 1,
          'visitor_name': 'Someone',
          'visitor_phone': '9876500000',
          'visitor_type': type,
          'approval_status': 'pending',
          'entry_status': 'pending',
          'created_at': '2026-09-08T10:00:00+00:00',
        });

        expect(visitor.visitorType, type);
      }
    });
  });
}
