import 'package:flutter/foundation.dart';

/// An ID a visitor can hand over at the gate, and what may be kept from it.
///
/// Aadhaar is deliberately special-cased throughout this file. A society gate
/// is not doing e-KYC, so it has no business keeping the full 12 digits: only
/// the last four are ever retained, and the redaction happens here, at parse
/// time, so the complete number never reaches a model field, a request body,
/// a log line or the review screen's text controllers. Every other document's
/// reference is its ordinary identifier - a PAN or a licence number is what a
/// paper gate register has always written down - so those are kept whole.
enum IdDocumentType { aadhaar, drivingLicence, pan, passport, other }

extension IdDocumentTypeX on IdDocumentType {
  String get label => switch (this) {
        IdDocumentType.aadhaar => 'Aadhaar',
        IdDocumentType.drivingLicence => 'Driving Licence',
        IdDocumentType.pan => 'PAN',
        IdDocumentType.passport => 'Passport',
        IdDocumentType.other => 'Other',
      };

  /// What the backend stores in `visitors.visitor_id_proof`.
  String get apiValue => switch (this) {
        IdDocumentType.aadhaar => 'aadhaar',
        IdDocumentType.drivingLicence => 'driving_licence',
        IdDocumentType.pan => 'pan',
        IdDocumentType.passport => 'passport',
        IdDocumentType.other => 'other',
      };

  /// Only the last four digits of an Aadhaar may be kept.
  bool get redactsNumber => this == IdDocumentType.aadhaar;

  /// 'Other' has no number format worth guessing at, so nothing is extracted
  /// for it beyond a name and address - the guard types the rest.
  bool get hasKnownNumberFormat => this != IdDocumentType.other;

  String get referenceLabel => switch (this) {
        IdDocumentType.aadhaar => 'Aadhaar (last 4 digits only)',
        IdDocumentType.drivingLicence => 'Licence number',
        IdDocumentType.pan => 'PAN',
        IdDocumentType.passport => 'Passport number',
        IdDocumentType.other => 'Document reference',
      };
}

/// The minimum necessary information taken off a scanned ID.
///
/// The raw OCR text is deliberately *not* a field: it is a local variable
/// inside the parser and is discarded the moment the fields are pulled out of
/// it, so an unredacted Aadhaar cannot survive on this object, be serialised
/// by accident, or be dumped by a `toString()`.
@immutable
class ScannedIdentity {
  final IdDocumentType documentType;
  final String? name;

  /// Shown to the guard for context only. There is no address column on a
  /// visitor record, and adding one to hold a visitor's home address would
  /// store more than the gate needs, so this is never sent to the API.
  final String? address;

  /// Already redacted where the document type requires it.
  final String? documentReference;

  /// The captured page, in the app's cache directory. Session-only: the gate
  /// has no requirement to keep ID images, so it is never uploaded.
  final String? imagePath;

  /// Whether OCR ran at all, and whether it found any text. The two are
  /// separate so the review screen can tell "we did not try" from "we tried
  /// and the card was unreadable" - and never imply data it does not have.
  final bool ocrAttempted;
  final bool ocrFoundText;

  const ScannedIdentity({
    required this.documentType,
    this.name,
    this.address,
    this.documentReference,
    this.imagePath,
    this.ocrAttempted = false,
    this.ocrFoundText = false,
  });

  /// A capture with nothing read off it: the guard still gets the image and a
  /// manual form, which is the honest outcome when OCR fails.
  const ScannedIdentity.unreadable({
    required this.documentType,
    this.imagePath,
    this.ocrAttempted = true,
  })  : name = null,
        address = null,
        documentReference = null,
        ocrFoundText = false;

  bool get hasAnyExtractedField =>
      (name?.isNotEmpty ?? false) ||
      (address?.isNotEmpty ?? false) ||
      (documentReference?.isNotEmpty ?? false);

  ScannedIdentity copyWith({
    IdDocumentType? documentType,
    String? name,
    String? address,
    String? documentReference,
    String? imagePath,
  }) =>
      ScannedIdentity(
        documentType: documentType ?? this.documentType,
        name: name ?? this.name,
        address: address ?? this.address,
        documentReference: documentReference ?? this.documentReference,
        imagePath: imagePath ?? this.imagePath,
        ocrAttempted: ocrAttempted,
        ocrFoundText: ocrFoundText,
      );

  /// No document content, so a stray log or error report cannot leak a name,
  /// an address or a document number.
  @override
  String toString() => 'ScannedIdentity(${documentType.apiValue}, '
      'fields: ${hasAnyExtractedField ? 'some' : 'none'}, ocr: $ocrFoundText)';
}

/// Pulls the few fields a gate needs out of OCR text.
///
/// Plain Dart with no plugin dependency, precisely so it can be unit tested
/// (`test/id_document_parser_test.dart`) - the extraction rules and, more
/// importantly, the Aadhaar redaction are verified on every `flutter test`
/// run rather than only by eye on a device.
class IdDocumentParser {
  /// 12 digits, printed in groups of four. The `\b` at each end is what keeps
  /// a 16-digit Aadhaar VID (or any longer run) from matching a 12-digit
  /// prefix of itself.
  static final RegExp _aadhaarNumber = RegExp(r'\b\d{4}\s?\d{4}\s?\d{4}\b');
  static final RegExp _pan = RegExp(r'\b[A-Z]{5}\d{4}[A-Z]\b');
  static final RegExp _passport = RegExp(r'\b[A-PR-WY]\d{7}\b');
  static final RegExp _licence =
      RegExp(r'\b[A-Z]{2}[-\s]?\d{2}[-\s]?\d{4}[-\s]?\d{7}\b');
  static final RegExp _pinCode = RegExp(r'\b\d{6}\b');
  static final RegExp _nameLabel =
      RegExp(r'^(name|नाम)\s*[:\-]?\s*(.*)$',
          caseSensitive: false);
  static final RegExp _addressLabel =
      RegExp(r'^(address|पता)\s*[:\-]?\s*(.*)$',
          caseSensitive: false);

  /// Words that appear on the cards themselves rather than in a person's
  /// name. Without this list the first line of an Aadhaar card
  /// ("GOVERNMENT OF INDIA") reads as a perfectly good two-word name.
  static const _noise = {
    'GOVERNMENT', 'INDIA', 'UNIQUE', 'IDENTIFICATION', 'AUTHORITY', 'AADHAAR',
    'UIDAI', 'VID', 'MALE', 'FEMALE', 'TRANSGENDER', 'DOB', 'BIRTH', 'YEAR',
    'ADDRESS', 'INCOME', 'TAX', 'DEPARTMENT', 'PERMANENT', 'ACCOUNT', 'NUMBER',
    'CARD', 'REPUBLIC', 'PASSPORT', 'DRIVING', 'LICENCE', 'LICENSE',
    'TRANSPORT', 'AUTHORISATION', 'ISSUE', 'ISSUED', 'VALID', 'VALIDITY',
    'SIGNATURE', 'NATIONALITY', 'SEX', 'PIN', 'PINCODE', 'FATHER', 'MOTHER',
    'HUSBAND', 'WIFE', 'SON', 'DAUGHTER', 'HELP', 'WWW', 'GOV',
    'ENROLMENT', 'ENROLLMENT', 'DETAILS', 'VEHICLE', 'BLOOD', 'GROUP',
    // Company and staff cards, which is most of what "Other" turns out to be.
    'ID', 'IDENTITY', 'COMPANY', 'EMPLOYEE', 'STAFF', 'VISITOR', 'CONTRACTOR',
    'LIMITED', 'PRIVATE', 'LTD', 'PVT',
  };

  static ScannedIdentity parse(
    String rawText,
    IdDocumentType type, {
    String? imagePath,
  }) {
    final lines = rawText
        .split('\n')
        .map((l) => l.trim())
        .where((l) => l.isNotEmpty)
        .toList();

    if (lines.isEmpty) {
      return ScannedIdentity.unreadable(
        documentType: type,
        imagePath: imagePath,
      );
    }

    return ScannedIdentity(
      documentType: type,
      name: _extractName(lines),
      address: _extractAddress(lines),
      documentReference: _redact(type, _extractReference(lines, type)),
      imagePath: imagePath,
      ocrAttempted: true,
      ocrFoundText: true,
    );
  }

  /// Aadhaar keeps its last four digits and nothing else. Anything shorter
  /// than four digits is dropped rather than padded - a partial number is
  /// worse than none, because it still looks like data.
  static String? redactReference(IdDocumentType type, String? number) =>
      _redact(type, number);

  static String? _redact(IdDocumentType type, String? number) {
    if (number == null || number.isEmpty) return null;
    if (!type.redactsNumber) return number;

    final digits = number.replaceAll(RegExp(r'\D'), '');
    if (digits.length < 4) return null;

    return 'XXXX XXXX ${digits.substring(digits.length - 4)}';
  }

  static String? _extractName(List<String> lines) {
    // A labelled name wins: it is the only case we actually know.
    for (var i = 0; i < lines.length; i++) {
      final m = _nameLabel.firstMatch(lines[i]);
      if (m == null) continue;
      final sameLine = _cleanName(m.group(2) ?? '');
      if (sameLine != null) return sameLine;
      if (i + 1 < lines.length) {
        final next = _cleanName(lines[i + 1]);
        if (next != null) return next;
      }
    }

    // Otherwise the first line that looks like a person rather than card
    // furniture.
    for (final line in lines) {
      final candidate = _cleanName(line);
      if (candidate != null) return candidate;
    }
    return null;
  }

  /// A name is two to four alphabetic words, none of them card furniture.
  static String? _cleanName(String line) {
    final text = line.replaceAll(RegExp(r'[^A-Za-z\s.]'), ' ').trim();
    if (text.isEmpty) return null;

    final words =
        text.split(RegExp(r'\s+')).where((w) => w.length > 1).toList();
    if (words.length < 2 || words.length > 4) return null;
    if (words.any((w) => _noise.contains(w.toUpperCase().replaceAll('.', '')))) {
      return null;
    }
    if (!words.every((w) => RegExp(r'^[A-Za-z.]+$').hasMatch(w))) return null;

    return words.map(_titleCase).join(' ');
  }

  static String _titleCase(String word) => word.length < 2
      ? word.toUpperCase()
      : word[0].toUpperCase() + word.substring(1).toLowerCase();

  /// Everything from an "Address" label up to and including the line that
  /// carries a PIN code, which is where an Indian address ends.
  static String? _extractAddress(List<String> lines) {
    for (var i = 0; i < lines.length; i++) {
      final m = _addressLabel.firstMatch(lines[i]);
      if (m == null) continue;

      final parts = <String>[];
      final sameLine = (m.group(2) ?? '').trim();
      if (sameLine.isNotEmpty) parts.add(sameLine);

      for (var j = i + 1; j < lines.length && parts.length < 6; j++) {
        parts.add(lines[j]);
        if (_pinCode.hasMatch(lines[j])) break;
      }

      if (parts.isEmpty) return null;
      final joined = parts.join(', ');
      return joined.length > 200 ? joined.substring(0, 200) : joined;
    }
    return null;
  }

  static String? _extractReference(List<String> lines, IdDocumentType type) {
    if (!type.hasKnownNumberFormat) return null;

    final pattern = switch (type) {
      IdDocumentType.aadhaar => _aadhaarNumber,
      IdDocumentType.pan => _pan,
      IdDocumentType.passport => _passport,
      IdDocumentType.drivingLicence => _licence,
      IdDocumentType.other => _aadhaarNumber, // unreachable: guarded above
    };

    for (final line in lines) {
      final match = pattern.firstMatch(line.toUpperCase());
      if (match != null) return match.group(0)!.trim();
    }
    return null;
  }
}
