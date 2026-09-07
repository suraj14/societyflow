import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../providers/auth_provider.dart';

class OtpVerifyScreen extends ConsumerStatefulWidget {
  final String phone;
  final String? devOtp; // pre-filled in dev mode

  const OtpVerifyScreen({super.key, required this.phone, this.devOtp});

  @override
  ConsumerState<OtpVerifyScreen> createState() => _OtpVerifyScreenState();
}

class _OtpVerifyScreenState extends ConsumerState<OtpVerifyScreen> {
  final List<TextEditingController> _controllers = List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _focusNodes = List.generate(4, (_) => FocusNode());
  bool _isLoading = false;
  bool _isResending = false;
  int _resendCountdown = 30;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startCountdown();
    // Pre-fill OTP in dev mode
    if (widget.devOtp != null && widget.devOtp!.length == 4) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        for (int i = 0; i < 4; i++) {
          _controllers[i].text = widget.devOtp![i];
        }
        setState(() {});
      });
    }
  }

  void _startCountdown() {
    _resendCountdown = 30;
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_resendCountdown <= 0) {
        t.cancel();
      } else {
        if (mounted) setState(() => _resendCountdown--);
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    for (final c in _controllers) c.dispose();
    for (final f in _focusNodes) f.dispose();
    super.dispose();
  }

  String get _otp => _controllers.map((c) => c.text).join();

  Future<void> _verify() async {
    if (_otp.length < 4) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter the 4-digit OTP'), backgroundColor: AppColors.error));
      return;
    }
    setState(() => _isLoading = true);
    try {
      await ref.read(authProvider.notifier).verifyOtp(phone: widget.phone, otp: _otp);
      if (mounted) Navigator.pushReplacementNamed(context, '/home');
    } catch (e) {
      if (mounted) {
        // Clear OTP fields on error
        for (final c in _controllers) c.clear();
        _focusNodes[0].requestFocus();
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

  Future<void> _resend() async {
    setState(() => _isResending = true);
    try {
      await ref.read(authProvider.notifier).sendOtp(phone: widget.phone);
      if (mounted) {
        _startCountdown();
        for (final c in _controllers) c.clear();
        _focusNodes[0].requestFocus();
        // Update dev OTP if returned
        final devOtp = ref.read(authProvider).devOtp;
        if (devOtp != null && devOtp.length == 4) {
          for (int i = 0; i < 4; i++) _controllers[i].text = devOtp[i];
        }
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Text('OTP resent successfully'),
          backgroundColor: AppColors.secondary,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString().replaceFirst('Exception: ', '')), backgroundColor: AppColors.error));
    } finally {
      if (mounted) setState(() => _isResending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Verify OTP'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_rounded), onPressed: () => Navigator.pop(context)),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const SizedBox(height: 16),
            // Icon
            Center(
              child: Container(
                width: 80, height: 80,
                decoration: BoxDecoration(gradient: AppColors.primaryGradient, borderRadius: BorderRadius.circular(24)),
                child: const Icon(Icons.sms_rounded, color: Colors.white, size: 36),
              ),
            ),
            const SizedBox(height: 24),
            const Center(child: Text('Enter Verification Code', style: AppTextStyles.h2)),
            const SizedBox(height: 8),
            Center(
              child: RichText(
                textAlign: TextAlign.center,
                text: TextSpan(
                  style: AppTextStyles.body2,
                  children: [
                    const TextSpan(text: 'We sent a 4-digit OTP to\n'),
                    TextSpan(text: '+91 ${widget.phone}', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 15)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 40),

            // OTP input boxes
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(4, (i) => Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: SizedBox(
                  width: 60, height: 64,
                  child: TextFormField(
                    controller: _controllers[i],
                    focusNode: _focusNodes[i],
                    keyboardType: TextInputType.number,
                    textAlign: TextAlign.center,
                    maxLength: 1,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                    decoration: InputDecoration(
                      counterText: '',
                      filled: true,
                      fillColor: _controllers[i].text.isNotEmpty ? AppColors.primary.withOpacity(0.08) : AppColors.background,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: _controllers[i].text.isNotEmpty ? AppColors.primary : AppColors.divider, width: _controllers[i].text.isNotEmpty ? 2 : 1)),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: _controllers[i].text.isNotEmpty ? AppColors.primary : AppColors.divider, width: _controllers[i].text.isNotEmpty ? 2 : 1)),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
                    ),
                    onChanged: (v) {
                      setState(() {});
                      if (v.isNotEmpty && i < 3) {
                        _focusNodes[i + 1].requestFocus();
                      } else if (v.isEmpty && i > 0) {
                        _focusNodes[i - 1].requestFocus();
                      }
                      // Auto-verify when all 4 digits entered
                      if (_otp.length == 4) _verify();
                    },
                  ),
                ),
              )),
            ),

            const SizedBox(height: 32),
            AppButton(label: 'Verify OTP', onPressed: _verify, isLoading: _isLoading, icon: Icons.verified_rounded),
            const SizedBox(height: 24),

            // Resend
            Center(
              child: _resendCountdown > 0
                  ? RichText(
                      text: TextSpan(
                        style: AppTextStyles.body2,
                        children: [
                          const TextSpan(text: 'Resend OTP in '),
                          TextSpan(text: '${_resendCountdown}s', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700)),
                        ],
                      ),
                    )
                  : _isResending
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary))
                      : GestureDetector(
                          onTap: _resend,
                          child: const Text('Resend OTP', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 15, decoration: TextDecoration.underline)),
                        ),
            ),

            // Dev mode hint
            if (widget.devOtp != null) ...[
              const SizedBox(height: 24),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: AppColors.warning.withOpacity(0.1), borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.warning.withOpacity(0.3))),
                child: Row(children: [
                  const Icon(Icons.developer_mode_rounded, color: AppColors.warning, size: 16),
                  const SizedBox(width: 8),
                  Text('DEV MODE — OTP: ${widget.devOtp}', style: const TextStyle(color: AppColors.warning, fontSize: 13, fontWeight: FontWeight.w600)),
                ]),
              ),
            ],
          ]),
        ),
      ),
    );
  }
}
