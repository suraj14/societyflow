class NoticeModel {
  final int id;
  final String title;
  final String content;
  final String status;
  final String? type;
  final bool isImportant;
  final DateTime createdAt;
  final String? postedBy;
  final String? image;
  final List<String> attachments;

  const NoticeModel({
    required this.id, required this.title, required this.content,
    required this.status, this.type, required this.isImportant,
    required this.createdAt, this.postedBy, this.image,
    this.attachments = const [],
  });

  factory NoticeModel.fromJson(Map<String, dynamic> json) => NoticeModel(
    id:          json['id'] as int,
    title:       json['title']?.toString() ?? '',
    content:     json['content']?.toString() ?? '',
    status:      json['status']?.toString() ?? 'active',
    type:        json['type']?.toString(),
    isImportant: json['is_important'] == true || json['is_important'] == 1,
    createdAt:   DateTime.parse(json['created_at'].toString()),
    postedBy:    json['posted_by']?.toString(),
    image:       json['image']?.toString(),
    attachments: (json['attachments'] as List<dynamic>? ?? []).map((a) => a.toString()).toList(),
  );
}
