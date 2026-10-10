import 'visitor_model.dart';

/// What the gate already knows about a phone number.
///
/// Asked before registering anyone, so a regular visitor becomes a check-in
/// instead of a third duplicate row. The API answers society-scoped and
/// without any pass token - see `VisitorApiController::lookup`.
class VisitorLookup {
  final bool found;

  /// The most recent visitor record for this number, if any.
  final VisitorModel? visitor;

  /// An active access pass for this number: the recurring maid/driver case,
  /// where the right action is to scan or check in, not to register again.
  final String? passPersonName;
  final String? passCategory;
  final bool passCurrentlyInside;

  const VisitorLookup({
    required this.found,
    this.visitor,
    this.passPersonName,
    this.passCategory,
    this.passCurrentlyInside = false,
  });

  bool get hasPass => (passPersonName ?? '').isNotEmpty;

  /// A visitor already approved and not yet inside can simply be let in.
  bool get canCheckInExisting =>
      visitor != null &&
      visitor!.approvalStatus == 'allowed' &&
      visitor!.entryStatus != 'entered';

  static const VisitorLookup none = VisitorLookup(found: false);

  factory VisitorLookup.fromJson(Map<String, dynamic> json) {
    final visitorJson = json['visitor'];
    final passJson = json['access_pass'];

    return VisitorLookup(
      found: json['found'] == true,
      visitor: visitorJson is Map<String, dynamic>
          ? VisitorModel.fromJson(visitorJson)
          : null,
      passPersonName: passJson is Map<String, dynamic>
          ? passJson['person_name']?.toString()
          : null,
      passCategory: passJson is Map<String, dynamic>
          ? passJson['category']?.toString()
          : null,
      passCurrentlyInside:
          passJson is Map<String, dynamic> && passJson['currently_inside'] == true,
    );
  }
}
