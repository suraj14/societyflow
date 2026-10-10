import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import '../../../core/config/api_config.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../auth/providers/auth_provider.dart';

final _packageInfoProvider = FutureProvider<PackageInfo>((ref) => PackageInfo.fromPlatform());

/// About SocietyFlow: what the app is and which build is installed.
class AboutScreen extends ConsumerWidget {
  const AboutScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final info = ref.watch(_packageInfoProvider);
    final society = ref.watch(currentUserProvider)?.societyName;
    final version = info.maybeWhen(data: (p) => '${p.version} (build ${p.buildNumber})', orElse: () => '...');

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('About SocietyFlow')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 24, 16, 32),
        children: [
          Center(
            child: Container(
              width: 76, height: 76,
              decoration: BoxDecoration(gradient: AppColors.primaryGradient, borderRadius: BorderRadius.circular(22)),
              child: const Icon(Icons.apartment_rounded, color: Colors.white, size: 38),
            ),
          ),
          const SizedBox(height: 14),
          const Text('SocietyFlow', textAlign: TextAlign.center, style: AppTextStyles.h2),
          const SizedBox(height: 4),
          Text('Version $version', key: const Key('about-version'), textAlign: TextAlign.center, style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary)),
          const SizedBox(height: 24),
          AppCard(
            padding: const EdgeInsets.all(16),
            child: Text(
              'SocietyFlow brings your residential society into one app: visitors and gate passes, '
              'bills and payments, complaints, facility bookings, notices, events and society rules.',
              style: AppTextStyles.body2.copyWith(height: 1.5),
            ),
          ),
          const SizedBox(height: 12),
          AppCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              if (society != null)
                ListTile(leading: const Icon(Icons.apartment_rounded), title: const Text('Your society'), subtitle: Text(society)),
              if (society != null) const Divider(height: 1, indent: 56),
              ListTile(
                leading: const Icon(Icons.dns_outlined),
                title: const Text('Server'),
                subtitle: Text(ApiConfig.isLocalHost(ApiConfig.baseUrl) ? 'Development server' : 'SocietyFlow cloud'),
              ),
            ]),
          ),
          const SizedBox(height: 24),
          Text('© ${DateTime.now().year} SocietyFlow. All rights reserved.', textAlign: TextAlign.center, style: AppTextStyles.caption),
        ],
      ),
    );
  }
}
