class FamilyMemberModel {
  final String name;
  final String relationship;
  final String? phone;

  const FamilyMemberModel({
    required this.name,
    required this.relationship,
    this.phone,
  });

  factory FamilyMemberModel.fromJson(Map<String, dynamic> json) =>
      FamilyMemberModel(
        name:         json['name']?.toString() ?? '',
        relationship: json['relationship']?.toString() ?? '',
        phone:        json['phone']?.toString(),
      );
}
