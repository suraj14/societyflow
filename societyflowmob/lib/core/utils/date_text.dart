/// Plain-language dates and clock times for screens. The app has no `intl`
/// (it was removed as unused), and what is needed is small and fixed.
class DateText {
  static const _weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  static const _weekdaysLong = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  static const _months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  /// "Sat, 12 Oct 2026".
  static String date(DateTime d) => '${_weekdays[d.weekday - 1]}, ${d.day} ${_months[d.month - 1]} ${d.year}';

  /// "12 Oct".
  static String dayMonth(DateTime d) => '${d.day} ${_months[d.month - 1]}';

  /// "Sat".
  static String weekdayShort(DateTime d) => _weekdays[d.weekday - 1];

  /// Lower-case English day name, the form facilities store in `available_days`.
  static String weekdayKey(DateTime d) => _weekdaysLong[d.weekday - 1].toLowerCase();

  /// "2026-10-12" - what the API takes and returns for a calendar date.
  static String iso(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  /// "2026-10-12" (or a full ISO timestamp) -> that calendar day, or null.
  static DateTime? parseDate(String? s) {
    if (s == null || s.length < 10) return null;
    final y = int.tryParse(s.substring(0, 4));
    final m = int.tryParse(s.substring(5, 7));
    final d = int.tryParse(s.substring(8, 10));
    if (y == null || m == null || d == null) return null;
    return DateTime(y, m, d);
  }

  /// "10:00" / "10:00:00" -> minutes since midnight, or null.
  static int? minutes(String? s) {
    if (s == null) return null;
    final p = s.split(':');
    if (p.length < 2) return null;
    final h = int.tryParse(p[0]);
    final m = int.tryParse(p[1]);
    if (h == null || m == null) return null;
    return h * 60 + m;
  }

  /// "14:30" / "14:30:00" -> "2:30 PM". Anything unreadable comes back as is.
  static String clock(String? s) {
    final total = minutes(s);
    if (total == null) return s ?? '';
    final h24 = (total ~/ 60) % 24;
    final m = total % 60;
    final h12 = h24 % 12 == 0 ? 12 : h24 % 12;
    return '$h12:${m.toString().padLeft(2, '0')} ${h24 < 12 ? 'AM' : 'PM'}';
  }

  /// "10:00 AM - 12:00 PM".
  static String range(String? start, String? end) => '${clock(start)} - ${clock(end)}';

  /// 570 -> "09:30" (the API's clock form).
  static String hhmm(int minutes) =>
      '${(minutes ~/ 60).toString().padLeft(2, '0')}:${(minutes % 60).toString().padLeft(2, '0')}';
}
