import 'dart:developer' as dev;

/// Centralised logger. Use instead of print() everywhere.
/// Does not affect any existing code — opt-in only.
class AppLogger {
  static void info(String message, {String name = 'App'}) {
    dev.log('[INFO] $message', name: name);
  }

  static void error(String message, {Object? error, StackTrace? stackTrace, String name = 'App'}) {
    dev.log('[ERROR] $message', name: name, error: error, stackTrace: stackTrace);
  }

  static void api(String message) => dev.log(message, name: 'API');

  static void warn(String message, {String name = 'App'}) {
    dev.log('[WARN] $message', name: name);
  }
}
