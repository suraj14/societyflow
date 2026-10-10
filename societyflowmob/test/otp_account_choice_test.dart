import 'dart:convert';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/test/test_flutter_secure_storage_platform.dart';
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:societyflow/core/services/api_service.dart';
import 'package:societyflow/core/services/server_discovery.dart';
import 'package:societyflow/features/auth/models/otp_account_choice.dart';
import 'package:societyflow/features/auth/models/user_model.dart';
import 'package:societyflow/features/auth/providers/auth_provider.dart';
import 'package:societyflow/features/auth/screens/choose_account_screen.dart';
import 'package:societyflow/features/auth/screens/otp_verify_screen.dart';

/// Regression: OTP login signed in whichever account the server's database
/// returned first when one phone number was saved on two accounts. On the
/// test phone that was a Guard whose number the society Admin also used -
/// the guard would have landed in the admin's account. The app now says it
/// can choose (`can_choose_account`), and after a verified code the user
/// picks the account.

// ── fake HTTP: canned JSON per path, every request recorded ─────────────────

class _Router implements HttpClientAdapter {
  final Map<String, (int, Map<String, dynamic>)> responses;
  final List<(String, Map<String, dynamic>)> calls = [];
  _Router(this.responses);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final path = options.uri.path;
    calls.add((path, Map<String, dynamic>.from(options.data as Map? ?? {})));
    final hit = responses.entries.where((e) => path.endsWith(e.key)).map((e) => e.value).firstOrNull ?? (404, {'message': 'not faked: $path'});
    return ResponseBody.fromBytes(utf8.encode(jsonEncode(hit.$2)), hit.$1, headers: {
      Headers.contentTypeHeader: ['application/json'],
    });
  }

  @override
  void close({bool force = false}) {}
}

const _chooserResponse = {
  'success': true,
  'requires_account_choice': true,
  'message': 'This number is linked to more than one account. Choose the one to sign in to.',
  'selection_token': 'sel-123',
  'accounts': [
    {'id': 67, 'name': 'Royal Eden', 'role': 'Admin', 'society_name': 'Royal Eden', 'unit': null},
    {'id': 77, 'name': 'Dinesh', 'role': 'Guard', 'society_name': 'Royal Eden', 'unit': null},
  ],
};

const _guardLogin = {
  'success': true,
  'message': 'Login successful',
  'token': 'tok-guard',
  'user': {'id': 77, 'name': 'Dinesh', 'email': 'g@x.in', 'role': 'Guard', 'status': 'active', 'society_name': 'Royal Eden'},
};

// ── fake notifier for the screens ───────────────────────────────────────────

const _choice = OtpAccountChoice(selectionToken: 'sel-123', accounts: [
  OtpAccountOption(id: 67, name: 'Royal Eden', role: 'Admin', societyName: 'Royal Eden'),
  OtpAccountOption(id: 77, name: 'Dinesh', role: 'Guard', societyName: 'Royal Eden'),
]);

class _ChoiceAuth extends AuthNotifier {
  final bool expireOnSelect;
  int? selectedId;
  _ChoiceAuth({this.expireOnSelect = false, OtpAccountChoice? initial}) {
    if (initial != null) state = state.copyWith(accountChoice: initial);
  }

  @override
  Future<void> sendOtp({required String phone}) async {}

  @override
  Future<void> verifyOtp({required String phone, required String otp}) async {
    state = state.copyWith(accountChoice: _choice);
  }

  @override
  Future<void> selectOtpAccount({required int userId}) async {
    selectedId = userId;
    if (expireOnSelect) {
      state = state.copyWith(clearAccountChoice: true);
      throw Exception('This sign-in has expired. Please request a new OTP.');
    }
    state = state.copyWith(
      user: UserModel.fromJson(Map<String, dynamic>.from(_guardLogin['user'] as Map)),
      clearAccountChoice: true,
    );
  }
}

Widget _app(Widget home, AuthNotifier auth) => ProviderScope(
      overrides: [authProvider.overrideWith((ref) => auth)],
      child: MaterialApp(home: home, routes: {'/home': (_) => const Scaffold(body: Text('HOME'))}),
    );

void main() {
  group('OtpAccountChoice parsing', () {
    test('an ordinary login response is not a choice', () {
      expect(OtpAccountChoice.fromVerifyResponse(Map<String, dynamic>.from(_guardLogin)), isNull);
      expect(OtpAccountChoice.fromVerifyResponse(null), isNull);
    });

    test('a choice response lists the accounts and skips malformed rows', () {
      final json = Map<String, dynamic>.from(_chooserResponse)
        ..['accounts'] = [
          ...(_chooserResponse['accounts'] as List),
          'not-a-map',
          {'name': 'No id'},
          {'id': 90, 'name': 'Asha', 'role': 'Villa Owner', 'society_name': 'Palm Villas', 'unit': 'Villa 159'},
        ];
      final c = OtpAccountChoice.fromVerifyResponse(json)!;
      expect(c.selectionToken, 'sel-123');
      expect(c.accounts.map((a) => a.id), [67, 77, 90]);
      expect(c.accounts.last.unit, 'Villa 159');
      expect(c.accounts.first.unit, isNull);
    });
  });

  group('AuthNotifier against the API', () {
    late _Router router;

    setUp(() {
      SharedPreferences.setMockInitialValues({});
      FlutterSecureStoragePlatform.instance = TestFlutterSecureStoragePlatform({});
      ServerDiscovery.overrideBaseUrl = 'http://fake.test/api/v1';
    });

    tearDown(() {
      ServerDiscovery.overrideBaseUrl = null;
      ApiService.debugClearDioOverride();
    });

    void serve(Map<String, (int, Map<String, dynamic>)> responses) {
      router = _Router(responses);
      final dio = Dio(BaseOptions(baseUrl: 'http://fake.test/api/v1'));
      dio.httpClientAdapter = router;
      ApiService.debugOverrideDio(dio);
    }

    test('send and verify tell the server this build can choose', () async {
      serve({'/auth/send-otp': (200, {'success': true, 'message': 'OTP sent'}), '/auth/verify-otp': (200, _chooserResponse)});
      final auth = AuthNotifier();

      await auth.sendOtp(phone: '7726884448');
      await auth.verifyOtp(phone: '7726884448', otp: '1234');

      expect(router.calls.map((c) => c.$2['can_choose_account']), [true, true]);
    });

    test('a shared number signs nobody in until an account is picked', () async {
      serve({'/auth/verify-otp': (200, _chooserResponse), '/auth/select-account': (200, _guardLogin)});
      final auth = AuthNotifier();

      await auth.verifyOtp(phone: '7726884448', otp: '1234');
      expect(auth.state.user, isNull, reason: 'no account is signed in by the verify step alone');
      expect(auth.state.accountChoice?.accounts.map((a) => a.role), ['Admin', 'Guard']);

      await auth.selectOtpAccount(userId: 77);

      expect(router.calls.last.$1, endsWith('/auth/select-account'));
      expect(router.calls.last.$2, {'selection_token': 'sel-123', 'user_id': 77});
      expect(auth.state.user?.id, 77);
      expect(auth.state.user?.role, 'Guard');
      expect(auth.state.accountChoice, isNull);
    });

    test('an expired choice is reported and dropped', () async {
      serve({
        '/auth/verify-otp': (200, _chooserResponse),
        '/auth/select-account': (422, {'success': false, 'code': 'selection_expired', 'message': 'This sign-in has expired. Please request a new OTP.'}),
      });
      final auth = AuthNotifier();
      await auth.verifyOtp(phone: '7726884448', otp: '1234');

      await expectLater(auth.selectOtpAccount(userId: 77), throwsA(predicate((e) => e.toString().contains('This sign-in has expired.'))));
      expect(auth.state.user, isNull);
      expect(auth.state.accountChoice, isNull);
    });

    test('a single-account number still signs straight in', () async {
      serve({'/auth/verify-otp': (200, _guardLogin)});
      final auth = AuthNotifier();

      await auth.verifyOtp(phone: '7726884448', otp: '1234');

      expect(auth.state.user?.id, 77);
      expect(auth.state.accountChoice, isNull);
    });
  });

  group('Screens', () {
    testWidgets('a verified code on a shared number opens the chooser, and picking signs in', (tester) async {
      final auth = _ChoiceAuth();
      await tester.pumpWidget(_app(const OtpVerifyScreen(phone: '7726884448'), auth));

      await tester.enterText(find.byKey(const Key('otp-input')), '4821');
      await tester.pumpAndSettle();

      expect(find.byType(ChooseAccountScreen), findsOneWidget);
      expect(find.text('Royal Eden'), findsWidgets);
      expect(find.text('Dinesh'), findsOneWidget);
      expect(find.text('Guard · Royal Eden'), findsOneWidget);

      await tester.tap(find.byKey(const Key('account-77')));
      await tester.pumpAndSettle();

      expect(auth.selectedId, 77);
      expect(find.text('HOME'), findsOneWidget);
    });

    testWidgets('an expired choice says so and offers a new code', (tester) async {
      final auth = _ChoiceAuth(expireOnSelect: true, initial: _choice);
      await tester.pumpWidget(_app(const ChooseAccountScreen(), auth));

      await tester.tap(find.byKey(const Key('account-67')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('choose-account-error')), findsOneWidget);
      expect(find.text('Request a new code'), findsOneWidget);
      expect(find.byKey(const Key('account-67')), findsNothing, reason: 'a spent choice cannot be tapped again');
    });

    testWidgets('many long-named accounts on a small phone with large text do not overflow', (tester) async {
      tester.view.physicalSize = const Size(640, 1136);
      tester.view.devicePixelRatio = 2.0;
      tester.platformDispatcher.textScaleFactorTestValue = 1.3;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
        tester.platformDispatcher.clearTextScaleFactorTestValue();
      });
      final many = OtpAccountChoice(selectionToken: 's', accounts: [
        for (var i = 1; i <= 6; i++)
          OtpAccountOption(
            id: i,
            name: 'Venkata Subrahmanya Lakshmi Narasimha $i',
            role: 'Apartment Owner',
            societyName: 'Sri Venkateswara Residency Phase $i',
            unit: 'Tower B - Flat 120$i',
          ),
      ]);
      await tester.pumpWidget(_app(const ChooseAccountScreen(), _ChoiceAuth(initial: many)));
      await tester.pump();

      expect(tester.takeException(), isNull, reason: 'a RenderFlex overflow throws here');
      await tester.scrollUntilVisible(find.byKey(const Key('account-6')), 200);
      expect(find.byKey(const Key('account-6')), findsOneWidget);
    });
  });
}
