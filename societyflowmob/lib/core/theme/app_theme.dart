import 'package:flutter/material.dart';

class AppColors {
  static const Color primary       = Color(0xFF4F46E5);
  static const Color primaryLight  = Color(0xFF818CF8);
  static const Color primaryDark   = Color(0xFF3730A3);
  static const Color secondary     = Color(0xFF22C55E);
  static const Color error         = Color(0xFFEF4444);
  static const Color warning       = Color(0xFFF59E0B);
  static const Color info          = Color(0xFF3B82F6);

  static const Color surface       = Color(0xFFFFFFFF);
  static const Color background    = Color(0xFFF8F9FF);
  static const Color cardBg        = Color(0xFFFFFFFF);

  static const Color textPrimary   = Color(0xFF111827);
  static const Color textSecondary = Color(0xFF6B7280);
  static const Color textHint      = Color(0xFF9CA3AF);

  static const Color divider       = Color(0xFFF3F4F6);

  static const Color shimmerBase      = Color(0xFFE5E7EB);
  static const Color shimmerHighlight = Color(0xFFF9FAFB);

  // ✅ REQUIRED GRADIENTS
  static const LinearGradient primaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF4F46E5), Color(0xFF7C3AED)],
  );

  static const LinearGradient splashGradient = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: [Color(0xFF4F46E5), Color(0xFF3730A3)],
  );

  static const LinearGradient cardGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF4F46E5), Color(0xFF6D28D9)],
  );
}

class AppTheme {
  static ThemeData get lightTheme => ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: Brightness.light,
    ),
    scaffoldBackgroundColor: AppColors.background,

    // ✅ FIXED
    cardTheme: CardThemeData(
      color: AppColors.cardBg,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
      ),
    ),
  );
}

// ✅ FULL TEXT STYLES (REQUIRED)
class AppTextStyles {
  static const TextStyle h1 = TextStyle(fontSize: 28, fontWeight: FontWeight.w800);
  static const TextStyle h2 = TextStyle(fontSize: 22, fontWeight: FontWeight.w700);
  static const TextStyle h3 = TextStyle(fontSize: 18, fontWeight: FontWeight.w700);

  static const TextStyle body1 = TextStyle(fontSize: 16);
  static const TextStyle body2 = TextStyle(fontSize: 14);

  static const TextStyle label = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: AppColors.textPrimary,
  );

  static const TextStyle caption = TextStyle(
    fontSize: 12,
    color: AppColors.textHint,
  );
}

// ✅ REQUIRED
class AppRadius {
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double full = 100;
}