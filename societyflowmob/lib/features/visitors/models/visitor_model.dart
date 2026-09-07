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
  );

  bool get isPending   => approvalStatus == 'pending';
  bool get isAllowed   => approvalStatus == 'allowed';
  bool get isDenied    => approvalStatus == 'denied';
  bool get hasEntered  => entryStatus == 'entered';
  bool get hasExited   => entryStatus == 'exited';
  bool get canCheckIn  => isAllowed && !hasEntered;
  bool get canCheckOut => hasEntered && !hasExited;

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
  );
}
