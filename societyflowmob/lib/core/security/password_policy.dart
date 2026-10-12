/// The password rules, exactly as the server enforces them (App\Support\PasswordPolicy on the backend).
///
/// The server is the authority: this only lets the screen tell the person what will be checked, while they type, so a
/// rejected password is never a surprise. The numbers come from `GET /api/v1/password-policy` (the signed-in user's
/// society, or the platform's when signed out); [PasswordPolicy.baseline] is what is used until - or if - that answer
/// does not arrive, and it is the server's own baseline (8 characters), never weaker.
class PasswordPolicy {
  final int min;
  final int max;
  final bool uppercase;
  final bool number;
  final bool special;
  final List<String> common;

  const PasswordPolicy({
    required this.min,
    required this.max,
    required this.uppercase,
    required this.number,
    required this.special,
    this.common = const [],
  });

  /// The server's baseline: at least 8 characters, at most 64, no well-known password.
  static const PasswordPolicy baseline = PasswordPolicy(
    min: 8,
    max: 64,
    uppercase: false,
    number: false,
    special: false,
    common: ['password', 'password1', 'password12', 'password123', 'password1234', '12345678', '123456789', '1234567890', 'qwerty123', 'admin123'],
  );

  factory PasswordPolicy.fromJson(Map<String, dynamic> json) {
    int asInt(Object? v, int fallback) => v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? fallback;
    bool asBool(Object? v) => v == true || v == 1 || v == '1' || v == 'true';

    return PasswordPolicy(
      // never below the baseline, whatever arrives
      min: asInt(json['min'], baseline.min) < baseline.min ? baseline.min : asInt(json['min'], baseline.min),
      max: asInt(json['max'], baseline.max),
      uppercase: asBool(json['uppercase']),
      number: asBool(json['number']),
      special: asBool(json['special']),
      common: (json['common'] is List) ? (json['common'] as List).map((e) => e.toString().toLowerCase()).toList() : baseline.common,
    );
  }

  /// The checklist shown under the field: key -> sentence, in order.
  List<PasswordRequirement> get requirements => [
        PasswordRequirement('min', 'At least $min characters'),
        if (uppercase) const PasswordRequirement('uppercase', 'An uppercase letter (A-Z)'),
        if (number) const PasswordRequirement('number', 'A number (0-9)'),
        if (special) const PasswordRequirement('special', 'A special character (! @ # \$ %)'),
      ];

  static final RegExp _upper = RegExp(r'\p{Lu}', unicode: true);
  static final RegExp _lower = RegExp(r'\p{Ll}', unicode: true);
  static final RegExp _digit = RegExp(r'\d');
  static final RegExp _special = RegExp(r'[^\p{L}\p{N}\s]', unicode: true);

  /// Which checks the password passes - the same predicates the server applies.
  Map<String, bool> checks(String pw) => {
        'min': pw.runes.length >= min && pw.runes.length <= max,
        'uppercase': !uppercase || _upper.hasMatch(pw),
        'number': !number || _digit.hasMatch(pw),
        'special': !special || _special.hasMatch(pw),
        'common': !common.contains(pw.toLowerCase()),
      };

  bool isAcceptable(String pw) => checks(pw).values.every((ok) => ok);

  /// The first thing wrong with [pw] as a sentence (matching the server's wording), or null when it is acceptable.
  String? validate(String? pw) {
    final value = pw ?? '';
    if (value.isEmpty) return 'Password is required';
    final c = checks(value);
    if (value.runes.length < min) return 'Use at least $min characters';
    if (value.runes.length > max) return 'Use at most $max characters';
    if (!c['uppercase']!) return 'Include an uppercase letter';
    if (!c['number']!) return 'Include a number';
    if (!c['special']!) return 'Include a special character such as ! @ # \$ %';
    if (!c['common']!) return 'That password is too common. Choose something harder to guess';
    return null;
  }

  /// 0 = empty, 1 = too weak (does not meet the policy), 2 = fair (meets it), 3 = good, 4 = strong.
  int level(String pw) {
    if (pw.isEmpty) return 0;
    if (!isAcceptable(pw)) return 1;
    final variety = [_lower.hasMatch(pw), _upper.hasMatch(pw), _digit.hasMatch(pw), _special.hasMatch(pw)].where((b) => b).length;
    final length = pw.runes.length;
    if (variety == 4 && length >= 14) return 4;
    if (variety >= 3 && length >= 10) return 3;
    return 2;
  }

  static const List<String> levelLabels = ['', 'Too weak', 'Fair', 'Good', 'Strong'];
}

class PasswordRequirement {
  final String key;
  final String label;
  const PasswordRequirement(this.key, this.label);
}
