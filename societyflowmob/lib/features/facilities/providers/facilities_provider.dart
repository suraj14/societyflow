import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/facility_model.dart';

/// A request the server refused or that never arrived, with the sentence to
/// show the resident. Screens print [message] as is - never `e.toString()`,
/// which put "Exception: " in front of every error.
class FacilityException implements Exception {
  final String message;
  const FacilityException(this.message);
  @override
  String toString() => message;
}

class FacilitiesState {
  final bool isLoading;
  final bool bookingsLoading;
  final List<FacilityModel> facilities;
  final List<FacilityBookingModel> bookings;
  final String? error;
  final String? bookingsError;

  const FacilitiesState({
    this.isLoading = false,
    this.bookingsLoading = false,
    this.facilities = const [],
    this.bookings = const [],
    this.error,
    this.bookingsError,
  });

  FacilitiesState copyWith({
    bool? isLoading,
    bool? bookingsLoading,
    List<FacilityModel>? facilities,
    List<FacilityBookingModel>? bookings,
    String? error,
    String? bookingsError,
    bool clearError = false,
    bool clearBookingsError = false,
  }) =>
      FacilitiesState(
        isLoading: isLoading ?? this.isLoading,
        bookingsLoading: bookingsLoading ?? this.bookingsLoading,
        facilities: facilities ?? this.facilities,
        bookings: bookings ?? this.bookings,
        error: clearError ? null : (error ?? this.error),
        bookingsError: clearBookingsError ? null : (bookingsError ?? this.bookingsError),
      );
}

class FacilitiesNotifier extends StateNotifier<FacilitiesState> {
  FacilitiesNotifier() : super(const FacilitiesState());

  /// The list is replaced, never blanked: a background refresh must not swap
  /// the screen for a skeleton, and a failed refresh keeps what is on show.
  Future<void> loadFacilities() async {
    state = state.copyWith(isLoading: state.facilities.isEmpty, clearError: true);
    final result = await HttpService.instance.get(ApiConfig.facilities);
    if (result.success && result.data != null) {
      final list = result.data!['data'] as List? ?? [];
      state = state.copyWith(
        isLoading: false,
        facilities: list.whereType<Map>().map((f) => FacilityModel.fromJson(Map<String, dynamic>.from(f))).toList(),
      );
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
    }
  }

  Future<void> loadBookings() async {
    state = state.copyWith(bookingsLoading: state.bookings.isEmpty, clearBookingsError: true);
    final result = await HttpService.instance.get(ApiConfig.facilityBookings);
    if (result.success && result.data != null) {
      final list = result.data!['data'] as List? ?? [];
      state = state.copyWith(
        bookingsLoading: false,
        bookings: list.whereType<Map>().map((b) => FacilityBookingModel.fromJson(Map<String, dynamic>.from(b))).toList(),
      );
    } else {
      state = state.copyWith(bookingsLoading: false, bookingsError: result.message);
    }
  }

  /// One day of one facility as a booking grid.
  Future<FacilityAvailability> availability({required int facilityId, required String date}) async {
    final result = await HttpService.instance.get('${ApiConfig.facilities}/$facilityId/availability?date=$date');
    final data = result.data?['data'];
    if (result.success && data is Map) {
      return FacilityAvailability.fromJson(Map<String, dynamic>.from(data));
    }
    throw FacilityException(result.message.isNotEmpty ? result.message : 'Could not load availability.');
  }

  Future<FacilityBookingModel> bookFacility({
    required int facilityId,
    required String bookingDate,
    required String startTime,
    required String endTime,
    String? purpose,
    int? expectedGuests,
  }) async {
    final result = await HttpService.instance.post(ApiConfig.facilityBookings, body: {
      'facility_id': facilityId,
      'booking_date': bookingDate,
      'start_time': startTime,
      'end_time': endTime,
      if (purpose != null && purpose.isNotEmpty) 'purpose': purpose,
      if (expectedGuests != null) 'expected_guests': expectedGuests,
    });
    final data = result.data?['data'];
    if (result.success && data is Map) {
      final booking = FacilityBookingModel.fromJson(Map<String, dynamic>.from(data));
      state = state.copyWith(bookings: [booking, ...state.bookings.where((b) => b.id != booking.id)]);
      return booking;
    }
    throw FacilityException(result.message.isNotEmpty ? result.message : 'The booking could not be made.');
  }

  Future<void> cancelBooking(int id, {String? reason}) async {
    final result = await HttpService.instance.post(
      '${ApiConfig.facilityBookings}/$id/cancel',
      body: {if (reason != null && reason.isNotEmpty) 'reason': reason},
    );
    if (result.success) {
      state = state.copyWith(
        bookings: state.bookings.map((b) => b.id == id ? b.copyWith(status: 'cancelled', canCancel: false) : b).toList(),
      );
    } else {
      throw FacilityException(result.message.isNotEmpty ? result.message : 'The booking could not be cancelled.');
    }
  }
}

final facilitiesProvider = StateNotifierProvider<FacilitiesNotifier, FacilitiesState>((ref) => FacilitiesNotifier());
