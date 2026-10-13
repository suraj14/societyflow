import 'dart:async';
import 'package:flutter/foundation.dart' show kReleaseMode;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../providers/auth_provider.dart';
import 'choose_account_screen.dart';

/// Enter the SMS code.
///
/// One real text field holds the whole code and is drawn as four boxes.
/// That gives SMS-code autofill (oneTimeCode), paste of the full code and
/// normal backspace for free - four separate one-character fields could do
/// none of these reliably (backspace in an empty box never fired onChanged).
/// The page scrolls and pads for the keyboard, so nothing overflows or hides
/// behind it on small phones or with large system fonts.
class OtpVerifyScreen extends ConsumerStatefulWidget {
  final String phone;
  final String? devOtp; // shown/pre-filled only when the server is in dev mode

  const OtpVerifyScreen({super.key, required this.phone, this.devOtp});

  static const int codeLength = 4;
  static const int resendSeconds = 30;

  @override
  ConsumerState<OtpVerifyScreen> createState() => _OtpVerifyScreenState();
}

class _OtpVerifyScreenState extends ConsumerState<OtpVerifyScreen> {
  final _controller = TextEditingController();
  final _focusNode = FocusNode();
  bool _isLoading = false;
  bool _isResending = false;
  String? _error;
  String? _devOtp;
  int _resendCountdown = OtpVerifyScreen.resendSeconds;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _devOtp = kReleaseMode ? null : widget.devOtp;
    _startCountdown();
    if (_devOtp != null && _devOtp!.length == OtpVerifyScreen.codeLength) {
      _controller.text = _devOtp!;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _focusNode.requestFocus());
  }

  void _startCountdown() {
    _timer?.cancel();
    setState(() => _resendCountdown = OtpVerifyScreen.resendSeconds);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return t.cancel();
      if (_resendCountdown <= 1) {
        t.cancel();
        setState(() => _resendCountdown = 0);
      } else {
        setState(() => _resendCountdown--);
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  String get _code => _controller.text;

  void _onChanged(String value) {
    if (_error != null) setState(() => _error = null);
    setState(() {});
    if (value.length == OtpVerifyScreen.codeLength) _verify();
  }

  Future<void> _verify() async {
    if (_isLoading) return;
    if (_code.length < OtpVerifyScreen.codeLength) {
      setState(() => _error = 'Enter the ${OtpVerifyScreen.codeLength}-digit code from the SMS.');
      return;
    }
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).verifyOtp(phone: widget.phone, otp: _code);
      if (!mounted) return;
      if (ref.read(authProvider).accountChoice != null) {
        // One number, several accounts. The code is spent, so the chooser
        // replaces this screen; back from it returns to request a new code.
        Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const ChooseAccountScreen()));
        return;
      }
      TextInput.finishAutofillContext();
      Navigator.of(context).pushNamedAndRemoveUntil('/home', (_) => false);
    } catch (e) {
      if (!mounted) return;
      _controller.clear();
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      _focusNode.requestFocus();
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _resend() async {
    setState(() {
      _isResending = true;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).sendOtp(phone: widget.phone);
      if (!mounted) return;
      _controller.clear();
      _devOtp = ref.read(authProvider).devOtp;
      if (_devOtp != null && _devOtp!.length == OtpVerifyScreen.codeLength) _controller.text = _devOtp!;
      _startCountdown();
      _focusNode.requestFocus();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('A new code has been sent.')));
    } catch (e) {
      if (mounted) setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _isResending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      resizeToAvoidBottomInset: true,
      appBar: AppBar(title: const Text('Verify OTP')),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight - 40),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(gradient: AppColors.primaryGradient, borderRadius: BorderRadius.circular(22)),
                      child: const Icon(Icons.sms_rounded, color: Colors.white, size: 32),
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text('Enter verification code', style: AppTextStyles.h2, textAlign: TextAlign.center),
                  const SizedBox(height: 8),
                  Text.rich(
                    TextSpan(
                      style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary),
                      children: [
                        const TextSpan(text: 'We sent a ${OtpVerifyScreen.codeLength}-digit code to\n'),
                        TextSpan(
                          text: '+91 ${widget.phone}',
                          style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 28),
                  _OtpBoxes(
                    controller: _controller,
                    focusNode: _focusNode,
                    length: OtpVerifyScreen.codeLength,
                    hasError: _error != null,
                    enabled: !_isLoading,
                    onChanged: _onChanged,
                  ),
                  AnimatedSize(
                    duration: const Duration(milliseconds: 150),
                    child: _error == null
                        ? const SizedBox(height: 12)
                        : Padding(
                            padding: const EdgeInsets.only(top: 12),
                            child: Text(
                              _error!,
                              key: const Key('otp-error'),
                              textAlign: TextAlign.center,
                              style: AppTextStyles.body2.copyWith(color: AppColors.error),
                            ),
                          ),
                  ),
                  const SizedBox(height: 20),
                  AppButton(label: 'Verify', onPressed: _verify, isLoading: _isLoading, icon: Icons.verified_rounded),
                  const SizedBox(height: 20),
                  Center(
                    child: _resendCountdown > 0
                        ? Text(
                            'Resend code in ${_resendCountdown}s',
                            key: const Key('otp-countdown'),
                            style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary),
                          )
                        : _isResending
                            ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))
                            : TextButton(onPressed: _resend, child: const Text('Resend code')),
                  ),
                  if (_devOtp != null) ...[
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.warning.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.warning.withValues(alpha: 0.3)),
                      ),
                      child: Text(
                        'Development server: code $_devOtp',
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: AppColors.warning, fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Four boxes drawn over one invisible text field. Tapping anywhere focuses it.
class _OtpBoxes extends StatelessWidget {
  final TextEditingController controller;
  final FocusNode focusNode;
  final int length;
  final bool hasError;
  final bool enabled;
  final ValueChanged<String> onChanged;

  const _OtpBoxes({
    required this.controller,
    required this.focusNode,
    required this.length,
    required this.hasError,
    required this.enabled,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => focusNode.requestFocus(),
      child: Stack(
        alignment: Alignment.center,
        children: [
          // The real input: full width, transparent, so taps, long-press
          // paste and the keyboard's suggested SMS code all reach it.
          Opacity(
            opacity: 0,
            child: TextField(
              key: const Key('otp-input'),
              controller: controller,
              focusNode: focusNode,
              enabled: enabled,
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.done,
              autofillHints: const [AutofillHints.oneTimeCode],
              maxLength: length,
              showCursor: false,
              enableInteractiveSelection: true,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(length)],
              decoration: const InputDecoration(counterText: '', border: InputBorder.none),
              onChanged: onChanged,
            ),
          ),
          IgnorePointer(
            child: ListenableBuilder(
              listenable: Listenable.merge([controller, focusNode]),
              builder: (context, _) {
                final text = controller.text;
                return Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(length, (i) {
                    final filled = i < text.length;
                    final active = focusNode.hasFocus && (i == text.length || (i == length - 1 && text.length == length));
                    final color = hasError ? AppColors.error : (active || filled ? AppColors.primary : AppColors.divider);
                    return Flexible(
                      child: Container(
                        constraints: const BoxConstraints(maxWidth: 60),
                        margin: const EdgeInsets.symmetric(horizontal: 6),
                        child: AspectRatio(
                          aspectRatio: 0.92,
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 120),
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: filled ? AppColors.primary.withValues(alpha: 0.08) : AppColors.surface,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: color, width: active || filled || hasError ? 2 : 1),
                            ),
                            child: Text(
                              filled ? text[i] : '',
                              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                            ),
                          ),
                        ),
                      ),
                    );
                  }),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
