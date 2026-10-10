import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:societyflow/core/utils/contact_launcher.dart';
import 'package:societyflow/features/auth/models/user_model.dart';
import 'package:societyflow/features/auth/providers/auth_provider.dart';
import 'package:societyflow/features/auth/screens/otp_verify_screen.dart';
import 'package:societyflow/features/services/models/service_model.dart';
import 'package:societyflow/features/services/providers/services_provider.dart';
import 'package:societyflow/features/services/screens/services_screen.dart';
import 'package:societyflow/features/support/models/society_contacts_model.dart';
import 'package:societyflow/features/support/providers/society_contacts_provider.dart';
import 'package:societyflow/features/support/screens/about_screen.dart';
import 'package:societyflow/features/support/screens/emergency_contacts_sheet.dart';
import 'package:societyflow/features/support/screens/help_support_screen.dart';

/// Regression tests for the mobile QA pass: the OTP screen and Emergency
/// Contacts sheet overflowed on a real phone, Help/About were dead menu
/// items, and Services mixed "directory" and "request" into one confusing
/// form.

// ── fakes ───────────────────────────────────────────────────────────────────

class _FakeAuth extends AuthNotifier {
  _FakeAuth(String role) {
    state = AuthState(user: UserModel.fromJson({'id': 1, 'name': 'Tara', 'email': 't@x.in', 'role': role, 'society_name': 'Royal Eden'}));
  }
  int verifyCalls = 0;
  @override
  Future<void> verifyOtp({required String phone, required String otp}) async {
    verifyCalls++;
    throw Exception('Invalid OTP. 4 attempts left.');
  }

  @override
  Future<void> sendOtp({required String phone}) async {}
}

class _FakeServices extends ServicesNotifier {
  @override
  Future<void> loadServices() async {
    state = ServicesState(
      services: const [
        ServiceModel(id: 1, name: 'Maid', charges: 0, status: 'active'),
        ServiceModel(id: 2, name: 'Cook', charges: 0, status: 'active'),
      ],
      providers: [
        ServiceProviderModel.fromJson({'id': 10, 'service_id': 1, 'name': 'Sunita', 'phone': '9876543210', 'availability': '7am-11am', 'is_daily_help': true, 'charges': 3000, 'price_type': 'monthly', 'rating': 0, 'status': 'active'}),
        ServiceProviderModel.fromJson({'id': 11, 'service_id': 1, 'name': 'Meena', 'phone': null, 'rating': 0, 'status': 'active'}),
      ],
    );
  }
}

SocietyContactsModel _contacts({int managers = 2}) => SocietyContactsModel.fromJson({
      'society': {'name': 'Royal Eden', 'phone': '04012345678', 'email': 'office@royaleden.in', 'address': 'Gachibowli, Hyderabad'},
      'managers': [for (var i = 0; i < managers; i++) {'name': 'Manager $i', 'role': 'Society Manager', 'phone': '98480000$i$i', 'email': null}],
      'emergency': [
        {'label': 'Emergency (all services)', 'phone': '112', 'kind': 'emergency'},
        {'label': 'Police', 'phone': '100', 'kind': 'police'},
        {'label': 'Ambulance', 'phone': '108', 'kind': 'ambulance'},
        {'label': 'Fire', 'phone': '101', 'kind': 'fire'},
      ],
    });

/// A small phone (iPhone SE-size logical screen) with the keyboard open and
/// a large system font - the conditions that produced the overflow stripes.
Future<void> _smallPhone(WidgetTester tester, {double keyboard = 0, double textScale = 1.0}) async {
  tester.view.physicalSize = const Size(640, 1136);
  tester.view.devicePixelRatio = 2.0;
  tester.view.viewInsets = FakeViewPadding(bottom: keyboard * 2);
  tester.platformDispatcher.textScaleFactorTestValue = textScale;
  addTearDown(() {
    tester.view.resetPhysicalSize();
    tester.view.resetDevicePixelRatio();
    tester.view.resetViewInsets();
    tester.platformDispatcher.clearTextScaleFactorTestValue();
  });
}

Widget _app(Widget home, {List<Override> overrides = const []}) =>
    ProviderScope(overrides: overrides, child: MaterialApp(home: home));

void main() {
  // ── OTP ───────────────────────────────────────────────────────────────────

  group('OTP verify screen', () {
    testWidgets('no overflow on a small phone with the keyboard open and large text', (tester) async {
      await _smallPhone(tester, keyboard: 300, textScale: 1.3);
      await tester.pumpWidget(_app(const OtpVerifyScreen(phone: '9876543210'), overrides: [authProvider.overrideWith((ref) => _FakeAuth('Tenant'))]));
      await tester.pump();

      expect(tester.takeException(), isNull, reason: 'a RenderFlex overflow throws here');
      await tester.ensureVisible(find.text('Verify'));
      expect(find.text('Verify'), findsOneWidget);
    });

    testWidgets('pasting the full code fills all four boxes and verifies once', (tester) async {
      final auth = _FakeAuth('Tenant');
      await tester.pumpWidget(_app(const OtpVerifyScreen(phone: '9876543210'), overrides: [authProvider.overrideWith((ref) => auth)]));
      await tester.pump();

      await tester.enterText(find.byKey(const Key('otp-input')), '4821');
      await tester.pump();
      await tester.pump();

      expect(auth.verifyCalls, 1);
      // The wrong-code error is shown inline and the boxes are cleared for retry.
      expect(find.byKey(const Key('otp-error')), findsOneWidget);
      expect(find.text('Invalid OTP. 4 attempts left.'), findsOneWidget);
    });

    testWidgets('Verify with a short code explains instead of calling the server', (tester) async {
      final auth = _FakeAuth('Tenant');
      await tester.pumpWidget(_app(const OtpVerifyScreen(phone: '9876543210'), overrides: [authProvider.overrideWith((ref) => auth)]));
      await tester.enterText(find.byKey(const Key('otp-input')), '48');
      await tester.tap(find.text('Verify'));
      await tester.pump();

      expect(auth.verifyCalls, 0);
      expect(find.text('Enter the 4-digit code from the SMS.'), findsOneWidget);
    });

    testWidgets('resend countdown is shown and the input only accepts digits', (tester) async {
      await tester.pumpWidget(_app(const OtpVerifyScreen(phone: '9876543210'), overrides: [authProvider.overrideWith((ref) => _FakeAuth('Tenant'))]));
      expect(find.text('Resend code in 30s'), findsOneWidget);
      await tester.pump(const Duration(seconds: 3));
      expect(find.text('Resend code in 27s'), findsOneWidget);

      await tester.enterText(find.byKey(const Key('otp-input')), '4a8');
      await tester.pump();
      expect(tester.widget<TextField>(find.byKey(const Key('otp-input'))).controller!.text, '48');
      await tester.pump(const Duration(seconds: 31));
      expect(find.text('Resend code'), findsOneWidget);
    });
  });

  // ── Emergency contacts ───────────────────────────────────────────────────

  group('Emergency contacts sheet', () {
    testWidgets('many contacts on a small phone scroll instead of overflowing', (tester) async {
      await _smallPhone(tester, textScale: 1.3);
      await tester.pumpWidget(_app(
        const Scaffold(body: EmergencyContactsSheet()),
        overrides: [societyContactsProvider.overrideWith((ref) async => _contacts(managers: 5))],
      ));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.text('Manager 0'), findsOneWidget);
      expect(find.text('1800-XXX-XXX'), findsNothing, reason: 'no placeholder numbers');
      await tester.scrollUntilVisible(find.text('Fire'), 200, scrollable: find.byType(Scrollable).first);
      expect(find.text('Fire'), findsOneWidget);
    });

    testWidgets('national numbers still work when contacts cannot load', (tester) async {
      await tester.pumpWidget(_app(
        const Scaffold(body: EmergencyContactsSheet()),
        overrides: [societyContactsProvider.overrideWith((ref) async => throw Exception('Cannot connect to the server.'))],
      ));
      await tester.pumpAndSettle();

      expect(find.text('Cannot connect to the server.'), findsOneWidget);
      expect(find.text('Retry'), findsOneWidget);
      expect(find.text('Ambulance'), findsOneWidget);
      expect(find.text('112'), findsOneWidget);
    });
  });

  // ── Services ─────────────────────────────────────────────────────────────

  group('Services', () {
    testWidgets('a service with providers is a directory with Call buttons', (tester) async {
      await tester.pumpWidget(_app(const ServicesScreen(), overrides: [
        servicesProvider.overrideWith((ref) => _FakeServices()),
        authProvider.overrideWith((ref) => _FakeAuth('Tenant')),
      ]));
      await tester.pumpAndSettle();

      expect(find.textContaining('2 providers'), findsOneWidget);
      await tester.tap(find.text('Maid'));
      await tester.pumpAndSettle();

      expect(find.text('Sunita'), findsOneWidget);
      expect(find.text('Call'), findsOneWidget, reason: 'only the provider with a phone gets Call');
      expect(find.text('7am-11am'), findsOneWidget);
      expect(find.textContaining('3000 / monthly'), findsOneWidget);
      expect(find.textContaining('No phone number listed'), findsOneWidget);
    });

    testWidgets('a service with no provider offers residents a request', (tester) async {
      await tester.pumpWidget(_app(const ServicesScreen(), overrides: [
        servicesProvider.overrideWith((ref) => _FakeServices()),
        authProvider.overrideWith((ref) => _FakeAuth('Tenant')),
      ]));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Cook'));
      await tester.pumpAndSettle();
      expect(find.text('Request Cook'), findsOneWidget);
      expect(find.text('Send request'), findsOneWidget);
    });

    testWidgets('a guard gets the directory only, never a request form', (tester) async {
      await tester.pumpWidget(_app(const ServicesScreen(), overrides: [
        servicesProvider.overrideWith((ref) => _FakeServices()),
        authProvider.overrideWith((ref) => _FakeAuth('Guard')),
      ]));
      await tester.pumpAndSettle();

      expect(find.text('No provider listed'), findsOneWidget);
      await tester.tap(find.text('Cook'));
      await tester.pumpAndSettle();
      expect(find.text('Send request'), findsNothing);
    });
  });

  // ── Help & About ─────────────────────────────────────────────────────────

  group('Profile support screens', () {
    testWidgets('Help & Support shows the real office contacts and actions', (tester) async {
      await tester.pumpWidget(_app(const HelpSupportScreen(), overrides: [
        societyContactsProvider.overrideWith((ref) async => _contacts()),
        authProvider.overrideWith((ref) => _FakeAuth('Tenant')),
      ]));
      await tester.pumpAndSettle();

      expect(find.text('Call office'), findsOneWidget);
      expect(find.text('Email office'), findsOneWidget);
      expect(find.text('Report a problem'), findsOneWidget);
      expect(find.text('Manager 0'), findsOneWidget);
    });

    testWidgets('Help & Support without configured contacts says so honestly', (tester) async {
      await tester.pumpWidget(_app(const HelpSupportScreen(), overrides: [
        societyContactsProvider.overrideWith((ref) async => SocietyContactsModel.fromJson({'society': {'name': 'X'}, 'managers': [], 'emergency': []})),
        authProvider.overrideWith((ref) => _FakeAuth('Guard')),
      ]));
      await tester.pumpAndSettle();

      expect(find.textContaining('has not added office contact details'), findsOneWidget);
      expect(find.text('Call office'), findsNothing);
      expect(find.text('Report a problem'), findsNothing, reason: 'guards do not raise resident tickets');
    });

    testWidgets('About shows the installed version', (tester) async {
      PackageInfo.setMockInitialValues(appName: 'SocietyFlow', packageName: 'x', version: '1.0.0', buildNumber: '7', buildSignature: '');
      await tester.pumpWidget(_app(const AboutScreen(), overrides: [authProvider.overrideWith((ref) => _FakeAuth('Tenant'))]));
      await tester.pumpAndSettle();

      expect(find.text('Version 1.0.0 (build 7)'), findsOneWidget);
      expect(find.text('Royal Eden'), findsOneWidget);
    });
  });

  // ── Pure logic ───────────────────────────────────────────────────────────

  group('Contact helpers and models', () {
    test('phone numbers are cleaned for the dialer and WhatsApp', () {
      expect(ContactLauncher.cleanPhone(' +91 98480-12345 '), '+919848012345');
      expect(ContactLauncher.whatsappNumber('98480 12345'), '919848012345');
      expect(ContactLauncher.whatsappNumber('919848012345'), '919848012345');
      expect(ContactLauncher.isDialable('100'), isTrue);
      expect(ContactLauncher.isDialable(''), isFalse);
    });

    test('society contacts parse defensively', () {
      final c = SocietyContactsModel.fromJson({'society': {'name': 'A', 'phone': '', 'email': null}, 'managers': [{'name': 'M', 'phone': null}], 'emergency': 'bad'});
      expect(c.societyPhone, isNull);
      expect(c.managers, isEmpty, reason: 'a manager without a phone is not callable');
      expect(c.emergency, isEmpty);
      expect(c.hasOfficeContact, isFalse);
    });

    test('providers group by service and format price', () {
      final p = ServiceProviderModel.fromJson({'id': '5', 'service_id': '3', 'name': 'Raju', 'phone': '98480', 'charges': '450.50', 'price_type': 'per_visit'});
      expect(p.serviceId, 3);
      expect(p.hasPhone, isFalse, reason: 'five digits is not a callable number');
      expect(p.priceLabel, '₹450.50 / per visit');
      final state = ServicesState(providers: [p]);
      expect(state.providersFor(3), hasLength(1));
      expect(state.providersFor(4), isEmpty);
    });
  });
}
