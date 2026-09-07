import 'dart:io';

/// Lightweight connectivity check.
/// Does not depend on any plugin — uses raw socket.
class NetworkChecker {
  static Future<bool> isConnected() async {
    try {
      final result = await InternetAddress.lookup('google.com')
          .timeout(const Duration(seconds: 5));
      return result.isNotEmpty && result.first.rawAddress.isNotEmpty;
    } catch (_) {
      return false;
    }
  }
}
