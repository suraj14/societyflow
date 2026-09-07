import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/notice_model.dart';

class NoticesState {
  final bool isLoading;
  final List<NoticeModel> notices;
  final String? error;

  const NoticesState({this.isLoading = false, this.notices = const [], this.error});

  NoticesState copyWith({bool? isLoading, List<NoticeModel>? notices, String? error}) =>
      NoticesState(isLoading: isLoading ?? this.isLoading, notices: notices ?? this.notices, error: error);
}

class NoticesNotifier extends StateNotifier<NoticesState> {
  NoticesNotifier() : super(const NoticesState());

  Future<void> loadNotices() async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.get(ApiConfig.notices);
    if (result.success && result.data != null) {
      final list = result.data!['data'] as List? ?? [];
      state = state.copyWith(isLoading: false, notices: list.map((n) => NoticeModel.fromJson(n as Map<String, dynamic>)).toList());
    } else {
      state = state.copyWith(isLoading: false, error: result.message.isNotEmpty ? result.message : 'Failed to load notices');
    }
  }
}

final noticesProvider = StateNotifierProvider<NoticesNotifier, NoticesState>((ref) => NoticesNotifier());
