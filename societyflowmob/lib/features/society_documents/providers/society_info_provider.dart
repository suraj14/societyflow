import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/config/api_config.dart';
import '../../../core/network/http_service.dart';
import '../models/society_info_model.dart';

class SocietyInfoState {
  final bool isLoading;
  final SocietyInfoModel? info;
  final String? error;

  const SocietyInfoState({this.isLoading = false, this.info, this.error});
}

class SocietyInfoNotifier extends StateNotifier<SocietyInfoState> {
  SocietyInfoNotifier() : super(const SocietyInfoState());

  Future<void> load() async {
    state = SocietyInfoState(isLoading: true, info: state.info);
    final result = await HttpService.instance.get(ApiConfig.societyInfo);
    final data = result.data?['data'];
    if (result.success && data is Map<String, dynamic>) {
      state = SocietyInfoState(info: SocietyInfoModel.fromJson(data));
    } else {
      // Keep whatever was already on screen; just report the problem.
      state = SocietyInfoState(
        info: state.info,
        error: result.message.isNotEmpty ? result.message : 'Could not load society info',
      );
    }
  }
}

final societyInfoProvider =
    StateNotifierProvider<SocietyInfoNotifier, SocietyInfoState>((ref) => SocietyInfoNotifier());
