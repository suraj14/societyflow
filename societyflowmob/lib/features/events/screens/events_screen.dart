import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/network_image_widget.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../providers/events_provider.dart';
import '../models/event_model.dart';

class EventsScreen extends ConsumerStatefulWidget {
  const EventsScreen({super.key});
  @override
  ConsumerState<EventsScreen> createState() => _EventsScreenState();
}

class _EventsScreenState extends ConsumerState<EventsScreen> with AutoRefreshMixin {
  @override
  void onAutoRefresh() => ref.read(eventsProvider.notifier).loadEvents();

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(eventsProvider.notifier).loadEvents());
    startAutoRefresh();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(eventsProvider);
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Events')),
      body: RefreshIndicator(
        onRefresh: () => ref.read(eventsProvider.notifier).loadEvents(),
        color: AppColors.primary,
        child: state.isLoading
            ? const ListSkeleton()
            : state.events.isEmpty
                ? const EmptyState(icon: Icons.event_outlined, title: 'No upcoming events', subtitle: 'Society events will appear here')
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: state.events.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (_, i) => _EventCard(event: state.events[i]),
                  ),
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  final EventModel event;
  const _EventCard({required this.event});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: EdgeInsets.zero,
      onTap: () => _showDetail(context),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (event.image != null)
          NetImage(url: event.image, width: double.infinity, height: 160, borderRadius: const BorderRadius.vertical(top: Radius.circular(16))),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(event.title, style: AppTextStyles.label.copyWith(fontSize: 15))),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: (event.isOngoing ? AppColors.secondary : AppColors.primary).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(event.isOngoing ? 'Ongoing' : 'Upcoming', style: TextStyle(color: event.isOngoing ? AppColors.secondary : AppColors.primary, fontSize: 10, fontWeight: FontWeight.w600)),
              ),
            ]),
            if (event.description != null) ...[
              const SizedBox(height: 6),
              Text(event.description!, style: AppTextStyles.body2, maxLines: 2, overflow: TextOverflow.ellipsis),
            ],
            const SizedBox(height: 10),
            Row(children: [
              if (event.eventDate != null) ...[
                const Icon(Icons.calendar_today_rounded, size: 13, color: AppColors.textHint),
                const SizedBox(width: 4),
                Text(event.eventDate!, style: AppTextStyles.caption),
                const SizedBox(width: 12),
              ],
              if (event.startTime != null) ...[
                const Icon(Icons.access_time_rounded, size: 13, color: AppColors.textHint),
                const SizedBox(width: 4),
                Text('${event.startTime}${event.endTime != null ? ' - ${event.endTime}' : ''}', style: AppTextStyles.caption),
              ],
            ]),
            if (event.venue != null) ...[
              const SizedBox(height: 4),
              Row(children: [
                const Icon(Icons.location_on_outlined, size: 13, color: AppColors.textHint),
                const SizedBox(width: 4),
                Text(event.venue!, style: AppTextStyles.caption),
              ]),
            ],
          ]),
        ),
      ]),
    );
  }

  void _showDetail(BuildContext context) {
    showModalBottomSheet(
      context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
      builder: (_) => DraggableScrollableSheet(
        initialChildSize: 0.75, maxChildSize: 0.95, minChildSize: 0.5,
        builder: (_, ctrl) => Container(
          decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
          child: Column(children: [
            const SizedBox(height: 12),
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
            Expanded(child: ListView(controller: ctrl, padding: const EdgeInsets.all(24), children: [
              if (event.image != null) ...[
                NetImage(url: event.image, width: double.infinity, height: 200, borderRadius: BorderRadius.circular(AppRadius.lg)),
                const SizedBox(height: 16),
              ],
              Text(event.title, style: AppTextStyles.h3),
              const SizedBox(height: 12),
              if (event.eventDate != null) _detailRow(Icons.calendar_today_rounded, 'Date', event.eventDate!),
              if (event.startTime != null) _detailRow(Icons.access_time_rounded, 'Time', '${event.startTime}${event.endTime != null ? ' - ${event.endTime}' : ''}'),
              if (event.venue != null) _detailRow(Icons.location_on_outlined, 'Venue', event.venue!),
              const SizedBox(height: 16),
              const Divider(),
              const SizedBox(height: 16),
              if (event.description != null) Text(event.description!, style: AppTextStyles.body1.copyWith(height: 1.6)),
            ])),
          ]),
        ),
      ),
    );
  }

  Widget _detailRow(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(children: [
      Icon(icon, size: 16, color: AppColors.primary),
      const SizedBox(width: 8),
      Text('$label: ', style: AppTextStyles.caption),
      Expanded(child: Text(value, style: AppTextStyles.label.copyWith(fontSize: 13))),
    ]),
  );
}
