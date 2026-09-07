import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';

/// Full-screen loading overlay. Wrap any widget tree with this.
/// Usage: LoadingOverlay(isLoading: state.isLoading, child: YourWidget())
class LoadingOverlay extends StatelessWidget {
  final bool isLoading;
  final Widget child;
  final String? message;

  const LoadingOverlay({
    super.key,
    required this.isLoading,
    required this.child,
    this.message,
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        child,
        if (isLoading)
          Container(
            color: Colors.black.withOpacity(0.3),
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 20)],
                ),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const CircularProgressIndicator(color: AppColors.primary, strokeWidth: 3),
                  if (message != null) ...[
                    const SizedBox(height: 12),
                    Text(message!, style: AppTextStyles.body2),
                  ],
                ]),
              ),
            ),
          ),
      ],
    );
  }
}
