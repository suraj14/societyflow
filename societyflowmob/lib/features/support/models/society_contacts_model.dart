/// GET /api/v1/society/contacts - the user's own society office, its
/// managers and national emergency numbers. Keys are the API contract
/// (SocietyContactApiController::index).
class SocietyContact {
  final String label;
  final String? name;
  final String phone;
  final String? email;
  final String kind;

  const SocietyContact({required this.label, this.name, required this.phone, this.email, this.kind = 'office'});
}

class SocietyContactsModel {
  final String? societyName;
  final String? societyPhone;
  final String? societyEmail;
  final String? address;
  final String? website;
  final List<SocietyContact> managers;
  final List<SocietyContact> emergency;

  const SocietyContactsModel({
    this.societyName,
    this.societyPhone,
    this.societyEmail,
    this.address,
    this.website,
    this.managers = const [],
    this.emergency = const [],
  });

  bool get hasOfficeContact => (societyPhone?.isNotEmpty ?? false) || (societyEmail?.isNotEmpty ?? false) || managers.isNotEmpty;

  static String? _s(dynamic v) {
    final t = v?.toString().trim();
    return (t == null || t.isEmpty) ? null : t;
  }

  factory SocietyContactsModel.fromJson(Map<String, dynamic> json) {
    final society = json['society'] is Map<String, dynamic> ? json['society'] as Map<String, dynamic> : const <String, dynamic>{};
    List<Map<String, dynamic>> list(dynamic v) => v is List ? v.whereType<Map<String, dynamic>>().toList() : const [];

    return SocietyContactsModel(
      societyName: _s(society['name']),
      societyPhone: _s(society['phone']),
      societyEmail: _s(society['email']),
      address: _s(society['address']),
      website: _s(society['website']),
      managers: list(json['managers'])
          .where((m) => _s(m['phone']) != null)
          .map((m) => SocietyContact(label: _s(m['role']) ?? 'Society Manager', name: _s(m['name']), phone: _s(m['phone'])!, email: _s(m['email'])))
          .toList(),
      emergency: list(json['emergency'])
          .where((e) => _s(e['phone']) != null)
          .map((e) => SocietyContact(label: _s(e['label']) ?? 'Emergency', phone: _s(e['phone'])!, kind: _s(e['kind']) ?? 'emergency'))
          .toList(),
    );
  }
}
