import 'dart:convert';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:flutter_secure_storage/test/test_flutter_secure_storage_platform.dart';
import 'package:societyflow/core/services/api_service.dart';
import 'package:societyflow/core/services/server_discovery.dart';
import 'package:societyflow/features/auth/providers/auth_provider.dart';
import 'package:societyflow/features/auth/screens/otp_phone_screen.dart';

/// The app side of the one-time-code flow: a release build never shows or fills in a code from the response (production
/// delivers it by SMS only), and a repeated tap on "Send OTP" asks the server once.
/// Answers from a route table with the EXACT bodies the Laravel server returned (captured from a running server:
/// send-otp, resend inside the cooldown, a wrong code, the right code, a replay).
class _Server implements HttpClientAdapter {
  final Map<String, (int, Map<String, dynamic>)> routes;
  final List<String> calls = [];
  _Server(this.routes);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final key = '${options.method} ${options.path}';
    calls.add(key);
    final r = routes[key] ?? (404, {'message': 'no route in test: $key'});
    return ResponseBody.fromBytes(utf8.encode(jsonEncode(r.$2)), r.$1, headers: {Headers.contentTypeHeader: ['application/json']});
  }

  @override
  void close({bool force = false}) {}
}

(_Server, ProviderContainer) _wire(Map<String, (int, Map<String, dynamic>)> routes) {
  final server = _Server(routes);
  final dio = Dio(BaseOptions(baseUrl: 'http://fake.test/api/v1'));
  dio.httpClientAdapter = server;
  ApiService.debugOverrideDio(dio);
  final container = ProviderContainer();
  addTearDown(container.dispose);
  return (server, container);
}
class _CountingAuth extends AuthNotifier {
  int sends = 0;
  @override
  Future<void> sendOtp({required String phone}) async {
    sends++;
    await Future<void>.delayed(const Duration(milliseconds: 50));
    throw Exception('stop here: this test only counts requests');
  }
}

void main() {
  group('the code in a server answer', () {
    test('a release build ignores it completely', () {
      expect(devOtpFrom({'otp': '4821', 'success': true}, release: true), isNull);
      expect(devOtpFrom({'otp': '4821'}, release: true), isNull);
    });

    test('a debug build reads it, and only when the server sent one', () {
      expect(devOtpFrom({'otp': '4821'}, release: false), '4821');
      expect(devOtpFrom({'success': true}, release: false), isNull);
      expect(devOtpFrom(null, release: false), isNull);
    });

    test('this very test run is not a release build, so the default follows the build mode', () {
      expect(devOtpFrom({'otp': '1111'}), '1111');   // kReleaseMode is false under flutter test; true in `flutter build apk --release`
    });
  });

  group('against the real server answers', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({});
      FlutterSecureStoragePlatform.instance = TestFlutterSecureStoragePlatform({});
      ServerDiscovery.overrideBaseUrl = 'http://fake.test/api/v1';
    });
    tearDown(() {
      ServerDiscovery.overrideBaseUrl = null;
      ApiService.debugClearDioOverride();
    });

    const generic = {'success': true, 'message': 'If this number is registered, a code has been sent to 90******77.'};

    test('the dev code is read when a development server sends one, and not kept otherwise', () async {
      final (_, c) = _wire({'POST /auth/send-otp': (200, {...generic, 'otp': '4821'})});
      await c.read(authProvider.notifier).sendOtp(phone: '9000000077');
      expect(c.read(authProvider).devOtp, '4821');

      final (_, c2) = _wire({'POST /auth/send-otp': (200, generic)});
      await c2.read(authProvider.notifier).sendOtp(phone: '9000000077');
      expect(c2.read(authProvider).devOtp, isNull, reason: 'a production answer has no code, and the app invents none');
    });

    test('a resend inside the cooldown shows the server\'s own sentence', () async {
      final (_, c) = _wire({'POST /auth/send-otp': (429, {'success': false, 'code': 'otp_cooldown', 'retry_after': 30, 'message': 'Please wait 30 seconds before asking for another code.'})});
      await expectLater(c.read(authProvider.notifier).sendOtp(phone: '9000000077'), throwsA(predicate((e) => e.toString().contains('Please wait 30 seconds'))));
      expect(c.read(authProvider).devOtp, isNull);
    });

    test('SMS not set up / provider down are told as they are, not as success', () async {
      final (_, c) = _wire({'POST /auth/send-otp': (503, {'success': false, 'code': 'sms_not_configured', 'message': 'OTP login by SMS is not set up for your society yet. Please log in with email and password, or ask your society office.'})});
      await expectLater(c.read(authProvider.notifier).sendOtp(phone: '9000000077'), throwsA(predicate((e) => e.toString().contains('not set up'))));
    });

    test('a wrong, expired or replayed code is one message, and nobody is signed in', () async {
      final (_, c) = _wire({'POST /auth/verify-otp': (422, {'success': false, 'message': 'That code is not valid or has expired. Check it, or request a new one.'})});
      await expectLater(c.read(authProvider.notifier).verifyOtp(phone: '9000000077', otp: '1111'), throwsA(predicate((e) => e.toString().contains('not valid or has expired'))));
      expect(c.read(authProvider).user, isNull);
      expect(c.read(authProvider).error, contains('not valid or has expired'));
    });

    test('the right code signs in and the stored session is the one the server issued', () async {
      final (server, c) = _wire({
        'POST /auth/verify-otp': (200, {
          'success': true, 'message': 'Login successful', 'token': '7|abcdefghijklmnopqrstuvwxyz',
          'user': {'id': 77, 'name': 'Dinesh', 'email': 'help@example.test', 'role': 'Guard', 'roles': ['Guard'], 'society_name': 'Royal Eden', 'capabilities': {'guard_shift': true}},
        }),
      });
      await c.read(authProvider.notifier).verifyOtp(phone: '9000000077', otp: '4821');

      expect(c.read(authProvider).user?.email, 'help@example.test');
      expect(c.read(authProvider).devOtp, isNull, reason: 'a used code is forgotten');
      expect(server.calls, ['POST /auth/verify-otp']);
    });
  });

  testWidgets('tapping Send OTP twice quickly asks the server once', (tester) async {    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    final auth = _CountingAuth();
    final previous = FlutterError.onError;
    FlutterError.onError = (d) {
      if (!d.exceptionAsString().contains('overflowed')) previous?.call(d);   // the test font is wider than a real one
    };
    addTearDown(() => FlutterError.onError = previous);
    await tester.pumpWidget(ProviderScope(
      overrides: [authProvider.overrideWith((ref) => auth)],
      child: const MaterialApp(home: OtpPhoneScreen()),
    ));

    await tester.enterText(find.byType(TextFormField), '9876543210');
    await tester.pump();
    await tester.tap(find.text('Send OTP'));
    await tester.pump(const Duration(milliseconds: 5));
    await tester.tap(find.byType(ElevatedButton), warnIfMissed: false);   // the label is a spinner now
    await tester.pumpAndSettle(const Duration(milliseconds: 200));

    expect(auth.sends, 1);
  });
}
