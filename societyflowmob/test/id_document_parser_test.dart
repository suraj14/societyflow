import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/features/visitors/models/id_document.dart';

/// What the gate may keep off a scanned ID.
///
/// The redaction cases matter more than the extraction ones: OCR getting a
/// name slightly wrong is a guard's problem to correct on the review screen,
/// but a full Aadhaar number surviving into a model field is a data breach in
/// waiting. Those cases assert the absence of the number, not just the
/// presence of the masked form.
void main() {
  // A plausible Aadhaar OCR dump: the card's own furniture, then the number,
  // then the address block.
  const aadhaarText = '''
GOVERNMENT OF INDIA
भारत सरकार
Ramesh Kumar Sharma
DOB: 14/08/1986
MALE
1234 5678 9012
Address: S/O Mohan Lal Sharma, 42 Gandhi Nagar,
Near Water Tank, Pune,
Maharashtra - 411014
''';

  group('Aadhaar', () {
    test('keeps only the last four digits', () {
      final id = IdDocumentParser.parse(aadhaarText, IdDocumentType.aadhaar);

      expect(id.documentReference, 'XXXX XXXX 9012');
    });

    test('never carries the full number on the parsed object', () {
      final id = IdDocumentParser.parse(aadhaarText, IdDocumentType.aadhaar);

      // Every field, and the toString, checked together: the complete number
      // must not survive anywhere on the object in any form.
      final everything = [
        id.documentReference,
        id.name,
        id.address,
        id.toString(),
      ].join(' | ');

      expect(everything, isNot(contains('1234 5678 9012')));
      expect(everything, isNot(contains('123456789012')));
      expect(everything, isNot(contains('1234')));
      expect(everything, isNot(contains('5678')));
    });

    test('a hand-typed full number is reduced the same way', () {
      expect(
        IdDocumentParser.redactReference(
            IdDocumentType.aadhaar, '1234 5678 9012'),
        'XXXX XXXX 9012',
      );
      expect(
        IdDocumentParser.redactReference(IdDocumentType.aadhaar, '123456789012'),
        'XXXX XXXX 9012',
      );
    });

    test('too few digits are dropped rather than padded into fake data', () {
      expect(
          IdDocumentParser.redactReference(IdDocumentType.aadhaar, '901'), isNull);
      expect(IdDocumentParser.redactReference(IdDocumentType.aadhaar, ''), isNull);
      expect(
          IdDocumentParser.redactReference(IdDocumentType.aadhaar, null), isNull);
    });

    test('a 16-digit VID is not mistaken for a 12-digit Aadhaar', () {
      final id = IdDocumentParser.parse(
        'Ramesh Kumar Sharma\nVID 9123456789012345\n',
        IdDocumentType.aadhaar,
      );

      expect(id.documentReference, isNull);
    });

    test('reads the name past the card furniture', () {
      final id = IdDocumentParser.parse(aadhaarText, IdDocumentType.aadhaar);

      expect(id.name, 'Ramesh Kumar Sharma');
    });

    test('reads the address block up to the PIN code', () {
      final id = IdDocumentParser.parse(aadhaarText, IdDocumentType.aadhaar);

      expect(id.address, contains('Gandhi Nagar'));
      expect(id.address, contains('411014'));
    });
  });

  group('other documents keep their ordinary reference', () {
    test('PAN', () {
      final id = IdDocumentParser.parse(
        'INCOME TAX DEPARTMENT\nGOVT OF INDIA\nRamesh Kumar Sharma\nABCDE1234F\n',
        IdDocumentType.pan,
      );

      expect(id.documentReference, 'ABCDE1234F');
      expect(id.name, 'Ramesh Kumar Sharma');
    });

    test('driving licence', () {
      final id = IdDocumentParser.parse(
        'DRIVING LICENCE\nMH12 20110012345\nName: Anita Desai\n',
        IdDocumentType.drivingLicence,
      );

      expect(id.documentReference, isNotNull);
      expect(id.name, 'Anita Desai');
    });

    test('passport', () {
      final id = IdDocumentParser.parse(
        'REPUBLIC OF INDIA\nPASSPORT\nK1234567\nName: Anita Desai\n',
        IdDocumentType.passport,
      );

      expect(id.documentReference, 'K1234567');
    });

    test('"Other" extracts no number, because it has no known format', () {
      final id = IdDocumentParser.parse(
        'SOME COMPANY ID\nAnita Desai\n9988776655\n',
        IdDocumentType.other,
      );

      expect(id.documentReference, isNull);
      expect(id.name, 'Anita Desai');
    });
  });

  group('when OCR reads nothing', () {
    test('empty text invents nothing and says OCR found nothing', () {
      final id = IdDocumentParser.parse('', IdDocumentType.aadhaar);

      expect(id.ocrFoundText, isFalse);
      expect(id.ocrAttempted, isTrue);
      expect(id.hasAnyExtractedField, isFalse);
      expect(id.name, isNull);
      expect(id.documentReference, isNull);
    });

    test('whitespace-only text is treated the same', () {
      final id = IdDocumentParser.parse('   \n\n  \n', IdDocumentType.pan);

      expect(id.ocrFoundText, isFalse);
      expect(id.hasAnyExtractedField, isFalse);
    });

    test('card furniture alone yields no name', () {
      final id = IdDocumentParser.parse(
        'GOVERNMENT OF INDIA\nUNIQUE IDENTIFICATION AUTHORITY\n',
        IdDocumentType.aadhaar,
      );

      expect(id.name, isNull);
    });
  });

  group('the stored document type', () {
    test('maps to the value the API expects', () {
      expect(IdDocumentType.aadhaar.apiValue, 'aadhaar');
      expect(IdDocumentType.drivingLicence.apiValue, 'driving_licence');
      expect(IdDocumentType.pan.apiValue, 'pan');
      expect(IdDocumentType.passport.apiValue, 'passport');
      expect(IdDocumentType.other.apiValue, 'other');
    });

    test('only Aadhaar redacts', () {
      expect(IdDocumentType.aadhaar.redactsNumber, isTrue);
      for (final t in IdDocumentType.values.where((t) => t != IdDocumentType.aadhaar)) {
        expect(t.redactsNumber, isFalse, reason: '${t.label} must keep its reference');
      }
    });
  });
}
