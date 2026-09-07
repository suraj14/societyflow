import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../providers/auth_provider.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});
  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  // Step 1: verify account
  final _step1Key    = GlobalKey<FormState>();
  final _emailCtrl   = TextEditingController();
  final _phoneCtrl   = TextEditingController();

  // Step 2: set password
  final _step2Key    = GlobalKey<FormState>();
  final _passwordCtrl  = TextEditingController();
  final _confirmCtrl   = TextEditingController();

  int _step = 1;
  bool _obscure   = true;
  bool _isLoading = false;
  Map<String, dynamic>? _accountInfo;

  @override
  void dispose() {
    _emailCtrl.dispose(); _phoneCtrl.dispose();
    _passwordCtrl.dispose(); _confirmCtrl.dispose();
    super.dispose();
  }

  Future<void> _checkAccount() async {
    if (!_step1Key.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      final info = await ref.read(authProvider.notifier).checkAccount(
        email: _emailCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
      );
      setState(() { _accountInfo = info; _step = 2; });
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error, behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), margin: const EdgeInsets.all(16)),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _register() async {
    if (!_step2Key.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      await ref.read(authProvider.notifier).register(
        email: _emailCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        password: _passwordCtrl.text,
      );
      if (mounted) Navigator.pushReplacementNamed(context, '/home');
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(_step == 1 ? 'Activate Account' : 'Set Password'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_rounded),
          onPressed: () => _step == 2 ? setState(() => _step = 1) : Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: _step == 1 ? _buildStep1() : _buildStep2(),
        ),
      ),
    );
  }

  Widget _buildStep1() {
    return Form(
      key: _step1Key,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Header
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.08), borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.primary.withOpacity(0.2))),
          child: Row(children: [
            const Icon(Icons.info_outline_rounded, color: AppColors.primary, size: 20),
            const SizedBox(width: 12),
            const Expanded(child: Text('Your account is created by your society admin. Enter your registered email and phone to activate it.', style: TextStyle(color: AppColors.primary, fontSize: 13, height: 1.4))),
          ]),
        ),
        const SizedBox(height: 28),
        const Text('Registered Email', style: AppTextStyles.label),
        const SizedBox(height: 8),
        TextFormField(
          controller: _emailCtrl,
          keyboardType: TextInputType.emailAddress,
          decoration: const InputDecoration(hintText: 'Enter your registered email', prefixIcon: Icon(Icons.email_outlined, color: AppColors.textHint)),
          validator: (v) { if (v?.isEmpty == true) return 'Email is required'; if (!v!.contains('@')) return 'Enter a valid email'; return null; },
        ),
        const SizedBox(height: 16),
        const Text('Registered Phone Number', style: AppTextStyles.label),
        const SizedBox(height: 8),
        TextFormField(
          controller: _phoneCtrl,
          keyboardType: TextInputType.phone,
          decoration: const InputDecoration(hintText: 'Enter your registered phone number', prefixIcon: Icon(Icons.phone_outlined, color: AppColors.textHint)),
          validator: (v) => v?.isEmpty == true ? 'Phone number is required' : null,
        ),
        const SizedBox(height: 32),
        AppButton(label: 'Find My Account', onPressed: _checkAccount, isLoading: _isLoading, icon: Icons.search_rounded),
        const SizedBox(height: 20),
        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          const Text('Already have an account? ', style: AppTextStyles.body2),
          GestureDetector(onTap: () => Navigator.pop(context), child: const Text('Sign In', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 14))),
        ]),
      ]),
    );
  }

  Widget _buildStep2() {
    return Form(
      key: _step2Key,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Account info card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: AppColors.secondary.withOpacity(0.08), borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.secondary.withOpacity(0.3))),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              const Icon(Icons.check_circle_rounded, color: AppColors.secondary, size: 20),
              const SizedBox(width: 8),
              const Text('Account Found!', style: TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w700, fontSize: 14)),
            ]),
            const SizedBox(height: 10),
            _infoRow(Icons.person_rounded, 'Name', _accountInfo?['name'] ?? ''),
            const SizedBox(height: 6),
            _infoRow(Icons.home_rounded, 'Unit', _accountInfo?['flat_number'] != null ? '${_accountInfo?['building_name'] ?? ''} - Flat ${_accountInfo?['flat_number']}' : 'N/A'),
            const SizedBox(height: 6),
            _infoRow(Icons.apartment_rounded, 'Society', _accountInfo?['society_name'] ?? 'N/A'),
            const SizedBox(height: 6),
            _infoRow(Icons.badge_rounded, 'Role', _accountInfo?['role'] ?? 'Resident'),
          ]),
        ),
        const SizedBox(height: 28),
        const Text('Create Password', style: AppTextStyles.h3),
        const SizedBox(height: 4),
        const Text('Set a secure password for your account', style: AppTextStyles.body2),
        const SizedBox(height: 24),
        const Text('New Password', style: AppTextStyles.label),
        const SizedBox(height: 8),
        TextFormField(
          controller: _passwordCtrl,
          obscureText: _obscure,
          decoration: InputDecoration(
            hintText: 'Create a password (min 6 characters)',
            prefixIcon: const Icon(Icons.lock_outline_rounded, color: AppColors.textHint),
            suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined, color: AppColors.textHint), onPressed: () => setState(() => _obscure = !_obscure)),
          ),
          validator: (v) { if (v?.isEmpty == true) return 'Password is required'; if (v!.length < 6) return 'Min 6 characters'; return null; },
        ),
        const SizedBox(height: 16),
        const Text('Confirm Password', style: AppTextStyles.label),
        const SizedBox(height: 8),
        TextFormField(
          controller: _confirmCtrl,
          obscureText: _obscure,
          decoration: const InputDecoration(hintText: 'Re-enter your password', prefixIcon: Icon(Icons.lock_outline_rounded, color: AppColors.textHint)),
          validator: (v) { if (v?.isEmpty == true) return 'Please confirm your password'; if (v != _passwordCtrl.text) return 'Passwords do not match'; return null; },
        ),
        const SizedBox(height: 32),
        AppButton(label: 'Activate Account', onPressed: _register, isLoading: _isLoading, icon: Icons.check_circle_rounded),
      ]),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Row(children: [
      Icon(icon, size: 14, color: AppColors.textSecondary),
      const SizedBox(width: 6),
      Text('$label: ', style: AppTextStyles.caption),
      Expanded(child: Text(value, style: AppTextStyles.label.copyWith(fontSize: 12), overflow: TextOverflow.ellipsis)),
    ]);
  }
}
