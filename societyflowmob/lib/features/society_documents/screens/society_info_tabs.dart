import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/contact_launcher.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_view.dart';
import '../../../shared/widgets/network_image_widget.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../models/society_info_model.dart';
import '../providers/society_info_provider.dart';

/// Shown wherever a value was never recorded - nothing is guessed or invented.
const String kNotAvailable = 'Not available';

// ── Society Details ──────────────────────────────────────────────────────────

class SocietyDetailsTab extends ConsumerWidget {
  const SocietyDetailsTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(societyInfoProvider);

    if (state.isLoading && state.info == null) return const ListSkeleton();
    if (state.error != null && state.info == null) {
      return ErrorView(message: state.error!, onRetry: () => ref.read(societyInfoProvider.notifier).load());
    }

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () => ref.read(societyInfoProvider.notifier).load(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [SocietyDetailsView(details: state.info?.details)],
      ),
    );
  }
}

/// The five detail rows. Pure (data in, widgets out) so it can be tested
/// without a server.
class SocietyDetailsView extends StatelessWidget {
  final SocietyDetails? details;
  const SocietyDetailsView({super.key, required this.details});

  @override
  Widget build(BuildContext context) {
    final d = details;
    if (d == null) {
      return const EmptyState(
        icon: Icons.apartment_rounded,
        title: 'No society linked',
        subtitle: 'Your account is not linked to a society yet.',
      );
    }

    final rows = <(String, String?)>[
      ('Society Name', d.name.isEmpty ? null : d.name),
      ('Registration Number', d.registrationNumber),
      ('Address', d.address),
      ('Society Type', d.societyType),
      ('Registered / Established', d.registeredOnLabel),
    ];

    return AppCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const Divider(height: 1),
            _DetailRow(label: rows[i].$1, value: rows[i].$2),
          ],
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String? value;
  const _DetailRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    final missing = value == null;
    return Semantics(
      container: true,
      label: '$label: ${value ?? kNotAvailable}',
      excludeSemantics: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label.toUpperCase(),
              style: AppTextStyles.label.copyWith(color: AppColors.textSecondary, letterSpacing: 0.8, fontSize: 11),
            ),
            const SizedBox(height: 4),
            Text(
              value ?? kNotAvailable,
              style: AppTextStyles.body1.copyWith(
                color: missing ? AppColors.textHint : AppColors.textPrimary,
                fontWeight: missing ? FontWeight.w400 : FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Committee ────────────────────────────────────────────────────────────────

class CommitteeTab extends ConsumerWidget {
  const CommitteeTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(societyInfoProvider);

    if (state.isLoading && state.info == null) return const ListSkeleton();
    if (state.error != null && state.info == null) {
      return ErrorView(message: state.error!, onRetry: () => ref.read(societyInfoProvider.notifier).load());
    }

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () => ref.read(societyInfoProvider.notifier).load(),
      child: CommitteeView(
        members: state.info?.committee ?? const [],
        onCall: (phone) => ContactLauncher.call(context, phone),
      ),
    );
  }
}

/// The committee cards (or an honest empty state). Pure for testing; the
/// phone launch is injected.
class CommitteeView extends StatelessWidget {
  final List<CommitteeMember> members;
  final void Function(String phone) onCall;
  const CommitteeView({super.key, required this.members, required this.onCall});

  @override
  Widget build(BuildContext context) {
    if (members.isEmpty) {
      // A ListView so pull-to-refresh still works on an empty list.
      return ListView(children: const [
        SizedBox(height: 80),
        EmptyState(
          icon: Icons.groups_rounded,
          title: 'No committee members listed yet',
          subtitle: 'When the society office adds its office bearers, they will appear here.',
        ),
      ]);
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      itemCount: members.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, i) => _MemberCard(member: members[i], onCall: onCall),
    );
  }
}

class _MemberCard extends StatelessWidget {
  final CommitteeMember member;
  final void Function(String phone) onCall;
  const _MemberCard({required this.member, required this.onCall});

  @override
  Widget build(BuildContext context) {
    final phone = member.phone;
    final callable = member.canCall && phone != null;

    return AppCard(
      child: Row(
        children: [
          NetAvatar(url: member.photo, name: member.name, size: 56),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  member.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.body1.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 2),
                Text(member.designation, style: AppTextStyles.body2.copyWith(color: AppColors.primary, fontWeight: FontWeight.w600)),
                if (callable)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(phone, style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary)),
                  ),
              ],
            ),
          ),
          if (callable) ...[
            const SizedBox(width: 8),
            Semantics(
              // Its own node: otherwise it merges into the card and a screen
              // reader never offers "Call <name>" as a separate action.
              container: true,
              button: true,
              label: 'Call ${member.name}, ${member.designation}',
              excludeSemantics: true,
              child: SizedBox(
                height: 48,
                child: ElevatedButton.icon(
                  onPressed: () => onCall(phone),
                  icon: const Icon(Icons.call_rounded, size: 18),
                  label: const Text('Call'),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
