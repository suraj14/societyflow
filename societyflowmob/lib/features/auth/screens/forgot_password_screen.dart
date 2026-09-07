import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});
  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey   = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  bool _isLoading = false;
  bool _sent      = false;

  @override
  void dispose() { _emailCtrl.dispose(); super.dispose(); }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    await Future.delayed(const Duration(seconds: 1));
    if (mounted) setState(() { _isLoading = false; _sent = true; });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Forgot Password')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: _sent
            ? Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.mark_email_read_rounded, size: 72, color: AppColors.secondary),
                  const SizedBox(height: 20),
                  const Text('Email Sent!', style: AppTextStyles.h2, textAlign: TextAlign.center),
                  const SizedBox(height: 8),
                  const Text('Check your inbox for password reset instructions.', style: AppTextStyles.body2, textAlign: TextAlign.center),
                  const SizedBox(height: 32),
                  AppButton(label: 'Back to Login', onPressed: () => Navigator.pop(context)),
                ],
              )
            : Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Reset Password', style: AppTextStyles.h2),
                    const SizedBox(height: 8),
                    const Text('Enter your email to receive a reset link.', style: AppTextStyles.body2),
                    const SizedBox(height: 32),
                    const Text('Email Address', style: AppTextStyles.label),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _emailCtrl,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(hintText: 'Enter your email', prefixIcon: Icon(Icons.email_outlined, color: AppColors.textHint)),
                      validator: (v) { if (v?.isEmpty == true) return 'Email is required'; if (!v!.contains('@')) return 'Enter a valid email'; return null; },
                    ),
                    const SizedBox(height: 32),
                    AppButton(label: 'Send Reset Link', onPressed: _submit, isLoading: _isLoading, icon: Icons.send_rounded),
                  ],
                ),
              ),
      ),
    );
  }
}
