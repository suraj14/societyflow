import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/main.dart';

void main() {
  testWidgets('App smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const SocietyFlowApp());
    expect(find.byType(SocietyFlowApp), findsOneWidget);
  });
}
