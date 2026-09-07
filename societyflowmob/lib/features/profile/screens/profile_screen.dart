import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/network_image_widget.dart';
import '../../auth/providers/auth_provider.dart';
import '../providers/family_members_provider.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);

    // Load family members when profile opens (only for owners)
    if (user?.isOwner == true) {
      Future.microtask(() => ref.read(familyMembersProvider.notifier).load());
    }

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Profile')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(children: [
          GradientCard(
            child: Row(children: [
              NetAvatar(url: user?.avatar, name: user?.name ?? 'U', size: 68),
              const SizedBox(width: 16),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(user?.name ?? 'Resident', style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700, letterSpacing: -0.3)),
                const SizedBox(height: 4),
                Text(user?.email ?? '', style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13)),
                const SizedBox(height: 6),
                Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3), decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), borderRadius: BorderRadius.circular(20)), child: Text(user?.role ?? 'Resident', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600))),
              ])),
            ]),
          ),
          const SizedBox(height: 20),
          AppCard(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Property Details', style: AppTextStyles.label),
              const SizedBox(height: 12),
              _InfoRow(icon: Icons.home_rounded,      label: 'Unit',    value: user?.displayUnit ?? 'N/A'),
              const Divider(height: 20),
              _InfoRow(icon: Icons.phone_rounded,     label: 'Phone',   value: user?.phone ?? 'N/A'),
              const Divider(height: 20),
              _InfoRow(icon: Icons.apartment_rounded, label: 'Society', value: user?.societyName ?? 'N/A'),
            ]),
          ),
          // Family Members — only shown for owners
          if (user?.isOwner == true) ...[
            const SizedBox(height: 16),
            _FamilyMembersCard(),
          ],
          const SizedBox(height: 16),
          AppCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              _MenuItem(icon: Icons.edit_rounded,         label: 'Edit Profile',      onTap: () => Navigator.pushNamed(context, '/edit-profile')),
              const Divider(height: 1, indent: 56),
              _MenuItem(icon: Icons.lock_rounded,         label: 'Change Password',   onTap: () => Navigator.pushNamed(context, '/change-password')),
              const Divider(height: 1, indent: 56),
              _MenuItem(icon: Icons.help_outline_rounded, label: 'Help & Support',    onTap: () {}),
              const Divider(height: 1, indent: 56),
              _MenuItem(icon: Icons.info_outline_rounded, label: 'About SocietyFlow', onTap: () {}),
            ]),
          ),
          const SizedBox(height: 24),
          AppButton(
            label: 'Logout',
            onPressed: () => _confirmLogout(context, ref),
            variant: ButtonVariant.danger,
            icon: Icons.logout_rounded,
          ),
          const SizedBox(height: 32),
        ]),
      ),
    );
  }

  void _confirmLogout(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Logout', style: AppTextStyles.h3),
        content: const Text('Are you sure you want to logout?', style: AppTextStyles.body2),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) Navigator.pushReplacementNamed(context, '/login');
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
            child: const Text('Logout', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label, value;
  const _InfoRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      Container(width: 36, height: 36, decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.08), borderRadius: BorderRadius.circular(8)), child: Icon(icon, color: AppColors.primary, size: 16)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: AppTextStyles.caption),
        Text(value, style: AppTextStyles.label),
      ])),
    ]);
  }
}

class _MenuItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _MenuItem({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.08), borderRadius: BorderRadius.circular(8)), child: Icon(icon, color: AppColors.primary, size: 18)),
      title: Text(label, style: AppTextStyles.body1.copyWith(fontWeight: FontWeight.w500)),
      trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.textHint, size: 20),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    );
  }
}

// ── Family Members Card ───────────────────────────────────────────────────────

class _FamilyMembersCard extends ConsumerWidget {
  const _FamilyMembersCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(familyMembersProvider);

    return AppCard(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            width: 32, height: 32,
            decoration: BoxDecoration(color: AppColors.info.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
            child: const Icon(Icons.people_rounded, color: AppColors.info, size: 16),
          ),
          const SizedBox(width: 10),
          const Text('Family Members', style: AppTextStyles.label),
          const Spacer(),
          if (state.isLoading)
            const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary)),
        ]),
        const SizedBox(height: 12),

        if (state.error != null && state.members.isEmpty)
          Text(state.error!, style: AppTextStyles.caption.copyWith(color: AppColors.error))
        else if (state.members.isEmpty && !state.isLoading)
          const Text('No family members added', style: AppTextStyles.caption)
        else
          ...state.members.asMap().entries.map((entry) {
            final i = entry.key;
            final m = entry.value;
            return Column(children: [
              if (i > 0) const Divider(height: 16),
              Row(children: [
                Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: _relationColor(m.relationship).withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Center(
                    child: Text(
                      m.name.isNotEmpty ? m.name[0].toUpperCase() : '?',
                      style: TextStyle(color: _relationColor(m.relationship), fontWeight: FontWeight.w700, fontSize: 14),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(m.name, style: AppTextStyles.label),
                  const SizedBox(height: 2),
                  Row(children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: _relationColor(m.relationship).withOpacity(0.1),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(m.relationship, style: TextStyle(color: _relationColor(m.relationship), fontSize: 10, fontWeight: FontWeight.w600)),
                    ),
                    if (m.phone != null && m.phone!.isNotEmpty) ...[
                      const SizedBox(width: 8),
                      const Icon(Icons.phone_outlined, size: 11, color: AppColors.textHint),
                      const SizedBox(width: 3),
                      Text(m.phone!, style: AppTextStyles.caption),
                    ],
                  ]),
                ])),
              ]),
            ]);
          }),
      ]),
    );
  }

  Color _relationColor(String rel) {
    switch (rel.toLowerCase()) {
      case 'spouse': case 'wife': case 'husband': return AppColors.secondary;
      case 'child': case 'son': case 'daughter': return AppColors.info;
      case 'parent': case 'father': case 'mother': return AppColors.warning;
      default: return AppColors.primary;
    }
  }
}
