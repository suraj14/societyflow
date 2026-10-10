import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/config/api_config.dart';
import '../../../core/network/http_service.dart';
import '../models/society_document_model.dart';

class SocietyDocumentsState {
  final bool isLoading;
  final List<SocietyDocumentModel> documents;
  final String? error;

  const SocietyDocumentsState({this.isLoading = false, this.documents = const [], this.error});

  SocietyDocumentsState copyWith({bool? isLoading, List<SocietyDocumentModel>? documents, String? error}) =>
      SocietyDocumentsState(
        isLoading: isLoading ?? this.isLoading,
        documents: documents ?? this.documents,
        error: error,
      );
}

class SocietyDocumentsNotifier extends StateNotifier<SocietyDocumentsState> {
  SocietyDocumentsNotifier() : super(const SocietyDocumentsState());

  Future<void> load() async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.get(ApiConfig.societyDocuments);
    if (result.success && result.data != null) {
      final list = result.data!['data'] as List? ?? [];
      state = state.copyWith(
        isLoading: false,
        documents: list.whereType<Map<String, dynamic>>().map(SocietyDocumentModel.fromJson).toList(),
      );
    } else {
      state = state.copyWith(
        isLoading: false,
        error: result.message.isNotEmpty ? result.message : 'Could not load society documents',
      );
    }
  }

  /// "I have read this". Returns an error message, or null on success.
  Future<String?> acknowledge(int id) async {
    final result = await HttpService.instance.post(ApiConfig.societyDocumentAcknowledge(id));
    if (!result.success) {
      return result.message.isNotEmpty ? result.message : 'Could not record your acknowledgement';
    }
    state = state.copyWith(
      documents: [for (final d in state.documents) d.id == id ? d.copyWith(acknowledged: true) : d],
    );
    return null;
  }
}

final societyDocumentsProvider =
    StateNotifierProvider<SocietyDocumentsNotifier, SocietyDocumentsState>((ref) => SocietyDocumentsNotifier());
