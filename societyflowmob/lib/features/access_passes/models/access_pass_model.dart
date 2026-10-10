/// One gate access pass, as `/api/v1/access-passes` returns it.
///
/// The pass *token* is deliberately absent from this model: the server
/// never sends it on these endpoints (it stays `$hidden` on the Eloquent
/// model), because only the gate needs it. Nothing here is a credential,
/// so the whole object is safe to hold in memory and log.
///
/// Every state question - expired, revoked, blocked, editable - is answered
/// by the server and simply read here. The app must not re-derive them from
/// dates, or a phone with a wrong clock would disagree with the gate.
class AccessPassModel {
  final int id;
  final String personName;
  final String? personPhone;
  final String? category;
  final String passType;
  final String accessScope;
  final int? flatId;
  final String? property;
  final List<int> allowedDays;
  final String? startTime;
  final String? endTime;
  final String? validFrom;
  final String? validUntil;
  final String status;
  final bool isExpired;
  final bool isRevoked;
  final bool isBlockedToday;
  final bool canEdit;
  final bool currentlyInside;

  const AccessPassModel({
    required this.id,
    required this.personName,
    this.personPhone,
    this.category,
    required this.passType,
    required this.accessScope,
    this.flatId,
    this.property,
    required this.allowedDays,
    this.startTime,
    this.endTime,
    this.validFrom,
    this.validUntil,
    required this.status,
    required this.isExpired,
    required this.isRevoked,
    required this.isBlockedToday,
    required this.canEdit,
    required this.currentlyInside,
  });

  factory AccessPassModel.fromJson(Map<String, dynamic> json) => AccessPassModel(
    id:          (json['id'] as num).toInt(),
    personName:  json['person_name']?.toString() ?? '',
    personPhone: json['person_phone']?.toString(),
    category:    json['category']?.toString(),
    passType:    json['pass_type']?.toString() ?? 'recurring_staff',
    accessScope: json['access_scope']?.toString() ?? 'property',
    flatId:      (json['flat_id'] as num?)?.toInt(),
    property:    json['property']?.toString(),
    // allowed_days arrives as ints from MySQL but as strings from a JSON
    // column round-trip, so it is normalised rather than cast.
    allowedDays: (json['allowed_days'] as List?)
            ?.map((d) => int.tryParse(d.toString()) ?? -1)
            .where((d) => d >= 0 && d <= 6)
            .toList() ??
        const [],
    startTime:   json['start_time']?.toString(),
    endTime:     json['end_time']?.toString(),
    validFrom:   json['valid_from']?.toString(),
    validUntil:  json['valid_until']?.toString(),
    status:      json['status']?.toString() ?? 'active',
    isExpired:   json['is_expired'] == true,
    isRevoked:   json['is_revoked'] == true,
    isBlockedToday:  json['is_blocked_today'] == true,
    canEdit:         json['can_edit'] == true,
    currentlyInside: json['currently_inside'] == true,
  );

  bool get isRecurring => passType == 'recurring_staff';

  /// What to show as the pass's state, in the order that matters to a
  /// reader: a revoked pass is revoked whether or not it also lapsed.
  String get stateLabel {
    if (isRevoked) return 'Revoked';
    if (isExpired) return 'Expired';
    if (isBlockedToday) return 'Blocked today';
    return 'Active';
  }
}
