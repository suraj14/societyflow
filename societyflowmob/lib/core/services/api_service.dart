import 'dart:developer' as dev;
import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kReleaseMode, visibleForTesting;
import 'package:dio/dio.dart';
import '../config/api_config.dart';
import '../utils/storage_service.dart';
import 'server_discovery.dart';

// ── Result model ─────────────────────────────────────────────────────────────

class ApiResult {
  final bool success;
  final Map<String, dynamic>? data;
  final String message;
  final int statusCode;

  const ApiResult({
    required this.success,
    this.data,
    required this.message,
    required this.statusCode,
  });

  factory ApiResult.error(String message, {int statusCode = 0}) =>
      ApiResult(success: false, message: message, statusCode: statusCode);
}

// ── API Service ───────────────────────────────────────────────────────────────

class ApiService {
  static ApiService? _instance;
  static ApiService get instance => _instance ??= ApiService._();

  /// Registered by the app root. Called once when an authenticated request
  /// returns 401 (token expired/revoked) so the app can reset auth state
  /// and return to the login screen.
  static void Function()? onSessionExpired;
  static bool _handlingExpiry = false;

  Dio? _dio;
  String? _currentBaseUrl;

  ApiService._();

  /// Test seam, same pattern as ServerDiscovery.overrideBaseUrl: swaps in a
  /// Dio wired to a fake [HttpClientAdapter] so error-mapping (_dioError)
  /// can be exercised without a real server. Production never calls this.
  @visibleForTesting
  static void debugOverrideDio(Dio dio) {
    instance._dio = dio;
    instance._currentBaseUrl = dio.options.baseUrl;
  }

  @visibleForTesting
  static void debugClearDioOverride() {
    instance._dio = null;
    instance._currentBaseUrl = null;
  }

  /// Returns a Dio instance with the correct base URL.
  /// Auto-discovers the server if needed.
  Future<Dio> _getDio() async {
    final baseUrl = await ServerDiscovery.resolveBaseUrl();

    // Recreate Dio if base URL changed (IP changed)
    if (_dio == null || _currentBaseUrl != baseUrl) {
      _currentBaseUrl = baseUrl;
      _dio = Dio(
        BaseOptions(
          baseUrl: baseUrl,
          connectTimeout: ApiConfig.connectTimeout,
          receiveTimeout: ApiConfig.receiveTimeout,
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            // PHP's single-threaded dev server (`php artisan serve`) drops
            // reused keep-alive sockets, which Dart's HttpClient reports as
            // "connection closed before full header" - the app then showed
            // "Cannot reach server" although every request had reached it.
            // One request per connection is harmless against Apache/nginx.
            'Connection': 'close',
          },
        ),
      );

      _dio!.interceptors.add(InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _getToken();
          if (token != null) options.headers['Authorization'] = 'Bearer $token';
          dev.log('→ ${options.method} ${options.uri.path}', name: 'API');
          handler.next(options);
        },
        onResponse: (response, handler) {
          dev.log('← ${response.statusCode} ${response.requestOptions.uri.path}', name: 'API');
          handler.next(response);
        },
        onError: (error, handler) async {
          dev.log('✗ ${error.response?.statusCode} ${error.requestOptions.uri.path}', name: 'API');
          // 401 on a request that carried a token = session expired/revoked.
          // (Public auth endpoints never attach a token, so they can't trigger this.)
          final hadToken = error.requestOptions.headers['Authorization'] != null;
          if (error.response?.statusCode == 401 && hadToken && !_handlingExpiry) {
            _handlingExpiry = true;
            try {
              // Full local sign-out, token included: a 401 on an authenticated
              // request means the server has already revoked this session.
              await StorageService.clearAll();
              onSessionExpired?.call();
            } finally {
              _handlingExpiry = false;
            }
          }
          handler.next(error);
        },
      ));
    }

    return _dio!;
  }

  Future<String?> _getToken() => StorageService.getToken();

  // ── GET ───────────────────────────────────────────────────────────────────

  Future<ApiResult> get(String endpoint) async {
    try {
      final dio = await _getDio();
      final response = await dio.get(endpoint);
      return _parse(response);
    } on DioException catch (e) {
      return ApiResult.error(_dioError(e), statusCode: e.response?.statusCode ?? 0);
    }
  }

  /// Raw bytes of a protected file (e.g. a society document PDF), fetched
  /// with the same bearer token as every other call - the file is never
  /// exposed on a public URL. Null on any failure; [onError] gets the reason.
  Future<Uint8List?> getBytes(String endpoint, {void Function(String message, int statusCode)? onError}) async {
    try {
      final dio = await _getDio();
      final response = await dio.get<List<int>>(
        endpoint,
        options: Options(responseType: ResponseType.bytes, headers: {'Accept': 'application/pdf'}),
      );
      final data = response.data;
      if (data == null || data.isEmpty) {
        onError?.call('The file is empty.', response.statusCode ?? 0);
        return null;
      }
      return Uint8List.fromList(data);
    } on DioException catch (e) {
      final code = e.response?.statusCode ?? 0;
      onError?.call(code == 404 ? 'This document is no longer available.' : _dioError(e), code);
      return null;
    }
  }

  // ── POST ──────────────────────────────────────────────────────────────────

  Future<ApiResult> post(String endpoint, {Map<String, dynamic>? body}) async {
    try {
      final dio = await _getDio();
      final response = await dio.post(endpoint, data: body ?? {});
      return _parse(response);
    } on DioException catch (e) {
      return ApiResult.error(_dioError(e), statusCode: e.response?.statusCode ?? 0);
    }
  }

  // ── PUT ───────────────────────────────────────────────────────────────────

  Future<ApiResult> put(String endpoint, {Map<String, dynamic>? body}) async {
    try {
      final dio = await _getDio();
      final response = await dio.put(endpoint, data: body ?? {});
      return _parse(response);
    } on DioException catch (e) {
      return ApiResult.error(_dioError(e), statusCode: e.response?.statusCode ?? 0);
    }
  }

  // ── Multipart POST ────────────────────────────────────────────────────────

  /// [onSendProgress] reports bytes sent / total while the body uploads, so a
  /// screen can show a progress bar for a photo. Optional - existing callers
  /// are unaffected.
  Future<ApiResult> postMultipart(
    String endpoint, {
    required Map<String, String> fields,
    Map<String, String>? filePaths,
    void Function(int sent, int total)? onSendProgress,
  }) async {
    try {
      final dio = await _getDio();
      final formData = FormData.fromMap({
        ...fields,
        if (filePaths != null)
          for (final e in filePaths.entries)
            e.key: await MultipartFile.fromFile(e.value),
      });
      final response = await dio.post(endpoint, data: formData, onSendProgress: onSendProgress);
      return _parse(response);
    } on DioException catch (e) {
      return ApiResult.error(_dioError(e), statusCode: e.response?.statusCode ?? 0);
    }
  }

  // ── Force re-discover server (call on Retry) ──────────────────────────────

  Future<void> resetConnection() async {
    _dio = null;
    _currentBaseUrl = null;
    await ServerDiscovery.rescan();
  }

  // ── Response parser ───────────────────────────────────────────────────────

  ApiResult _parse(Response response) {
    final body = response.data;
    if (body is Map<String, dynamic>) {
      final ok = response.statusCode != null &&
          response.statusCode! >= 200 &&
          response.statusCode! < 300;
      final msg = body['message']?.toString() ??
          (body['errors'] != null ? _flattenErrors(body['errors']) : null) ??
          (ok ? 'Success' : 'Error');
      return ApiResult(
        success: ok,
        data: body,
        message: msg,
        statusCode: response.statusCode ?? 0,
      );
    }
    return ApiResult.error(
      'Unexpected response format',
      statusCode: response.statusCode ?? 0,
    );
  }

  String _flattenErrors(dynamic errors) {
    if (errors is Map) {
      return errors.values
          .map((v) => v is List ? v.first.toString() : v.toString())
          .join(', ');
    }
    return errors.toString();
  }

  // ── Error messages ────────────────────────────────────────────────────────

  /// In debug builds, appends the request that actually failed.
  ///
  /// Every transport failure used to surface as "Request timed out. Please
  /// check your connection", which hid the real cause - a refused
  /// connection, a 404 from a mismatched endpoint, or a dev server that was
  /// simply not running. The category and the URL are what a developer
  /// needs; release builds keep the plain message.
  String _withDevDetail(String message, DioException e, String category) {
    if (kReleaseMode) return message;
    final url = e.requestOptions.uri.toString();
    return '$message\n\n[dev] $category\n[dev] ${e.requestOptions.method} $url';
  }

  String _dioError(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
        return _withDevDetail(
          'Could not connect to the server.',
          e,
          'USB debugging connection required for local API. Connect the Android device by USB. '
              'CONNECTION TIMEOUT - the tunnel did not accept a connection.',
        );
      case DioExceptionType.sendTimeout:
        return _withDevDetail('Request timed out while sending.', e, 'SEND TIMEOUT');
      case DioExceptionType.receiveTimeout:
        return _withDevDetail(
          'The server took too long to respond.',
          e,
          'RECEIVE TIMEOUT - connected, but no response in '
              '${ApiConfig.receiveTimeout.inSeconds}s',
        );
      case DioExceptionType.connectionError:
        // Invalidate cached URL so next request triggers re-discovery
        _dio = null;
        _currentBaseUrl = null;
        ServerDiscovery.invalidate();
        return _withDevDetail(
          'Cannot reach server.',
          e,
          'USB debugging connection required for local API. Connect the Android device by USB. '
              'CONNECTION REFUSED - nothing is listening on the tunnel. '
              'Run scripts/run_android_usb.ps1 to start the API and set up adb reverse. '
              'Underlying: ${e.error}',
        );
      case DioExceptionType.badCertificate:
        return _withDevDetail('Secure connection failed.', e, 'BAD TLS CERTIFICATE');
      case DioExceptionType.cancel:
        return _withDevDetail('Request cancelled.', e, 'CANCELLED');
      case DioExceptionType.badResponse:
        final status = e.response?.statusCode;
        final body = e.response?.data;
        if (status == 401) {
          // On the login endpoint itself a 401/422 means bad credentials,
          // not an expired session - don't mislabel it.
          final isLogin = e.requestOptions.path.contains('/auth/');
          return _withDevDetail(
            isLogin ? 'Incorrect email or password.' : 'Session expired. Please login again.',
            e,
            'HTTP 401 UNAUTHENTICATED',
          );
        }
        if (status == 403) {
          // The API says why in plain words ("Facility booking is for residents.", "Events module is not
          // enabled."); show that, not a generic line that hides the reason.
          final serverSays = body is Map ? body['message']?.toString() : null;
          return _withDevDetail(
            (serverSays != null && serverSays.isNotEmpty) ? serverSays : 'Access denied.',
            e,
            'HTTP 403 FORBIDDEN - role/permission check failed',
          );
        }
        if (status == 404) {
          return _withDevDetail(
            'Resource not found.',
            e,
            'HTTP 404 NOT FOUND - the endpoint does not exist on this server. '
                'Check the path against routes/api.php (prefix /api/v1).',
          );
        }
        if (status == 419) {
          return _withDevDetail('Session token mismatch.', e, 'HTTP 419 - CSRF/session, the API should be stateless');
        }
        if (status == 422) {
          final errors = body?['errors'];
          final msg = errors != null
              ? _flattenErrors(errors)
              : (body?['message']?.toString() ?? 'Validation failed.');
          return _withDevDetail(msg, e, 'HTTP 422 VALIDATION');
        }
        if (status == 503) {
          // 503 is this API's deliberate "I could not complete this, here is
          // why" status (e.g. send-otp when the SMS provider rejects the
          // request) - the message is a controlled, written-for-users string,
          // not leaked exception detail, so it is safe to show as-is. 500/502/504
          // stay generic below: those bodies can carry raw server error text.
          final msg = body?['message']?.toString();
          if (msg != null && msg.isNotEmpty) {
            return _withDevDetail(msg, e, 'HTTP 503 (${body?['code'] ?? 'service unavailable'})');
          }
        }
        if (status != null && status >= 500) {
          return _withDevDetail(
            'Server error. Please try again later.',
            e,
            'HTTP $status SERVER ERROR - check storage/logs/laravel.log',
          );
        }
        return _withDevDetail(
          body?['message']?.toString() ?? 'Request failed (HTTP $status).',
          e,
          'HTTP $status',
        );
      default:
        if (e.error is SocketException) {
          return _withDevDetail(
            'No internet connection.',
            e,
            'SOCKET EXCEPTION - ${e.error}',
          );
        }
        return _withDevDetail('Something went wrong. Please try again.', e, 'UNKNOWN - ${e.error}');
    }
  }
}
