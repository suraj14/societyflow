import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../models/access_pass_model.dart';
import '../providers/access_passes_provider.dart';

/// Edit a pass, or - with [extendOnly] - just push its end date out.
///
/// The expired case is the one that happens constantly, and it needs
/// exactly one field, so it gets its own stripped screen rather than the
/// full form with a date buried in it. Both call the same API, which calls
/// the same service the web form does.
///
/// The QR is never reissued by either path, so nothing has to be reprinted
/// or redistributed after a change here.
class AccessPassEditScreen extends ConsumerStatefulWidget {
  final AccessPassModel pass;
  final bool extendOnly;

  const AccessPassEditScreen({
    super.key,
    required this.pass,
    this.extendOnly = false,
  });

  @override
  ConsumerState<AccessPassEditScreen> createState() => _AccessPassEditScreenState();
}

class _AccessPassEditScreenState extends ConsumerState<AccessPassEditScreen> {
  late final TextEditingController _name;
  late final TextEditingController _phone;
  late final TextEditingController _category;

  DateTime? _validFrom;
  DateTime? _validUntil;
  late Set<int> _days;
  TimeOfDay? _start;
  TimeOfDay? _end;

  static const _dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  @override
  void initState() {
    super.initState();
    final p = widget.pass;
    _name = TextEditingController(text: p.personName);
    _phone = TextEditingController(text: p.personPhone ?? '');
    _category = TextEditingController(text: p.category ?? '');
    _validFrom = _parseDate(p.validFrom);
    // An expired pass opens with a sensible new date already in place, so
    // the common case is "confirm", not "pick".
    _validUntil = p.isExpired
        ? DateTime.now().add(const Duration(days: 30))
        : _parseDate(p.validUntil);
    _days = p.allowedDays.toSet();
    _start = _parseTime(p.startTime);
    _end = _parseTime(p.endTime);
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _category.dispose();
    super.dispose();
  }

  static DateTime? _parseDate(String? s) =>
      (s == null || s.isEmpty) ? null : DateTime.tryParse(s);

  static TimeOfDay? _parseTime(String? s) {
    if (s == null || s.isEmpty) return null;
    final parts = s.split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null) return null;
    return TimeOfDay(hour: h, minute: m);
  }

  static String _fmtDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  static String _fmtTime(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final saving = ref.watch(accessPassesProvider).isSaving;
    final p = widget.pass;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: Text(widget.extendOnly ? 'Extend Pass' : 'Edit Pass')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (p.isExpired)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.warning.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                'This pass expired on ${p.validUntil ?? '-'}. '
                'Setting a new end date brings it back into use — the same QR keeps working.',
                style: AppTextStyles.caption,
              ),
            ),
          if (p.isExpired) const SizedBox(height: 16),

          if (!widget.extendOnly) ...[
            _label('Name'),
            TextField(controller: _name, decoration: _dec('Name')),
            const SizedBox(height: 12),
            _label('Phone'),
            TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: _dec('Phone (optional)')),
            const SizedBox(height: 12),
            _label('Category'),
            TextField(controller: _category, decoration: _dec('Cleaner, Electrician, ...')),
            const SizedBox(height: 16),
            _label('Valid From'),
            _dateTile(_validFrom, (d) => setState(() => _validFrom = d)),
            const SizedBox(height: 12),
          ],

          _label('Valid Until'),
          _dateTile(
            _validUntil,
            (d) => setState(() => _validUntil = d),
            firstDate: widget.extendOnly ? DateTime.now() : null,
          ),
          const SizedBox(height: 16),

          if (p.isRecurring) ...[
            _label('Allowed Days'),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: List.generate(7, (i) {
                final on = _days.contains(i);
                return ChoiceChip(
                  label: Text(_dayLabels[i]),
                  selected: on,
                  onSelected: (v) => setState(() => v ? _days.add(i) : _days.remove(i)),
                );
              }),
            ),
            const SizedBox(height: 16),
          ],

          _label('Time Window'),
          Row(
            children: [
              Expanded(child: _timeTile('From', _start, (t) => setState(() => _start = t))),
              const SizedBox(width: 12),
              Expanded(child: _timeTile('To', _end, (t) => setState(() => _end = t))),
            ],
          ),
          const SizedBox(height: 24),

          SizedBox(
            height: 50,
            child: FilledButton(
              // Disabled while a save is in flight - the provider also
              // refuses a second call, so a double tap cannot double-submit.
              onPressed: saving ? null : _save,
              style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
              child: saving
                  ? const SizedBox(
                      width: 20, height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(widget.extendOnly ? 'Extend Pass' : 'Save Changes'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Text(text, style: AppTextStyles.label),
      );

  InputDecoration _dec(String hint) => InputDecoration(
        hintText: hint,
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      );

  Widget _dateTile(DateTime? value, ValueChanged<DateTime> onPick, {DateTime? firstDate}) {
    return InkWell(
      onTap: () async {
        final now = DateTime.now();
        final picked = await showDatePicker(
          context: context,
          initialDate: value ?? now,
          firstDate: firstDate ?? DateTime(now.year - 2),
          lastDate: DateTime(now.year + 5),
        );
        if (picked != null) onPick(picked);
      },
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.divider),
        ),
        child: Row(
          children: [
            const Icon(Icons.calendar_today_rounded, size: 18, color: AppColors.primary),
            const SizedBox(width: 10),
            Text(value == null ? 'No end date' : _fmtDate(value), style: AppTextStyles.body2),
          ],
        ),
      ),
    );
  }

  Widget _timeTile(String label, TimeOfDay? value, ValueChanged<TimeOfDay> onPick) {
    return InkWell(
      onTap: () async {
        final picked = await showTimePicker(
          context: context,
          initialTime: value ?? const TimeOfDay(hour: 8, minute: 0),
        );
        if (picked != null) onPick(picked);
      },
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.divider),
        ),
        child: Row(
          children: [
            const Icon(Icons.schedule_rounded, size: 18, color: AppColors.primary),
            const SizedBox(width: 8),
            Text(value == null ? label : _fmtTime(value), style: AppTextStyles.body2),
          ],
        ),
      ),
    );
  }

  Future<void> _save() async {
    final notifier = ref.read(accessPassesProvider.notifier);

    if (widget.extendOnly) {
      if (_validUntil == null) {
        _toast('Pick an end date first.');
        return;
      }
      final error = await notifier.extend(
        widget.pass.id,
        validUntil: _fmtDate(_validUntil!),
        // Only send a schedule the user could actually see and change -
        // otherwise an extend would overwrite fields this screen hid.
        allowedDays: widget.pass.isRecurring ? (_days.toList()..sort()) : null,
        startTime: _start == null ? null : _fmtTime(_start!),
        endTime: _end == null ? null : _fmtTime(_end!),
      );
      _finish(error);
      return;
    }

    if (_name.text.trim().isEmpty) {
      _toast('Name is required.');
      return;
    }
    if (_validFrom == null) {
      _toast('Pick a start date.');
      return;
    }

    final error = await notifier.update(
      widget.pass.id,
      personName: _name.text.trim(),
      validFrom: _fmtDate(_validFrom!),
      personPhone: _phone.text.trim(),
      category: _category.text.trim(),
      flatId: widget.pass.flatId,
      allowedDays: widget.pass.isRecurring ? (_days.toList()..sort()) : null,
      startTime: _start == null ? null : _fmtTime(_start!),
      endTime: _end == null ? null : _fmtTime(_end!),
      validUntil: _validUntil == null ? null : _fmtDate(_validUntil!),
    );
    _finish(error);
  }

  void _finish(String? error) {
    if (!mounted) return;
    if (error != null) {
      // The server's own words - "This pass was revoked...", a validation
      // message - rather than a generic failure.
      _toast(error);
      return;
    }
    Navigator.pop(context);
  }

  void _toast(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}
