/// One account a phone number is saved on. Offered after the OTP is
/// verified, when the number belongs to more than one active account (an
/// owner who is also on the office staff, one phone shared by a family).
///
/// JSON keys are the API contract of POST /auth/verify-otp's `accounts`.
class OtpAccountOption {
  final int id;
  final String name;
  final String role;
  final String? societyName;
  final String? unit;

  const OtpAccountOption({
    required this.id,
    required this.name,
    required this.role,
    this.societyName,
    this.unit,
  });

  factory OtpAccountOption.fromJson(Map<String, dynamic> json) => OtpAccountOption(
        id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
        name: json['name']?.toString() ?? '',
        role: json['role']?.toString() ?? '',
        societyName: _text(json['society_name']),
        unit: _text(json['unit']),
      );

  static String? _text(dynamic v) {
    final s = v?.toString().trim();
    return (s == null || s.isEmpty) ? null : s;
  }
}

/// verify-otp's answer for a number on several accounts: which ones, and the
/// short-lived, single-use token that signs in to the one the user picks.
class OtpAccountChoice {
  final String selectionToken;
  final List<OtpAccountOption> accounts;

  const OtpAccountChoice({required this.selectionToken, required this.accounts});

  /// Null when [json] is an ordinary login response (token + user).
  static OtpAccountChoice? fromVerifyResponse(Map<String, dynamic>? json) {
    if (json == null || json['requires_account_choice'] != true) return null;
    final list = json['accounts'];
    return OtpAccountChoice(
      selectionToken: json['selection_token']?.toString() ?? '',
      accounts: list is List
          ? list
              .whereType<Map>()
              .map((m) => OtpAccountOption.fromJson(Map<String, dynamic>.from(m)))
              .where((a) => a.id > 0)
              .toList()
          : const [],
    );
  }
}
