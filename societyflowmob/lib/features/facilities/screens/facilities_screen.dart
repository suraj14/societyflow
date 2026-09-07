import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/network_image_widget.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../providers/facilities_provider.dart';
import '../models/facility_model.dart';

class FacilitiesScreen extends ConsumerStatefulWidget {
  const FacilitiesScreen({super.key});
  @override
  ConsumerState<FacilitiesScreen> createState() => _FacilitiesScreenState();
}

class _FacilitiesScreenState extends ConsumerState<FacilitiesScreen>
    with SingleTickerProviderStateMixin, AutoRefreshMixin {
  late TabController _tabController;

  @override
  void onAutoRefresh() {
    ref.read(facilitiesProvider.notifier).loadFacilities();
    ref.read(facilitiesProvider.notifier).loadBookings();
  }

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    Future.microtask(() {
      ref.read(facilitiesProvider.notifier).loadFacilities();
      ref.read(facilitiesProvider.notifier).loadBookings();
    });
    startAutoRefresh();
  }

  @override
  void dispose() { _tabController.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(facilitiesProvider);
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Facilities'),
        bottom: TabBar(controller: _tabController, labelColor: AppColors.primary, unselectedLabelColor: AppColors.textHint, indicatorColor: AppColors.primary, tabs: const [Tab(text: 'Available'), Tab(text: 'My Bookings')]),
      ),
      body: state.isLoading
          ? const ListSkeleton()
          : RefreshIndicator(
              onRefresh: () async {
                await ref.read(facilitiesProvider.notifier).loadFacilities();
                await ref.read(facilitiesProvider.notifier).loadBookings();
              },
              color: AppColors.primary,
              child: TabBarView(controller: _tabController, children: [
                _FacilityList(facilities: state.facilities),
                _BookingList(bookings: state.bookings),
              ]),
            ),
    );
  }
}

class _FacilityList extends StatelessWidget {
  final List<FacilityModel> facilities;
  const _FacilityList({required this.facilities});

  @override
  Widget build(BuildContext context) {
    if (facilities.isEmpty) return const EmptyState(icon: Icons.meeting_room_outlined, title: 'No facilities', subtitle: 'No facilities available in your society');
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: facilities.length,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (_, i) => _FacilityCard(facility: facilities[i]),
    );
  }
}

class _FacilityCard extends StatelessWidget {
  final FacilityModel facility;
  const _FacilityCard({required this.facility});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: EdgeInsets.zero,
      onTap: () => _showBookingSheet(context),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (facility.image != null)
          NetImage(url: facility.image, width: double.infinity, height: 140, borderRadius: const BorderRadius.vertical(top: Radius.circular(16))),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(facility.name, style: AppTextStyles.label.copyWith(fontSize: 15))),
              if (facility.charges > 0)
                Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: AppColors.secondary.withOpacity(0.1), borderRadius: BorderRadius.circular(20)), child: Text('₹${facility.charges.toStringAsFixed(0)}/hr', style: const TextStyle(color: AppColors.secondary, fontSize: 11, fontWeight: FontWeight.w600))),
            ]),
            if (facility.description != null) ...[
              const SizedBox(height: 4),
              Text(facility.description!, style: AppTextStyles.caption, maxLines: 2, overflow: TextOverflow.ellipsis),
            ],
            const SizedBox(height: 8),
            Row(children: [
              if (facility.capacity != null) ...[
                const Icon(Icons.people_outline_rounded, size: 13, color: AppColors.textHint),
                const SizedBox(width: 4),
                Text('Capacity: ${facility.capacity}', style: AppTextStyles.caption),
                const SizedBox(width: 12),
              ],
              if (facility.openTime != null) ...[
                const Icon(Icons.access_time_rounded, size: 13, color: AppColors.textHint),
                const SizedBox(width: 4),
                Text('${facility.openTime} - ${facility.closeTime}', style: AppTextStyles.caption),
              ],
              const Spacer(),
              ElevatedButton(
                onPressed: () => _showBookingSheet(context),
                style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, elevation: 0, padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
                child: const Text('Book', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
              ),
            ]),
          ]),
        ),
      ]),
    );
  }

  void _showBookingSheet(BuildContext context) {
    showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.transparent, builder: (_) => _BookingSheet(facility: facility));
  }
}

class _BookingSheet extends ConsumerStatefulWidget {
  final FacilityModel facility;
  const _BookingSheet({required this.facility});
  @override
  ConsumerState<_BookingSheet> createState() => _BookingSheetState();
}

class _BookingSheetState extends ConsumerState<_BookingSheet> {
  DateTime? _selectedDate;
  String? _startTime;
  String? _endTime;
  final _purposeCtrl = TextEditingController();
  bool _isLoading = false;

  final List<String> _timeSlots = ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'];

  @override
  void dispose() { _purposeCtrl.dispose(); super.dispose(); }

  Future<void> _book() async {
    if (_selectedDate == null || _startTime == null || _endTime == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select date and time'), backgroundColor: AppColors.error));
      return;
    }
    setState(() => _isLoading = true);
    try {
      await ref.read(facilitiesProvider.notifier).bookFacility(
        facilityId: widget.facility.id,
        bookingDate: '${_selectedDate!.year}-${_selectedDate!.month.toString().padLeft(2,'0')}-${_selectedDate!.day.toString().padLeft(2,'0')}',
        startTime: '$_startTime:00',
        endTime: '$_endTime:00',
        purpose: _purposeCtrl.text.trim().isNotEmpty ? _purposeCtrl.text.trim() : null,
      );
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Booking submitted! Awaiting approval.'), backgroundColor: AppColors.secondary, behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), margin: const EdgeInsets.all(16)));
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
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
          const SizedBox(height: 16),
          Text('Book ${widget.facility.name}', style: AppTextStyles.h3),
          const SizedBox(height: 20),
          const Text('Select Date', style: AppTextStyles.label),
          const SizedBox(height: 8),
          GestureDetector(
            onTap: () async {
              final d = await showDatePicker(context: context, initialDate: DateTime.now().add(const Duration(days: 1)), firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 30)));
              if (d != null) setState(() => _selectedDate = d);
            },
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(border: Border.all(color: AppColors.divider), borderRadius: BorderRadius.circular(12), color: AppColors.background),
              child: Row(children: [
                const Icon(Icons.calendar_today_rounded, color: AppColors.textHint, size: 18),
                const SizedBox(width: 10),
                Text(_selectedDate == null ? 'Choose a date' : '${_selectedDate!.day}/${_selectedDate!.month}/${_selectedDate!.year}', style: _selectedDate == null ? AppTextStyles.body2 : AppTextStyles.label),
              ]),
            ),
          ),
          const SizedBox(height: 16),
          Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Start Time', style: AppTextStyles.label),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                value: _startTime,
                hint: const Text('From'),
                decoration: InputDecoration(border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                items: _timeSlots.map((t) => DropdownMenuItem(value: t, child: Text(t))).toList(),
                onChanged: (v) => setState(() => _startTime = v),
              ),
            ])),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('End Time', style: AppTextStyles.label),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                value: _endTime,
                hint: const Text('To'),
                decoration: InputDecoration(border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
                items: _timeSlots.map((t) => DropdownMenuItem(value: t, child: Text(t))).toList(),
                onChanged: (v) => setState(() => _endTime = v),
              ),
            ])),
          ]),
          const SizedBox(height: 16),
          const Text('Purpose (Optional)', style: AppTextStyles.label),
          const SizedBox(height: 8),
          TextFormField(controller: _purposeCtrl, decoration: const InputDecoration(hintText: 'e.g. Birthday party, Meeting...'), maxLines: 2),
          const SizedBox(height: 24),
          AppButton(label: 'Confirm Booking', onPressed: _book, isLoading: _isLoading, icon: Icons.check_circle_rounded),
        ]),
      ),
    );
  }
}

class _BookingList extends ConsumerWidget {
  final List<FacilityBookingModel> bookings;
  const _BookingList({required this.bookings});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (bookings.isEmpty) return const EmptyState(icon: Icons.event_available_outlined, title: 'No bookings', subtitle: 'Your facility bookings will appear here');
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: bookings.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, i) {
        final b = bookings[i];
        return AppCard(
          padding: const EdgeInsets.all(14),
          child: Row(children: [
            Container(width: 44, height: 44, decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.meeting_room_rounded, color: AppColors.primary, size: 20)),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(b.facilityName ?? 'Facility', style: AppTextStyles.label),
              const SizedBox(height: 2),
              Text('${b.bookingDate ?? ''} • ${b.startTime ?? ''} - ${b.endTime ?? ''}', style: AppTextStyles.caption),
            ])),
            StatusBadge(status: b.status),
          ]),
        );
      },
    );
  }
}
