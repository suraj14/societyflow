import '../../../core/utils/date_text.dart';

/// One assignment of the signed-in guard to a shift at a gate, as the server
/// describes it. Times are society-clock wall times ("09:00"); the ISO
/// timestamps carry the society's offset (+05:30) and are shown by their own
/// digits, never converted to the phone's timezone.
class GuardShift {
  final int id;
  final String shiftName;
  final String gateName;
  final String date;
  final String startTime;
  final String endTime;
  final bool overnight;
  final String status;

  /// `upcoming`, `active`, `ended` or `missed` - decided on the server's
  /// clock so the phone does no time maths.
  final String phase;
  final String phaseLabel;
  final bool late;
  final String? checkedInAt;
  final String? checkedOutAt;
  final bool canStart;
  final bool canEnd;
  final String? handoverNotes;
  final bool hasHandover;

  const GuardShift({
    required this.id,
    required this.shiftName,
    required this.gateName,
    required this.date,
    required this.startTime,
    required this.endTime,
    this.overnight = false,
    required this.status,
    required this.phase,
    required this.phaseLabel,
    this.late = false,
    this.checkedInAt,
    this.checkedOutAt,
    this.canStart = false,
    this.canEnd = false,
    this.handoverNotes,
    this.hasHandover = false,
  });

  factory GuardShift.fromJson(Map<String, dynamic> json) {
    final handover = json['handover'];
    return GuardShift(
      id: (json['id'] as num?)?.toInt() ?? 0,
      shiftName: json['shift_name']?.toString() ?? 'Shift',
      gateName: json['gate_name']?.toString() ?? '',
      date: json['date']?.toString() ?? '',
      startTime: json['start_time']?.toString() ?? '',
      endTime: json['end_time']?.toString() ?? '',
      overnight: json['overnight'] == true,
      status: json['status']?.toString() ?? 'scheduled',
      phase: json['phase']?.toString() ?? 'upcoming',
      phaseLabel: json['phase_label']?.toString() ?? '',
      late: json['late'] == true,
      checkedInAt: json['checked_in_at']?.toString(),
      checkedOutAt: json['checked_out_at']?.toString(),
      canStart: json['can_start'] == true,
      canEnd: json['can_end'] == true,
      hasHandover: handover is Map,
      handoverNotes: handover is Map ? handover['notes']?.toString() : null,
    );
  }

  bool get isActive => phase == 'active';
  bool get isUpcoming => phase == 'upcoming';

  /// The guard has started this shift and not ended it: they are on duty.
  bool get onDuty => checkedInAt != null && checkedOutAt == null;

  /// "09:05" out of "2026-10-10T09:05:00+05:30" - already the society's time.
  static String? clockOf(String? iso) => (iso != null && iso.length >= 16) ? iso.substring(11, 16) : null;
}

/// Everything the Guard app shows about duty: today's shift(s), which one is
/// on now, and what is coming up.
class GuardShiftOverview {
  final GuardShift? current;
  final List<GuardShift> today;
  final GuardShift? next;
  final List<GuardShift> upcoming;

  const GuardShiftOverview({this.current, this.today = const [], this.next, this.upcoming = const []});

  factory GuardShiftOverview.fromJson(Map<String, dynamic> json) {
    List<GuardShift> list(dynamic v) =>
        v is List ? v.whereType<Map>().map((m) => GuardShift.fromJson(Map<String, dynamic>.from(m))).toList() : const [];
    GuardShift? one(dynamic v) => v is Map ? GuardShift.fromJson(Map<String, dynamic>.from(v)) : null;

    return GuardShiftOverview(
      current: one(json['current']),
      today: list(json['today']),
      next: one(json['next']),
      upcoming: list(json['upcoming']),
    );
  }

  /// The one shift to feature: the one on now, else the next one today, else
  /// the last one that ran today. Null when there is no shift today at all.
  GuardShift? get featured {
    if (current != null) return current;
    for (final s in today) {
      if (s.isUpcoming) return s;
    }
    return today.isEmpty ? null : today.last;
  }

  bool get hasShiftToday => today.isNotEmpty;

  /// The badge on the guard home, from the real roster. It used to read
  /// "On Duty" for every guard at every hour. Null while nothing is known.
  String? get dutyLabel {
    final s = featured;
    if (s == null) return 'No shift today';
    if (s.onDuty) return 'On Duty';
    if (s.isUpcoming) return 'Starts ${DateText.clock(s.startTime)}';
    if (s.isActive) return 'Not started';
    return 'Off duty';
  }
}
