import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/config/api_config.dart';
import '../../../core/network/http_service.dart';
import '../models/society_contacts_model.dart';

/// Loaded on demand (Emergency Contacts sheet, Help & Support); refreshed
/// with ref.invalidate. Throws a readable message on failure so the screens
/// can show it with a Retry.
final societyContactsProvider = FutureProvider.autoDispose<SocietyContactsModel>((ref) async {
  final result = await HttpService.instance.get(ApiConfig.societyContacts);
  if (!result.success || result.data == null) {
    throw Exception(result.message.isNotEmpty ? result.message : 'Could not load contacts');
  }
  final data = result.data!['data'];
  return SocietyContactsModel.fromJson(data is Map<String, dynamic> ? data : const {});
});
