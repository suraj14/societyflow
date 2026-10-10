// Society Info as returned by GET /api/v1/society/info - the "Society
// Details" and "Committee" sections. The JSON keys are the API contract; do
// not rename them without a coordinated release.
//
// Parsing is defensive and never invents anything: a missing, blank or
// wrong-typed value becomes null, which the screens show as "Not available".

String? _text(dynamic v) {
  final s = v?.toString().trim() ?? '';
  return s.isEmpty ? null : s;
}

class SocietyDetails {
  final String name;
  final String? registrationNumber;
  final String? address;
  final String? societyType;

  /// Ready-to-show date ("17 Apr 2015"), formatted by the server so web and
  /// app read the same.
  final String? registeredOnLabel;

  const SocietyDetails({
    required this.name,
    this.registrationNumber,
    this.address,
    this.societyType,
    this.registeredOnLabel,
  });

  factory SocietyDetails.fromJson(Map<String, dynamic> json) => SocietyDetails(
        name: _text(json['name']) ?? '',
        registrationNumber: _text(json['registration_number']),
        address: _text(json['address']),
        societyType: _text(json['society_type']),
        registeredOnLabel: _text(json['registered_on_label']),
      );
}

class CommitteeMember {
  final int id;
  final String name;
  final String designation;

  /// A number that can really be dialled (server-validated), else null.
  final String? phone;
  final bool canCall;

  /// Root-relative path ("/storage/avatars/x.png"); resolved against the
  /// server base URL by NetAvatar.
  final String? photo;

  const CommitteeMember({
    required this.id,
    required this.name,
    required this.designation,
    this.phone,
    this.canCall = false,
    this.photo,
  });

  factory CommitteeMember.fromJson(Map<String, dynamic> json) {
    final phone = _text(json['phone']);
    return CommitteeMember(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: _text(json['name']) ?? '',
      designation: _text(json['designation']) ?? '',
      phone: phone,
      // Never show a Call button for a member without a number, whatever
      // the flag says.
      canCall: phone != null && json['can_call'] == true,
      photo: _text(json['photo']),
    );
  }
}

class SocietyInfoModel {
  final SocietyDetails? details;
  final List<CommitteeMember> committee;

  const SocietyInfoModel({this.details, this.committee = const []});

  factory SocietyInfoModel.fromJson(Map<String, dynamic> json) {
    final details = json['details'];
    final committee = json['committee'];
    return SocietyInfoModel(
      details: details is Map<String, dynamic> ? SocietyDetails.fromJson(details) : null,
      committee: committee is List
          ? committee
              .whereType<Map<String, dynamic>>()
              .map(CommitteeMember.fromJson)
              .where((m) => m.name.isNotEmpty)
              .toList()
          : const [],
    );
  }
}
