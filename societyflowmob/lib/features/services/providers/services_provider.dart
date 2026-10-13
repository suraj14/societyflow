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

  /// Providers this society lists for one service.
  List<ServiceProviderModel> providersFor(int serviceId) => providers.where((p) => p.serviceId == serviceId).toList();

  ServicesState copyWith({bool? isLoading, List<ServiceModel>? services, List<ServiceProviderModel>? providers, String? error}) =>
      ServicesState(isLoading: isLoading ?? this.isLoading, services: services ?? this.services, providers: providers ?? this.providers, error: error);
}

class ServicesNotifier extends StateNotifier<ServicesState> {
  ServicesNotifier() : super(const ServicesState());

  /// The directory is the society's own active providers (`/service-providers`); the catalogue (`/services`) only adds
  /// categories that have no provider yet, which the directory does not show.
  Future<void> loadServices() async {
    state = state.copyWith(isLoading: true, error: null);
    final r2 = await HttpService.instance.get(ApiConfig.serviceProviders);
    final r1 = await HttpService.instance.get(ApiConfig.services);
    state = state.copyWith(
      isLoading: false,
      services: r1.success ? (r1.data!['data'] as List? ?? []).map((s) => ServiceModel.fromJson(s as Map<String, dynamic>)).toList() : state.services,
      providers: r2.success ? (r2.data!['data'] as List? ?? []).map((p) => ServiceProviderModel.fromJson(p as Map<String, dynamic>)).toList() : state.providers,
      // Without the providers there is nothing to show: say so instead of presenting an empty directory.
      error: r2.success ? null : (r2.message.isNotEmpty ? r2.message : 'Could not load service providers'),
    );
  }
}
final servicesProvider = StateNotifierProvider<ServicesNotifier, ServicesState>((ref) => ServicesNotifier());
