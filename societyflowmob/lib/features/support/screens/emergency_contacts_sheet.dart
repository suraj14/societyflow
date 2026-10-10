import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/contact_launcher.dart';
import '../models/society_contacts_model.dart';
import '../providers/society_contacts_provider.dart';

/// National numbers never depend on the network - the sheet must work when
/// the API (or the internet) does not.
const kNationalEmergency = [
  SocietyContact(label: 'Emergency (all services)', phone: '112', kind: 'emergency'),
  SocietyContact(label: 'Police', phone: '100', kind: 'police'),
  SocietyContact(label: 'Ambulance', phone: '108', kind: 'ambulance'),
  SocietyContact(label: 'Fire', phone: '101', kind: 'fire'),
];

Future<void> showEmergencyContacts(BuildContext context) => showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const EmergencyContactsSheet(),
    );

/// Sized to the screen (never taller than 85%) and scrollable, so any number
/// of contacts fits on any phone without overflowing.
class EmergencyContactsSheet extends ConsumerWidget {
  const EmergencyContactsSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final contacts = ref.watch(societyContactsProvider);
    final maxHeight = MediaQuery.sizeOf(context).height * 0.85;

    final national = contacts.valueOrNull?.emergency.isNotEmpty == true ? contacts.value!.emergency : kNationalEmergency;

    return ConstrainedBox(
      constraints: BoxConstraints(maxHeight: maxHeight),
      child: Container(
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const SizedBox(height: 12),
          Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2))),
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 14, 20, 8),
            child: Row(children: [
              Icon(Icons.emergency_rounded, color: AppColors.error),
              SizedBox(width: 8),
              Expanded(child: Text('Emergency Contacts', style: AppTextStyles.h3, overflow: TextOverflow.ellipsis)),
            ]),
          ),
          Flexible(
            child: ListView(
              shrinkWrap: true,
              padding: EdgeInsets.fromLTRB(16, 4, 16, 16 + MediaQuery.paddingOf(context).bottom),
              children: [
                const _SectionTitle('Society office'),
                contacts.when(
                  loading: () => const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))),
                  ),
                  error: (e, _) => _InlineError(
                    message: e.toString().replaceFirst('Exception: ', ''),
                    onRetry: () => ref.invalidate(societyContactsProvider),
                  ),
                  data: (c) {
                    final office = [
                      if (c.societyPhone != null) SocietyContact(label: c.societyName ?? 'Society office', phone: c.societyPhone!, kind: 'office'),
                      ...c.managers,
                    ];
                    if (office.isEmpty) {
                      return const Padding(
                        padding: EdgeInsets.symmetric(vertical: 8),
                        child: Text('Your society has not added an office phone number yet.', style: AppTextStyles.body2),
                      );
                    }
                    return Column(children: [for (final o in office) ContactTile(contact: o)]);
                  },
                ),
                const SizedBox(height: 8),
                const _SectionTitle('National emergency'),
                for (final n in national) ContactTile(contact: n),
              ],
            ),
          ),
        ]),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 8, 4, 6),
        child: Text(text.toUpperCase(), style: AppTextStyles.label.copyWith(fontSize: 12, color: AppColors.textSecondary, letterSpacing: 0.6)),
      );
}

class _InlineError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _InlineError({required this.message, required this.onRetry});
  @override
  Widget build(BuildContext context) => Row(children: [
        Expanded(child: Text(message, style: AppTextStyles.body2.copyWith(color: AppColors.error))),
        TextButton(onPressed: onRetry, child: const Text('Retry')),
      ]);
}

/// One callable contact: tap the row or the green button to dial.
class ContactTile extends StatelessWidget {
  final SocietyContact contact;
  final bool showWhatsApp;
  const ContactTile({super.key, required this.contact, this.showWhatsApp = false});

  IconData get _icon => switch (contact.kind) {
        'police' => Icons.local_police_rounded,
        'ambulance' => Icons.medical_services_rounded,
        'fire' => Icons.local_fire_department_rounded,
        'emergency' => Icons.sos_rounded,
        _ => Icons.apartment_rounded,
      };

  Color get _color => switch (contact.kind) {
        'police' => const Color(0xFF3F51B5),
        'ambulance' => AppColors.error,
        'fire' => const Color(0xFFFF5722),
        'emergency' => AppColors.error,
        _ => AppColors.primary,
      };

  @override
  Widget build(BuildContext context) {
    final title = contact.name != null ? contact.name! : contact.label;
    // Label and number on separate lines: with Call + WhatsApp buttons beside
    // it, "Society Manager · 98480…" was cut off before the number on a phone.
    final subtitle = contact.name != null ? '${contact.label}\n${contact.phone}' : contact.phone;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: AppColors.background,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => ContactLauncher.call(context, contact.phone),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Row(children: [
              Container(
                width: 40, height: 40,
                decoration: BoxDecoration(color: _color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
                child: Icon(_icon, color: _color, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(title, style: AppTextStyles.label, maxLines: 1, overflow: TextOverflow.ellipsis),
                  Text(subtitle, style: AppTextStyles.caption, maxLines: 2, overflow: TextOverflow.ellipsis),
                ]),
              ),
              if (showWhatsApp)
                IconButton(
                  tooltip: 'WhatsApp',
                  onPressed: () => ContactLauncher.whatsapp(context, contact.phone),
                  icon: const Icon(Icons.chat_rounded, color: AppColors.secondary),
                ),
              IconButton.filled(
                tooltip: 'Call ${contact.phone}',
                style: IconButton.styleFrom(backgroundColor: AppColors.secondary),
                onPressed: () => ContactLauncher.call(context, contact.phone),
                icon: const Icon(Icons.call_rounded, color: Colors.white, size: 20),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}
