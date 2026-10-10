import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

/// Opens the phone's own dialer, WhatsApp, mail app or browser. Every action
/// either opens something or tells the user why it could not - never a
/// button that silently does nothing.
class ContactLauncher {
  ContactLauncher._();

  /// Digits and a leading + only - what a dialer accepts.
  static String cleanPhone(String raw) {
    final trimmed = raw.trim();
    final digits = trimmed.replaceAll(RegExp(r'[^0-9]'), '');
    return trimmed.startsWith('+') ? '+$digits' : digits;
  }

  /// A 10-digit Indian mobile becomes 91XXXXXXXXXX for WhatsApp links.
  static String whatsappNumber(String raw) {
    final digits = raw.replaceAll(RegExp(r'[^0-9]'), '');
    return digits.length == 10 ? '91$digits' : digits;
  }

  static bool isDialable(String? raw) => raw != null && cleanPhone(raw).replaceAll('+', '').length >= 3;

  static Future<void> call(BuildContext context, String phone) =>
      _open(context, Uri(scheme: 'tel', path: cleanPhone(phone)), 'Could not open the phone dialer.');

  static Future<void> whatsapp(BuildContext context, String phone) =>
      _open(context, Uri.parse('https://wa.me/${whatsappNumber(phone)}'), 'Could not open WhatsApp.');

  static Future<void> email(BuildContext context, String address, {String? subject}) => _open(
        context,
        Uri(scheme: 'mailto', path: address.trim(), query: subject == null ? null : 'subject=${Uri.encodeComponent(subject)}'),
        'Could not open an email app.',
      );

  static Future<void> web(BuildContext context, String url) =>
      _open(context, Uri.parse(url.startsWith('http') ? url : 'https://$url'), 'Could not open the link.');

  static Future<void> _open(BuildContext context, Uri uri, String failure) async {
    final messenger = ScaffoldMessenger.maybeOf(context);
    try {
      final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!ok) messenger?.showSnackBar(SnackBar(content: Text(failure)));
    } catch (_) {
      messenger?.showSnackBar(SnackBar(content: Text(failure)));
    }
  }
}
