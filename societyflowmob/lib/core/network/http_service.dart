// Thin wrapper — delegates to ApiService (Dio-based with auto server discovery).
// All providers use this so they don't need to change.
import 'dart:typed_data';
import '../services/api_service.dart';
export '../services/api_service.dart' show ApiResult;

class HttpService {
  static HttpService? _instance;
  static HttpService get instance => _instance ??= HttpService._();
  HttpService._();

  Future<ApiResult> get(String endpoint) => ApiService.instance.get(endpoint);

  Future<ApiResult> post(String endpoint, {Map<String, dynamic>? body}) =>
      ApiService.instance.post(endpoint, body: body);

  Future<ApiResult> put(String endpoint, {Map<String, dynamic>? body}) =>
      ApiService.instance.put(endpoint, body: body);

  Future<ApiResult> postMultipart(
    String endpoint, {
    required Map<String, String> fields,
    Map<String, String>? filePaths,
    void Function(int sent, int total)? onSendProgress,
  }) => ApiService.instance.postMultipart(endpoint, fields: fields, filePaths: filePaths, onSendProgress: onSendProgress);

  Future<Uint8List?> getBytes(String endpoint, {void Function(String message, int statusCode)? onError}) =>
      ApiService.instance.getBytes(endpoint, onError: onError);

  /// Call this when user taps Retry — forces re-scan of the network
  Future<void> resetConnection() => ApiService.instance.resetConnection();
}
