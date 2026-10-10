import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/access_pass_model.dart';

class AccessPassesState {
  final bool isLoading;
  final bool isSaving;
  final List<AccessPassModel> passes;
  final String? error;

  const AccessPassesState({
    this.isLoading = false,
    this.isSaving = false,
    this.passes = const [],
    this.error,
  });

  AccessPassesState copyWith({
    bool? isLoading,
    bool? isSaving,
    List<AccessPassModel>? passes,
    String? error,
  }) =>
      AccessPassesState(
        isLoading: isLoading ?? this.isLoading,
        isSaving: isSaving ?? this.isSaving,
        passes: passes ?? this.passes,
        error: error,
      );
}

/// Reading and editing gate passes from the app.
///
/// Every rule - who may edit, whether a revoked pass is touchable, whether
/// a date is allowed - is enforced by AccessPassEditService on the server,
/// which is the same code the web screen goes through. Nothing here
/// re-implements those checks; the UI only reflects the flags the server
/// sends (`can_edit`, `is_expired`, ...) and surfaces whatever the server
/// says when it refuses.
class AccessPassesNotifier extends StateNotifier<AccessPassesState> {
  AccessPassesNotifier() : super(const AccessPassesState());

  Future<void> load() async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.get(ApiConfig.accessPasses);

    if (result.success && result.data != null) {
      final list = result.data!['data'] as List? ?? [];
      state = state.copyWith(
        isLoading: false,
        passes: list
            .map((p) => AccessPassModel.fromJson(p as Map<String, dynamic>))
            .toList(),
      );
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
    }
  }

  /// Push the validity out. Only the fields given are sent, so extending a
  /// date never clears a time window the screen did not ask about - the
  /// server applies the same rule.
  ///
  /// Returns null on success, or the server's own message on refusal, so
  /// the caller shows the real reason rather than a generic failure.
  Future<String?> extend(
    int passId, {
    required String validUntil,
    List<int>? allowedDays,
    String? startTime,
    String? endTime,
  }) async {
    if (state.isSaving) return null; // guards against a double tap
    state = state.copyWith(isSaving: true, error: null);

    final result = await HttpService.instance.post(
      ApiConfig.accessPassExtend(passId),
      body: {
        'valid_until': validUntil,
        if (allowedDays != null) 'allowed_days': allowedDays,
        if (startTime != null) 'start_time': startTime,
        if (endTime != null) 'end_time': endTime,
      },
    );

    state = state.copyWith(isSaving: false);

    if (result.success) {
      await load();
      return null;
    }

    state = state.copyWith(error: result.message);
    return result.message;
  }

  /// Full edit. `validFrom` is required by the server, mirroring the web form.
  Future<String?> update(
    int passId, {
    required String personName,
    required String validFrom,
    String? personPhone,
    String? category,
    int? flatId,
    List<int>? allowedDays,
    String? startTime,
    String? endTime,
    String? validUntil,
  }) async {
    if (state.isSaving) return null;
    state = state.copyWith(isSaving: true, error: null);

    final result = await HttpService.instance.put(
      ApiConfig.accessPassDetail(passId),
      body: {
        'person_name': personName,
        'valid_from': validFrom,
        if (personPhone != null) 'person_phone': personPhone,
        if (category != null) 'category': category,
        if (flatId != null) 'flat_id': flatId,
        if (allowedDays != null) 'allowed_days': allowedDays,
        if (startTime != null) 'start_time': startTime,
        if (endTime != null) 'end_time': endTime,
        if (validUntil != null) 'valid_until': validUntil,
      },
    );

    state = state.copyWith(isSaving: false);

    if (result.success) {
      await load();
      return null;
    }

    state = state.copyWith(error: result.message);
    return result.message;
  }
}

final accessPassesProvider =
    StateNotifierProvider<AccessPassesNotifier, AccessPassesState>(
  (ref) => AccessPassesNotifier(),
);
