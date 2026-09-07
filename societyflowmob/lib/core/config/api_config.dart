/// Central API configuration.
/// The app auto-discovers the server IP at startup.
/// You only need to update the subnet if your router uses a different range.
class ApiConfig {
  // ── Production URL (when deployed to live server) ─────────────────────────
  static const String prodUrl = 'https://societyflow.joboapps.com/api/v1';

  // ── Local dev: STATIC IP — set this once after fixing your router DHCP ──────
  // Go to router admin → DHCP Reservation → assign 192.168.29.50 to your PC MAC
  // Then this never needs to change again.
  static const String devFallbackUrl = 'http://192.168.1.14:8000/api/v1';

  // Set to true to use production URL, false for local dev
  static const bool useProduction = false;

  static String get baseUrl => useProduction ? prodUrl : devFallbackUrl;

  static const Duration connectTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 30);

  // Auth
  static const String login          = '/auth/login';
  static const String register       = '/auth/register';
  static const String logout         = '/auth/logout';
  static const String forgotPassword = '/auth/forgot-password';
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
}
