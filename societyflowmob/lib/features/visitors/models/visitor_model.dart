class VisitorModel {
  final int id;
  final String visitorName;
  final String visitorPhone;
  final String visitorType;
  final String approvalStatus;
  final String entryStatus;
  final String? purpose;
  final String? flatNumber;
  final String? buildingName;
  final String? vehicleNumber;
  final DateTime expectedEntryTime;
  final DateTime? expectedExitTime;
  final DateTime? actualEntryTime;
  final DateTime? actualExitTime;
  final String? photo;

  // Gate approval workflow. The server decides what the signed-in person may do
  // (`can_*`); a payload without them (an older server) falls back to the
  // status, which is what the app did before.
  final String? unitLabel;
  final String? requestedBy;
  final String? decidedBy;
  final DateTime? decidedAt;
  final String? decisionRole;
  final String? rejectionReason;
  final String? overrideReason;
  final bool escalated;
  final bool? canDecideFlag;
  final bool? canOverrideFlag;
  final bool? canCheckInFlag;
  final bool? canCheckOutFlag;

  const VisitorModel({
    required this.id,
    required this.visitorName,
    required this.visitorPhone,
    required this.visitorType,
    required this.approvalStatus,
    required this.entryStatus,
    this.purpose,
    this.flatNumber,
    this.buildingName,
    this.vehicleNumber,
    required this.expectedEntryTime,
    this.expectedExitTime,
    this.actualEntryTime,
    this.actualExitTime,
    this.photo,
    this.unitLabel,
    this.requestedBy,
    this.decidedBy,
    this.decidedAt,
    this.decisionRole,
    this.rejectionReason,
    this.overrideReason,
    this.escalated = false,
    this.canDecideFlag,
    this.canOverrideFlag,
    this.canCheckInFlag,
    this.canCheckOutFlag,
  });

  factory VisitorModel.fromJson(Map<String, dynamic> json) => VisitorModel(
    id:                 json['id'] as int,
    visitorName:        json['visitor_name']?.toString() ?? '',
    visitorPhone:       json['visitor_phone']?.toString() ?? '',
    visitorType:        json['visitor_type']?.toString() ?? 'guest',
    approvalStatus:     json['approval_status']?.toString() ?? 'pending',
    entryStatus:        json['entry_status']?.toString() ?? 'pending',
    purpose:            json['purpose']?.toString(),
    flatNumber:         json['flat']?['flat_number']?.toString(),
    buildingName:       json['flat']?['building']?['name']?.toString(),
    vehicleNumber:      json['vehicle_number']?.toString(),
    expectedEntryTime:  DateTime.tryParse(json['expected_entry_time']?.toString() ?? '') ?? DateTime.now(),
    expectedExitTime:   json['expected_exit_time'] != null ? DateTime.tryParse(json['expected_exit_time'].toString()) : null,
    actualEntryTime:    json['actual_entry_time'] != null ? DateTime.tryParse(json['actual_entry_time'].toString()) : null,
    actualExitTime:     json['actual_exit_time'] != null ? DateTime.tryParse(json['actual_exit_time'].toString()) : null,
    photo:              json['photo']?.toString(),
    unitLabel:          json['unit']?.toString(),
    requestedBy:        json['requested_by']?.toString(),
    decidedBy:          json['decided_by']?.toString(),
    decidedAt:          json['decided_at'] != null ? DateTime.tryParse(json['decided_at'].toString()) : null,
    decisionRole:       json['decision_role']?.toString(),
    rejectionReason:    json['rejection_reason']?.toString(),
    overrideReason:     json['override_reason']?.toString(),
    escalated:          json['escalated'] == true,
    canDecideFlag:      json['can_decide'] as bool?,
    canOverrideFlag:    json['can_override'] as bool?,
    canCheckInFlag:     json['can_check_in'] as bool?,
    canCheckOutFlag:    json['can_check_out'] as bool?,
  );

  bool get isPending   => approvalStatus == 'pending';
  bool get isAllowed   => approvalStatus == 'allowed';
  bool get isDenied    => approvalStatus == 'denied';
  bool get hasEntered  => entryStatus == 'entered';
  bool get hasExited   => entryStatus == 'exited';

  /// Whether the signed-in person may allow or deny this visitor right now.
  /// Gate staff never can: they record the visitor, the resident (or an admin) decides.
  bool get canDecide   => canDecideFlag ?? (isPending && entryStatus == 'pending');

  /// An administrator may change a decision (with a reason) until the visitor has entered.
  bool get canOverride => canOverrideFlag ?? false;

  bool get canCheckIn  => canCheckInFlag ?? (isAllowed && !hasEntered);
  bool get canCheckOut => canCheckOutFlag ?? (hasEntered && !hasExited);

  /// pending_approval | approved | rejected | checked_in | checked_out, derived the same way the server does.
  String get status {
    if (hasExited) return 'checked_out';
    if (hasEntered) return 'checked_in';
    if (isAllowed) return 'approved';
    if (isDenied) return 'rejected';
    return 'pending_approval';
  }

  String get statusLabel {
    switch (status) {
      case 'checked_out': return 'Checked out';
      case 'checked_in':  return 'Inside';
      case 'approved':    return 'Approved';
      case 'rejected':    return 'Rejected';
      default:            return escalated ? 'No answer yet' : 'Pending approval';
    }
  }

  String get unitDisplay => [buildingName, flatNumber].where((e) => e != null && e.isNotEmpty).join(' - ');

  VisitorModel copyWith({String? approvalStatus, String? entryStatus}) => VisitorModel(
    id: id, visitorName: visitorName, visitorPhone: visitorPhone,
    visitorType: visitorType,
    approvalStatus: approvalStatus ?? this.approvalStatus,
    entryStatus: entryStatus ?? this.entryStatus,
    purpose: purpose, flatNumber: flatNumber, buildingName: buildingName,
    vehicleNumber: vehicleNumber, expectedEntryTime: expectedEntryTime,
    expectedExitTime: expectedExitTime, actualEntryTime: actualEntryTime,
    actualExitTime: actualExitTime, photo: photo,
    unitLabel: unitLabel, requestedBy: requestedBy, decidedBy: decidedBy,
    decidedAt: decidedAt, decisionRole: decisionRole,
    rejectionReason: rejectionReason, overrideReason: overrideReason,
    escalated: escalated,
    // a local patch cannot know what the server would now allow: forget the flags
    // and let the next refresh (or the server's own answer) set them
    canDecideFlag: null, canOverrideFlag: null, canCheckInFlag: null, canCheckOutFlag: null,
  );
}
