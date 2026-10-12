import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../../../core/utils/date_text.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_view.dart';
import '../../../shared/widgets/network_image_widget.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/facility_model.dart';
import '../providers/facilities_provider.dart';
import 'facility_booking_screen.dart';

class FacilitiesScreen extends ConsumerStatefulWidget {
  const FacilitiesScreen({super.key});
  @override
  ConsumerState<FacilitiesScreen> createState() => _FacilitiesScreenState();
}

class _FacilitiesScreenState extends ConsumerState<FacilitiesScreen>
    with SingleTickerProviderStateMixin, AutoRefreshMixin {
  late TabController _tabController;

  Future<void> _reload() async {
    final n = ref.read(facilitiesProvider.notifier);
    await Future.wait([n.loadFacilities(), n.loadBookings()]);
  }

  @override
  void onAutoRefresh() => _reload();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    Future.microtask(_reload);
    startAutoRefresh();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _book(FacilityModel facility) async {
    final booking = await Navigator.of(context).push<FacilityBookingModel>(
      MaterialPageRoute(builder: (_) => FacilityBookingScreen(facility: facility)),
    );
    if (booking != null && mounted) {
      _tabController.animateTo(1);
      ref.read(facilitiesProvider.notifier).loadBookings();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(facilitiesProvider);
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Facilities'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textHint,
          indicatorColor: AppColors.primary,
          tabs: const [Tab(text: 'Available'), Tab(text: 'My Bookings')],
        ),
      ),
      body: TabBarView(controller: _tabController, children: [
        _FacilityList(state: state, onRefresh: _reload, onBook: _book, canBook: ref.watch(currentUserProvider)?.can('book_facility') ?? false),
        _BookingList(state: state, onRefresh: _reload),
      ]),
    );
  }
}

class _FacilityList extends StatelessWidget {
  final FacilitiesState state;
  final Future<void> Function() onRefresh;
  final void Function(FacilityModel) onBook;
  final bool canBook;
  const _FacilityList({required this.state, required this.onRefresh, required this.onBook, required this.canBook});

  @override
  Widget build(BuildContext context) {
    if (state.isLoading) return const ListSkeleton();
    if (state.error != null && state.facilities.isEmpty) {
      return ErrorView(message: state.error!, onRetry: onRefresh);
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      color: AppColors.primary,
      child: state.facilities.isEmpty
          ? ListView(children: const [
              SizedBox(height: 80),
              EmptyState(
                icon: Icons.meeting_room_outlined,
                title: 'No facilities yet',
                subtitle: 'Your society has not added any bookable facilities. Please ask the society office.',
              ),
            ])
          : ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              itemCount: state.facilities.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (_, i) => _FacilityCard(facility: state.facilities[i], canBook: canBook, onBook: () => onBook(state.facilities[i])),
            ),
    );
  }
}

class _FacilityCard extends StatelessWidget {
  final FacilityModel facility;
  final VoidCallback onBook;

  /// False for accounts the API refuses (office, staff, guards): they see the facility, not a button that can only 403.
  final bool canBook;
  const _FacilityCard({required this.facility, required this.onBook, required this.canBook});

  @override
  Widget build(BuildContext context) {
    final f = facility;
    return AppCard(
      padding: EdgeInsets.zero,
      onTap: canBook ? onBook : null,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (f.image != null)
          NetImage(url: f.image, width: double.infinity, height: 140, borderRadius: const BorderRadius.vertical(top: Radius.circular(16))),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(f.name, style: AppTextStyles.label.copyWith(fontSize: 15))),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: AppColors.secondary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
                child: Text(f.priceLabel, style: const TextStyle(color: AppColors.secondary, fontSize: 11, fontWeight: FontWeight.w600)),
              ),
            ]),
            if (f.description != null && f.description!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(f.description!, style: AppTextStyles.caption, maxLines: 2, overflow: TextOverflow.ellipsis),
            ],
            const SizedBox(height: 8),
            Wrap(spacing: 12, runSpacing: 4, crossAxisAlignment: WrapCrossAlignment.center, children: [
              if (f.capacity != null) _Meta(Icons.people_outline_rounded, 'Capacity ${f.capacity}'),
              if (f.hoursLabel.isNotEmpty) _Meta(Icons.access_time_rounded, f.hoursLabel),
              _Meta(f.requiresApproval ? Icons.hourglass_top_rounded : Icons.verified_rounded, f.requiresApproval ? 'Needs approval' : 'Instant'),
            ]),
            const SizedBox(height: 10),
            if (!canBook)
              const Text('Booking is for residents. Ask the society office to book on your behalf.', key: Key('booking-residents-only'), style: AppTextStyles.caption)
            else
            Align(
              alignment: Alignment.centerRight,
              child: ElevatedButton(
                key: Key('book-${f.id}'),
                onPressed: onBook,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  elevation: 0,
                  minimumSize: const Size(96, 44),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Book', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
              ),
            ),
          ]),
        ),
      ]),
    );
  }
}

class _Meta extends StatelessWidget {
  final IconData icon;
  final String text;
  const _Meta(this.icon, this.text);
  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 13, color: AppColors.textHint),
        const SizedBox(width: 4),
        Text(text, style: AppTextStyles.caption),
      ]);
}

class _BookingList extends ConsumerWidget {
  final FacilitiesState state;
  final Future<void> Function() onRefresh;
  const _BookingList({required this.state, required this.onRefresh});

  Future<void> _cancel(BuildContext context, WidgetRef ref, FacilityBookingModel b) async {
    // null = keep the booking; a string (possibly empty) = cancel with that reason.
    final reason = await showDialog<String>(
      context: context,
      builder: (_) => _CancelDialog(booking: b),
    );
    if (reason == null || !context.mounted) return;
    final text = reason.trim();
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(facilitiesProvider.notifier).cancelBooking(b.id, reason: text);
      messenger.showSnackBar(const SnackBar(content: Text('Booking cancelled.')));
    } catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (state.bookingsLoading) return const ListSkeleton();
    if (state.bookingsError != null && state.bookings.isEmpty) {
      return ErrorView(message: state.bookingsError!, onRetry: onRefresh);
    }

    final now = DateTime.now();
    final upcoming = state.bookings.where((b) => b.isUpcoming(now)).toList()
      ..sort((a, b) => (a.startsAt ?? now).compareTo(b.startsAt ?? now));
    final past = state.bookings.where((b) => !b.isUpcoming(now)).toList();

    return RefreshIndicator(
      onRefresh: onRefresh,
      color: AppColors.primary,
      child: state.bookings.isEmpty
          ? ListView(children: const [
              SizedBox(height: 80),
              EmptyState(
                icon: Icons.event_available_outlined,
                title: 'No bookings yet',
                subtitle: 'Book a facility from the Available tab. Your bookings and their status appear here.',
              ),
            ])
          : ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                if (upcoming.isNotEmpty) ...[
                  const _SectionLabel('Upcoming'),
                  for (final b in upcoming) _BookingCard(booking: b, onCancel: b.canCancel ? () => _cancel(context, ref, b) : null),
                ],
                if (past.isNotEmpty) ...[
                  const _SectionLabel('Past & cancelled'),
                  for (final b in past) _BookingCard(booking: b),
                ],
              ],
            ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String text;
  const _SectionLabel(this.text);
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 8, 4, 8),
        child: Text(text.toUpperCase(), style: AppTextStyles.label.copyWith(fontSize: 12, color: AppColors.textSecondary, letterSpacing: 0.6)),
      );
}

class _BookingCard extends StatelessWidget {
  final FacilityBookingModel booking;
  final VoidCallback? onCancel;
  const _BookingCard({required this.booking, this.onCancel});

  @override
  Widget build(BuildContext context) {
    final b = booking;
    final date = DateText.parseDate(b.bookingDate);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppCard(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
              child: const Icon(Icons.meeting_room_rounded, color: AppColors.primary, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(b.facilityName ?? 'Facility', style: AppTextStyles.label, maxLines: 1, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 2),
                Text(date == null ? (b.bookingDate ?? '') : DateText.date(date), style: AppTextStyles.caption),
                Text(DateText.range(b.startTime, b.endTime), style: AppTextStyles.caption),
              ]),
            ),
            StatusBadge(status: b.status),
          ]),
          if (b.amount > 0 || b.bookingNumber != null) ...[
            const SizedBox(height: 8),
            Text(
              [if (b.bookingNumber != null) b.bookingNumber!, if (b.amount > 0) '₹${b.amount.toStringAsFixed(b.amount % 1 == 0 ? 0 : 2)}'].join(' · '),
              style: AppTextStyles.caption,
            ),
          ],
          if (b.adminNotes != null && b.adminNotes!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text('Office: ${b.adminNotes}', style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary)),
          ],
          if (onCancel != null) ...[
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                key: Key('cancel-${b.id}'),
                onPressed: onCancel,
                icon: const Icon(Icons.close_rounded, size: 18, color: AppColors.error),
                label: const Text('Cancel', style: TextStyle(color: AppColors.error)),
              ),
            ),
          ],
        ]),
      ),
    );
  }
}

/// Asks whether to cancel a booking. Owns its reason field, so the controller
/// is disposed with the dialog - disposing it from the caller while the close
/// animation was still running threw "used after being disposed".
class _CancelDialog extends StatefulWidget {
  final FacilityBookingModel booking;
  const _CancelDialog({required this.booking});

  @override
  State<_CancelDialog> createState() => _CancelDialogState();
}

class _CancelDialogState extends State<_CancelDialog> {
  final _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final b = widget.booking;
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text('Cancel this booking?'),
      content: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${b.facilityName ?? 'Facility'} · ${DateText.range(b.startTime, b.endTime)}', style: AppTextStyles.body2),
          const SizedBox(height: 12),
          TextField(
            key: const Key('cancel-reason'),
            controller: _reason,
            maxLength: 200,
            decoration: const InputDecoration(labelText: 'Reason (optional)', border: OutlineInputBorder()),
          ),
        ]),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Keep booking')),
        TextButton(
          key: const Key('confirm-cancel'),
          onPressed: () => Navigator.pop(context, _reason.text),
          child: const Text('Cancel booking', style: TextStyle(color: AppColors.error)),
        ),
      ],
    );
  }
}
