import 'dart:async';
import 'package:flutter/material.dart';

/// Mixin that adds automatic periodic refresh to any StatefulWidget.
/// Usage:
///   class _MyScreenState extends ConsumerState<MyScreen> with AutoRefreshMixin {
///     @override int get refreshIntervalSeconds => 30;
///     @override void onRefresh() => ref.read(myProvider.notifier).loadData();
///   }
mixin AutoRefreshMixin<T extends StatefulWidget> on State<T> {
  Timer? _refreshTimer;

  /// Override to set refresh interval. Default: 30 seconds.
  int get refreshIntervalSeconds => 30;

  /// Override with the refresh logic.
  void onAutoRefresh();

  void startAutoRefresh() {
    _refreshTimer?.cancel();
    _refreshTimer = Timer.periodic(
      Duration(seconds: refreshIntervalSeconds),
      (_) { if (mounted) onAutoRefresh(); },
    );
  }

  void stopAutoRefresh() {
    _refreshTimer?.cancel();
    _refreshTimer = null;
  }

  @override
  void dispose() {
    stopAutoRefresh();
    super.dispose();
  }
}
