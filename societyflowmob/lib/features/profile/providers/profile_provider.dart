import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../../auth/providers/auth_provider.dart';

class ProfileNotifier extends StateNotifier<AsyncValue<void>> {
  final Ref _ref;
  ProfileNotifier(this._ref) : super(const AsyncValue.data(null));

  /// Name/phone only travel as before (JSON PUT). When a photo is being
  /// changed - [avatarPath] to replace it, [removeAvatar] to clear it - the
  /// same endpoint is called as a multipart POST (PHP cannot read a
  /// multipart body on PUT). Neither set keeps the current photo.
  Future<void> updateProfile({
    required String name,
    String? phone,
    String? avatarPath,
    bool removeAvatar = false,
    void Function(int sent, int total)? onProgress,
  }) async {
    state = const AsyncValue.loading();
    final changingPhoto = avatarPath != null || removeAvatar;
    final result = changingPhoto
        ? await HttpService.instance.postMultipart(
            ApiConfig.profile,
            fields: {
              'name': name,
              if (phone != null) 'phone': phone,
              if (removeAvatar && avatarPath == null) 'remove_avatar': '1',
            },
            filePaths: avatarPath != null ? {'avatar': avatarPath} : null,
            onSendProgress: onProgress,
          )
        : await HttpService.instance.put(ApiConfig.profile, body: {'name': name, if (phone != null) 'phone': phone});
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
