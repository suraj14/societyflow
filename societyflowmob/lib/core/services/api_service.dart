import 'dart:developer' as dev;
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';
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

  Dio? _dio;
  String? _currentBaseUrl;

  ApiService._();

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
          },
        ),
      );

      _dio!.interceptors.add(InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _getToken();
          if (token != null) options.headers['Authorization'] = 'Bearer $token';
          dev.log('→ ${options.method} ${options.uri}', name: 'API');
          handler.next(options);
        },
        onResponse: (response, handler) {
          dev.log('← ${response.statusCode} ${response.requestOptions.uri}', name: 'API');
          handler.next(response);
        },
        onError: (error, handler) {
          dev.log('✗ ${error.response?.statusCode} ${error.requestOptions.uri}', name: 'API');
          handler.next(error);
        },
      ));
    }

    return _dio!;
  }

  Future<String?> _getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

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

  Future<ApiResult> postMultipart(
    String endpoint, {
    required Map<String, String> fields,
    Map<String, String>? filePaths,
  }) async {
    try {
      final dio = await _getDio();
      final formData = FormData.fromMap({
        ...fields,
        if (filePaths != null)
          for (final e in filePaths.entries)
            e.key: await MultipartFile.fromFile(e.value),
      });
      final response = await dio.post(endpoint, data: formData);
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

  String _dioError(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return 'Request timed out. Please check your connection.';
      case DioExceptionType.connectionError:
        // Invalidate cached URL so next request triggers re-discovery
        _dio = null;
        _currentBaseUrl = null;
        ServerDiscovery.invalidate();
        return 'Cannot reach server.\n• Make sure Laravel server is running\n• Phone is on same WiFi as PC\n• Run: php artisan serve --host=0.0.0.0 --port=8000';
      case DioExceptionType.badResponse:
        final status = e.response?.statusCode;
        final body = e.response?.data;
        if (status == 401) return 'Session expired. Please login again.';
        if (status == 403) return 'Access denied.';
        if (status == 404) return 'Resource not found.';
        if (status == 422) {
          final errors = body?['errors'];
          if (errors != null) return _flattenErrors(errors);
          return body?['message']?.toString() ?? 'Validation failed.';
        }
        if (status != null && status >= 500) return 'Server error. Please try again later.';
        return body?['message']?.toString() ?? 'Request failed (HTTP $status).';
      default:
        if (e.error is SocketException) return 'No internet connection.';
        return 'Something went wrong. Please try again.';
    }
  }
}
