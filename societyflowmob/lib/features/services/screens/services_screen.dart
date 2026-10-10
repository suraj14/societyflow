import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/contact_launcher.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/service_model.dart';
import '../providers/services_provider.dart';

/// Services, decided by the society's own data:
///  - a service with listed providers is a DIRECTORY: open it, call or
///    WhatsApp the provider directly;
///  - a service with no provider listed in this society can be REQUESTED
///    from the society office, which arranges someone (residents only).
class ServicesScreen extends ConsumerStatefulWidget {
  const ServicesScreen({super.key});
  @override
  ConsumerState<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends ConsumerState<ServicesScreen> {
  String _query = '';

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(servicesProvider.notifier).loadServices());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(servicesProvider);
    final canRequest = ref.watch(currentUserProvider)?.role != 'Guard' && ref.watch(currentUserProvider)?.role != 'Staff';

    Widget body;
    if (state.isLoading && state.services.isEmpty) {
      body = const ListSkeleton();
    } else if (state.error != null && state.services.isEmpty) {
      body = ErrorView(message: state.error!, onRetry: () => ref.read(servicesProvider.notifier).loadServices());
    } else {
      final q = _query.trim().toLowerCase();
      final services = state.services.where((s) => q.isEmpty || s.name.toLowerCase().contains(q)).toList()
        ..sort((a, b) {
          final pa = state.providersFor(a.id).length, pb = state.providersFor(b.id).length;
          return pa != pb ? pb.compareTo(pa) : a.name.compareTo(b.name);
        });

      body = RefreshIndicator(
        color: AppColors.primary,
        onRefresh: () => ref.read(servicesProvider.notifier).loadServices(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: [
            TextField(
              onChanged: (v) => setState(() => _query = v),
              decoration: InputDecoration(
                hintText: 'Search services (maid, plumber...)',
                prefixIcon: const Icon(Icons.search_rounded),
                filled: true,
                fillColor: AppColors.surface,
                contentPadding: const EdgeInsets.symmetric(vertical: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
              ),
            ),
            const SizedBox(height: 12),
            if (services.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 40),
                child: EmptyState(icon: Icons.search_off_rounded, title: 'No matching service', subtitle: 'Try another word.'),
              ),
            for (final s in services) ...[
              _ServiceCard(service: s, providers: state.providersFor(s.id), canRequest: canRequest),
              const SizedBox(height: 10),
            ],
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

class _ServiceCard extends StatelessWidget {
  final ServiceModel service;
  final List<ServiceProviderModel> providers;
  final bool canRequest;
  const _ServiceCard({required this.service, required this.providers, required this.canRequest});

  @override
  Widget build(BuildContext context) {
    final count = providers.length;
    return AppCard(
      padding: const EdgeInsets.all(14),
      onTap: () {
        if (count > 0) {
          Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => ServiceProvidersScreen(service: service, providers: providers, canRequest: canRequest),
          ));
        } else if (canRequest) {
          showRequestServiceSheet(context, service);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('No ${service.name} provider is listed by the society yet.')));
        }
      },
      child: Row(children: [
        Container(
          width: 48, height: 48,
          decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
          child: const Icon(Icons.home_repair_service_rounded, color: AppColors.primary, size: 22),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(service.name, style: AppTextStyles.label, maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 2),
            Text(
              count > 0
                  ? '$count provider${count == 1 ? '' : 's'} · call directly'
                  : (canRequest ? 'None listed · ask the office to arrange' : 'No provider listed'),
              style: AppTextStyles.caption,
            ),
          ]),
        ),
        if (count > 0)
          const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.textHint)
        else if (canRequest)
          const Text('Request', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 13)),
      ]),
    );
  }
}

/// The providers the society lists for one service, each with Call/WhatsApp.
class ServiceProvidersScreen extends StatelessWidget {
  final ServiceModel service;
  final List<ServiceProviderModel> providers;
  final bool canRequest;
  const ServiceProvidersScreen({super.key, required this.service, required this.providers, required this.canRequest});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: Text(service.name)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          for (final p in providers) ...[
            _ProviderCard(provider: p),
            const SizedBox(height: 12),
          ],
          if (canRequest) ...[
            const SizedBox(height: 8),
            Center(
              child: TextButton.icon(
                onPressed: () => showRequestServiceSheet(context, service),
                icon: const Icon(Icons.support_agent_rounded),
                label: const Text('Ask the society office to arrange instead'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ProviderCard extends StatelessWidget {
  final ServiceProviderModel provider;
  const _ProviderCard({required this.provider});

  @override
  Widget build(BuildContext context) {
    final p = provider;
    return AppCard(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(color: AppColors.info.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: const Icon(Icons.person_rounded, color: AppColors.info),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p.name, style: AppTextStyles.label),
              if (p.hasPhone) Text(p.phone!, style: AppTextStyles.caption),
            ]),
          ),
          if (p.isDailyHelp)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(color: AppColors.secondary.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
              child: const Text('Daily help', style: TextStyle(color: AppColors.secondary, fontSize: 11, fontWeight: FontWeight.w600)),
            ),
        ]),
        if (p.availability != null || p.priceLabel != null) ...[
          const SizedBox(height: 10),
          Wrap(spacing: 16, runSpacing: 6, children: [
            if (p.availability != null)
              Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.schedule_rounded, size: 15, color: AppColors.textSecondary),
                const SizedBox(width: 4),
                Flexible(child: Text(p.availability!, style: AppTextStyles.caption)),
              ]),
            if (p.priceLabel != null)
              Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.payments_outlined, size: 15, color: AppColors.textSecondary),
                const SizedBox(width: 4),
                Text(p.priceLabel!, style: AppTextStyles.caption),
              ]),
          ]),
        ],
        const SizedBox(height: 12),
        if (p.hasPhone)
          Row(children: [
            Expanded(
              child: FilledButton.icon(
                onPressed: () => ContactLauncher.call(context, p.phone!),
                icon: const Icon(Icons.call_rounded, size: 18),
                label: const Text('Call'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => ContactLauncher.whatsapp(context, p.phone!),
                icon: const Icon(Icons.chat_rounded, size: 18),
                label: const Text('WhatsApp'),
              ),
            ),
          ])
        else
          Text('No phone number listed - contact the society office.', style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary)),
      ]),
    );
  }
}

/// Ask the office to arrange a service. Scrolls and lifts above the keyboard.
Future<void> showRequestServiceSheet(BuildContext context, ServiceModel service) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    builder: (_) => _RequestSheet(service: service),
  );
}

class _RequestSheet extends ConsumerStatefulWidget {
  final ServiceModel service;
  const _RequestSheet({required this.service});
  @override
  ConsumerState<_RequestSheet> createState() => _RequestSheetState();
}

class _RequestSheetState extends ConsumerState<_RequestSheet> {
  final _descCtrl = TextEditingController();
  DateTime? _date;
  bool _isLoading = false;
  String? _error;

  @override
  void dispose() {
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(context: context, initialDate: _date ?? now, firstDate: now, lastDate: now.add(const Duration(days: 90)));
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _submit() async {
    if (_descCtrl.text.trim().length < 5) {
      setState(() => _error = 'Tell the office what you need (a few words).');
      return;
    }
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      await ref.read(servicesProvider.notifier).requestService(
            serviceId: widget.service.id,
            description: _descCtrl.text.trim(),
            preferredDate: _date == null ? null : '${_date!.year}-${_date!.month.toString().padLeft(2, '0')}-${_date!.day.toString().padLeft(2, '0')}',
          );
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('Request sent. The society office will arrange ${widget.service.name}.'),
        backgroundColor: AppColors.secondary,
      ));
    } catch (e) {
      if (mounted) setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Container(
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            Text('Request ${widget.service.name}', style: AppTextStyles.h3),
            const SizedBox(height: 4),
            Text('The society office will arrange someone and update you.', style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary)),
            const SizedBox(height: 16),
            TextField(
              controller: _descCtrl,
              maxLines: 3,
              maxLength: 500,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(labelText: 'What do you need?', hintText: 'e.g. Cook for weekday lunches'),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _pickDate,
              icon: const Icon(Icons.event_rounded, size: 18),
              label: Text(_date == null ? 'Preferred date (optional)' : 'Preferred: ${_date!.day}/${_date!.month}/${_date!.year}'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 10),
              Text(_error!, style: AppTextStyles.body2.copyWith(color: AppColors.error)),
            ],
            const SizedBox(height: 16),
            AppButton(label: 'Send request', onPressed: _submit, isLoading: _isLoading, icon: Icons.send_rounded),
          ]),
        ),
      ),
    );
  }
}
