import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/visitor_model.dart';

// ── Dashboard Stats ───────────────────────────────────────────────────────────

class VisitorStats {
  final int todayTotal, pending, checkedIn, checkedOut;
  const VisitorStats({
    this.todayTotal = 0,
    this.pending = 0,
    this.checkedIn = 0,
    this.checkedOut = 0,
  });

  factory VisitorStats.fromJson(Map<String, dynamic> j) => VisitorStats(
    todayTotal: (j['today_total'] as num?)?.toInt() ?? 0,
    pending:    (j['pending']     as num?)?.toInt() ?? 0,
    checkedIn:  (j['checked_in']  as num?)?.toInt() ?? 0,
    checkedOut: (j['checked_out'] as num?)?.toInt() ?? 0,
  );
}

// ── Flat item for guard picker ────────────────────────────────────────────────

class FlatItem {
  final int id;
  final String display;
  const FlatItem({required this.id, required this.display});

  factory FlatItem.fromJson(Map<String, dynamic> j) => FlatItem(
    id:      j['id'] as int,
    display: j['display']?.toString() ?? '',
  );
}

// ── State ─────────────────────────────────────────────────────────────────────

class VisitorsState {
  final bool isLoading;
  final List<VisitorModel> visitors;
  final List<VisitorModel> allVisitors;
  final List<FlatItem> flats;
  final VisitorStats stats;
  final String? error;

  const VisitorsState({
    this.isLoading = false,
    this.visitors = const [],
    this.allVisitors = const [],
    this.flats = const [],
    this.stats = const VisitorStats(),
    this.error,
  });

  VisitorsState copyWith({
    bool? isLoading,
    List<VisitorModel>? visitors,
    List<VisitorModel>? allVisitors,
    List<FlatItem>? flats,
    VisitorStats? stats,
    String? error,
    bool clearError = false,
  }) =>
      VisitorsState(
        isLoading:   isLoading   ?? this.isLoading,
        visitors:    visitors    ?? this.visitors,
        allVisitors: allVisitors ?? this.allVisitors,
        flats:       flats       ?? this.flats,
        stats:       stats       ?? this.stats,
        error:       clearError ? null : (error ?? this.error),
      );
}

// ── Notifier ──────────────────────────────────────────────────────────────────

class VisitorsNotifier extends StateNotifier<VisitorsState> {
  VisitorsNotifier() : super(const VisitorsState());

  bool _isLoadingAll = false;

  // ── Parse visitor list from API response ──────────────────────────────────

  static List<VisitorModel> _parseVisitors(Map<String, dynamic>? body) {
    if (body == null) return [];
    final raw = body['data'];
    List items;
    if (raw is List) {
      items = raw;
    } else if (raw is Map && raw['data'] is List) {
      items = raw['data'] as List;
    } else {
      return [];
    }
    final result = <VisitorModel>[];
    for (final v in items) {
      try {
        if (v is Map<String, dynamic>) result.add(VisitorModel.fromJson(v));
      } catch (_) {}
    }
    return result;
  }

  static List<FlatItem> _parseFlats(Map<String, dynamic>? body) {
    if (body == null) return [];
    final raw = body['data'];
    if (raw is! List) return [];
    final result = <FlatItem>[];
    for (final f in raw) {
      try {
        if (f is Map<String, dynamic>) result.add(FlatItem.fromJson(f));
      } catch (_) {}
    }
    return result;
  }

  static VisitorStats? _parseStats(Map<String, dynamic>? body) {
    if (body == null) return null;
    final d = body['data'];
    if (d is Map<String, dynamic>) return VisitorStats.fromJson(d);
    return null;
  }

  // ── Full load (initial or manual refresh) ─────────────────────────────────
  // Shows loading skeleton only on first load (when list is empty).

  Future<void> loadAll() async {
    if (_isLoadingAll) return; // prevent concurrent loads
    _isLoadingAll = true;

    // Show skeleton only if no data yet
    final showSkeleton = state.allVisitors.isEmpty;
    if (showSkeleton) {
      state = state.copyWith(isLoading: true, clearError: true);
    } else {
      state = state.copyWith(clearError: true);
    }

    try {
      // Run all three requests in parallel
      final results = await Future.wait([
        HttpService.instance.get(ApiConfig.visitors),
        HttpService.instance.get('${ApiConfig.visitors}/dashboard'),
        HttpService.instance.get('${ApiConfig.visitors}/flats'),
      ]);

      final vResult = results[0];
      final sResult = results[1];
      final fResult = results[2];

      // Only update each piece if the request succeeded
      final newList  = vResult.success ? _parseVisitors(vResult.data) : state.allVisitors;
      final newStats = sResult.success ? (_parseStats(sResult.data) ?? state.stats) : state.stats;
      final newFlats = fResult.success ? _parseFlats(fResult.data) : state.flats;

      if (!mounted) return;
      state = state.copyWith(
        isLoading: false,
        visitors:    newList,
        allVisitors: newList,
        stats:       newStats,
        flats:       newFlats,
        clearError:  true,
      );
    } catch (e) {
      if (!mounted) return;
      // Keep existing data — only show error if we had nothing
      state = state.copyWith(
        isLoading: false,
        error: state.allVisitors.isEmpty ? e.toString() : null,
      );
    } finally {
      _isLoadingAll = false;
    }
  }

  // ── Silent background refresh (auto-refresh timer) ────────────────────────
  // Never wipes the list, never shows skeleton.

  Future<void> loadVisitors() async {
    try {
      final vResult = await HttpService.instance.get(ApiConfig.visitors);
      if (vResult.success && mounted) {
        final newList = _parseVisitors(vResult.data);
        if (newList.isNotEmpty) {
          state = state.copyWith(visitors: newList, allVisitors: newList);
        }
      }

      final sResult = await HttpService.instance.get('${ApiConfig.visitors}/dashboard');
      if (sResult.success && mounted) {
        final newStats = _parseStats(sResult.data);
        if (newStats != null) state = state.copyWith(stats: newStats);
      }
    } catch (_) {
      // Silent fail — never touch existing state
    }
  }

  // ── Search filter ─────────────────────────────────────────────────────────

  void search(String query) {
    if (query.isEmpty) {
      state = state.copyWith(visitors: state.allVisitors);
      return;
    }
    final q = query.toLowerCase();
    state = state.copyWith(
      visitors: state.allVisitors.where((v) =>
        v.visitorName.toLowerCase().contains(q) ||
        v.visitorPhone.contains(q) ||
        (v.flatNumber?.toLowerCase().contains(q) ?? false) ||
        (v.buildingName?.toLowerCase().contains(q) ?? false)
      ).toList(),
    );
  }

  // ── Actions ───────────────────────────────────────────────────────────────

  Future<void> allowVisitor(int id) async {
    final result = await HttpService.instance.post('${ApiConfig.visitors}/$id/approve');
    if (result.success) {
      _updateVisitorLocal(id, approvalStatus: 'allowed');
      loadVisitors(); // background refresh stats
    } else {
      throw Exception(result.message);
    }
  }

  Future<void> denyVisitor(int id, {String reason = 'Denied'}) async {
    final result = await HttpService.instance.post(
      '${ApiConfig.visitors}/$id/reject',
      body: {'reason': reason},
    );
    if (result.success) {
      _updateVisitorLocal(id, approvalStatus: 'denied');
      loadVisitors();
    } else {
      throw Exception(result.message);
    }
  }

  Future<void> checkIn(int id) async {
    final result = await HttpService.instance.post('${ApiConfig.visitors}/$id/check-in');
    if (result.success) {
      _updateVisitorLocal(id, entryStatus: 'entered');
      loadVisitors();
    } else {
      throw Exception(result.message);
    }
  }

  Future<void> checkOut(int id) async {
    final result = await HttpService.instance.post('${ApiConfig.visitors}/$id/check-out');
    if (result.success) {
      _updateVisitorLocal(id, entryStatus: 'exited');
      loadVisitors();
    } else {
      throw Exception(result.message);
    }
  }

  Future<void> addVisitor({
    required String phone,
    required String visitorType,
    int? flatId,
    String? name,
    String? vehicleNumber,
    String? purpose,
  }) async {
    final body = <String, dynamic>{
      'visitor_phone': phone,
      'visitor_type':  visitorType,
      if (name != null && name.isNotEmpty)          'visitor_name':   name,
      if (flatId != null)                           'flat_id':        flatId,
      if (vehicleNumber != null && vehicleNumber.isNotEmpty) 'vehicle_number': vehicleNumber,
      if (purpose != null && purpose.isNotEmpty)    'purpose':        purpose,
    };

    final result = await HttpService.instance.post(ApiConfig.visitors, body: body);
    if (result.success && result.data != null) {
      final newData = result.data!['data'];
      if (newData is Map<String, dynamic>) {
        final newVisitor = VisitorModel.fromJson(newData);
        // Prepend to list immediately so it shows without waiting for refresh
        final updated = [newVisitor, ...state.allVisitors];
        if (mounted) state = state.copyWith(visitors: updated, allVisitors: updated);
      }
      // Background refresh to get accurate stats
      loadVisitors();
    } else {
      throw Exception(
        result.message.isNotEmpty ? result.message : 'Failed to add visitor',
      );
    }
  }

  // ── Local state update ────────────────────────────────────────────────────

  void _updateVisitorLocal(int id, {String? approvalStatus, String? entryStatus}) {
    final updated = state.allVisitors.map((v) {
      if (v.id != id) return v;
      return v.copyWith(approvalStatus: approvalStatus, entryStatus: entryStatus);
    }).toList();
    if (mounted) state = state.copyWith(visitors: updated, allVisitors: updated);
  }
}

// ── Provider ──────────────────────────────────────────────────────────────────

final visitorsProvider = StateNotifierProvider<VisitorsNotifier, VisitorsState>(
  (ref) => VisitorsNotifier(),
);
