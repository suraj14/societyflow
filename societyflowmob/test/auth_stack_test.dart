import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/features/auth/models/user_model.dart';
import 'package:societyflow/features/auth/providers/auth_provider.dart';
import 'package:societyflow/features/auth/screens/login_screen.dart';

/// Found on a real phone: signing in replaced only the login screen, so the OTP / login screens that were under it
/// stayed on the stack and the phone's Back button took a signed-in admin back to a login screen. Signing in now clears
/// the whole stack: Home is the root, and Back from Home leaves the app instead.
class _OkAuth extends AuthNotifier {
  @override
  Future<void> login({required String email, required String password}) async {
    state = AuthState(user: UserModel.fromJson({'id': 1, 'name': 'Priya', 'email': email, 'role': 'Admin', 'society_name': 'Royal Eden'}));
  }
}

void main() {
  testWidgets('after signing in there is nothing under Home to go back to', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    // The test font (Ahem) is far wider than a real one, so the login footer row overflows here but not on a phone.
    final previous = FlutterError.onError;
    FlutterError.onError = (d) {
      if (!d.exceptionAsString().contains('overflowed')) previous?.call(d);
    };
    addTearDown(() => FlutterError.onError = previous);

    await tester.pumpWidget(ProviderScope(
      overrides: [authProvider.overrideWith((ref) => _OkAuth())],
      child: MaterialApp(
        initialRoute: '/otp',
        routes: {
          '/otp': (ctx) => Scaffold(body: Center(child: ElevatedButton(onPressed: () => Navigator.pushNamed(ctx, '/login'), child: const Text('to login')))),
          '/login': (_) => const LoginScreen(),
          '/home': (ctx) => Scaffold(body: Center(child: Text(Navigator.of(ctx).canPop() ? 'HOME WITH STACK UNDER IT' : 'HOME IS THE ROOT'))),
        },
      ),
    ));
    await tester.tap(find.text('to login'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).at(0), 'admin@x.in');
    await tester.enterText(find.byType(TextFormField).at(1), 'a-good-password');
    await tester.tap(find.text('Sign In'));
    await tester.pumpAndSettle();

    expect(find.text('HOME IS THE ROOT'), findsOneWidget);
    expect(find.text('HOME WITH STACK UNDER IT'), findsNothing);
  });
}
