import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_text.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/error_view.dart';
import '../models/facility_model.dart';
import '../providers/facilities_provider.dart';

/// Book one facility: pick a day, see which hours are free (from the server,
/// so what is taken, closed or already past is never offered), pick a start
/// and a length, confirm. Pops with the new [FacilityBookingModel] on success.
class FacilityBookingScreen extends ConsumerStatefulWidget {
  final FacilityModel facility;

  /// Overrides "today" so tests are not tied to the real clock.
  final DateTime? today;

  const FacilityBookingScreen({super.key, required this.facility, this.today});

  @override
  ConsumerState<FacilityBookingScreen> createState() => _FacilityBookingScreenState();
}

class _FacilityBookingScreenState extends ConsumerState<FacilityBookingScreen> {
  late final List<DateTime> _days;
  late DateTime _date;

  FacilityAvailability? _availability;
  bool _loading = true;
  String? _loadError;
  int _loadToken = 0; // a slow answer for an earlier date must not overwrite a later one

  FacilitySlot? _slot;
  int _hours = 1;

  final _purpose = TextEditingController();
  final _guests = TextEditingController();
  bool _submitting = false;
  String? _submitError;

  FacilityModel get _f => widget.facility;

  @override
  void initState() {
    super.initState();
    final now = widget.today ?? DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final span = _f.advanceBookingDays.clamp(1, 60);
    _days = [for (var i = 0; i <= span; i++) today.add(Duration(days: i))];
    _date = _days.firstWhere(_f.opensOn, orElse: () => today);
    _loadAvailability();
  }

  @override
  void dispose() {
    _purpose.dispose();
    _guests.dispose();
    super.dispose();
  }

  Future<void> _loadAvailability() async {
    final token = ++_loadToken;
    setState(() {
      _loading = true;
      _loadError = null;
      _slot = null;
      _hours = 1;
      _submitError = null;
    });
    try {
      final a = await ref.read(facilitiesProvider.notifier).availability(facilityId: _f.id, date: DateText.iso(_date));
      if (!mounted || token != _loadToken) return;
      setState(() {
        _availability = a;
        _loading = false;
      });
    } catch (e) {
      if (!mounted || token != _loadToken) return;
      setState(() {
        _loadError = e.toString();
        _loading = false;
      });
    }
  }

  void _pickDate(DateTime d) {
    if (d == _date) return;
    setState(() => _date = d);
    _loadAvailability();
  }

  void _pickSlot(FacilitySlot s) => setState(() {
        _slot = s;
        _hours = _hours.clamp(1, s.maxHours);
        _submitError = null;
      });

  String get _endTime {
    final start = DateText.minutes(_slot!.start)!;
    return DateText.hhmm(start + _hours * 60);
  }

  Future<void> _confirm() async {
    final slot = _slot;
    if (slot == null || _submitting) return;
    setState(() {
      _submitting = true;
      _submitError = null;
    });
    try {
      final booking = await ref.read(facilitiesProvider.notifier).bookFacility(
            facilityId: _f.id,
            bookingDate: DateText.iso(_date),
            startTime: '${slot.start}:00',
            endTime: '$_endTime:00',
            purpose: _purpose.text.trim(),
            expectedGuests: int.tryParse(_guests.text.trim()),
          );
      if (!mounted) return;
      // Not "still submitting" any more: otherwise the button's spinner keeps
      // turning behind the confirmation.
      setState(() => _submitting = false);
      await showDialog<void>(
        context: context,
        builder: (_) => _ConfirmationDialog(booking: booking, facility: _f),
      );
      if (mounted) Navigator.of(context).pop(booking);
    } catch (e) {
      if (!mounted) return;
      setState(() => _submitError = e.toString());
      // Someone may have taken the slot a moment ago: show the day as it is now.
      if (e.toString().contains('already booked')) {
        await _loadAvailability();
        if (mounted) setState(() => _submitError = e.toString());
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Scaffold(
      backgroundColor: AppColors.background,
      resizeToAvoidBottomInset: true,
      appBar: AppBar(title: Text('Book ${_f.name}', overflow: TextOverflow.ellipsis)),
      body: SafeArea(
        // An eager scroll view, not a lazy ListView: the form is short, and a
        // lazy list leaves the confirm button unbuilt until scrolled to.
        child: SingleChildScrollView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: EdgeInsets.fromLTRB(16, 12, 16, 24 + bottom),
          child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _InfoCard(facility: _f),
            const SizedBox(height: 20),
            const Text('Choose a date', style: AppTextStyles.label),
            const SizedBox(height: 8),
            SizedBox(
              height: 84,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _days.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (_, i) => _DayChip(
                  day: _days[i],
                  selected: _days[i] == _date,
                  enabled: _f.opensOn(_days[i]),
                  onTap: () => _pickDate(_days[i]),
                ),
              ),
            ),
            const SizedBox(height: 20),
            const Text('Choose a start time', style: AppTextStyles.label),
            const SizedBox(height: 8),
            _slotsSection(),
            if (_slot != null) ...[
              const SizedBox(height: 20),
              const Text('How long?', style: AppTextStyles.label),
              const SizedBox(height: 8),
              Wrap(spacing: 8, runSpacing: 8, children: [
                for (var h = 1; h <= _slot!.maxHours; h++)
                  ChoiceChip(
                    key: Key('duration-$h'),
                    label: Text(h == 1 ? '1 hour' : '$h hours'),
                    selected: _hours == h,
                    onSelected: (_) => setState(() => _hours = h),
                  ),
              ]),
              const SizedBox(height: 16),
              _Summary(facility: _f, date: _date, start: _slot!.start, end: _endTime),
              const SizedBox(height: 16),
              TextField(
                key: const Key('booking-purpose'),
                controller: _purpose,
                maxLines: 2,
                maxLength: 500,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(labelText: 'Purpose (optional)', hintText: 'e.g. Birthday party, meeting', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 8),
              TextField(
                key: const Key('booking-guests'),
                controller: _guests,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(4)],
                decoration: const InputDecoration(labelText: 'Expected guests (optional)', border: OutlineInputBorder()),
              ),
            ],
            if (_submitError != null) ...[
              const SizedBox(height: 12),
              Container(key: const Key('booking-error'), child: ErrorBanner(message: _submitError!)),
            ],
            const SizedBox(height: 16),
            AppButton(
              label: _f.requiresApproval ? 'Request booking' : 'Confirm booking',
              icon: Icons.event_available_rounded,
              isLoading: _submitting,
              onPressed: _slot == null ? null : _confirm,
            ),
          ],
          ),
        ),
      ),
    );
  }

  Widget _slotsSection() {
    if (_loading) {
      return const Padding(padding: EdgeInsets.symmetric(vertical: 24), child: Center(child: CircularProgressIndicator(strokeWidth: 2)));
    }
    if (_loadError != null) {
      return Row(children: [
        Expanded(child: Text(_loadError!, style: AppTextStyles.body2.copyWith(color: AppColors.error))),
        TextButton(onPressed: _loadAvailability, child: const Text('Retry')),
      ]);
    }
    final a = _availability!;
    if (!a.bookable) {
      return _Note(icon: Icons.event_busy_rounded, text: a.message ?? 'This day cannot be booked.');
    }
    if (!a.hasFreeSlot) {
      return const _Note(icon: Icons.event_busy_rounded, text: 'Everything is taken or has passed on this day. Try another date.');
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Wrap(spacing: 8, runSpacing: 8, children: [
        for (final s in a.slots) _SlotChip(slot: s, selected: _slot?.start == s.start, onTap: s.available ? () => _pickSlot(s) : null),
      ]),
      const SizedBox(height: 8),
      const Text('Greyed times are already booked or have passed.', style: AppTextStyles.caption),
    ]);
  }
}

class _InfoCard extends StatelessWidget {
  final FacilityModel facility;
  const _InfoCard({required this.facility});

  @override
  Widget build(BuildContext context) {
    final f = facility;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.divider)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const Icon(Icons.access_time_rounded, size: 16, color: AppColors.textHint),
          const SizedBox(width: 6),
          Expanded(child: Text(f.hoursLabel.isEmpty ? 'Open hours not set' : 'Open ${f.hoursLabel}', style: AppTextStyles.body2)),
        ]),
        const SizedBox(height: 6),
        Row(children: [
          const Icon(Icons.payments_outlined, size: 16, color: AppColors.textHint),
          const SizedBox(width: 6),
          Expanded(child: Text(f.priceLabel, style: AppTextStyles.body2)),
        ]),
        const SizedBox(height: 6),
        Row(children: [
          const Icon(Icons.timer_outlined, size: 16, color: AppColors.textHint),
          const SizedBox(width: 6),
          Expanded(child: Text('Up to ${f.maxBookingHours} hour${f.maxBookingHours == 1 ? '' : 's'} per booking', style: AppTextStyles.body2)),
        ]),
        const SizedBox(height: 6),
        Row(children: [
          Icon(f.requiresApproval ? Icons.hourglass_top_rounded : Icons.verified_rounded, size: 16, color: AppColors.textHint),
          const SizedBox(width: 6),
          Expanded(child: Text(f.requiresApproval ? 'The society office approves each booking' : 'Confirmed instantly', style: AppTextStyles.body2)),
        ]),
      ]),
    );
  }
}

class _DayChip extends StatelessWidget {
  final DateTime day;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;
  const _DayChip({required this.day, required this.selected, required this.enabled, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final fg = selected ? Colors.white : (enabled ? AppColors.textPrimary : AppColors.textHint);
    return Semantics(
      button: true,
      enabled: enabled,
      selected: selected,
      label: '${DateText.date(day)}${enabled ? '' : ', closed'}',
      child: InkWell(
        key: Key('date-${DateText.iso(day)}'),
        borderRadius: BorderRadius.circular(14),
        onTap: enabled ? onTap : null,
        child: Container(
          width: 62,
          decoration: BoxDecoration(
            color: selected ? AppColors.primary : (enabled ? Colors.white : AppColors.divider),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: selected ? AppColors.primary : AppColors.divider),
          ),
          // FittedBox: a large system font scales the three lines down
          // instead of overflowing the chip.
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text(DateText.weekdayShort(day), style: TextStyle(fontSize: 12, color: fg)),
                Text('${day.day}', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: fg)),
                Text(enabled ? DateText.dayMonth(day).split(' ').last : 'Closed', style: TextStyle(fontSize: 11, color: fg)),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

class _SlotChip extends StatelessWidget {
  final FacilitySlot slot;
  final bool selected;
  final VoidCallback? onTap;
  const _SlotChip({required this.slot, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final label = DateText.clock(slot.start);
    return Semantics(
      button: true,
      enabled: onTap != null,
      selected: selected,
      label: slot.available ? label : '$label, ${slot.reason == 'booked' ? 'already booked' : 'not available'}',
      child: InkWell(
        key: Key('slot-${slot.start}'),
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minWidth: 88, minHeight: 44),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: selected ? AppColors.primary : (slot.available ? Colors.white : AppColors.divider),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: selected ? AppColors.primary : AppColors.divider),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: selected ? Colors.white : (slot.available ? AppColors.textPrimary : AppColors.textHint),
              decoration: slot.available ? null : TextDecoration.lineThrough,
            ),
          ),
        ),
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  final FacilityModel facility;
  final DateTime date;
  final String start;
  final String end;
  const _Summary({required this.facility, required this.date, required this.start, required this.end});

  @override
  Widget build(BuildContext context) {
    return Container(
      key: const Key('booking-summary'),
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(14)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(DateText.date(date), style: AppTextStyles.label),
        const SizedBox(height: 2),
        Text(DateText.range(start, end), style: AppTextStyles.body2),
        const SizedBox(height: 6),
        Text(facility.charges > 0 ? 'Amount: ₹${facility.charges.toStringAsFixed(facility.charges % 1 == 0 ? 0 : 2)}' : 'No charge', style: AppTextStyles.body2),
      ]),
    );
  }
}

class _Note extends StatelessWidget {
  final IconData icon;
  final String text;
  const _Note({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: AppColors.warning.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(14)),
        child: Row(children: [
          Icon(icon, color: AppColors.warning, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(text, style: AppTextStyles.body2)),
        ]),
      );
}

class _ConfirmationDialog extends StatelessWidget {
  final FacilityBookingModel booking;
  final FacilityModel facility;
  const _ConfirmationDialog({required this.booking, required this.facility});

  @override
  Widget build(BuildContext context) {
    final approved = booking.isApproved;
    final date = DateText.parseDate(booking.bookingDate);
    return AlertDialog(
      key: const Key('booking-confirmation'),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      icon: Icon(approved ? Icons.check_circle_rounded : Icons.hourglass_top_rounded, size: 44, color: approved ? AppColors.secondary : AppColors.warning),
      title: Text(approved ? 'Booking confirmed' : 'Request sent', textAlign: TextAlign.center),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        Text(booking.facilityName ?? facility.name, style: AppTextStyles.label, textAlign: TextAlign.center),
        const SizedBox(height: 4),
        if (date != null) Text(DateText.date(date), style: AppTextStyles.body2),
        Text(DateText.range(booking.startTime, booking.endTime), style: AppTextStyles.body2),
        if (booking.bookingNumber != null) ...[
          const SizedBox(height: 8),
          Text('Booking no. ${booking.bookingNumber}', style: AppTextStyles.caption),
        ],
        const SizedBox(height: 10),
        Text(
          approved ? 'See it under My Bookings.' : 'The society office will approve it. You will be notified, and you can follow it under My Bookings.',
          style: AppTextStyles.body2,
          textAlign: TextAlign.center,
        ),
      ]),
      actions: [TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('OK'))],
    );
  }
}
