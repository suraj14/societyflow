/// What kind of property a society contains.
///
/// The server decides this once, per society, and sends it with the user.
/// The app must branch on it rather than inferring from whether
/// [UserModel.villaNumber] happens to be populated — that guess renders
/// apartment UI inside a villa township whenever a unit has not been
/// assigned to the resident yet.
enum SocietyPropertyType {
  apartment,
  villa,
  both;

  static SocietyPropertyType fromJson(String? value) {
    switch (value) {
      case 'apartment':
        return SocietyPropertyType.apartment;
      case 'villa':
        return SocietyPropertyType.villa;
      default:
        // 'both' is the permissive default: it hides nothing, which is the
        // right behaviour for an unknown or missing value.
        return SocietyPropertyType.both;
    }
  }

  String get value => name;

  bool get supportsApartments => this != SocietyPropertyType.villa;
  bool get supportsVillas => this != SocietyPropertyType.apartment;
  bool get isMixed => this == SocietyPropertyType.both;
}

/// The society a user belongs to, plus what it contains.
class SocietyConfig {
  final int? id;
  final String? name;
  final SocietyPropertyType propertyType;
  final String? propertyTypeLabel;

  const SocietyConfig({
    this.id,
    this.name,
    this.propertyType = SocietyPropertyType.both,
    this.propertyTypeLabel,
  });

  /// Trusts the server's explicit booleans when present, falling back to the
  /// enum. Older builds of the API send neither, in which case 'both' keeps
  /// every screen available.
  factory SocietyConfig.fromJson(Map<String, dynamic> json) {
    final type =
        SocietyPropertyType.fromJson(json['property_type']?.toString());

    return SocietyConfig(
      id: json['id'] is int ? json['id'] as int : int.tryParse('${json['id']}'),
      name: json['name']?.toString(),
      propertyType: type,
      propertyTypeLabel: json['property_type_label']?.toString(),
    );
  }

  bool get supportsApartments => propertyType.supportsApartments;
  bool get supportsVillas => propertyType.supportsVillas;
  bool get isMixed => propertyType.isMixed;

  /// Label for the resident's own property, used where the UI says
  /// "My Villa" / "My Apartment" / the neutral "My Property".
  String get propertyNoun {
    switch (propertyType) {
      case SocietyPropertyType.villa:
        return 'Villa';
      case SocietyPropertyType.apartment:
        return 'Apartment';
      case SocietyPropertyType.both:
        return 'Property';
    }
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'property_type': propertyType.value,
        'property_type_label': propertyTypeLabel,
      };
}
