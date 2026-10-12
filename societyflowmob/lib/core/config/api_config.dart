import 'package:flutter/foundation.dart' show kReleaseMode;

/// Central API configuration. The single source of truth for the base URL.
///
/// Three builds, no LAN address anywhere:
///
///   Release (flutter build apk --release) : prodUrl over HTTPS. Always.
///   Debug, USB phone                      : http://127.0.0.1:8000/api/v1,
///                                           reached through
///                                           `adb reverse tcp:8000 tcp:8000`.
///   Debug, emulator                       : pass
///                                           --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///
/// The PC's Wi-Fi/LAN address changes, so it is never compiled in and never
/// probed. The USB tunnel gives the phone a fixed address for the PC.
///
/// A release build never uses a local address, whatever is passed in (see
/// [resolveBaseUrl]). A debug build uses production only when
/// USE_PRODUCTION=true is passed explicitly.
class ApiConfig {
  // ── Production (the deployed HTTPS server) ─────────────────────────────────
  static const String prodUrl = 'https://societyflow.joboapps.com/api/v1';

  // ── Local development: the PC's Laravel server, via the USB tunnel ─────────
  static const String localDevUrl = 'http://127.0.0.1:8000/api/v1';

  // ── Explicit override from the build command (emulator, staging) ───────────
  static const String overrideUrl = String.fromEnvironment('API_BASE_URL');

  /// Release builds talk to production unless told otherwise.
  static const bool useProduction =
      bool.fromEnvironment('USE_PRODUCTION', defaultValue: kReleaseMode);

  static bool get hasOverride => overrideUrl.isNotEmpty;

  static String get baseUrl => resolveBaseUrl(
        override: overrideUrl,
        release: kReleaseMode,
        production: useProduction,
        prodUrl: prodUrl,
        devUrl: localDevUrl,
      );

  /// Which host a build talks to. Pure, so the tests can drive it directly.
  ///
  /// A release build never uses a local address, whatever produced the value:
  /// an `--dart-define=API_BASE_URL=` pointing at the PC, or a local dev URL
  /// left on by `USE_PRODUCTION=false`, is replaced by [prodUrl].
  static String resolveBaseUrl({
    required String override,
    required bool release,
    required bool production,
    required String prodUrl,
    required String devUrl,
  }) {
    final candidate =
        override.isNotEmpty ? override : (production ? prodUrl : devUrl);
    if (release && isLocalHost(candidate)) return prodUrl;
    return candidate;
  }

  /// Loopback, the emulator's host alias, and private LAN ranges. An empty or
  /// unparseable value counts as local, so it can never reach a release.
  static bool isLocalHost(String url) {
    final host = (Uri.tryParse(url)?.host ?? '')
        .toLowerCase()
        .replaceAll(RegExp(r'[\[\]]'), '');
    if (host.isEmpty) return true;
    if (host == 'localhost' || host == '127.0.0.1' || host == '::1') return true;
    if (host == '10.0.2.2' || host.startsWith('192.168.') || host.startsWith('10.')) {
      return true;
    }
    final octets = host.split('.');
    if (octets.length == 4 && octets[0] == '172') {
      final second = int.tryParse(octets[1]) ?? -1;
      return second >= 16 && second <= 31;
    }
    return false;
  }

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
  static const String selectAccount  = '/auth/select-account';

  // Modules
  static const String dashboard        = '/dashboard';
  static const String visitors         = '/visitors';
  static const String visitorsLookup   = '/visitors/lookup';
  static const String payments         = '/payments';
  static const String bills            = '/bills';
  static const String complaints       = '/complaints';
  static const String complaintDetail  = '/complaints'; // append /{id}
  static const String complaintCategories = '/complaints/categories';
  static const String notices          = '/notices';
  static const String profile          = '/profile';
  static const String changePassword   = '/change-password';
  static const String passwordPolicy   = '/password-policy';
  static const String familyMembers    = '/profile/family-members';
  static const String facilities       = '/facilities';
  static const String facilityBookings = '/facility-bookings';
  static const String services         = '/services';
  static const String serviceProviders = '/service-providers';
  static const String serviceRequests  = '/service-requests';
  static const String events           = '/events';
  static const String accessPassScan   = '/access-passes/scan';
  static const String accessPasses     = '/access-passes';
  static String accessPassDetail(int id) => '/access-passes/$id';
  static String accessPassExtend(int id) => '/access-passes/$id/extend';
  static const String societyContacts = '/society/contacts';
  static const String societyDocuments = '/society-documents';

  /// Society Info: the "Society Details" and "Committee" sections
  /// ({details, committee}). Documents keep their own endpoints above.
  static const String societyInfo = '/society/info';

  /// In-app notification feed (the same entries as the web header bell):
  /// GET list, POST /{id}/read, POST /read-all. Was referenced by
  /// notifications_provider.dart but never defined, which stopped the app
  /// compiling.
  static const String notifications = '/notifications';
  static String societyDocumentAcknowledge(int id) => '/society-documents/$id/acknowledge';
}
