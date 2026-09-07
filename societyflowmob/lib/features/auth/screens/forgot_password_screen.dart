import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../providers/auth_provider.dart';

/// Two-step password reset:
/// 1) email -> backend sends OTP to the account's registered mobile number
/// 2) OTP + new password -> password reset, user is logged in
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});
  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _formKey      = GlobalKey<FormState>();
  final _emailCtrl    = TextEditingController();
  final _otpCtrl      = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _confirmCtrl  = TextEditingController();
  bool _isLoading = false;
  bool _otpSent   = false;
  bool _obscure   = true;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _otpCtrl.dispose();
    _passwordCtrl.dispose();
    _confirmCtrl.dispose();
    super.dispose();
  }

  void _showError(Object e) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(e.toString().replaceFirst('Exception: ', '')),
      backgroundColor: AppColors.error,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      margin: const EdgeInsets.all(16),
    ));
  }

  Future<void> _sendOtp() async {
    // On resend (_otpSent) the email was already validated; the step-2
    // fields may legitimately still be empty.
    if (!_otpSent && !_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      await ref.read(authProvider.notifier).forgotPassword(email: _emailCtrl.text.trim());
      if (mounted) {
        final devOtp = ref.read(authProvider).devOtp;
        if (devOtp != null && devOtp.length == 4) _otpCtrl.text = devOtp;
        setState(() => _otpSent = true);
      }
    } catch (e) {
      if (mounted) _showError(e);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _resetPassword() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      await ref.read(authProvider.notifier).resetPassword(
        email:    _emailCtrl.text.trim(),
        otp:      _otpCtrl.text.trim(),
        password: _passwordCtrl.text,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Text('Password reset successful!'),
          backgroundColor: AppColors.secondary,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ));
        Navigator.pushNamedAndRemoveUntil(context, '/home', (route) => false);
      }
    } catch (e) {
      if (mounted) _showError(e);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Forgot Password')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(_otpSent ? 'Verify & Reset' : 'Reset Password', style: AppTextStyles.h2),
              const SizedBox(height: 8),
              Text(
                _otpSent
                    ? 'Enter the OTP sent to your registered mobile number and choose a new password.'
                    : 'Enter your account email. We will send an OTP to your registered mobile number.',
                style: AppTextStyles.body2,
              ),
              const SizedBox(height: 32),
              const Text('Email Address', style: AppTextStyles.label),
              const SizedBox(height: 8),
              TextFormField(
                controller: _emailCtrl,
                enabled: !_otpSent,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(hintText: 'Enter your email', prefixIcon: Icon(Icons.email_outlined, color: AppColors.textHint)),
                validator: (v) {
                  if (v?.isEmpty == true) return 'Email is required';
                  if (!v!.contains('@')) return 'Enter a valid email';
                  return null;
                },
              ),
              if (_otpSent) ...[
                const SizedBox(height: 20),
                const Text('OTP', style: AppTextStyles.label),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _otpCtrl,
                  keyboardType: TextInputType.number,
                  maxLength: 4,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(hintText: '4-digit OTP', counterText: '', prefixIcon: Icon(Icons.pin_outlined, color: AppColors.textHint)),
                  validator: (v) => (v == null || v.length != 4) ? 'Enter the 4-digit OTP' : null,
                ),
                const SizedBox(height: 20),
                const Text('New Password', style: AppTextStyles.label),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _passwordCtrl,
                  obscureText: _obscure,
                  decoration: InputDecoration(
                    hintText: 'Minimum 6 characters',
                    prefixIcon: const Icon(Icons.lock_outline, color: AppColors.textHint),
                    suffixIcon: IconButton(
                      icon: Icon(_obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined, color: AppColors.textHint),
                      onPressed: () => setState(() => _obscure = !_obscure),
                    ),
                  ),
                  validator: (v) => (v == null || v.length < 6) ? 'Password must be at least 6 characters' : null,
                ),
                const SizedBox(height: 20),
                const Text('Confirm Password', style: AppTextStyles.label),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _confirmCtrl,
                  obscureText: _obscure,
                  decoration: const InputDecoration(hintText: 'Re-enter new password', prefixIcon: Icon(Icons.lock_outline, color: AppColors.textHint)),
                  validator: (v) => (v != _passwordCtrl.text) ? 'Passwords do not match' : null,
                ),
              ],
              const SizedBox(height: 32),
              AppButton(
                label: _otpSent ? 'Reset Password' : 'Send OTP',
                onPressed: _otpSent ? _resetPassword : _sendOtp,
                isLoading: _isLoading,
                icon: _otpSent ? Icons.lock_reset_rounded : Icons.send_rounded,
              ),
              if (_otpSent) ...[
                const SizedBox(height: 16),
                Center(
                  child: TextButton(
                    onPressed: _isLoading ? null : _sendOtp,
                    child: const Text('Resend OTP', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w600)),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
