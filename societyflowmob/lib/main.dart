import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/services/api_service.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/providers/auth_provider.dart';
import 'features/splash/splash_screen.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/auth/screens/register_screen.dart';
import 'features/auth/screens/forgot_password_screen.dart';
import 'features/auth/screens/otp_phone_screen.dart';
import 'features/shell/main_shell.dart';
import 'features/profile/screens/edit_profile_screen.dart';
import 'features/profile/screens/change_password_screen.dart';
import 'features/society_documents/screens/society_documents_screen.dart';

final navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
  ));
  runApp(const ProviderScope(child: SocietyFlowApp()));
}

class SocietyFlowApp extends ConsumerWidget {
  const SocietyFlowApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Token expired/revoked mid-session: reset auth state and return to login.
    ApiService.onSessionExpired = () {
      ref.read(authProvider.notifier).clearSession();
      final nav = navigatorKey.currentState;
      if (nav != null) {
        nav.pushNamedAndRemoveUntil('/login', (route) => false);
        ScaffoldMessenger.maybeOf(nav.context)?.showSnackBar(const SnackBar(
          content: Text('Your session has expired. Please login again.'),
          behavior: SnackBarBehavior.floating,
        ));
      }
    };

    return MaterialApp(
      title: 'SocietyFlow',
      debugShowCheckedModeBanner: false,
      navigatorKey: navigatorKey,
      theme: AppTheme.lightTheme,
      initialRoute: '/',
      routes: {
        '/':                (_) => const SplashScreen(),
        '/login':           (_) => const LoginScreen(),
        '/register':        (_) => const RegisterScreen(),
        '/forgot-password': (_) => const ForgotPasswordScreen(),
        '/otp-login':       (_) => const OtpPhoneScreen(),
        '/home':            (_) => MainShell(key: mainShellKey),
        '/edit-profile':    (_) => const EditProfileScreen(),
        '/change-password': (_) => const ChangePasswordScreen(),
        '/society-documents': (_) => const SocietyDocumentsScreen(),
      },
    );
  }
}
