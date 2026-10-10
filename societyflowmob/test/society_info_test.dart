import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/features/society_documents/models/society_info_model.dart';
import 'package:societyflow/features/society_documents/providers/society_documents_provider.dart';
import 'package:societyflow/features/society_documents/providers/society_info_provider.dart';
import 'package:societyflow/features/society_documents/screens/society_documents_screen.dart';
import 'package:societyflow/features/society_documents/screens/society_info_tabs.dart';

/// Society Info on the phone: Society Details, Committee, Documents.
/// The server side (data, ordering, phone validation, isolation, roles) is
/// covered by tests/Feature/SocietyInfoTest.php; these cover parsing and what
/// the screens show and do.
void main() {
  group('SocietyInfoModel.fromJson', () {
    test('parses the full payload', () {
      final info = SocietyInfoModel.fromJson({
        'details': {
          'name': 'Green Meadows',
          'registration_number': 'REG/42',
          'address': '12 Lake Road, Pune',
          'society_type': 'Villa Township',
          'registered_on': '2015-04-17',
          'registered_on_label': '17 Apr 2015',
        },
        'committee': [
          {'id': 3, 'name': 'Asha Rao', 'designation': 'President', 'phone': '+919876543210', 'can_call': true, 'photo': '/storage/avatars/a.png'},
        ],
      });

      expect(info.details!.name, 'Green Meadows');
      expect(info.details!.registrationNumber, 'REG/42');
      expect(info.details!.address, '12 Lake Road, Pune');
      expect(info.details!.societyType, 'Villa Township');
      expect(info.details!.registeredOnLabel, '17 Apr 2015');
      expect(info.committee, hasLength(1));
      expect(info.committee.single.designation, 'President');
      expect(info.committee.single.canCall, isTrue);
      expect(info.committee.single.photo, '/storage/avatars/a.png');
    });

    test('unrecorded values stay null - nothing is invented', () {
      final d = SocietyDetails.fromJson({
        'name': 'Green Meadows',
        'registration_number': null,
        'address': '   ',
        'society_type': '',
        'registered_on_label': null,
      });

      expect(d.registrationNumber, isNull);
      expect(d.address, isNull);
      expect(d.societyType, isNull);
      expect(d.registeredOnLabel, isNull);
    });

    test('a member with no number never gets a Call button, whatever the flag says', () {
      final noPhone = CommitteeMember.fromJson({'id': 1, 'name': 'Raj', 'designation': 'Secretary', 'phone': null, 'can_call': true});
      final blank = CommitteeMember.fromJson({'id': 2, 'name': 'Bina', 'designation': 'Treasurer', 'phone': '  ', 'can_call': true});
      final notFlagged = CommitteeMember.fromJson({'id': 3, 'name': 'Cara', 'designation': 'Member', 'phone': '9876543210', 'can_call': false});

      expect(noPhone.canCall, isFalse);
      expect(blank.canCall, isFalse);
      expect(notFlagged.canCall, isFalse);
    });

    test('tolerates missing sections, wrong types and nameless members', () {
      final empty = SocietyInfoModel.fromJson({});
      expect(empty.details, isNull);
      expect(empty.committee, isEmpty);

      final odd = SocietyInfoModel.fromJson({
        'details': 'nope',
        'committee': [
          {'id': 1, 'name': '', 'designation': 'President'},
          {'id': 2, 'name': 'Real Person', 'designation': 'Secretary'},
          'junk',
        ],
      });
      expect(odd.details, isNull);
      expect(odd.committee.map((m) => m.name), ['Real Person']);
    });
  });

  group('SocietyDetailsView', () {
    Widget host(SocietyDetails? d) => MaterialApp(home: Scaffold(body: SingleChildScrollView(child: SocietyDetailsView(details: d))));

    testWidgets('shows all five rows, "Not available" for what was never recorded', (tester) async {
      await tester.pumpWidget(host(const SocietyDetails(name: 'Green Meadows', address: '12 Lake Road', societyType: 'Villa Township')));

      for (final label in ['SOCIETY NAME', 'REGISTRATION NUMBER', 'ADDRESS', 'SOCIETY TYPE', 'REGISTERED / ESTABLISHED']) {
        expect(find.text(label), findsOneWidget, reason: label);
      }
      expect(find.text('Green Meadows'), findsOneWidget);
      expect(find.text('12 Lake Road'), findsOneWidget);
      expect(find.text('Villa Township'), findsOneWidget);
      expect(find.text(kNotAvailable), findsNWidgets(2), reason: 'registration number and date');
    });

    testWidgets('a fully recorded society shows no "Not available"', (tester) async {
      await tester.pumpWidget(host(const SocietyDetails(
        name: 'Green Meadows',
        registrationNumber: 'REG/42',
        address: '12 Lake Road',
        societyType: 'Villa Township',
        registeredOnLabel: '17 Apr 2015',
      )));

      expect(find.text(kNotAvailable), findsNothing);
      expect(find.text('REG/42'), findsOneWidget);
      expect(find.text('17 Apr 2015'), findsOneWidget);
    });

    testWidgets('each row is one screen-reader announcement', (tester) async {
      final handle = tester.ensureSemantics();
      await tester.pumpWidget(host(const SocietyDetails(name: 'Green Meadows')));

      expect(find.bySemanticsLabel('Registration Number: Not available'), findsOneWidget);
      expect(find.bySemanticsLabel('Society Name: Green Meadows'), findsOneWidget);
      handle.dispose();
    });

    testWidgets('no society at all is an explicit empty state', (tester) async {
      await tester.pumpWidget(host(null));

      expect(find.text('No society linked'), findsOneWidget);
    });
  });

  group('CommitteeView', () {
    const asha = CommitteeMember(id: 1, name: 'Asha Rao', designation: 'President', phone: '+919876543210', canCall: true);
    const raj = CommitteeMember(id: 2, name: 'Raj Mehta', designation: 'Secretary');

    Widget host(List<CommitteeMember> members, [void Function(String)? onCall]) =>
        MaterialApp(home: Scaffold(body: CommitteeView(members: members, onCall: onCall ?? (_) {})));

    testWidgets('no members is a helpful empty state, never placeholder people', (tester) async {
      await tester.pumpWidget(host(const []));

      expect(find.text('No committee members listed yet'), findsOneWidget);
      expect(find.text('Call'), findsNothing);
    });

    testWidgets('a card per member with designation; Call only where there is a real number', (tester) async {
      await tester.pumpWidget(host(const [asha, raj]));

      expect(find.text('Asha Rao'), findsOneWidget);
      expect(find.text('President'), findsOneWidget);
      expect(find.text('+919876543210'), findsOneWidget);
      expect(find.text('Raj Mehta'), findsOneWidget);
      expect(find.text('Secretary'), findsOneWidget);
      expect(find.text('Call'), findsOneWidget, reason: 'Raj has no number');
    });

    testWidgets('tapping Call hands the member\'s number to the dialer', (tester) async {
      String? dialled;
      await tester.pumpWidget(host(const [asha, raj], (p) => dialled = p));

      await tester.tap(find.text('Call'));

      expect(dialled, '+919876543210');
    });

    testWidgets('the Call button announces who it calls', (tester) async {
      final handle = tester.ensureSemantics();
      await tester.pumpWidget(host(const [asha]));

      expect(find.bySemanticsLabel('Call Asha Rao, President'), findsOneWidget);
      handle.dispose();
    });

    testWidgets('a member without a photo gets initials, not a broken image', (tester) async {
      await tester.pumpWidget(host(const [asha]));

      expect(find.text('AR'), findsOneWidget);
    });
  });

  group('SocietyDocumentsScreen is now Society Info', () {
    Widget app({int initialTab = 0, SocietyInfoState? info}) => ProviderScope(
          overrides: [
            societyInfoProvider.overrideWith((ref) => _FakeInfo(info ??
                const SocietyInfoState(
                  info: SocietyInfoModel(
                    details: SocietyDetails(name: 'Green Meadows', address: '12 Lake Road'),
                    committee: [CommitteeMember(id: 1, name: 'Asha Rao', designation: 'President', phone: '9876543210', canCall: true)],
                  ),
                ))),
            societyDocumentsProvider.overrideWith((ref) => _FakeDocs()),
          ],
          child: MaterialApp(home: SocietyDocumentsScreen(initialTab: initialTab)),
        );

    testWidgets('one screen, titled Society Info, with exactly the three sections', (tester) async {
      await tester.pumpWidget(app());
      await tester.pumpAndSettle();

      expect(find.text('Society Info'), findsOneWidget);
      expect(find.byType(Tab), findsNWidgets(3));
      expect(find.widgetWithText(Tab, 'Society Details'), findsOneWidget);
      expect(find.widgetWithText(Tab, 'Committee'), findsOneWidget);
      expect(find.widgetWithText(Tab, 'Documents'), findsOneWidget);
      // opens on Society Details
      expect(find.text('Green Meadows'), findsOneWidget);
    });

    testWidgets('Committee tab shows the member and Documents tab keeps its own empty state', (tester) async {
      await tester.pumpWidget(app());
      await tester.pumpAndSettle();

      await tester.tap(find.widgetWithText(Tab, 'Committee'));
      await tester.pumpAndSettle();
      expect(find.text('Asha Rao'), findsOneWidget);
      expect(find.text('Call'), findsOneWidget);

      await tester.tap(find.widgetWithText(Tab, 'Documents'));
      await tester.pumpAndSettle();
      expect(find.text('No documents yet'), findsOneWidget);
    });

    testWidgets('can open straight on a section', (tester) async {
      await tester.pumpWidget(app(initialTab: 1));
      await tester.pumpAndSettle();

      expect(find.text('Asha Rao'), findsOneWidget);
    });

    testWidgets('a load failure on Society Details is retryable, not blank', (tester) async {
      await tester.pumpWidget(app(info: const SocietyInfoState(error: 'Could not load society info')));
      await tester.pumpAndSettle();

      expect(find.text('Could not load society info'), findsWidgets);
    });
  });
}

class _FakeInfo extends SocietyInfoNotifier {
  _FakeInfo(SocietyInfoState initial) {
    state = initial;
  }

  @override
  Future<void> load() async {}
}

class _FakeDocs extends SocietyDocumentsNotifier {
  @override
  Future<void> load() async {}
}
