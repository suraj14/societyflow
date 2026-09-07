class ComplaintModel {
  final int id;
  final String title;
  final String description;
  final String status;
  final String? category;
  final String? imagePath;
  final DateTime createdAt;
  final String? assignedTo;
  final List<ComplaintUpdate> updates;

  const ComplaintModel({
    required this.id, required this.title, required this.description,
    required this.status, this.category, this.imagePath,
    required this.createdAt, this.assignedTo, this.updates = const [],
  });

  factory ComplaintModel.fromJson(Map<String, dynamic> json) => ComplaintModel(
    id:          json['id'] as int,
    title:       json['title']?.toString() ?? '',
    description: json['description']?.toString() ?? '',
    status:      json['status']?.toString() ?? 'open',
    category:    json['category']?['name']?.toString(),
    imagePath:   json['image_path']?.toString(),
    createdAt:   DateTime.parse(json['created_at'].toString()),
    assignedTo:  json['assigned_to']?.toString(),
    updates:     (json['updates'] as List<dynamic>? ?? []).map((u) => ComplaintUpdate.fromJson(u as Map<String, dynamic>)).toList(),
  );

  bool get isOpen       => status == 'open';
  bool get isInProgress => status == 'in_progress';
  bool get isClosed     => status == 'closed' || status == 'resolved';
}

class ComplaintUpdate {
  final int id;
  final String message;
  final DateTime createdAt;
  final String? updatedBy;

  const ComplaintUpdate({required this.id, required this.message, required this.createdAt, this.updatedBy});

  factory ComplaintUpdate.fromJson(Map<String, dynamic> json) => ComplaintUpdate(
    id:        json['id'] as int,
    message:   json['message']?.toString() ?? '',
    createdAt: DateTime.parse(json['created_at'].toString()),
    updatedBy: json['updated_by']?.toString(),
  );
}
