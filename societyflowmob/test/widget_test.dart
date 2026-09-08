import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:societyflow/features/auth/screens/login_screen.dart';
import 'package:societyflow/core/services/server_discovery.dart';
import 'package:societyflow/main.dart';

/// The previous version of this test pumped SocietyFlowApp directly. That
/// could never pass: SocietyFlowApp is a ConsumerWidget and main() wraps it
/// in a ProviderScope, so pumping it bare throws immediately. It also left
/// the splash screen's 1.8s timer pending, which fails the test regardless.
///
/// Both are fixed here rather than deleted, so the app still has a smoke
/// test that actually exercises boot.
void main() {
  setUp(() {
    // StorageService reads SharedPreferences during the splash auth check.
    SharedPreferences.setMockInitialValues({});

    // Discovery scans the local subnet over real sockets, which cannot run
    // in a test. Pinning the URL skips the scan without changing any other
    // boot behaviour.
    ServerDiscovery.overrideBaseUrl = 'http://127.0.0.1:8000/api/v1';
  });

  tearDown(() {
    ServerDiscovery.overrideBaseUrl = null;
  });

  testWidgets('app boots and renders the splash screen', (tester) async {
    await tester.pumpWidget(const ProviderScope(child: SocietyFlowApp()));

    expect(find.byType(SocietyFlowApp), findsOneWidget);
    expect(find.byType(MaterialApp), findsOneWidget);

    // Let the splash animation and its minimum-display timer run out so no
    // timer is left pending when the test ends.
    await tester.pump(const Duration(seconds: 2));
  });

  testWidgets('an unauthenticated launch ends on the login screen', (tester) async {
    SharedPreferences.setMockInitialValues({}); // no auth_token

    await tester.pumpWidget(const ProviderScope(child: SocietyFlowApp()));

    // Splash minimum is 1.8s; server discovery runs in parallel and fails
    // fast in a test environment with no reachable host.
    await tester.pump(const Duration(seconds: 2));
    await tester.pumpAndSettle(const Duration(seconds: 1));

    expect(find.byType(LoginScreen), findsOneWidget);
  });
}
