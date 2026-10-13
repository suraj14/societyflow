import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/shared/widgets/app_card.dart';
import 'package:societyflow/shared/widgets/discard_guard.dart';

/// Back navigation: a secondary screen has a visible way back, Android's system Back does the same thing, and a form
/// with something typed in it asks before throwing that away (a clean form closes at once).

Widget _host({required Widget Function(BuildContext) form}) => MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          appBar: AppBar(title: const Text('Dashboard')),
          body: Center(
            child: ElevatedButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: form)), child: const Text('open')),
          ),
        ),
      ),
    );

void main() {
  group('DiscardGuard', () {
    testWidgets('a clean form closes at once on the app-bar arrow and on Android Back', (tester) async {
      await tester.pumpWidget(_host(form: (_) => DiscardGuard(isDirty: () => false, child: Scaffold(appBar: AppBar(title: const Text('Form'))))));
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
      expect(find.text('Form'), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Dashboard'), findsOneWidget);
      expect(find.byType(AlertDialog), findsNothing);

      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
      await tester.binding.handlePopRoute();   // the system Back button
      await tester.pumpAndSettle();
      expect(find.text('Dashboard'), findsOneWidget);
      expect(find.byType(AlertDialog), findsNothing);
    });

    testWidgets('a form with typed input asks first; Keep editing stays, Discard leaves', (tester) async {
      final ctrl = TextEditingController();
      await tester.pumpWidget(_host(form: (_) => DiscardGuard(
            isDirty: () => ctrl.text.trim().isNotEmpty,
            child: Scaffold(appBar: AppBar(title: const Text('Form')), body: TextField(key: const Key('field'), controller: ctrl)),
          )));
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('field')), 'half-written complaint');

      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text('Discard what you entered?'), findsOneWidget);

      await tester.tap(find.byKey(const Key('discard-keep')));
      await tester.pumpAndSettle();
      expect(find.text('Form'), findsOneWidget, reason: 'still on the form');
      expect(ctrl.text, 'half-written complaint', reason: 'nothing was lost');

      await tester.pageBack();    // the app-bar arrow asks too
      await tester.pumpAndSettle();
      expect(find.text('Discard what you entered?'), findsOneWidget);
      await tester.tap(find.byKey(const Key('discard-confirm')));
      await tester.pumpAndSettle();
      expect(find.text('Dashboard'), findsOneWidget);
    });

    testWidgets('emptying the field again makes it clean: no question', (tester) async {
      final ctrl = TextEditingController(text: 'x');
      await tester.pumpWidget(_host(form: (_) => DiscardGuard(isDirty: () => ctrl.text.isNotEmpty, child: Scaffold(appBar: AppBar(title: const Text('Form'))))));
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
      ctrl.clear();

      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text('Dashboard'), findsOneWidget);
    });
  });

  group('a tappable card', () {
    testWidgets('is one touch target with ripple feedback and announces itself as a button', (tester) async {
      final handle = tester.ensureSemantics();
      var taps = 0;
      await tester.pumpWidget(MaterialApp(home: Scaffold(body: AppCard(semanticLabel: 'Pending dues: 5. Opens the list.', onTap: () => taps++, child: const Text('Pending dues')))));

      expect(find.byType(InkWell), findsOneWidget, reason: 'touch feedback');
      expect(tester.getSemantics(find.bySemanticsLabel('Pending dues: 5. Opens the list.')), isNotNull);
      await tester.tap(find.text('Pending dues'));
      expect(taps, 1);
      handle.dispose();
    });

    testWidgets('a card with no destination is not a button and has no ripple', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: Scaffold(body: AppCard(child: Text('Information only')))));
      expect(find.byType(InkWell), findsNothing);
    });
  });
}
