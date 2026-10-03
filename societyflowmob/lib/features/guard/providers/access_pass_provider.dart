import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';

/// One QR scan's outcome. Mirrors the web gate's scanResponse() shape
/// (POST /api/v1/access-passes/scan, same AccessPassService behind both) -
/// see AccessPassApiController's own docblock.
class ScanOutcome {
  final bool success;
  final String message;
  final String? person;
  final String? category;

  const ScanOutcome({
    required this.success,
    required this.message,
    this.person,
    this.category,
  });

  factory ScanOutcome.fromResult(ApiResult result) {
    final data = result.data?['data'];
    final person = data is Map ? data['person']?.toString() : null;
    final category = data is Map ? data['category']?.toString() : null;
    return ScanOutcome(
      success: result.success,
      message: result.message,
      person: person,
      category: category,
    );
  }
}

/// Sends a scanned/typed token to the server. Every decision (society,
/// property, schedule, revoked/blocked status, duplicate entry) is made
/// server-side - this call carries nothing but the opaque token itself,
/// same as the web gate.
class AccessPassApi {
  static Future<ScanOutcome> scan(String token) async {
    final result = await HttpService.instance.post(
      ApiConfig.accessPassScan,
      body: {'token': token},
    );
    return ScanOutcome.fromResult(result);
  }
}
