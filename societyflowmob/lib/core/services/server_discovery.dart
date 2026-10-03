import 'dart:convert';
import 'package:flutter/foundation.dart' show visibleForTesting;
import 'dart:io';
import 'dart:developer' as dev;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';

/// Fast server discovery — finds Laravel server in under 3 seconds.
/// Strategy:
///   1. Try 10.0.2.2 (Android emulator → host loopback)
///   2. Try cached URL (instant if IP hasn't changed)
///   3. Try fallback URL from api_config.dart
///   4. Scan only the device's own subnet, IPs 1-30 and 100-120 (common DHCP ranges)
///   5. Give up and show error
class ServerDiscovery {
  static const String _cachedUrlKey = 'discovered_server_url';
  static const int _port = 8000;
  static const Duration _fastTimeout = Duration(milliseconds: 800);
  static const Duration _scanTimeout = Duration(milliseconds: 1200);

  static String? _resolvedUrl;

  /// Short-circuits discovery with a fixed URL.
  ///
  /// Discovery performs real socket and HTTP I/O against the local subnet,
  /// which cannot run under `flutter test` - it made the splash screen, and
  /// therefore the whole boot path, untestable. Setting this skips the scan.
  /// Production never assigns it.
  @visibleForTesting
  static String? overrideBaseUrl;

  /// The currently resolved base URL (available after resolveBaseUrl() completes).
  static String get currentBaseUrl =>
      overrideBaseUrl ?? _resolvedUrl ?? ApiConfig.baseUrl;

  static Future<String> resolveBaseUrl() async {
    if (overrideBaseUrl != null) return overrideBaseUrl!;
    // An explicit --dart-define=API_BASE_URL wins over everything, including
    // the subnet scan: the developer said where the server is.
    if (ApiConfig.hasOverride) return ApiConfig.overrideUrl;
    if (ApiConfig.useProduction) return ApiConfig.prodUrl;
    if (_resolvedUrl != null) return _resolvedUrl!;

    // 1. USB ADB reverse forwarding — phone connected via USB cable
    // "adb reverse tcp:8000 tcp:8000" makes 127.0.0.1:8000 on phone → PC's port 8000
    const adbUrl = 'http://127.0.0.1:$_port/api/v1';
    if (await _isReachable(adbUrl, _fastTimeout)) {
      _resolvedUrl = adbUrl;
      await _cacheUrl(adbUrl);
      dev.log('Server found (USB/ADB): $adbUrl', name: 'Discovery');
      return adbUrl;
    }

    // 2. Android emulator — always try 10.0.2.2 first (instant)
    const emulatorUrl = 'http://10.0.2.2:$_port/api/v1';
    if (await _isReachable(emulatorUrl, _fastTimeout)) {
      _resolvedUrl = emulatorUrl;
      dev.log('Server found (emulator): $emulatorUrl', name: 'Discovery');
      return emulatorUrl;
    }

    // 2. Try cached URL (fast path — avoids scan on every launch)
    final cached = await _getCachedUrl();
    if (cached != null && await _isReachable(cached, _fastTimeout)) {
      _resolvedUrl = cached;
      dev.log('Server OK (cached): $cached', name: 'Discovery');
      return cached;
    }
    if (cached != null) {
      await _clearCache();
      dev.log('Cached URL dead, scanning...', name: 'Discovery');
    }

    // 3. Try hardcoded fallback (updated in api_config.dart)
    if (await _isReachable(ApiConfig.devFallbackUrl, _fastTimeout)) {
      _resolvedUrl = ApiConfig.devFallbackUrl;
      await _cacheUrl(ApiConfig.devFallbackUrl);
      dev.log('Server found (fallback): ${ApiConfig.devFallbackUrl}', name: 'Discovery');
      return ApiConfig.devFallbackUrl;
    }

    // 4. Smart scan — only common DHCP ranges on device's subnet
    final found = await _smartScan();
    if (found != null) {
      _resolvedUrl = found;
      await _cacheUrl(found);
      dev.log('Server found (scan): $found', name: 'Discovery');
      return found;
    }

    // 5. Give up — return fallback so app shows proper error
    dev.log('Discovery failed', name: 'Discovery');
    _resolvedUrl = ApiConfig.devFallbackUrl;
    return ApiConfig.devFallbackUrl;
  }

  static Future<String> rescan() async {
    _resolvedUrl = null;
    await _clearCache();
    return resolveBaseUrl();
  }

  static void invalidate() => _resolvedUrl = null;

  /// Scans only likely IPs — common DHCP ranges to keep it fast
  static Future<String?> _smartScan() async {
    final subnet = await _getDeviceSubnet();
    if (subnet == null) return null;

    dev.log('Smart scan on $subnet.*', name: 'Discovery');

    // Scan common DHCP ranges in parallel — all at once, 1.2s timeout
    // Most routers assign: 1-30 (static/reserved) and 100-150 (DHCP pool)
    final candidates = <int>[
      ...List.generate(30, (i) => i + 1),   // 1-30
      ...List.generate(51, (i) => i + 100), // 100-150
      ...List.generate(10, (i) => i + 200), // 200-210
    ];

    final futures = candidates
        .map((i) => _checkUrl('http://$subnet.$i:$_port/api/v1'))
        .toList();

    final results = await Future.wait(futures);
    return results.firstWhere((r) => r != null, orElse: () => null);
  }

  static Future<String?> _getDeviceSubnet() async {
    try {
      final interfaces = await NetworkInterface.list(
        type: InternetAddressType.IPv4,
        includeLinkLocal: false,
      );
      for (final iface in interfaces) {
        for (final addr in iface.addresses) {
          final ip = addr.address;
          if (ip.startsWith('192.168.') || ip.startsWith('10.') || ip.startsWith('172.')) {
            final parts = ip.split('.');
            if (parts.length == 4) return '${parts[0]}.${parts[1]}.${parts[2]}';
          }
        }
      }
    } catch (_) {}
    return null;
  }

  static Future<String?> _checkUrl(String url) async {
    if (await _isReachable(url, _scanTimeout)) return url;
    return null;
  }

  /// Whether [baseUrl] is actually a SocietyFlow server.
  ///
  /// This used to be a bare `Socket.connect` - any machine on the subnet
  /// with something listening on port 8000 passed, and the app then sent it
  /// the user's bearer token. A printer, a router admin page or another
  /// developer's server would all have qualified.
  ///
  /// Now the port check is only a fast pre-filter; the host must also answer
  /// GET /health with the application marker before it is trusted.
  static Future<bool> _isReachable(String baseUrl, Duration timeout) async {
    final host = _hostOf(baseUrl);
    if (host == null) return false;

    // Cheap reject: skip the HTTP round trip for the vast majority of
    // scanned addresses that have nothing listening at all.
    try {
      final socket = await Socket.connect(host, _port, timeout: timeout);
      socket.destroy();
    } catch (_) {
      return false;
    }

    return _isSocietyFlow(baseUrl, timeout);
  }

  /// Confirms the server identifies itself as SocietyFlow.
  static Future<bool> _isSocietyFlow(String baseUrl, Duration timeout) async {
    HttpClient? client;
    try {
      client = HttpClient()
        ..connectionTimeout = timeout
        ..idleTimeout = timeout;

      final request = await client
          .getUrl(Uri.parse('$baseUrl/health'))
          .timeout(timeout);
      request.headers.set(HttpHeaders.acceptHeader, 'application/json');

      final response = await request.close().timeout(timeout);
      if (response.statusCode != 200) return false;

      final body = await response
          .transform(const Utf8Decoder())
          .join()
          .timeout(timeout);

      final decoded = jsonDecode(body);
      return decoded is Map && decoded['application'] == 'societyflow';
    } catch (_) {
      return false;
    } finally {
      client?.close(force: true);
    }
  }

  static String? _hostOf(String baseUrl) {
    final uri = Uri.tryParse(baseUrl);
    if (uri == null || uri.host.isEmpty) return null;
    return uri.host;
  }

  static Future<String?> _getCachedUrl() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_cachedUrlKey);
    } catch (_) {
      return null;
    }
  }

  static Future<void> _cacheUrl(String url) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_cachedUrlKey, url);
    } catch (_) {}
  }

  static Future<void> _clearCache() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_cachedUrlKey);
    } catch (_) {}
  }
}
