import 'app_logger.dart';

/// Mixin for safe API calls with automatic error logging.
/// Add to any StateNotifier: class MyNotifier extends StateNotifier<S> with SafeApiCall
mixin SafeApiCall {
  /// Wraps an async call with try-catch and logging.
  /// Returns null on failure instead of throwing.
  Future<T?> safeCall<T>(
    Future<T> Function() call, {
    String context = 'API',
    T? fallback,
  }) async {
    try {
      return await call();
    } catch (e, stack) {
      AppLogger.error('$context failed: $e', error: e, stackTrace: stack);
      return fallback;
    }
  }
}
