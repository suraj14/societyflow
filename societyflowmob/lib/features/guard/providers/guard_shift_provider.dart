import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../models/guard_shift_model.dart';

class GuardShiftState {
  final GuardShiftOverview? overview;
  final bool isLoading;
  final String? error;

  /// A start/end/handover request is in flight.
  final bool busy;

  const GuardShiftState({this.overview, this.isLoading = false, this.error, this.busy = false});

  GuardShiftState copyWith({
    GuardShiftOverview? overview,
    bool? isLoading,
    String? error,
    bool clearError = false,
    bool? busy,
  }) =>
      GuardShiftState(
        overview: overview ?? this.overview,
        isLoading: isLoading ?? this.isLoading,
        error: clearError ? null : (error ?? this.error),
        busy: busy ?? this.busy,
      );
}

/// A refused or failed shift action, with the sentence for the guard.
class GuardShiftException implements Exception {
  final String message;
  const GuardShiftException(this.message);
  @override
  String toString() => message;
}

class GuardShiftNotifier extends StateNotifier<GuardShiftState> {
  GuardShiftNotifier() : super(const GuardShiftState());

  static const _base = '/guard/shift';

  /// Loads today's shift. A background refresh keeps what is on screen if it
  /// fails; the first load shows the error.
  Future<void> load() async {
    state = state.copyWith(isLoading: state.overview == null, clearError: true);
    final result = await HttpService.instance.get(_base);
    final data = result.data?['data'];
    if (result.success && data is Map) {
      state = state.copyWith(isLoading: false, overview: GuardShiftOverview.fromJson(Map<String, dynamic>.from(data)));
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
    }
  }

  Future<void> start() => _act('$_base/start');
  Future<void> end() => _act('$_base/end');

  Future<void> saveHandover({String? notes, String? pendingVisitors, String? activePasses}) => _act(
        '$_base/handover',
        body: {
          if (notes != null && notes.isNotEmpty) 'notes': notes,
          if (pendingVisitors != null && pendingVisitors.isNotEmpty) 'pending_visitors_note': pendingVisitors,
          if (activePasses != null && activePasses.isNotEmpty) 'active_passes_note': activePasses,
        },
      );

  Future<void> _act(String path, {Map<String, dynamic>? body}) async {
    if (state.busy) return;
    state = state.copyWith(busy: true);
    final result = await HttpService.instance.post(path, body: body);
    if (!result.success) {
      state = state.copyWith(busy: false);
      throw GuardShiftException(result.message.isNotEmpty ? result.message : 'That did not work. Please try again.');
    }
    state = state.copyWith(busy: false);
    await load(); // the server's own picture of the shift, not a guess
  }
}

final guardShiftProvider = StateNotifierProvider<GuardShiftNotifier, GuardShiftState>((ref) => GuardShiftNotifier());
