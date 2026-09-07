/// Feature flags — set to true to enable, false to disable safely.
/// Existing features are always ON. New features start as false.
class AppFeatures {
  // ── Existing features (always enabled) ───────────────────────────────────
  static const bool enableLogin         = true;
  static const bool enableRegister      = true;
  static const bool enableOtpLogin      = true;
  static const bool enableDashboard     = true;
  static const bool enableVisitors      = true;
  static const bool enablePayments      = true;
  static const bool enableComplaints    = true;
  static const bool enableNotices       = true;
  static const bool enableProfile       = true;
  static const bool enableFacilities    = true;
  static const bool enableServices      = true;
  static const bool enableEvents        = true;

  // ── New features (disabled by default — enable when ready) ───────────────
  static const bool enablePushNotifications = false;
  static const bool enableQrScanner        = false;
  static const bool enableGatePass         = false;
  static const bool enablePollsAndSurveys  = false;
  static const bool enableEmergencyAlert   = false;
  static const bool enableDocumentVault    = false;
  static const bool enableCommunityForum   = false;
}
