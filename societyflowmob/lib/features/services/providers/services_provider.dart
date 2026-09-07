import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/service_model.dart';

class ServicesState {
  final bool isLoading;
  final List<ServiceModel> services;
  final List<ServiceProviderModel> providers;
  final String? error;

  const ServicesState({this.isLoading = false, this.services = const [], this.providers = const [], this.error});

  ServicesState copyWith({bool? isLoading, List<ServiceModel>? services, List<ServiceProviderModel>? providers, String? error}) =>
      ServicesState(isLoading: isLoading ?? this.isLoading, services: services ?? this.services, providers: providers ?? this.providers, error: error);
}

class ServicesNotifier extends StateNotifier<ServicesState> {
  ServicesNotifier() : super(const ServicesState());

  Future<void> loadServices() async {
    state = state.copyWith(isLoading: true, error: null);
    final r1 = await HttpService.instance.get(ApiConfig.services);
    final r2 = await HttpService.instance.get(ApiConfig.serviceProviders);
    state = state.copyWith(
      isLoading: false,
      services: r1.success ? (r1.data!['data'] as List? ?? []).map((s) => ServiceModel.fromJson(s as Map<String, dynamic>)).toList() : [],
      providers: r2.success ? (r2.data!['data'] as List? ?? []).map((p) => ServiceProviderModel.fromJson(p as Map<String, dynamic>)).toList() : [],
      error: (!r1.success && !r2.success) ? r1.message : null,
    );
  }

  Future<void> requestService({required int serviceId, required String description, String? preferredDate}) async {
    final result = await HttpService.instance.post(ApiConfig.serviceRequests, body: {
      'service_id': serviceId,
      'description': description,
      if (preferredDate != null) 'preferred_date': preferredDate,
    });
    if (!result.success) throw Exception(result.message);
  }
}

final servicesProvider = StateNotifierProvider<ServicesNotifier, ServicesState>((ref) => ServicesNotifier());
