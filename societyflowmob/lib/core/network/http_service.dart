// Thin wrapper — delegates to ApiService (Dio-based with auto server discovery).
// All providers use this so they don't need to change.
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
  }) => ApiService.instance.postMultipart(endpoint, fields: fields, filePaths: filePaths);

  /// Call this when user taps Retry — forces re-scan of the network
  Future<void> resetConnection() => ApiService.instance.resetConnection();
}
