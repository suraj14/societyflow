class ServiceModel {
  final int id;
  final String name;
  final String? description;
  final String? category;
  final double charges;
  final String status;

  const ServiceModel({
    required this.id, required this.name, this.description,
    this.category, required this.charges, required this.status,
  });

  factory ServiceModel.fromJson(Map<String, dynamic> json) => ServiceModel(
    id:          json['id'] as int,
    name:        json['name']?.toString() ?? '',
    description: json['description']?.toString(),
    category:    json['category']?.toString(),
    charges:     double.parse((json['charges'] ?? 0).toString()),
    status:      json['status']?.toString() ?? 'active',
  );
}

class ServiceProviderModel {
  final int id;
  final String name;
  final String? phone;
  final String? serviceName;
  final double rating;
  final String status;

  const ServiceProviderModel({
    required this.id, required this.name, this.phone,
    this.serviceName, required this.rating, required this.status,
  });

  factory ServiceProviderModel.fromJson(Map<String, dynamic> json) => ServiceProviderModel(
    id:          json['id'] as int,
    name:        json['name']?.toString() ?? '',
    phone:       json['phone']?.toString(),
    serviceName: json['service_name']?.toString(),
    rating:      double.parse((json['rating'] ?? 0).toString()),
    status:      json['status']?.toString() ?? 'active',
  );
}
