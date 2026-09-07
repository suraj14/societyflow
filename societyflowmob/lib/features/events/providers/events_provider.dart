import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/event_model.dart';

class EventsState {
  final bool isLoading;
  final List<EventModel> events;
  final String? error;

  const EventsState({this.isLoading = false, this.events = const [], this.error});

  EventsState copyWith({bool? isLoading, List<EventModel>? events, String? error}) =>
      EventsState(isLoading: isLoading ?? this.isLoading, events: events ?? this.events, error: error);
}

class EventsNotifier extends StateNotifier<EventsState> {
  EventsNotifier() : super(const EventsState());

  Future<void> loadEvents() async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.get(ApiConfig.events);
    if (result.success && result.data != null) {
      final list = result.data!['data'] as List? ?? [];
      state = state.copyWith(isLoading: false, events: list.map((e) => EventModel.fromJson(e as Map<String, dynamic>)).toList());
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
    }
  }
}

final eventsProvider = StateNotifierProvider<EventsNotifier, EventsState>((ref) => EventsNotifier());
