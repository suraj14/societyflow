import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/app_notification.dart';

class NotificationsState {
  final List<AppNotification> items;
  final int unread;
  final bool isLoading;
  final String? error;

  const NotificationsState({this.items = const [], this.unread = 0, this.isLoading = false, this.error});

  NotificationsState copyWith({List<AppNotification>? items, int? unread, bool? isLoading, String? error, bool clearError = false}) =>
      NotificationsState(
        items: items ?? this.items,
        unread: unread ?? this.unread,
        isLoading: isLoading ?? this.isLoading,
        error: clearError ? null : (error ?? this.error),
      );
}

class NotificationsNotifier extends StateNotifier<NotificationsState> {
  NotificationsNotifier() : super(const NotificationsState());

  /// The latest page and the unread count. A failed background refresh keeps
  /// what is already on screen; only the first load shows the error.
  Future<void> load() async {
    state = state.copyWith(isLoading: state.items.isEmpty, clearError: true);
    final result = await HttpService.instance.get(ApiConfig.notifications);
    final data = result.data?['data'];
    if (result.success && data is List) {
      state = state.copyWith(
        isLoading: false,
        items: data.whereType<Map>().map((m) => AppNotification.fromJson(Map<String, dynamic>.from(m))).toList(),
        unread: ((result.data?['meta'] as Map?)?['unread_count'] as num?)?.toInt() ?? 0,
      );
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
    }
  }

  Future<void> markRead(AppNotification n) async {
    if (n.read) return;
    // Optimistic: the badge drops at once, and is corrected from the server.
    state = state.copyWith(
      items: state.items.map((i) => i.id == n.id ? i.asRead() : i).toList(),
      unread: state.unread > 0 ? state.unread - 1 : 0,
    );
    final result = await HttpService.instance.post('${ApiConfig.notifications}/${n.id}/read');
    final count = (result.data?['data'] as Map?)?['unread_count'];
    if (result.success && count is num) state = state.copyWith(unread: count.toInt());
  }

  Future<void> markAllRead() async {
    final before = state;
    state = state.copyWith(items: state.items.map((i) => i.asRead()).toList(), unread: 0);
    final result = await HttpService.instance.post('${ApiConfig.notifications}/read-all');
    if (!result.success) state = before; // not saved: put it back rather than pretend
  }

  /// Back to nothing - called on logout so the next account never glimpses
  /// the last one's entries.
  void clear() => state = const NotificationsState();
}

final notificationsProvider = StateNotifierProvider<NotificationsNotifier, NotificationsState>((ref) => NotificationsNotifier());
