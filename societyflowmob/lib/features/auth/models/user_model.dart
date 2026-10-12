import 'society_config.dart';

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

  /// Every role the account holds (the server's `roles`). [role] is only the first one; an account can be
  /// Admin + Accountant or Owner + Tenant, so never branch on [role] alone.
  final List<String> roles;

  /// What the account may do, computed by the server from the same rules its endpoints enforce
  /// (App\Support\MobileCapabilities). Ask [can]; never keep a role list in a widget.
  final Map<String, bool> capabilities;

  /// Settings -> Features switches for the account's society (facilities, visitors, ... events).
  final Map<String, bool> modules;
  final String status;
  final String? societyName;
  final bool isOwner;
  final bool isTenant;

  /// What the user's society contains. Screens branch on this instead of
  /// guessing from whether [villaNumber] is populated.
  final SocietyConfig society;

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
    this.roles = const [],
    this.capabilities = const {},
    this.modules = const {},
    required this.status,
    this.societyName,
    this.isOwner = false,
    this.isTenant = false,
    this.society = const SocietyConfig(),
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
    roles:        (json['roles'] is List) ? [for (final r in json['roles'] as List) r.toString()] : const [],
    capabilities: _boolMap(json['capabilities']),
    modules:      _boolMap(json['modules']),    status:       json['status']?.toString() ?? 'active',
    societyName:  json['society_name']?.toString(),
    isOwner:      json['is_owner'] == true || json['is_owner'] == 1,
    isTenant:     json['is_tenant'] == true || json['is_tenant'] == 1,
    // Nested object when the server supplies it; otherwise fall back to the
    // flat `property_type` key, and finally to the permissive default so a
    // response from an older build still parses.
    society:      json['society'] is Map<String, dynamic>
                      ? SocietyConfig.fromJson(json['society'] as Map<String, dynamic>)
                      : SocietyConfig(
                          name: json['society_name']?.toString(),
                          propertyType: SocietyPropertyType.fromJson(
                            json['property_type']?.toString(),
                          ),
                        ),
  );

  Map<String, dynamic> toJson() => {
    'id': id, 'name': name, 'email': email, 'phone': phone,
    'avatar': avatar, 'flat_number': flatNumber,
    'building_name': buildingName, 'villa_number': villaNumber,
    'villa_area': villaArea, 'role': role,
    'roles': roles, 'capabilities': capabilities, 'modules': modules,
    'status': status, 'society_name': societyName,
    'is_owner': isOwner, 'is_tenant': isTenant,
    'society': society.toJson(),
  };

  static Map<String, bool> _boolMap(dynamic v) =>
      v is Map ? {for (final e in v.entries) e.key.toString(): e.value == true || e.value == 1} : const {};

  /// All roles, including the legacy single [role] when the server did not send the list.
  List<String> get allRoles => roles.isNotEmpty ? roles : [role];

  bool hasRole(String name) => allRoles.contains(name);

  /// May this account use [capability]? The server's answer when it sent one; otherwise (an older server) a
  /// conservative guess from the legacy [role] that matches what the app did before capabilities existed.
  bool can(String capability) {
    if (capabilities.isNotEmpty) return capabilities[capability] ?? false;
    return _legacyCan(capability);
  }

  bool _legacyCan(String c) {
    final guard = allRoles.contains('Guard');
    final admin = allRoles.contains('Admin') || allRoles.contains('Super Admin');
    const alwaysRead = {'notices', 'events', 'services_directory', 'society_info', 'visitors'};
    if (alwaysRead.contains(c)) return true;
    if (c == 'gate_scan' || c == 'guard_shift' || c == 'visitor_manage' || c == 'visitor_register') return guard || admin;
    if (c == 'admin_console' || c == 'review_bookings' || c == 'manage_complaints' || c == 'assign_service_requests') return admin;
    if (guard && !admin) return false;                 // a guard-only account reaches only the gate surface
    if (c == 'book_facility') return !admin && !allRoles.contains('Staff') && !allRoles.contains('Accountant');
    return true;                                       // view_facilities, request_service, file_complaint, bills, access_passes
  }

  /// Label for the user's own property, e.g. "My Villa" / "My Apartment".
  ///
  /// Comes from the society's configuration, so a villa township never says
  /// "Apartment" and a resident is never asked which they have.
  String get propertyLabel => 'My ${society.propertyNoun}';

  /// True only when the user actually has a villa or flat (guards, staff and
  /// admins do not - displayUnit then falls back to the society name).
  bool get hasUnit => (villaNumber?.isNotEmpty ?? false) || (flatNumber?.isNotEmpty ?? false);

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
