import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart' show visibleForTesting;
import '../config/api_config.dart';

/// Finds the development API for a debug build, and nothing else.
///
/// There is one local route: the USB cable. `adb reverse tcp:8000 tcp:8000`
/// makes the phone's own 127.0.0.1:8000 tunnel to the PC's port 8000, so the
/// app never needs the PC's LAN address, which changes with Wi-Fi.
///
/// The old LAN fallback, the subnet scan and the cached address were removed.
/// They trusted whatever answered on port 8000 on the network, and then sent
/// the user's bearer token there. A host is trusted only after GET
/// /api/v1/health answers with `application: societyflow`.
///
/// Release builds, an explicit --dart-define, and USE_PRODUCTION skip probing
/// entirely and use [ApiConfig.baseUrl].
class ServerDiscovery {
  static const Duration _timeout = Duration(seconds: 2);

  /// The only addresses a debug build probes. Both are loopback, so the
  /// check cannot be pointed at a LAN host.
  @visibleForTesting
  static const List<String> candidateBaseUrls = [ApiConfig.localDevUrl];

  static String? _resolvedUrl;

  /// Short-circuits discovery with a fixed URL. Discovery does real socket I/O,
  /// which cannot run under `flutter test`, so tests set this. Production never does.
  @visibleForTesting
  static String? overrideBaseUrl;

  /// The base URL in use (after [resolveBaseUrl] has completed).
  static String get currentBaseUrl =>
      overrideBaseUrl ?? _resolvedUrl ?? ApiConfig.baseUrl;

  /// Resolves the base URL. Debug probes the USB tunnel once and caches the
  /// answer. If the tunnel is not there, it returns the local URL anyway, so
  /// requests fail with the USB message in ApiService and never reach another host.
  static Future<String> resolveBaseUrl() async {
    if (overrideBaseUrl != null) return overrideBaseUrl!;
    if (ApiConfig.hasOverride || ApiConfig.useProduction) return ApiConfig.baseUrl;
    if (_resolvedUrl != null) return _resolvedUrl!;

    for (final url in candidateBaseUrls) {
      if (await _isSocietyFlow(url)) {
        _resolvedUrl = url;
        return url;
      }
    }
    return ApiConfig.localDevUrl;
  }

  static Future<String> rescan() async {
    _resolvedUrl = null;
    return resolveBaseUrl();
  }

  static void invalidate() => _resolvedUrl = null;

  /// Whether [baseUrl] answers /health as a SocietyFlow server.
  static Future<bool> _isSocietyFlow(String baseUrl) async {
    HttpClient? client;
    try {
      client = HttpClient()
        ..connectionTimeout = _timeout
        ..idleTimeout = _timeout;

      final request = await client
          .getUrl(Uri.parse('$baseUrl/health'))
          .timeout(_timeout);
      request.headers.set(HttpHeaders.acceptHeader, 'application/json');

      final response = await request.close().timeout(_timeout);
      if (response.statusCode != 200) return false;

      final body = await response
          .transform(const Utf8Decoder())
          .join()
          .timeout(_timeout);

      final decoded = jsonDecode(body);
      return decoded is Map && decoded['application'] == 'societyflow';
    } catch (_) {
      return false;
    } finally {
      client?.close(force: true);
    }
  }
}
