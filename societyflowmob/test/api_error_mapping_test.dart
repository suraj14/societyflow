import 'dart:convert';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:flutter_secure_storage/test/test_flutter_secure_storage_platform.dart';
import 'package:societyflow/core/services/api_service.dart';
import 'package:societyflow/core/services/server_discovery.dart';

/// Returns a canned response for every request - no network involved.
class _FakeAdapter implements HttpClientAdapter {
  final int statusCode;
  final Map<String, dynamic> body;
  _FakeAdapter(this.statusCode, this.body);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final bytes = utf8.encode(jsonEncode(body));
    return ResponseBody.fromBytes(bytes, statusCode, headers: {
      Headers.contentTypeHeader: ['application/json'],
    });
  }

  @override
  void close({bool force = false}) {}
}

/// Regression: ApiService._dioError hard-coded "Server error. Please try
/// again later." for every 5xx response, discarding the body's own
/// `message` - the same handling 422/401/404 already got right. That ate
/// OtpAuthController::sendOtp's honest 503 explanation ("We could not send
/// the OTP SMS right now...") and showed the generic string instead, found
/// on a real device testing OTP login.
void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStoragePlatform.instance = TestFlutterSecureStoragePlatform({});
    ServerDiscovery.overrideBaseUrl = 'http://fake.test/api/v1';
  });

  tearDown(() {
    ServerDiscovery.overrideBaseUrl = null;
    ApiService.debugClearDioOverride();
  });

  Dio fakeDio(int status, Map<String, dynamic> body) {
    final dio = Dio(BaseOptions(baseUrl: 'http://fake.test/api/v1'));
    dio.httpClientAdapter = _FakeAdapter(status, body);
    return dio;
  }

  test('a 503 with a server-written message shows that message, not the generic one', () async {
    ApiService.debugOverrideDio(fakeDio(503, {
      'success': false,
      'code': 'sms_not_configured',
      'message': 'OTP login by SMS is not set up for your society yet. Please log in with email and password, or ask your society office.',
    }));

    final result = await ApiService.instance.post('/auth/send-otp', body: {'phone': '9876543210'});

    expect(result.success, isFalse);
    expect(result.statusCode, 503);
    expect(result.message, startsWith('OTP login by SMS is not set up for your society yet.'));
    expect(result.message, isNot(contains('Server error. Please try again later.')));
  });

  test('a 503 with no message body falls back to the generic message', () async {
    ApiService.debugOverrideDio(fakeDio(503, {}));

    final result = await ApiService.instance.post('/auth/send-otp', body: {'phone': '9876543210'});

    expect(result.message, startsWith('Server error. Please try again later.'));
  });

  test('a real 500 crash still shows the generic message, never raw server text', () async {
    ApiService.debugOverrideDio(fakeDio(500, {
      'message': 'SQLSTATE[HY000]: General error: 1 no such table: whatever (Connection: sqlite, SQL: select * ...)',
    }));

    final result = await ApiService.instance.post('/auth/send-otp', body: {'phone': '9876543210'});

    expect(result.message, startsWith('Server error. Please try again later.'));
    expect(result.message, isNot(contains('SQLSTATE')), reason: '500s must never leak raw exception text to the user');
  });

  test('a 422 validation message still passes through unchanged (not a regression)', () async {
    ApiService.debugOverrideDio(fakeDio(422, {
      'message': 'The phone field is required.',
    }));

    final result = await ApiService.instance.post('/auth/send-otp', body: {});

    expect(result.message, startsWith('The phone field is required.'));
  });

  test('a 409 (e.g. OTP ambiguous-phone) falls through to the body message, unmassaged', () async {
    // OtpAuthController refuses a phone number shared by two active
    // accounts with 409 phone_ambiguous rather than guessing which
    // account to log into. No status-specific branch exists for 409, so
    // this relies on the generic badResponse fallback below - add a 409
    // branch there only if it keeps passing body['message'] through.
    ApiService.debugOverrideDio(fakeDio(409, {
      'success': false,
      'code': 'phone_ambiguous',
      'message': 'This mobile number is linked to more than one account. Please log in with your email and password, or ask your society office to update the numbers.',
    }));

    final result = await ApiService.instance.post('/auth/send-otp', body: {'phone': '7726884448'});

    expect(result.statusCode, 409);
    expect(result.message, startsWith('This mobile number is linked to more than one account.'));
  });
}
