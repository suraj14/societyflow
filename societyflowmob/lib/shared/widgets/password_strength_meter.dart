import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/config/api_config.dart';
import '../../core/network/http_service.dart';
import '../../core/security/password_policy.dart';
import '../../core/theme/app_theme.dart';

/// The policy the server will enforce: fetched once, and the server's own baseline until (or if) it arrives, so a
/// failed request never leaves a screen without rules.
final passwordPolicyProvider = FutureProvider<PasswordPolicy>((ref) async {
  try {
    final result = await HttpService.instance.get(ApiConfig.passwordPolicy);
    final data = result.data;
    if (result.success && data != null) {
      return PasswordPolicy.fromJson(Map<String, dynamic>.from((data['data'] ?? data) as Map));
    }
  } catch (_) {
    // fall through to the baseline
  }
  return PasswordPolicy.baseline;
});

/// Strength bars, a label and the requirement checklist for a password being typed. Pure presentation: it reads
/// [password] and [policy] and holds no state, so it is rebuilt by the field's own listener.
class PasswordStrengthMeter extends StatelessWidget {
  final String password;
  final PasswordPolicy policy;

  const PasswordStrengthMeter({super.key, required this.password, required this.policy});

  static const _levelColors = [Color(0xFFE5E7EB), Color(0xFFDC2626), Color(0xFFD97706), Color(0xFF65A30D), Color(0xFF059669)];

  @override
  Widget build(BuildContext context) {
    final level = policy.level(password);
    final checks = policy.checks(password);
    final color = _levelColors[level];

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(
          key: const ValueKey('password-strength-bars'),
          children: List.generate(
            4,
            (i) => Expanded(
              child: Container(
                height: 5,
                margin: EdgeInsets.only(right: i == 3 ? 0 : 4),
                decoration: BoxDecoration(color: i < level ? color : _levelColors[0], borderRadius: BorderRadius.circular(3)),
              ),
            ),
          ),
        ),
        const SizedBox(height: 4),
        Semantics(
          liveRegion: true,
          child: Text(
            PasswordPolicy.levelLabels[level],
            key: const ValueKey('password-strength-label'),
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: level == 0 ? Colors.transparent : color),
          ),
        ),
        const SizedBox(height: 4),
        for (final r in policy.requirements) _line(r.label, checks[r.key]!, password.isNotEmpty, ValueKey('requirement-${r.key}')),
        _line('Not a commonly used password', checks['common']!, password.isNotEmpty, const ValueKey('requirement-common')),
      ]),
    );
  }

  Widget _line(String text, bool ok, bool started, Key key) {
    final color = !started ? AppColors.textHint : (ok ? const Color(0xFF047857) : const Color(0xFFB91C1C));

    return Padding(
      key: key,
      padding: const EdgeInsets.only(top: 2),
      child: Row(children: [
        Icon(!started ? Icons.radio_button_unchecked : (ok ? Icons.check_circle_rounded : Icons.cancel_rounded), size: 14, color: color),
        const SizedBox(width: 6),
        Expanded(child: Text(text, style: TextStyle(fontSize: 12, color: color))),
      ]),
    );
  }
}
