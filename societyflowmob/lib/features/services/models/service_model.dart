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
    id:          int.tryParse(json['id']?.toString() ?? '') ?? 0,
    name:        json['name']?.toString() ?? '',
    description: json['description']?.toString(),
    category:    json['category']?.toString(),
    charges:     double.tryParse((json['charges'] ?? 0).toString()) ?? 0,
    status:      json['status']?.toString() ?? 'active',
  );
}

/// A person/company the society lists for a service (Maid, Cook...).
/// JSON keys are the API contract - see ServiceApiController::providers().
class ServiceProviderModel {
  final int id;
  final int? serviceId;
  final String name;
  final String? phone;
  final String? serviceName;
  final String? availability;
  final bool isDailyHelp;
  final double charges;
  final String? priceType;
  final double rating;
  final String status;

  const ServiceProviderModel({
    required this.id, this.serviceId, required this.name, this.phone,
    this.serviceName, this.availability, this.isDailyHelp = false,
    this.charges = 0, this.priceType, required this.rating, required this.status,
  });

  /// A number worth dialling: 7 to 15 digits (E.164 allows at most 15). Anything else shows "no phone listed" rather than a dead button.
  bool get hasPhone {
    final digits = (phone ?? '').replaceAll(RegExp(r'[^0-9]'), '').length;
    return digits >= 7 && digits <= 15;
  }

  /// The category the directory groups and filters by.
  String get categoryLabel => (serviceName ?? '').trim().isEmpty ? 'General' : serviceName!.trim();

  /// "₹500 / month" style label, or null when no price is listed.
  String? get priceLabel {
    if (charges <= 0) return null;
    final amount = '₹${charges.toStringAsFixed(charges == charges.roundToDouble() ? 0 : 2)}';
    final unit = (priceType ?? '').replaceAll('_', ' ').trim();
    return unit.isEmpty ? amount : '$amount / $unit';
  }

  factory ServiceProviderModel.fromJson(Map<String, dynamic> json) => ServiceProviderModel(
    id:           int.tryParse(json['id']?.toString() ?? '') ?? 0,
    serviceId:    int.tryParse(json['service_id']?.toString() ?? ''),
    name:         json['name']?.toString() ?? '',
    phone:        json['phone']?.toString(),
    serviceName:  json['service_name']?.toString(),
    availability: (json['availability']?.toString().trim().isEmpty ?? true) ? null : json['availability'].toString(),
    isDailyHelp:  json['is_daily_help'] == true || json['is_daily_help'] == 1,
    charges:      double.tryParse((json['charges'] ?? 0).toString()) ?? 0,
    priceType:    json['price_type']?.toString(),
    rating:       double.tryParse((json['rating'] ?? 0).toString()) ?? 0,
    status:       json['status']?.toString() ?? 'active',
  );
}
