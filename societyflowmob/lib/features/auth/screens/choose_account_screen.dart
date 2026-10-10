import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../models/otp_account_choice.dart';
import '../providers/auth_provider.dart';

/// After a verified OTP, when the number is saved on more than one account
/// (an owner who also works for the office, one phone shared by a family):
/// the user says which account to sign in to. Before this, the server signed
/// in whichever account its database returned first.
///
/// The code is already spent, so "back" means "request a new code".
class ChooseAccountScreen extends ConsumerStatefulWidget {
  const ChooseAccountScreen({super.key});

  @override
  ConsumerState<ChooseAccountScreen> createState() => _ChooseAccountScreenState();
}

class _ChooseAccountScreenState extends ConsumerState<ChooseAccountScreen> {
  int? _busyId;
  String? _error;

  Future<void> _choose(OtpAccountOption account) async {
    if (_busyId != null) return;
    setState(() {
      _busyId = account.id;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).selectOtpAccount(userId: account.id);
      if (!mounted) return;
      TextInput.finishAutofillContext();
      Navigator.of(context).pushNamedAndRemoveUntil('/home', (_) => false);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final choice = ref.watch(authProvider.select((s) => s.accountChoice));

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Choose account')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
          children: [
            const Text('This number has more than one account', style: AppTextStyles.h3),
            const SizedBox(height: 6),
            Text(
              choice == null
                  ? 'Request a new code to sign in.'
                  : 'Choose the account you want to use. To switch later, log out and sign in again.',
              style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 16),
            if (choice != null)
              for (final account in choice.accounts)
                _AccountTile(
                  account: account,
                  busy: _busyId == account.id,
                  enabled: _busyId == null,
                  onTap: () => _choose(account),
                ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(
                _error!,
                key: const Key('choose-account-error'),
                style: AppTextStyles.body2.copyWith(color: AppColors.error),
              ),
            ],
            if (choice == null) ...[
              const SizedBox(height: 20),
              AppButton(
                label: 'Request a new code',
                icon: Icons.refresh_rounded,
                onPressed: () => Navigator.of(context).maybePop(),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _AccountTile extends StatelessWidget {
  final OtpAccountOption account;
  final bool busy;
  final bool enabled;
  final VoidCallback onTap;

  const _AccountTile({required this.account, required this.busy, required this.enabled, required this.onTap});

  IconData get _icon => switch (account.role) {
        'Guard' => Icons.security_rounded,
        'Staff' || 'Accountant' => Icons.badge_rounded,
        'Admin' || 'Super Admin' => Icons.admin_panel_settings_rounded,
        _ => Icons.home_rounded,
      };

  @override
  Widget build(BuildContext context) {
    final details = [
      account.role,
      if (account.societyName != null) account.societyName!,
    ].join(' · ');

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          key: Key('account-${account.id}'),
          borderRadius: BorderRadius.circular(14),
          onTap: enabled ? onTap : null,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(_icon, color: AppColors.primary, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(account.name, style: AppTextStyles.label, maxLines: 2, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 2),
                  Text(details, style: AppTextStyles.caption, maxLines: 2, overflow: TextOverflow.ellipsis),
                  if (account.unit != null)
                    Text(account.unit!, style: AppTextStyles.caption, maxLines: 1, overflow: TextOverflow.ellipsis),
                ]),
              ),
              const SizedBox(width: 8),
              busy
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.chevron_right_rounded, color: AppColors.textHint),
            ]),
          ),
        ),
      ),
    );
  }
}
