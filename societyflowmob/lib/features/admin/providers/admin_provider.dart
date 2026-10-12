import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';

/// The society-office endpoints (`/api/v1/admin/*`, Admin only - the server answers 403 for everyone else).
/// Thin and stateless: screens keep their own list state, the server is the only source of truth.
class AdminApi {
  AdminApi._();

  static const String _base = '/admin';

  static Future<ApiResult> dashboard() => HttpService.instance.get('$_base/dashboard');

  static Future<ApiResult> bookings(String status) => HttpService.instance.get('$_base/facility-bookings?status=$status');
  static Future<ApiResult> bookingStatus(int id, String status, {String? notes}) =>
      HttpService.instance.post('$_base/facility-bookings/$id/status', body: {'status': status, if (notes != null && notes.isNotEmpty) 'admin_notes': notes});

  static Future<ApiResult> complaints(String status) => HttpService.instance.get('$_base/complaints?status=$status');
  static Future<ApiResult> complaintStatus(int id, String status, {String? message}) =>
      HttpService.instance.post('$_base/complaints/$id/status', body: {'status': status, if (message != null && message.isNotEmpty) 'message': message});

  static Future<ApiResult> serviceRequests(String status) => HttpService.instance.get('$_base/service-requests?status=$status');
  static Future<ApiResult> assignServiceRequest(int id, int providerId) =>
      HttpService.instance.post('$_base/service-requests/$id/assign', body: {'provider_id': providerId});
  static Future<ApiResult> serviceRequestStatus(int id, String status, {String? reason}) =>
      HttpService.instance.post('$_base/service-requests/$id/status', body: {'status': status, if (reason != null && reason.isNotEmpty) 'cancellation_reason': reason});
}

/// One "needs attention" row from the dashboard.
class AdminAttention {
  final String key;
  final String label;
  final int count;
  const AdminAttention({required this.key, required this.label, required this.count});

  factory AdminAttention.fromJson(Map<String, dynamic> j) => AdminAttention(
        key: j['key']?.toString() ?? '',
        label: j['label']?.toString() ?? '',
        count: (j['count'] as num?)?.toInt() ?? 0,
      );
}

class AdminDashboardState {
  final bool isLoading;
  final bool loaded;
  final String? error;
  final String? societyName;
  final Map<String, num> kpis;
  final List<AdminAttention> attention;
  final List<Map<String, dynamic>> guardsOnDuty;

  const AdminDashboardState({
    this.isLoading = false,
    this.loaded = false,
    this.error,
    this.societyName,
    this.kpis = const {},
    this.attention = const [],
    this.guardsOnDuty = const [],
  });

  /// A KPI, or null when the server did not send it - the UI shows "-" rather than inventing a zero.
  num? kpi(String key) => kpis[key];
}

class AdminDashboardNotifier extends StateNotifier<AdminDashboardState> {
  AdminDashboardNotifier() : super(const AdminDashboardState());

  Future<void> load() async {
    state = AdminDashboardState(isLoading: !state.loaded, loaded: state.loaded, societyName: state.societyName, kpis: state.kpis, attention: state.attention, guardsOnDuty: state.guardsOnDuty);
    final result = await AdminApi.dashboard();
    if (!result.success || result.data == null) {
      // Keep what was already on screen; say what failed. Never replace a failed call with zeros.
      state = AdminDashboardState(
        loaded: state.loaded, societyName: state.societyName, kpis: state.kpis, attention: state.attention, guardsOnDuty: state.guardsOnDuty,
        error: result.statusCode == 403 ? 'This account cannot open the office console.' : (result.message.isNotEmpty ? result.message : 'Could not load the dashboard.'),
      );
      return;
    }
    final data = (result.data!['data'] ?? result.data!) as Map<String, dynamic>;
    final kpis = <String, num>{};
    if (data['kpis'] is Map) {
      (data['kpis'] as Map).forEach((k, v) {
        if (v is num) kpis[k.toString()] = v;
      });
    }
    state = AdminDashboardState(
      loaded: true,
      societyName: (data['society'] is Map ? (data['society'] as Map)['name'] : null)?.toString(),
      kpis: kpis,
      attention: [for (final a in (data['attention'] as List? ?? const [])) AdminAttention.fromJson(Map<String, dynamic>.from(a as Map))],
      guardsOnDuty: [for (final g in (data['guards_on_duty'] as List? ?? const [])) Map<String, dynamic>.from(g as Map)],
    );
  }
}

final adminDashboardProvider = StateNotifierProvider<AdminDashboardNotifier, AdminDashboardState>((ref) => AdminDashboardNotifier());
