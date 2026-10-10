/// One published society document (rules, bye-laws, parking...), as
/// returned by GET /api/v1/society-documents. The JSON keys are the API
/// contract - see SocietyDocumentApiController::format().
class SocietyDocumentModel {
  final int id;
  final String title;
  final String category;
  final String categoryLabel;
  final String description;
  final String version;
  final DateTime? effectiveFrom;
  final DateTime? publishedAt;
  final int fileSize;
  final bool requiresAcknowledgement;
  final bool acknowledged;

  const SocietyDocumentModel({
    required this.id,
    required this.title,
    required this.category,
    required this.categoryLabel,
    required this.description,
    required this.version,
    this.effectiveFrom,
    this.publishedAt,
    this.fileSize = 0,
    this.requiresAcknowledgement = false,
    this.acknowledged = false,
  });

  /// API path of the PDF (bearer-authorised, streamed by the server).
  String get filePath => '/society-documents/$id/file';

  bool get needsAcknowledgement => requiresAcknowledgement && !acknowledged;

  SocietyDocumentModel copyWith({bool? acknowledged}) => SocietyDocumentModel(
        id: id,
        title: title,
        category: category,
        categoryLabel: categoryLabel,
        description: description,
        version: version,
        effectiveFrom: effectiveFrom,
        publishedAt: publishedAt,
        fileSize: fileSize,
        requiresAcknowledgement: requiresAcknowledgement,
        acknowledged: acknowledged ?? this.acknowledged,
      );

  factory SocietyDocumentModel.fromJson(Map<String, dynamic> json) => SocietyDocumentModel(
        id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
        title: json['title']?.toString() ?? '',
        category: json['category']?.toString() ?? 'other',
        categoryLabel: json['category_label']?.toString() ?? 'Other',
        description: json['description']?.toString() ?? '',
        version: json['version']?.toString() ?? '',
        effectiveFrom: DateTime.tryParse(json['effective_from']?.toString() ?? ''),
        publishedAt: DateTime.tryParse(json['published_at']?.toString() ?? ''),
        fileSize: int.tryParse(json['file_size']?.toString() ?? '') ?? 0,
        requiresAcknowledgement: json['requires_acknowledgement'] == true || json['requires_acknowledgement'] == 1,
        acknowledged: json['acknowledged'] == true || json['acknowledged'] == 1,
      );
}
