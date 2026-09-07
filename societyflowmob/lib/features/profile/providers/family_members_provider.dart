import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/family_member_model.dart';

class FamilyMembersState {
  final bool isLoading;
  final List<FamilyMemberModel> members;
  final String? error;

  const FamilyMembersState({
    this.isLoading = false,
    this.members = const [],
    this.error,
  });

  FamilyMembersState copyWith({bool? isLoading, List<FamilyMemberModel>? members, String? error}) =>
      FamilyMembersState(
        isLoading: isLoading ?? this.isLoading,
        members:   members   ?? this.members,
        error:     error,
      );
}

class FamilyMembersNotifier extends StateNotifier<FamilyMembersState> {
  FamilyMembersNotifier() : super(const FamilyMembersState());

  Future<void> load() async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final result = await HttpService.instance.get(ApiConfig.familyMembers);
      if (result.success && result.data != null) {
        final list = result.data!['data'] as List? ?? [];
        state = state.copyWith(
          isLoading: false,
          members: list.map((m) => FamilyMemberModel.fromJson(m as Map<String, dynamic>)).toList(),
        );
      } else {
        state = state.copyWith(isLoading: false, error: result.message);
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }
}

final familyMembersProvider = StateNotifierProvider<FamilyMembersNotifier, FamilyMembersState>(
  (ref) => FamilyMembersNotifier(),
);
