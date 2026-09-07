import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';

class HomeState {
  final bool isLoading;
  final int pendingBills, visitorsToday, openComplaints, activeNotices;
  final List<Map<String, dynamic>> notices, recentActivities;
  final String? error;

  const HomeState({
    this.isLoading = false, this.pendingBills = 0, this.visitorsToday = 0,
    this.openComplaints = 0, this.activeNotices = 0,
    this.notices = const [], this.recentActivities = const [], this.error,
  });

  HomeState copyWith({bool? isLoading, int? pendingBills, int? visitorsToday, int? openComplaints, int? activeNotices, List<Map<String, dynamic>>? notices, List<Map<String, dynamic>>? recentActivities, String? error}) =>
      HomeState(
        isLoading: isLoading ?? this.isLoading, pendingBills: pendingBills ?? this.pendingBills,
        visitorsToday: visitorsToday ?? this.visitorsToday, openComplaints: openComplaints ?? this.openComplaints,
        activeNotices: activeNotices ?? this.activeNotices, notices: notices ?? this.notices,
        recentActivities: recentActivities ?? this.recentActivities, error: error,
      );
}

class HomeNotifier extends StateNotifier<HomeState> {
  HomeNotifier() : super(const HomeState());

  Future<void> loadDashboard() async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.get(ApiConfig.dashboard);
    if (result.success && result.data != null) {
      final data = result.data!['data'] ?? result.data!;
      state = state.copyWith(
        isLoading: false,
        pendingBills:     (data['pending_bills'] as num?)?.toInt() ?? 0,
        visitorsToday:    (data['visitors_today'] as num?)?.toInt() ?? 0,
        openComplaints:   (data['open_complaints'] as num?)?.toInt() ?? 0,
        activeNotices:    (data['active_notices'] as num?)?.toInt() ?? 0,
        notices:          List<Map<String, dynamic>>.from(data['notices'] ?? []),
        recentActivities: List<Map<String, dynamic>>.from(data['recent_activities'] ?? []),
      );
    } else {
      state = state.copyWith(isLoading: false, error: result.message.isNotEmpty ? result.message : 'Failed to load dashboard');
    }
  }
}

final homeProvider = StateNotifierProvider<HomeNotifier, HomeState>((ref) => HomeNotifier());
