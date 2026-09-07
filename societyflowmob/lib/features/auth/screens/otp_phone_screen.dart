import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../providers/auth_provider.dart';
import 'otp_verify_screen.dart';

class OtpPhoneScreen extends ConsumerStatefulWidget {
  const OtpPhoneScreen({super.key});
  @override
  ConsumerState<OtpPhoneScreen> createState() => _OtpPhoneScreenState();
}

class _OtpPhoneScreenState extends ConsumerState<OtpPhoneScreen> {
  final _formKey  = GlobalKey<FormState>();
  final _phoneCtrl = TextEditingController();
  bool _isLoading = false;

  @override
  void dispose() { _phoneCtrl.dispose(); super.dispose(); }

  Future<void> _sendOtp() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      await ref.read(authProvider.notifier).sendOtp(phone: _phoneCtrl.text.trim());
      if (mounted) {
        final devOtp = ref.read(authProvider).devOtp;
        Navigator.push(context, MaterialPageRoute(builder: (_) => OtpVerifyScreen(phone: _phoneCtrl.text.trim(), devOtp: devOtp)));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(e.toString().replaceFirst('Exception: ', '')),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            children: [
              // Header
              Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(24, 48, 24, 40),
                decoration: const BoxDecoration(
                  gradient: AppColors.primaryGradient,
                  borderRadius: BorderRadius.only(bottomLeft: Radius.circular(32), bottomRight: Radius.circular(32)),
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Container(width: 56, height: 56, decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), borderRadius: BorderRadius.circular(16)), child: const Icon(Icons.phone_android_rounded, color: Colors.white, size: 28)),
                  const SizedBox(height: 20),
                  const Text('Login with OTP', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: -0.5)),
                  const SizedBox(height: 6),
                  Text('Enter your registered mobile number', style: TextStyle(fontSize: 15, color: Colors.white.withOpacity(0.8))),
                ]),
              ),

              Padding(
                padding: const EdgeInsets.all(24),
                child: Form(
                  key: _formKey,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const SizedBox(height: 8),
                    const Text('Mobile Number', style: AppTextStyles.label),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _phoneCtrl,
                      keyboardType: TextInputType.phone,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(10)],
                      decoration: const InputDecoration(
                        hintText: 'Enter 10-digit mobile number',
                        prefixIcon: Icon(Icons.phone_outlined, color: AppColors.textHint),
                        prefixText: '+91  ',
                        prefixStyle: TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w500),
                      ),
                      validator: (v) {
                        if (v == null || v.isEmpty) return 'Mobile number is required';
                        if (v.length < 10) return 'Enter a valid 10-digit number';
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.06), borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.primary.withOpacity(0.15))),
                      child: Row(children: [
                        const Icon(Icons.info_outline_rounded, color: AppColors.primary, size: 16),
                        const SizedBox(width: 8),
                        const Expanded(child: Text('Use the mobile number registered with your society admin.', style: TextStyle(color: AppColors.primary, fontSize: 12, height: 1.4))),
                      ]),
                    ),
                    const SizedBox(height: 32),
                    AppButton(label: 'Send OTP', onPressed: _sendOtp, isLoading: _isLoading, icon: Icons.send_rounded),
                    const SizedBox(height: 20),
                    Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      const Text('Login with email instead? ', style: AppTextStyles.body2),
                      GestureDetector(
                        onTap: () => Navigator.pushReplacementNamed(context, '/login'),
                        child: const Text('Email Login', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 14)),
                      ),
                    ]),
                  ]),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
