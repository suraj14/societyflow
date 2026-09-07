import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../providers/profile_provider.dart';

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _formKey     = GlobalKey<FormState>();
  final _currentCtrl = TextEditingController();
  final _newCtrl     = TextEditingController();
  final _confirmCtrl = TextEditingController();
  bool _obscure   = true;
  bool _isLoading = false;

  @override
  void dispose() { _currentCtrl.dispose(); _newCtrl.dispose(); _confirmCtrl.dispose(); super.dispose(); }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      await ref.read(profileProvider.notifier).changePassword(currentPassword: _currentCtrl.text, newPassword: _newCtrl.text);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Password changed successfully'), backgroundColor: AppColors.secondary, behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), margin: const EdgeInsets.all(16)));
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Change Password')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Update your password', style: AppTextStyles.h3),
            const SizedBox(height: 4),
            const Text('Enter your current password and choose a new one', style: AppTextStyles.body2),
            const SizedBox(height: 32),
            _pwField('Current Password', _currentCtrl, validator: (v) => v?.isEmpty == true ? 'Required' : null),
            const SizedBox(height: 16),
            _pwField('New Password', _newCtrl, validator: (v) { if (v?.isEmpty == true) return 'Required'; if (v!.length < 6) return 'Min 6 characters'; return null; }),
            const SizedBox(height: 16),
            _pwField('Confirm New Password', _confirmCtrl, validator: (v) { if (v?.isEmpty == true) return 'Required'; if (v != _newCtrl.text) return 'Passwords do not match'; return null; }),
            const SizedBox(height: 32),
            AppButton(label: 'Change Password', onPressed: _submit, isLoading: _isLoading, icon: Icons.lock_reset_rounded),
          ]),
        ),
      ),
    );
  }

  Widget _pwField(String label, TextEditingController ctrl, {String? Function(String?)? validator}) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: AppTextStyles.label),
      const SizedBox(height: 8),
      TextFormField(
        controller: ctrl,
        obscureText: _obscure,
        decoration: InputDecoration(
          hintText: label,
          prefixIcon: const Icon(Icons.lock_outline_rounded, color: AppColors.textHint),
          suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined, color: AppColors.textHint), onPressed: () => setState(() => _obscure = !_obscure)),
        ),
        validator: validator,
      ),
    ]);
  }
}
