import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/facility_model.dart';

class FacilitiesState {
  final bool isLoading;
  final List<FacilityModel> facilities;
  final List<FacilityBookingModel> bookings;
  final String? error;

  const FacilitiesState({
    this.isLoading = false, this.facilities = const [],
    this.bookings = const [], this.error,
  });

  FacilitiesState copyWith({bool? isLoading, List<FacilityModel>? facilities, List<FacilityBookingModel>? bookings, String? error}) =>
      FacilitiesState(
        isLoading: isLoading ?? this.isLoading,
        facilities: facilities ?? this.facilities,
        bookings: bookings ?? this.bookings,
        error: error,
      );
}

class FacilitiesNotifier extends StateNotifier<FacilitiesState> {
  FacilitiesNotifier() : super(const FacilitiesState());

  Future<void> loadFacilities() async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.get(ApiConfig.facilities);
    if (result.success && result.data != null) {
      final list = result.data!['data'] as List? ?? [];
      state = state.copyWith(isLoading: false, facilities: list.map((f) => FacilityModel.fromJson(f as Map<String, dynamic>)).toList());
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
    }
  }

  Future<void> loadBookings() async {
    final result = await HttpService.instance.get(ApiConfig.facilityBookings);
    if (result.success && result.data != null) {
      final list = result.data!['data'] as List? ?? [];
      state = state.copyWith(bookings: list.map((b) => FacilityBookingModel.fromJson(b as Map<String, dynamic>)).toList());
    }
  }

  Future<void> bookFacility({
    required int facilityId,
    required String bookingDate,
    required String startTime,
    required String endTime,
    String? purpose,
  }) async {
    final result = await HttpService.instance.post(ApiConfig.facilityBookings, body: {
      'facility_id': facilityId,
      'booking_date': bookingDate,
      'start_time': startTime,
      'end_time': endTime,
      if (purpose != null) 'purpose': purpose,
    });
    if (result.success) {
      await loadBookings();
    } else {
      throw Exception(result.message);
    }
  }

  Future<void> cancelBooking(int id) async {
    final result = await HttpService.instance.post('${ApiConfig.facilityBookings}/$id/cancel');
    if (result.success) {
      final updated = state.bookings.map((b) => b.id == id ? FacilityBookingModel(id: b.id, facilityName: b.facilityName, bookingDate: b.bookingDate, startTime: b.startTime, endTime: b.endTime, purpose: b.purpose, status: 'cancelled', createdAt: b.createdAt) : b).toList();
      state = state.copyWith(bookings: updated);
    } else {
      throw Exception(result.message);
    }
  }
}

final facilitiesProvider = StateNotifierProvider<FacilitiesNotifier, FacilitiesState>((ref) => FacilitiesNotifier());
