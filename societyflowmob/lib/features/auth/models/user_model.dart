class UserModel {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? avatar;
  final String? flatNumber;
  final String? buildingName;
  final String? villaNumber;
  final String? villaArea;
  final String role;
  final String status;
  final String? societyName;
  final bool isOwner;
  final bool isTenant;

  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.avatar,
    this.flatNumber,
    this.buildingName,
    this.villaNumber,
    this.villaArea,
    required this.role,
    required this.status,
    this.societyName,
    this.isOwner = false,
    this.isTenant = false,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) => UserModel(
    id:           json['id'] as int,
    name:         json['name']?.toString() ?? '',
    email:        json['email']?.toString() ?? '',
    phone:        json['phone']?.toString(),
    avatar:       json['avatar']?.toString(),
    flatNumber:   json['flat_number']?.toString(),
    buildingName: json['building_name']?.toString(),
    villaNumber:  json['villa_number']?.toString(),
    villaArea:    json['villa_area']?.toString(),
    role:         json['role']?.toString() ?? 'Resident',
    status:       json['status']?.toString() ?? 'active',
    societyName:  json['society_name']?.toString(),
    isOwner:      json['is_owner'] == true || json['is_owner'] == 1,
    isTenant:     json['is_tenant'] == true || json['is_tenant'] == 1,
  );

  Map<String, dynamic> toJson() => {
    'id': id, 'name': name, 'email': email, 'phone': phone,
    'avatar': avatar, 'flat_number': flatNumber,
    'building_name': buildingName, 'villa_number': villaNumber,
    'villa_area': villaArea, 'role': role,
    'status': status, 'society_name': societyName,
    'is_owner': isOwner, 'is_tenant': isTenant,
  };

  /// Display unit — shows villa or flat depending on user type
  String get displayUnit {
    if (villaNumber != null && villaNumber!.isNotEmpty) {
      final area = (villaArea != null && villaArea!.isNotEmpty) ? ' - $villaArea' : '';
      // Avoid double "Villa" prefix if villaNumber already starts with it
      final num = villaNumber!.startsWith('Villa ') ? villaNumber! : 'Villa $villaNumber';
      return '$num$area';
    }
    if (flatNumber != null && buildingName != null) return '$buildingName - Flat $flatNumber';
    if (flatNumber != null) return 'Flat $flatNumber';
    return societyName ?? '';
  }

  /// Short unit label for welcome card
  String get shortUnit {
    if (villaNumber != null && villaNumber!.isNotEmpty) {
      return villaNumber!.startsWith('Villa ') ? villaNumber! : 'Villa $villaNumber';
    }
    if (flatNumber != null) return 'Flat $flatNumber';
    return societyName ?? '';
  }
}
