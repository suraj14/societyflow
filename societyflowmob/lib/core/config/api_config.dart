import 'package:flutter/foundation.dart' show kReleaseMode;

/// Central API configuration.
///
/// One switch, three environments, no file edits:
///
///   Emulator  : flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///   USB phone : adb reverse tcp:8000 tcp:8000  (discovery then finds 127.0.0.1)
///   LAN phone : flutter run --dart-define=API_BASE_URL=http://<PC-LAN-IP>:8000/api/v1
///   Staging   : flutter run --dart-define=API_BASE_URL=https://staging.example/api/v1
///   Release   : flutter build apk --release   → prodUrl, discovery disabled
///
/// With no API_BASE_URL given, a debug build auto-discovers the dev server
/// (ServerDiscovery: ADB reverse → 10.0.2.2 → cached → devFallbackUrl →
/// subnet scan, each confirmed via /health), and a release build always
/// uses [prodUrl]. A release build can never carry 127.0.0.1 / localhost /
/// 10.0.2.2 unless USE_PRODUCTION=false is passed explicitly.
class ApiConfig {
  // ── Production URL (the deployed server) ───────────────────────────────────
  static const String prodUrl = 'https://societyflow.joboapps.com/api/v1';

  // ── Explicit override from the build command (see class docs) ─────────────
  static const String overrideUrl = String.fromEnvironment('API_BASE_URL');

  // ── Local dev fallback: the PC's LAN IP (START-DEV.bat rewrites this) ──────
  static const String devFallbackUrl = 'http://192.168.1.14:8000/api/v1';

  /// Release builds talk to production unless told otherwise.
  static const bool useProduction =
      bool.fromEnvironment('USE_PRODUCTION', defaultValue: kReleaseMode);

  static bool get hasOverride => overrideUrl.isNotEmpty;

  static String get baseUrl =>
      hasOverride ? overrideUrl : (useProduction ? prodUrl : devFallbackUrl);

  static const Duration connectTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 30);

  // Auth
  static const String login          = '/auth/login';
  static const String register       = '/auth/register';
  static const String logout         = '/auth/logout';
  static const String forgotPassword = '/auth/forgot-password';
  static const String resetPassword  = '/auth/reset-password';
  static const String checkAccount   = '/auth/check-account';
  static const String me             = '/auth/me';
  static const String sendOtp        = '/auth/send-otp';
  static const String verifyOtp      = '/auth/verify-otp';

  // Modules
  static const String dashboard        = '/dashboard';
  static const String visitors         = '/visitors';
  static const String payments         = '/payments';
  static const String bills            = '/bills';
  static const String complaints       = '/complaints';
  static const String complaintDetail  = '/complaints'; // append /{id}
  static const String complaintCategories = '/complaints/categories';
  static const String notices          = '/notices';
  static const String profile          = '/profile';
  static const String changePassword   = '/change-password';
  static const String familyMembers    = '/profile/family-members';
  static const String facilities       = '/facilities';
  static const String facilityBookings = '/facility-bookings';
  static const String services         = '/services';
  static const String serviceProviders = '/service-providers';
  static const String serviceRequests  = '/service-requests';
  static const String events           = '/events';
  static const String accessPassScan   = '/access-passes/scan';
}
