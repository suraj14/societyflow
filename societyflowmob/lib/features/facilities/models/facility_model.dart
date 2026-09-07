class FacilityModel {
  final int id;
  final String name;
  final String? description;
  final int? capacity;
  final double charges;
  final String? image;
  final String status;
  final String? openTime;
  final String? closeTime;

  const FacilityModel({
    required this.id, required this.name, this.description,
    this.capacity, required this.charges, this.image,
    required this.status, this.openTime, this.closeTime,
  });

  factory FacilityModel.fromJson(Map<String, dynamic> json) => FacilityModel(
    id:          json['id'] as int,
    name:        json['name']?.toString() ?? '',
    description: json['description']?.toString(),
    capacity:    (json['capacity'] as num?)?.toInt(),
    charges:     double.parse((json['charges'] ?? 0).toString()),
    image:       json['image']?.toString(),
    status:      json['status']?.toString() ?? 'active',
    openTime:    json['open_time']?.toString(),
    closeTime:   json['close_time']?.toString(),
  );
}

class FacilityBookingModel {
  final int id;
  final String? facilityName;
  final String? bookingDate;
  final String? startTime;
  final String? endTime;
  final String? purpose;
  final String status;
  final DateTime createdAt;

  const FacilityBookingModel({
    required this.id, this.facilityName, this.bookingDate,
    this.startTime, this.endTime, this.purpose,
    required this.status, required this.createdAt,
  });

  factory FacilityBookingModel.fromJson(Map<String, dynamic> json) => FacilityBookingModel(
    id:           json['id'] as int,
    facilityName: json['facility']?['name']?.toString(),
    bookingDate:  json['booking_date']?.toString(),
    startTime:    json['start_time']?.toString(),
    endTime:      json['end_time']?.toString(),
    purpose:      json['purpose']?.toString(),
    status:       json['status']?.toString() ?? 'pending',
    createdAt:    DateTime.parse(json['created_at'].toString()),
  );

  bool get isPending  => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isCancelled=> status == 'cancelled';
}
