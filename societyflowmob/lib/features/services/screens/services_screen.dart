import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/contact_launcher.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../models/service_model.dart';
import '../providers/services_provider.dart';

/// Services is a DIRECTORY of the providers this society lists - electrician, plumber, maid, cook, driver and so on -
/// each with a Call and a WhatsApp button. Nothing is requested, assigned or submitted: the resident contacts the
/// provider directly. Rows are the society's own active providers, as stored (name, category, phone, price, availability);
/// a missing price reads "Price not specified" and a missing number says so instead of showing a dead button.
class ServicesScreen extends ConsumerStatefulWidget {
  const ServicesScreen({super.key});
  @override
  ConsumerState<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends ConsumerState<ServicesScreen> {
  String _query = '';
  String? _category;

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(servicesProvider.notifier).loadServices());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(servicesProvider);

    Widget body;
    if (state.isLoading && state.providers.isEmpty) {
      body = const ListSkeleton();
    } else if (state.error != null && state.providers.isEmpty) {
      body = ErrorView(message: state.error!, onRetry: () => ref.read(servicesProvider.notifier).loadServices());
    } else if (state.providers.isEmpty) {
      body = const EmptyState(icon: Icons.home_repair_service_rounded, title: 'No service providers yet', subtitle: 'The society office has not listed any provider. Ask the office to add electricians, plumbers, maids and others.');
    } else {
      final categories = {for (final p in state.providers) p.categoryLabel}.toList()..sort();
      final q = _query.trim().toLowerCase();
      final shown = state.providers.where((p) {
        if (_category != null && p.categoryLabel != _category) return false;
        return q.isEmpty || p.name.toLowerCase().contains(q) || p.categoryLabel.toLowerCase().contains(q);
      }).toList()
        ..sort((a, b) => a.categoryLabel != b.categoryLabel ? a.categoryLabel.compareTo(b.categoryLabel) : a.name.compareTo(b.name));

      body = RefreshIndicator(
        color: AppColors.primary,
        onRefresh: () => ref.read(servicesProvider.notifier).loadServices(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: [
            // Search and category chips only earn their space once there is something to filter.
            if (state.providers.length > 6)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: TextField(
                  key: const Key('services-search'),
                  onChanged: (v) => setState(() => _query = v),
                  decoration: InputDecoration(
                    hintText: 'Search a name or service',
                    prefixIcon: const Icon(Icons.search_rounded),
                    filled: true,
                    fillColor: AppColors.surface,
                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
                  ),
                ),
              ),
            if (categories.length > 1)
              SizedBox(
                height: 44,
                child: ListView(scrollDirection: Axis.horizontal, children: [
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(key: const Key('services-cat-all'), label: const Text('All'), selected: _category == null, onSelected: (_) => setState(() => _category = null)),
                  ),
                  for (final c in categories)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(key: Key('services-cat-$c'), label: Text(c), selected: _category == c, onSelected: (_) => setState(() => _category = _category == c ? null : c)),
                    ),
                ]),
              ),
            const SizedBox(height: 8),
            if (shown.isEmpty)
              const Padding(padding: EdgeInsets.only(top: 40), child: EmptyState(icon: Icons.search_off_rounded, title: 'No matching provider', subtitle: 'Try another word or category.')),
            for (final p in shown) ...[ProviderCard(provider: p), const SizedBox(height: 10)],
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Services')),
      body: body,
    );
  }
}

/// One provider: who, what, how much, available or not, and the two ways to reach them.
class ProviderCard extends StatelessWidget {
  final ServiceProviderModel provider;
  const ProviderCard({super.key, required this.provider});

  @override
  Widget build(BuildContext context) {
    final p = provider;
    return AppCard(
      key: Key('provider-${p.id}'),
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(color: AppColors.info.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: const Icon(Icons.person_rounded, color: AppColors.info, size: 20),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p.name, style: AppTextStyles.label, maxLines: 1, overflow: TextOverflow.ellipsis),
              Text(p.categoryLabel + (p.isDailyHelp ? ' - daily help' : ''), style: AppTextStyles.caption, maxLines: 1, overflow: TextOverflow.ellipsis),
            ]),
          ),
        ]),
        const SizedBox(height: 8),
        Wrap(spacing: 14, runSpacing: 4, children: [
          _fact(Icons.payments_outlined, p.priceLabel ?? 'Price not specified', key: Key('price-${p.id}')),
          if (p.availability != null) _fact(Icons.schedule_rounded, _titleCase(p.availability!)),
          if (p.hasPhone) _fact(Icons.phone_outlined, p.phone!),
        ]),
        const SizedBox(height: 10),
        if (p.hasPhone)
          Row(children: [
            Expanded(
              child: FilledButton.icon(
                key: Key('call-${p.id}'),
                onPressed: () => ContactLauncher.call(context, p.phone!),
                icon: const Icon(Icons.call_rounded, size: 18),
                label: const Text('Call'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton.icon(
                key: Key('whatsapp-${p.id}'),
                onPressed: () => ContactLauncher.whatsapp(context, p.phone!),
                icon: const Icon(Icons.chat_rounded, size: 18),
                label: const Text('WhatsApp'),
              ),
            ),
          ])
        else
          Text('No phone number listed - ask the society office.', key: Key('nophone-${p.id}'), style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary)),
      ]),
    );
  }

  static String _titleCase(String s) => s.isEmpty ? s : s[0].toUpperCase() + s.substring(1).replaceAll('_', ' ');

  static Widget _fact(IconData icon, String text, {Key? key}) => Row(key: key, mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 15, color: AppColors.textSecondary),
        const SizedBox(width: 4),
        Flexible(child: Text(text, style: AppTextStyles.caption)),
      ]);
}
