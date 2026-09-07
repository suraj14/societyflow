import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/app_button.dart';
import '../providers/services_provider.dart';
import '../models/service_model.dart';

class ServicesScreen extends ConsumerStatefulWidget {
  const ServicesScreen({super.key});
  @override
  ConsumerState<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends ConsumerState<ServicesScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    Future.microtask(() => ref.read(servicesProvider.notifier).loadServices());
  }

  @override
  void dispose() { _tabController.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(servicesProvider);
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Services'),
        bottom: TabBar(controller: _tabController, labelColor: AppColors.primary, unselectedLabelColor: AppColors.textHint, indicatorColor: AppColors.primary, tabs: const [Tab(text: 'Services'), Tab(text: 'Providers')]),
      ),
      body: state.isLoading
          ? const ListSkeleton()
          : RefreshIndicator(
              onRefresh: () => ref.read(servicesProvider.notifier).loadServices(),
              color: AppColors.primary,
              child: TabBarView(controller: _tabController, children: [
                _ServiceList(services: state.services),
                _ProviderList(providers: state.providers),
              ]),
            ),
    );
  }
}

class _ServiceList extends StatelessWidget {
  final List<ServiceModel> services;
  const _ServiceList({required this.services});

  @override
  Widget build(BuildContext context) {
    if (services.isEmpty) return const EmptyState(icon: Icons.build_outlined, title: 'No services', subtitle: 'No services available in your society');
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: services.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, i) => _ServiceCard(service: services[i]),
    );
  }
}

class _ServiceCard extends StatelessWidget {
  final ServiceModel service;
  const _ServiceCard({required this.service});

  IconData _icon(String? cat) {
    switch (cat?.toLowerCase()) {
      case 'plumbing': return Icons.plumbing_rounded;
      case 'electrical': return Icons.electrical_services_rounded;
      case 'cleaning': return Icons.cleaning_services_rounded;
      case 'security': return Icons.security_rounded;
      default: return Icons.build_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: const EdgeInsets.all(14),
      onTap: () => showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.transparent, builder: (_) => _RequestSheet(service: service)),
      child: Row(children: [
        Container(width: 48, height: 48, decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(12)), child: Icon(_icon(service.category), color: AppColors.primary, size: 22)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(service.name, style: AppTextStyles.label),
          if (service.description != null) ...[const SizedBox(height: 2), Text(service.description!, style: AppTextStyles.caption, maxLines: 1, overflow: TextOverflow.ellipsis)],
          if (service.charges > 0) ...[const SizedBox(height: 2), Text('₹${service.charges.toStringAsFixed(0)}', style: const TextStyle(color: AppColors.secondary, fontSize: 12, fontWeight: FontWeight.w600))],
        ])),
        const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.textHint),
      ]),
    );
  }
}

class _RequestSheet extends ConsumerStatefulWidget {
  final ServiceModel service;
  const _RequestSheet({required this.service});
  @override
  ConsumerState<_RequestSheet> createState() => _RequestSheetState();
}

class _RequestSheetState extends ConsumerState<_RequestSheet> {
  final _descCtrl = TextEditingController();
  bool _isLoading = false;

  @override
  void dispose() { _descCtrl.dispose(); super.dispose(); }

  Future<void> _submit() async {
    if (_descCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please describe your requirement'), backgroundColor: AppColors.error));
      return;
    }
    setState(() => _isLoading = true);
    try {
      await ref.read(servicesProvider.notifier).requestService(serviceId: widget.service.id, description: _descCtrl.text.trim());
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Service request submitted!'), backgroundColor: AppColors.secondary, behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), margin: const EdgeInsets.all(16)));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      padding: EdgeInsets.fromLTRB(24, 20, 24, MediaQuery.of(context).viewInsets.bottom + 24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
        const SizedBox(height: 16),
        Text('Request: ${widget.service.name}', style: AppTextStyles.h3),
        const SizedBox(height: 20),
        const Text('Describe your requirement', style: AppTextStyles.label),
        const SizedBox(height: 8),
        TextFormField(controller: _descCtrl, maxLines: 4, decoration: const InputDecoration(hintText: 'e.g. Leaking pipe in bathroom, need urgent fix...')),
        const SizedBox(height: 24),
        AppButton(label: 'Submit Request', onPressed: _submit, isLoading: _isLoading, icon: Icons.send_rounded),
      ]),
    );
  }
}

class _ProviderList extends StatelessWidget {
  final List<ServiceProviderModel> providers;
  const _ProviderList({required this.providers});

  @override
  Widget build(BuildContext context) {
    if (providers.isEmpty) return const EmptyState(icon: Icons.person_search_outlined, title: 'No providers', subtitle: 'No service providers registered');
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: providers.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, i) {
        final p = providers[i];
        return AppCard(
          padding: const EdgeInsets.all(14),
          child: Row(children: [
            Container(width: 44, height: 44, decoration: BoxDecoration(color: AppColors.info.withOpacity(0.1), shape: BoxShape.circle), child: const Icon(Icons.person_rounded, color: AppColors.info, size: 22)),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p.name, style: AppTextStyles.label),
              const SizedBox(height: 2),
              Text(p.serviceName ?? 'General', style: AppTextStyles.caption),
            ])),
            if (p.rating > 0) Row(children: [const Icon(Icons.star_rounded, color: AppColors.warning, size: 14), const SizedBox(width: 2), Text(p.rating.toStringAsFixed(1), style: AppTextStyles.caption)]),
          ]),
        );
      },
    );
  }
}
