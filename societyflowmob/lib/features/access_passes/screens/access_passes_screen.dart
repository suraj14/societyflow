import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../models/access_pass_model.dart';
import '../providers/access_passes_provider.dart';
import 'access_pass_edit_screen.dart';

/// The passes the signed-in person may manage. The server decides which
/// those are - an admin sees the society's, a resident only their own
/// property's - so this screen just renders what comes back.
class AccessPassesScreen extends ConsumerStatefulWidget {
  const AccessPassesScreen({super.key});

  @override
  ConsumerState<AccessPassesScreen> createState() => _AccessPassesScreenState();
}

class _AccessPassesScreenState extends ConsumerState<AccessPassesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(accessPassesProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(accessPassesProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Access Passes')),
      body: RefreshIndicator(
        onRefresh: () => ref.read(accessPassesProvider.notifier).load(),
        child: _body(state),
      ),
    );
  }

  Widget _body(AccessPassesState state) {
    if (state.isLoading && state.passes.isEmpty) {
      return const Center(child: CircularProgressIndicator(color: AppColors.primary));
    }

    if (state.error != null && state.passes.isEmpty) {
      return ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 80),
          const Icon(Icons.error_outline_rounded, size: 40, color: AppColors.textHint),
          const SizedBox(height: 12),
          Text(state.error!, textAlign: TextAlign.center, style: AppTextStyles.body2),
        ],
      );
    }

    if (state.passes.isEmpty) {
      return ListView(
        padding: const EdgeInsets.all(24),
        children: const [
          SizedBox(height: 80),
          Icon(Icons.badge_outlined, size: 40, color: AppColors.textHint),
          SizedBox(height: 12),
          Text('No access passes yet.', textAlign: TextAlign.center, style: AppTextStyles.body2),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: state.passes.length,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (_, i) => _PassCard(pass: state.passes[i]),
    );
  }
}

class _PassCard extends StatelessWidget {
  final AccessPassModel pass;
  const _PassCard({required this.pass});

  Color get _stateColor {
    if (pass.isRevoked) return AppColors.error;
    if (pass.isExpired) return AppColors.warning;
    if (pass.isBlockedToday) return AppColors.warning;
    return AppColors.secondary;
  }

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(pass.personName, style: AppTextStyles.label),
                    if (pass.category != null && pass.category!.isNotEmpty)
                      Text(pass.category!, style: AppTextStyles.caption),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: _stateColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  pass.stateLabel,
                  style: TextStyle(color: _stateColor, fontSize: 11, fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (pass.property != null)
            Text('Property: ${pass.property}', style: AppTextStyles.caption),
          Text(
            pass.validUntil == null
                ? 'Valid from ${pass.validFrom ?? '-'} · no end date'
                : 'Valid ${pass.validFrom ?? '-'} → ${pass.validUntil}',
            style: AppTextStyles.caption,
          ),
          if (pass.startTime != null && pass.endTime != null)
            Text('Time ${pass.startTime} – ${pass.endTime}', style: AppTextStyles.caption),

          // A revoked pass offers nothing: the server refuses every write
          // on it, so showing an Edit button here would only produce a 403.
          if (pass.canEdit) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                if (pass.isExpired)
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () => _open(context, extendOnly: true),
                      icon: const Icon(Icons.event_repeat_rounded, size: 18),
                      label: const Text('Extend'),
                      style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
                    ),
                  ),
                if (pass.isExpired) const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _open(context, extendOnly: false),
                    icon: const Icon(Icons.edit_rounded, size: 18),
                    label: const Text('Edit'),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  void _open(BuildContext context, {required bool extendOnly}) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AccessPassEditScreen(pass: pass, extendOnly: extendOnly),
      ),
    );
  }
}
