import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/visitor_lookup.dart';
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
      if (vResult.success && mounted && vResult.data?['data'] is List) {
        // A successful answer is the truth, an empty one included: a resident whose last pending visitor was just decided
        // elsewhere (the other owner, an admin) must see the list empty, not the stale card with live buttons.
        final newList = _parseVisitors(vResult.data);
        state = state.copyWith(visitors: newList, allVisitors: newList);
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
  //
  // Every action is the server's decision, not the app's: the visitor as the
  // server now holds it replaces the card, so the screen can never claim a state
  // the server did not reach. A refusal - someone else decided first (409), the
  // visitor is not yours (404), you may not (403) - reloads the list so the real
  // state shows, and surfaces the server's own plain message.

  Future<void> _act(String path, {Map<String, dynamic>? body}) async {
    final result = await HttpService.instance.post(path, body: body);
    if (result.success) {
      final data = result.data?['data'];
      if (data is Map<String, dynamic>) _applyServerVisitor(VisitorModel.fromJson(data));
      loadVisitors(); // background refresh of the list and the counts
    } else {
      if (const {403, 404, 409, 422}.contains(result.statusCode)) loadVisitors();
      throw VisitorActionException(result.message, result.statusCode);
    }
  }

  /// A resident (or an administrator) lets the visitor in.
  Future<void> allowVisitor(int id) => _act('${ApiConfig.visitors}/$id/approve');

  /// A resident turns the visitor away; the reason is optional for them, required for an administrator (the server checks).
  Future<void> denyVisitor(int id, {String? reason}) => _act(
        '${ApiConfig.visitors}/$id/reject',
        body: {if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim()},
      );

  /// An administrator changes an earlier decision. The reason is mandatory and the residents and the guard are told.
  Future<void> overrideVisitor(int id, {required bool allow, required String reason}) => _act(
        '${ApiConfig.visitors}/$id/override',
        body: {'decision': allow ? 'allow' : 'deny', 'reason': reason.trim()},
      );
  /// Has this number been here before, or does it hold a pass?
  ///
  /// Returns null when the question could not be answered - a failed lookup
  /// must never stop a guard registering someone, so the caller treats null
  /// as "carry on" rather than as an error.
  Future<VisitorLookup?> lookupByPhone(String phone) async {
    final digits = phone.replaceAll(RegExp(r'\D'), '');
    if (digits.length < 10) return null;

    try {
      final result = await HttpService.instance
          // POST with a body, not GET: a phone number in a query string lands in
          // web server and proxy access logs.
          .post(ApiConfig.visitorsLookup, body: {'phone': digits});
      final data = result.data?['data'];
      if (!result.success || data is! Map<String, dynamic>) return null;

      return VisitorLookup.fromJson(data);
    } catch (_) {
      return null;
    }
  }

  Future<void> checkIn(int id) => _act('${ApiConfig.visitors}/$id/check-in');

  Future<void> checkOut(int id) => _act('${ApiConfig.visitors}/$id/check-out');

  /// Logs a visitor at the gate (guard) or invites one (resident).
  ///
  /// [clientRequestId] identifies THIS submission: send the same one again for a retry - a tap that did not
  /// seem to work, a request that timed out - and the server returns the visitor it already has instead of
  /// creating a second one and notifying the residents twice.
  Future<VisitorAddResult> addVisitor({
    required String phone,
    required String visitorType,
    int? flatId,
    String? name,
    String? vehicleNumber,
    String? purpose,
    String? idProof,
    String? idNumber,
    String? photoPath,
    String? clientRequestId,
  }) async {
    final fields = <String, dynamic>{
      'visitor_phone': phone,
      'visitor_type':  visitorType,
      if (name != null && name.isNotEmpty)          'visitor_name':   name,
      if (flatId != null)                           'flat_id':        flatId,
      if (vehicleNumber != null && vehicleNumber.isNotEmpty) 'vehicle_number': vehicleNumber,
      if (purpose != null && purpose.isNotEmpty)    'purpose':        purpose,
      // From a scanned ID. Aadhaar arrives already reduced to its last four
      // digits, and the API reduces it again rather than trusting us.
      if (idProof != null && idProof.isNotEmpty)    'visitor_id_proof':  idProof,
      if (idNumber != null && idNumber.isNotEmpty)  'visitor_id_number': idNumber,
      if (clientRequestId != null && clientRequestId.isNotEmpty) 'client_request_id': clientRequestId,
    };

    final result = (photoPath != null && photoPath.isNotEmpty)
        ? await HttpService.instance.postMultipart(
            ApiConfig.visitors,
            fields: fields.map((k, v) => MapEntry(k, v.toString())),
            filePaths: {'photo': photoPath},
          )
        : await HttpService.instance.post(ApiConfig.visitors, body: fields);

    if (result.success && result.data != null) {
      final newData = result.data!['data'];
      if (newData is Map<String, dynamic>) _applyServerVisitor(VisitorModel.fromJson(newData), prependIfNew: true);
      loadVisitors(); // background refresh to get accurate stats
      final meta = result.data!['meta'];
      return VisitorAddResult(
        message: result.message,
        residentsLinked: meta is Map ? meta['residents_linked'] != false : true,
        duplicate: meta is Map && meta['duplicate'] == true,
      );
    }
    throw VisitorActionException(
      result.message.isNotEmpty ? result.message : 'Failed to add visitor',
      result.statusCode,
    );
  }

  // ── State update from the server's answer ─────────────────────────────────

  void _applyServerVisitor(VisitorModel fresh, {bool prependIfNew = false}) {
    if (!mounted) return;
    var found = false;
    final updated = state.allVisitors.map((v) {
      if (v.id != fresh.id) return v;
      found = true;
      return fresh;
    }).toList();
    if (!found && prependIfNew) updated.insert(0, fresh);
    state = state.copyWith(visitors: updated, allVisitors: updated);
  }
}

/// A refusal or failure from a visitor action. [toString] is the server's plain message, so a snackbar can show it as it is.
class VisitorActionException implements Exception {
  final String message;
  final int statusCode;
  const VisitorActionException(this.message, this.statusCode);

  /// Someone else decided first, or the visitor has already moved on.
  bool get isConflict => statusCode == 409;

  @override
  String toString() => message;
}

/// What the server said about a visitor that was just logged.
class VisitorAddResult {
  final String message;

  /// False when the unit has no owner or tenant linked: the administrators were asked to decide instead.
  final bool residentsLinked;

  /// True when this was a retry of a submission the server already had.
  final bool duplicate;
  const VisitorAddResult({required this.message, this.residentsLinked = true, this.duplicate = false});
}
// ── Provider ──────────────────────────────────────────────────────────────────

final visitorsProvider = StateNotifierProvider<VisitorsNotifier, VisitorsState>(
  (ref) => VisitorsNotifier(),
);
