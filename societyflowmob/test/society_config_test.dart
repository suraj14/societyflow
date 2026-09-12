import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/features/auth/models/society_config.dart';
import 'package:societyflow/features/auth/models/user_model.dart';

/// The app must branch on the society's configuration, not guess.
///
/// Before this, screens inferred the society type from whether villaNumber
/// happened to be populated, which renders apartment UI inside a villa
/// township whenever a resident has no unit assigned yet.
void main() {
  group('SocietyPropertyType', () {
    test('parses the values the API sends', () {
      expect(SocietyPropertyType.fromJson('villa'), SocietyPropertyType.villa);
      expect(SocietyPropertyType.fromJson('apartment'), SocietyPropertyType.apartment);
      expect(SocietyPropertyType.fromJson('both'), SocietyPropertyType.both);
    });

    test('an unknown or missing value falls back to both, which hides nothing', () {
      expect(SocietyPropertyType.fromJson(null), SocietyPropertyType.both);
      expect(SocietyPropertyType.fromJson(''), SocietyPropertyType.both);
      expect(SocietyPropertyType.fromJson('penthouse'), SocietyPropertyType.both);
    });

    test('capability flags', () {
      expect(SocietyPropertyType.villa.supportsVillas, isTrue);
      expect(SocietyPropertyType.villa.supportsApartments, isFalse);
      expect(SocietyPropertyType.apartment.supportsApartments, isTrue);
      expect(SocietyPropertyType.apartment.supportsVillas, isFalse);
      expect(SocietyPropertyType.both.isMixed, isTrue);
      expect(SocietyPropertyType.villa.isMixed, isFalse);
    });
  });

  group('SocietyConfig', () {
    test('parses the nested society object from the API', () {
      final config = SocietyConfig.fromJson({
        'id': 1,
        'name': 'Royal Prime',
        'property_type': 'villa',
        'property_type_label': 'Villa Township',
        'supports_apartments': false,
        'supports_villas': true,
        'is_mixed': false,
      });

      expect(config.id, 1);
      expect(config.name, 'Royal Prime');
      expect(config.supportsVillas, isTrue);
      expect(config.supportsApartments, isFalse);
      expect(config.isMixed, isFalse);
      expect(config.propertyNoun, 'Villa');
    });

    test('property noun per society type', () {
      expect(const SocietyConfig(propertyType: SocietyPropertyType.villa).propertyNoun, 'Villa');
      expect(const SocietyConfig(propertyType: SocietyPropertyType.apartment).propertyNoun, 'Apartment');
      expect(const SocietyConfig(propertyType: SocietyPropertyType.both).propertyNoun, 'Property');
    });
  });

  group('UserModel society parsing', () {
    Map<String, dynamic> baseUser() => {
          'id': 7,
          'name': 'Asha Verma',
          'email': 'asha@example.com',
          'role': 'Villa Owner',
          'status': 'active',
          'society_name': 'Royal Prime',
        };

    test('reads the nested society object', () {
      final user = UserModel.fromJson({
        ...baseUser(),
        'property_type': 'villa',
        'society': {
          'id': 3,
          'name': 'Royal Prime',
          'property_type': 'villa',
          'property_type_label': 'Villa Township',
        },
      });

      expect(user.society.id, 3);
      expect(user.society.supportsVillas, isTrue);
      expect(user.society.supportsApartments, isFalse);
      expect(user.propertyLabel, 'My Villa');
    });

    test('falls back to the flat property_type key', () {
      final user = UserModel.fromJson({...baseUser(), 'property_type': 'apartment'});

      expect(user.society.supportsApartments, isTrue);
      expect(user.society.supportsVillas, isFalse);
      expect(user.propertyLabel, 'My Apartment');
    });

    test('a response with no society information still parses', () {
      final user = UserModel.fromJson(baseUser());

      expect(user.society.propertyType, SocietyPropertyType.both);
      expect(user.society.supportsVillas, isTrue);
      expect(user.society.supportsApartments, isTrue);
      expect(user.propertyLabel, 'My Property');
    });

    test('survives a round trip through toJson', () {
      final user = UserModel.fromJson({
        ...baseUser(),
        'society': {'id': 3, 'name': 'Royal Prime', 'property_type': 'villa'},
      });

      final restored = UserModel.fromJson(user.toJson());

      expect(restored.society.propertyType, SocietyPropertyType.villa);
      expect(restored.society.id, 3);
      expect(restored.propertyLabel, 'My Villa');
    });
  });
}
