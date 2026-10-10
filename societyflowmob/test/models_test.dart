import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/core/network/http_service.dart';
import 'package:societyflow/features/auth/models/user_model.dart';
import 'package:societyflow/features/events/models/event_model.dart';
import 'package:societyflow/features/facilities/models/facility_model.dart';
import 'package:societyflow/features/access_passes/models/access_pass_model.dart';
import 'package:societyflow/features/guard/providers/access_pass_provider.dart';
import 'package:societyflow/features/payments/models/payment_model.dart';
import 'package:societyflow/features/society_documents/models/society_document_model.dart';
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

  group('ScanOutcome', () {
    // Mirrors AccessPassApiController's {success, message, data:{person,
    // category, action}} shape - the same response the web gate's
    // scanResponse() sends, just parsed on the Flutter side here.
    test('parses a successful entry response', () {
      final outcome = ScanOutcome.fromResult(const ApiResult(
        success: true,
        statusCode: 200,
        message: 'Ramraj - entry allowed.',
        data: {
          'success': true,
          'message': 'Ramraj - entry allowed.',
          'data': {'person': 'Ramraj', 'category': 'Cleaner', 'action': 'entered'},
        },
      ));

      expect(outcome.success, isTrue);
      expect(outcome.person, 'Ramraj');
      expect(outcome.category, 'Cleaner');
      expect(outcome.message, 'Ramraj - entry allowed.');
    });

    test('parses a denied response (revoked pass) without throwing', () {
      final outcome = ScanOutcome.fromResult(const ApiResult(
        success: false,
        statusCode: 422,
        message: 'Access revoked.',
        data: {
          'success': false,
          'message': 'Access revoked.',
          'data': {'person': 'Ramraj'},
        },
      ));

      expect(outcome.success, isFalse);
      expect(outcome.message, 'Access revoked.');
      expect(outcome.person, 'Ramraj');
      expect(outcome.category, isNull);
    });

    test('survives a network-error result with no data at all', () {
      final outcome = ScanOutcome.fromResult(
        ApiResult.error('Cannot reach server.', statusCode: 0),
      );

      expect(outcome.success, isFalse);
      expect(outcome.person, isNull);
      expect(outcome.category, isNull);
      expect(outcome.message, 'Cannot reach server.');
    });
  });

  group('AccessPassModel', () {
    Map<String, dynamic> payload([Map<String, dynamic> overrides = const {}]) => {
          'id': 5,
          'person_name': 'Ramraj',
          'person_phone': '9876500001',
          'category': 'Cleaner',
          'pass_type': 'recurring_staff',
          'access_scope': 'property',
          'flat_id': 1,
          'property': '101',
          'allowed_days': [1, 2, 3, 4, 5],
          'start_time': '08:00:00',
          'end_time': '20:00:00',
          'valid_from': '2026-09-01',
          'valid_until': '2026-10-31',
          'status': 'active',
          'is_expired': false,
          'is_revoked': false,
          'is_blocked_today': false,
          'can_edit': true,
          'currently_inside': false,
          ...overrides,
        };

    test('parses the pass payload the API sends', () {
      final pass = AccessPassModel.fromJson(payload());

      expect(pass.id, 5);
      expect(pass.personName, 'Ramraj');
      expect(pass.allowedDays, [1, 2, 3, 4, 5]);
      expect(pass.isRecurring, isTrue);
      expect(pass.canEdit, isTrue);
      expect(pass.stateLabel, 'Active');
    });

    /// allowed_days comes back as ints from MySQL but as strings after a
    /// JSON-column round trip, and both must land as ints.
    test('normalises allowed_days sent as strings', () {
      final pass = AccessPassModel.fromJson(payload({'allowed_days': ['0', '6']}));

      expect(pass.allowedDays, [0, 6]);
    });

    test('an open-ended pass parses with a null valid_until', () {
      final pass = AccessPassModel.fromJson(payload({'valid_until': null}));

      expect(pass.validUntil, isNull);
      expect(pass.isExpired, isFalse);
    });

    test('state label prefers revoked over expired', () {
      final pass = AccessPassModel.fromJson(
        payload({'is_revoked': true, 'is_expired': true, 'can_edit': false}),
      );

      expect(pass.stateLabel, 'Revoked');
      expect(pass.canEdit, isFalse);
    });

    test('expired and blocked states are read from the server, not the clock', () {
      expect(AccessPassModel.fromJson(payload({'is_expired': true})).stateLabel, 'Expired');
      expect(
        AccessPassModel.fromJson(payload({'is_blocked_today': true})).stateLabel,
        'Blocked today',
      );
    });

    /// The token is a gate credential and must never appear in these payloads.
    test('carries no token field', () {
      final pass = AccessPassModel.fromJson(payload({'token': 'should-be-ignored'}));

      expect(pass.toString().contains('should-be-ignored'), isFalse);
    });

    test('survives a payload missing every optional field', () {
      final pass = AccessPassModel.fromJson({'id': 9, 'person_name': 'Minimal'});

      expect(pass.id, 9);
      expect(pass.allowedDays, isEmpty);
      expect(pass.category, isNull);
      expect(pass.validUntil, isNull);
      expect(pass.canEdit, isFalse);
    });
  });
  group('SocietyDocumentModel', () {
    // Mirrors SocietyDocumentApiController::format().
    test('parses the society-documents payload', () {
      final doc = SocietyDocumentModel.fromJson({
        'id': 7,
        'title': 'Parking Rules',
        'category': 'parking',
        'category_label': 'Parking',
        'description': 'Two cars per villa',
        'version': 'v2',
        'effective_from': '2026-11-01',
        'published_at': '2026-10-08T10:00:00+05:30',
        'file_size': 20480,
        'requires_acknowledgement': true,
        'acknowledged': false,
        'file_url': '/society-documents/7/file',
      });

      expect(doc.id, 7);
      expect(doc.categoryLabel, 'Parking');
      expect(doc.version, 'v2');
      expect(doc.effectiveFrom, DateTime(2026, 11, 1));
      expect(doc.publishedAt, isNotNull);
      expect(doc.filePath, '/society-documents/7/file');
      expect(doc.needsAcknowledgement, isTrue);
      expect(doc.copyWith(acknowledged: true).needsAcknowledgement, isFalse);
    });

    test('tolerates nulls and missing optional keys', () {
      final doc = SocietyDocumentModel.fromJson({'id': '3', 'title': null, 'effective_from': null, 'description': null});

      expect(doc.id, 3);
      expect(doc.title, '');
      expect(doc.description, '');
      expect(doc.categoryLabel, 'Other');
      expect(doc.effectiveFrom, isNull);
      expect(doc.requiresAcknowledgement, isFalse);
      expect(doc.needsAcknowledgement, isFalse);
    });
  });
}
