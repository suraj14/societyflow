import 'dart:io';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/complaint_model.dart';

class ComplaintsState {
  final bool isLoading;
  final List<ComplaintModel> complaints;
  final List<String> categories;
  final String? error;

  const ComplaintsState({this.isLoading = false, this.complaints = const [], this.categories = const [], this.error});

  ComplaintsState copyWith({bool? isLoading, List<ComplaintModel>? complaints, List<String>? categories, String? error}) =>
      ComplaintsState(isLoading: isLoading ?? this.isLoading, complaints: complaints ?? this.complaints, categories: categories ?? this.categories, error: error);
}

class ComplaintsNotifier extends StateNotifier<ComplaintsState> {
  ComplaintsNotifier() : super(const ComplaintsState());

  Future<void> loadComplaints() async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.get(ApiConfig.complaints);
    if (result.success && result.data != null) {
      final data = result.data!['data'] as List? ?? [];
      state = state.copyWith(isLoading: false, complaints: data.map((c) => ComplaintModel.fromJson(c as Map<String, dynamic>)).toList());
    } else {
      state = state.copyWith(isLoading: false, error: result.message.isNotEmpty ? result.message : 'Failed to load complaints');
    }
  }

  Future<void> loadCategories() async {
    final result = await HttpService.instance.get('${ApiConfig.complaints}/categories');
    if (result.success && result.data != null) {
      state = state.copyWith(categories: (result.data!['data'] as List? ?? []).map((c) => c.toString()).toList());
    }
  }

  Future<void> raiseComplaint({required String title, required String description, String? category, File? image}) async {
    final fields = <String, String>{'title': title, 'description': description, if (category != null) 'category': category};
    final result = await HttpService.instance.postMultipart(ApiConfig.complaints, fields: fields, filePaths: image != null ? {'image': image.path} : null);
    if (result.success && result.data != null) {
      state = state.copyWith(complaints: [ComplaintModel.fromJson(result.data!['data'] as Map<String, dynamic>), ...state.complaints]);
    } else {
      throw Exception(result.message.isNotEmpty ? result.message : 'Failed to submit complaint');
    }
  }
  Future<ComplaintModel?> fetchComplaint(int id) async {
    final result = await HttpService.instance.get('${ApiConfig.complaints}/$id');
    if (result.success && result.data != null) {
      final fresh = ComplaintModel.fromJson(result.data!['data'] as Map<String, dynamic>);
      // Update in the list too
      state = state.copyWith(
        complaints: state.complaints.map((c) => c.id == id ? fresh : c).toList(),
      );
      return fresh;
    }
    return null;
  }
}

final complaintsProvider = StateNotifierProvider<ComplaintsNotifier, ComplaintsState>((ref) => ComplaintsNotifier());
