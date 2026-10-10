import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:url_launcher_platform_interface/url_launcher_platform_interface.dart';
import 'package:url_launcher_platform_interface/link.dart';
import 'package:societyflow/core/utils/contact_launcher.dart';
import 'package:societyflow/features/support/models/society_contacts_model.dart';
import 'package:societyflow/features/support/screens/emergency_contacts_sheet.dart';

/// Records every launch the real device/OS would have been asked to open,
/// without needing a real dialer/WhatsApp/mail app under `flutter test`.
class _FakeUrlLauncher extends UrlLauncherPlatform {
  final List<String> launched = [];
  bool canLaunchResult = true;
  bool launchResult = true;

  @override
  LinkDelegate? get linkDelegate => null;

  @override
  Future<bool> canLaunch(String url) async => canLaunchResult;

  @override
  Future<bool> launchUrl(String url, LaunchOptions options) async {
    launched.add(url);
    return launchResult;
  }
}

/// Regression: on a real device, tapping a Call button in Emergency
/// Contacts / the Services directory appeared to do nothing. This proves
/// each action builds the exact URI the OS dialer/WhatsApp/mail app expects
/// and that it reaches `UrlLauncherPlatform.instance.launchUrl` - the seam
/// a real device failure (missing AndroidManifest <queries>, wrong scheme)
/// would also go through.
void main() {
  late _FakeUrlLauncher fake;

  setUp(() {
    fake = _FakeUrlLauncher();
    UrlLauncherPlatform.instance = fake;
  });

  group('ContactLauncher builds the right URI', () {
    testWidgets('call uses tel: with cleaned digits', (tester) async {
      await tester.pumpWidget(MaterialApp(home: Builder(builder: (context) {
        return ElevatedButton(onPressed: () => ContactLauncher.call(context, '+91 98480-12345'), child: const Text('go'));
      })));
      await tester.tap(find.text('go'));
      await tester.pumpAndSettle();

      expect(fake.launched, ['tel:+919848012345']);
    });

    testWidgets('whatsapp uses wa.me with a 91-prefixed 10-digit number', (tester) async {
      await tester.pumpWidget(MaterialApp(home: Builder(builder: (context) {
        return ElevatedButton(onPressed: () => ContactLauncher.whatsapp(context, '9848012345'), child: const Text('go'));
      })));
      await tester.tap(find.text('go'));
      await tester.pumpAndSettle();

      expect(fake.launched, ['https://wa.me/919848012345']);
    });

    testWidgets('email uses mailto: with an encoded subject', (tester) async {
      await tester.pumpWidget(MaterialApp(home: Builder(builder: (context) {
        return ElevatedButton(onPressed: () => ContactLauncher.email(context, 'office@royaleden.in', subject: 'Help needed'), child: const Text('go'));
      })));
      await tester.tap(find.text('go'));
      await tester.pumpAndSettle();

      expect(fake.launched, ['mailto:office@royaleden.in?subject=Help%20needed']);
    });

    testWidgets('a failed launch shows a SnackBar instead of silently doing nothing', (tester) async {
      fake.launchResult = false;
      await tester.pumpWidget(MaterialApp(home: Scaffold(body: Builder(builder: (context) {
        return ElevatedButton(onPressed: () => ContactLauncher.call(context, '100'), child: const Text('go'));
      }))));
      await tester.tap(find.text('go'));
      await tester.pumpAndSettle();

      expect(find.text('Could not open the phone dialer.'), findsOneWidget);
    });
  });

  group('ContactTile (Emergency Contacts row) is actually wired to the launcher', () {
    testWidgets('tapping the row calls the number', (tester) async {
      await tester.pumpWidget(const MaterialApp(
        home: Scaffold(body: ContactTile(contact: SocietyContact(label: 'Police', phone: '100', kind: 'police'))),
      ));

      await tester.tap(find.byType(ContactTile));
      await tester.pumpAndSettle();

      expect(fake.launched, ['tel:100']);
    });

    testWidgets('tapping the dedicated call icon also calls the number', (tester) async {
      await tester.pumpWidget(const MaterialApp(
        home: Scaffold(body: ContactTile(contact: SocietyContact(label: 'Royal Eden', phone: '7726884448', kind: 'office'))),
      ));

      await tester.tap(find.byTooltip('Call 7726884448'));
      await tester.pumpAndSettle();

      expect(fake.launched, ['tel:7726884448']);
    });

    testWidgets('the WhatsApp icon only shows, and only works, when asked for', (tester) async {
      await tester.pumpWidget(const MaterialApp(
        home: Scaffold(body: ContactTile(contact: SocietyContact(label: 'Manager', phone: '9848000001'), showWhatsApp: true)),
      ));

      expect(find.byTooltip('WhatsApp'), findsOneWidget);
      await tester.tap(find.byTooltip('WhatsApp'));
      await tester.pumpAndSettle();

      expect(fake.launched, ['https://wa.me/919848000001']);
    });
  });
}
