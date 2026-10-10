import '../../../core/utils/date_text.dart';

class FacilityModel {
  final int id;
  final String name;
  final String? description;
  final String? type;
  final int? capacity;

  /// A flat charge per booking (not per hour) - what the web shows and the
  /// office is paid.
  final double charges;
  final String? image;
  final String status;
  final String? openTime;
  final String? closeTime;

  /// Lower-case English day names; empty means every day.
  final List<String> availableDays;
  final int advanceBookingDays;
  final int maxBookingHours;
  final bool requiresApproval;

  const FacilityModel({
    required this.id,
    required this.name,
    this.description,
    this.type,
    this.capacity,
    required this.charges,
    this.image,
    required this.status,
    this.openTime,
    this.closeTime,
    this.availableDays = const [],
    this.advanceBookingDays = 30,
    this.maxBookingHours = 4,
    this.requiresApproval = true,
  });

  factory FacilityModel.fromJson(Map<String, dynamic> json) => FacilityModel(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        description: json['description']?.toString(),
        type: json['type']?.toString(),
        capacity: (json['capacity'] as num?)?.toInt(),
        charges: double.tryParse((json['charges'] ?? 0).toString()) ?? 0,
        image: json['image']?.toString(),
        status: json['status']?.toString() ?? 'active',
        openTime: json['open_time']?.toString(),
        closeTime: json['close_time']?.toString(),
        availableDays: json['available_days'] is List
            ? (json['available_days'] as List).map((d) => d.toString().toLowerCase()).toList()
            : const [],
        advanceBookingDays: (json['advance_booking_days'] as num?)?.toInt() ?? 30,
        maxBookingHours: (json['max_booking_hours'] as num?)?.toInt() ?? 4,
        requiresApproval: json['requires_approval'] != false && json['requires_approval'] != 0,
      );

  /// Whether the facility takes bookings on this weekday at all.
  bool opensOn(DateTime d) => availableDays.isEmpty || availableDays.contains(DateText.weekdayKey(d));

  /// "Free" or "₹500 per booking".
  String get priceLabel => charges > 0 ? '₹${charges.toStringAsFixed(charges % 1 == 0 ? 0 : 2)} per booking' : 'Free';

  String get hoursLabel => (openTime == null || closeTime == null) ? '' : DateText.range(openTime, closeTime);
}

/// One start time in a day's booking grid.
class FacilitySlot {
  final String start;
  final String end;
  final bool available;

  /// How many hours can be booked from this start (0 when unavailable).
  final int maxHours;

  /// Why it cannot be booked: `booked`, `past` or `closed`.
  final String? reason;

  const FacilitySlot({required this.start, required this.end, required this.available, required this.maxHours, this.reason});

  factory FacilitySlot.fromJson(Map<String, dynamic> json) => FacilitySlot(
        start: json['start']?.toString() ?? '',
        end: json['end']?.toString() ?? '',
        available: json['available'] == true,
        maxHours: (json['max_hours'] as num?)?.toInt() ?? 0,
        reason: json['reason']?.toString(),
      );
}

/// A facility on one date: what is free, what is taken, and why a day is shut.
class FacilityAvailability {
  final String date;
  final String open;
  final String close;
  final int maxHours;
  final double charge;
  final bool requiresApproval;
  final bool bookable;
  final String? message;
  final List<FacilitySlot> slots;

  const FacilityAvailability({
    required this.date,
    required this.open,
    required this.close,
    required this.maxHours,
    required this.charge,
    required this.requiresApproval,
    required this.bookable,
    this.message,
    required this.slots,
  });

  factory FacilityAvailability.fromJson(Map<String, dynamic> json) => FacilityAvailability(
        date: json['date']?.toString() ?? '',
        open: json['open']?.toString() ?? '',
        close: json['close']?.toString() ?? '',
        maxHours: (json['max_hours'] as num?)?.toInt() ?? 1,
        charge: double.tryParse((json['charge'] ?? 0).toString()) ?? 0,
        requiresApproval: json['requires_approval'] != false,
        bookable: json['bookable'] != false,
        message: json['message']?.toString(),
        slots: json['slots'] is List
            ? (json['slots'] as List).whereType<Map>().map((s) => FacilitySlot.fromJson(Map<String, dynamic>.from(s))).toList()
            : const [],
      );

  bool get hasFreeSlot => slots.any((s) => s.available);
}

class FacilityBookingModel {
  final int id;
  final String? bookingNumber;
  final String? facilityName;
  final String? property;
  final String? bookingDate;
  final String? startTime;
  final String? endTime;
  final String? purpose;
  final double amount;
  final String? paymentStatus;
  final String status;
  final String? adminNotes;
  final bool canCancel;
  final DateTime createdAt;

  const FacilityBookingModel({
    required this.id,
    this.bookingNumber,
    this.facilityName,
    this.property,
    this.bookingDate,
    this.startTime,
    this.endTime,
    this.purpose,
    this.amount = 0,
    this.paymentStatus,
    required this.status,
    this.adminNotes,
    this.canCancel = false,
    required this.createdAt,
  });

  factory FacilityBookingModel.fromJson(Map<String, dynamic> json) => FacilityBookingModel(
        id: (json['id'] as num).toInt(),
        bookingNumber: json['booking_number']?.toString(),
        facilityName: json['facility'] is Map ? json['facility']['name']?.toString() : null,
        property: json['property']?.toString(),
        bookingDate: json['booking_date']?.toString(),
        startTime: json['start_time']?.toString(),
        endTime: json['end_time']?.toString(),
        purpose: json['purpose']?.toString(),
        amount: double.tryParse((json['amount'] ?? 0).toString()) ?? 0,
        paymentStatus: json['payment_status']?.toString(),
        status: json['status']?.toString() ?? 'pending',
        adminNotes: json['admin_notes']?.toString(),
        canCancel: json['can_cancel'] == true,
        createdAt: DateTime.tryParse(json['created_at']?.toString() ?? '') ?? DateTime.now(),
      );

  FacilityBookingModel copyWith({String? status, bool? canCancel}) => FacilityBookingModel(
        id: id,
        bookingNumber: bookingNumber,
        facilityName: facilityName,
        property: property,
        bookingDate: bookingDate,
        startTime: startTime,
        endTime: endTime,
        purpose: purpose,
        amount: amount,
        paymentStatus: paymentStatus,
        status: status ?? this.status,
        adminNotes: adminNotes,
        canCancel: canCancel ?? this.canCancel,
        createdAt: createdAt,
      );

  bool get isPending => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isCancelled => status == 'cancelled';

  DateTime? _at(String? time) {
    final d = DateText.parseDate(bookingDate);
    if (d == null) return null;
    return DateTime(d.year, d.month, d.day).add(Duration(minutes: DateText.minutes(time) ?? 0));
  }

  /// When the booking starts / ends, on the phone's calendar. Null when the
  /// date cannot be read.
  DateTime? get startsAt => _at(startTime);
  DateTime? get endsAt => _at(endTime) ?? startsAt;

  /// Still ahead of us (or under way) and still alive - not cancelled,
  /// rejected or completed. Used to split Upcoming from Past.
  bool isUpcoming(DateTime now) {
    if (status != 'pending' && status != 'approved') return false;
    final end = endsAt;
    return end == null || !end.isBefore(now);
  }
}
