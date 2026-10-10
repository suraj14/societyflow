import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/contact_launcher.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/error_view.dart';
import '../../auth/providers/auth_provider.dart';
import '../../shell/main_shell.dart';
import '../models/society_contacts_model.dart';
import '../providers/society_contacts_provider.dart';
import 'emergency_contacts_sheet.dart';

/// Help & Support: reach the society office (call / WhatsApp / email the
/// numbers the society actually configured), raise a ticket, quick answers.
/// Every action here opens something real; nothing is shown that cannot work.
class HelpSupportScreen extends ConsumerWidget {
  const HelpSupportScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final contacts = ref.watch(societyContactsProvider);
    final role = ref.watch(currentUserProvider)?.role ?? '';
    final isResident = const {'Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'}.contains(role);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Help & Support')),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(societyContactsProvider),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            const _Heading('Contact your society office'),
            contacts.when(
              loading: () => const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
              error: (e, _) => ErrorView(
                message: e.toString().replaceFirst('Exception: ', ''),
                onRetry: () => ref.invalidate(societyContactsProvider),
              ),
              data: (c) => _OfficeCard(contacts: c),
            ),
            const SizedBox(height: 20),
            const _Heading('Get help'),
            AppCard(
              padding: EdgeInsets.zero,
              child: Material(type: MaterialType.transparency, child: Column(children: [
                if (isResident) ...[
                  ListTile(
                    leading: const Icon(Icons.support_agent_rounded, color: AppColors.primary),
                    title: const Text('Report a problem'),
                    subtitle: const Text('Raise a ticket the office can track'),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () {
                      Navigator.of(context).pop();
                      mainShellKey.currentState?.openComplaints();
                    },
                  ),
                  const Divider(height: 1, indent: 56),
                ],
                ListTile(
                  leading: const Icon(Icons.emergency_rounded, color: AppColors.error),
                  title: const Text('Emergency contacts'),
                  subtitle: const Text('Society office, police, ambulance, fire'),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => showEmergencyContacts(context),
                ),
              ])),
            ),
            const SizedBox(height: 20),
            const _Heading('Quick answers'),
            AppCard(
              padding: EdgeInsets.zero,
              child: Material(type: MaterialType.transparency, child: Column(children: [
                for (final (q, a) in _faq(isResident))
                  ExpansionTile(
                    shape: const Border(),
                    title: Text(q, style: AppTextStyles.body1.copyWith(fontWeight: FontWeight.w600)),
                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                    expandedCrossAxisAlignment: CrossAxisAlignment.start,
                    children: [Text(a, style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary, height: 1.4))],
                  ),
              ])),
            ),
          ],
        ),
      ),
    );
  }

  static List<(String, String)> _faq(bool resident) => resident
      ? const [
          ('I did not get the OTP SMS', 'Check the number is the one registered with your society office, wait a minute and tap "Resend code". If it still does not arrive, log in with your email and password, or contact the office.'),
          ('How do I let a visitor in?', 'When the guard registers your visitor you will see it under Visitors - approve or deny it there. For regular staff (maid, driver) create an Access Pass so the guard can just scan it.'),
          ('Where are my bills and receipts?', 'Open Bills from the bottom bar. Paid bills show their receipt.'),
          ('How do I book the clubhouse or gym?', 'Home -> Facilities -> choose the facility, date and time. The office confirms the booking.'),
          ('Where are the society rules and the committee?', 'Profile -> Society Info. It has the society details, the committee with a Call button, and the rules and documents.'),
        ]
      : const [
          ('A visitor is at the gate', 'Use New Visitor on the Guard home, enter the details and the flat. The resident approves it in their app.'),
          ('Scanning an access pass', 'Tap SCAN QR and point the camera at the resident\'s pass. The app tells you if entry is allowed.'),
          ('Where are the gate rules?', 'Guard home -> Society Info -> Documents.'),
        ];
}

class _Heading extends StatelessWidget {
  final String text;
  const _Heading(this.text);
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 0, 4, 8),
        child: Text(text, style: AppTextStyles.h3),
      );
}

class _OfficeCard extends StatelessWidget {
  final SocietyContactsModel contacts;
  const _OfficeCard({required this.contacts});

  @override
  Widget build(BuildContext context) {
    final c = contacts;
    if (!c.hasOfficeContact) {
      return AppCard(
        padding: const EdgeInsets.all(16),
        child: Text(
          'Your society has not added office contact details to SocietyFlow yet. Please ask the office in person, or raise a ticket.',
          style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary),
        ),
      );
    }
    return AppCard(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (c.societyName != null) Text(c.societyName!, style: AppTextStyles.label),
        if (c.address != null) ...[
          const SizedBox(height: 4),
          Text(c.address!, style: AppTextStyles.caption),
        ],
        const SizedBox(height: 12),
        Wrap(spacing: 10, runSpacing: 10, children: [
          if (c.societyPhone != null)
            FilledButton.icon(
              onPressed: () => ContactLauncher.call(context, c.societyPhone!),
              icon: const Icon(Icons.call_rounded, size: 18),
              label: const Text('Call office'),
            ),
          if (c.societyEmail != null)
            OutlinedButton.icon(
              onPressed: () => ContactLauncher.email(context, c.societyEmail!, subject: 'Help needed - SocietyFlow'),
              icon: const Icon(Icons.email_outlined, size: 18),
              label: const Text('Email office'),
            ),
        ]),
        if (c.managers.isNotEmpty) ...[
          const SizedBox(height: 14),
          for (final m in c.managers) ContactTile(contact: m, showWhatsApp: true),
        ],
      ]),
    );
  }
}
