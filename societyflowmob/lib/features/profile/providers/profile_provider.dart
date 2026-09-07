import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../../auth/providers/auth_provider.dart';

class ProfileNotifier extends StateNotifier<AsyncValue<void>> {
  final Ref _ref;
  ProfileNotifier(this._ref) : super(const AsyncValue.data(null));

  Future<void> updateProfile({required String name, String? phone}) async {
    state = const AsyncValue.loading();
    final result = await HttpService.instance.put(ApiConfig.profile, body: {'name': name, if (phone != null) 'phone': phone});
    if (result.success) {
      await _ref.read(authProvider.notifier).refreshProfile();
      state = const AsyncValue.data(null);
    } else {
      state = AsyncValue.error(result.message, StackTrace.current);
      throw Exception(result.message);
    }
  }

  Future<void> changePassword({required String currentPassword, required String newPassword}) async {
    state = const AsyncValue.loading();
    final result = await HttpService.instance.post(ApiConfig.changePassword, body: {'current_password': currentPassword, 'password': newPassword, 'password_confirmation': newPassword});
    if (result.success) {
      state = const AsyncValue.data(null);
    } else {
      state = AsyncValue.error(result.message, StackTrace.current);
      throw Exception(result.message);
    }
  }
}

final profileProvider = StateNotifierProvider<ProfileNotifier, AsyncValue<void>>((ref) => ProfileNotifier(ref));
