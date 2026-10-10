import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show PlatformException;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/network_image_widget.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/profile_photo.dart';
import '../providers/profile_provider.dart';

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();

  late final TextEditingController _nameCtrl;
  late final TextEditingController _phoneCtrl;
  final ImagePicker _picker = ImagePicker();

  bool _isLoading = false;

  /// A photo chosen on this screen but not saved yet (local file path).
  String? _pickedPath;

  /// The person asked to clear their current photo.
  bool _removePhoto = false;

  /// 0..1 while the photo uploads, null = not uploading / size unknown.
  double? _progress;

  @override
  void initState() {
    super.initState();

    _nameCtrl = TextEditingController();
    _phoneCtrl = TextEditingController();

    // Safe initialization after widget build
    Future.microtask(() {
      final user = ref.read(currentUserProvider);
      if (user != null) {
        _nameCtrl.text = user.name;
        _phoneCtrl.text = user.phone ?? '';
      }
    });
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  void _toast(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? AppColors.error : AppColors.secondary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
      ),
    );
  }

  Future<void> _pick(ImageSource source) async {
    try {
      final picked = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1600,
        maxHeight: 1600,
      );
      // Closing the picker without choosing is a normal cancel, not an error.
      if (picked == null) return;

      final problem = ProfilePhotoRules.validate(
        path: picked.path,
        sizeBytes: await picked.length(),
      );
      if (problem != null) {
        _toast(problem, error: true);
        return;
      }

      if (mounted) {
        setState(() {
          _pickedPath = picked.path;
          _removePhoto = false;
        });
      }
    } on PlatformException {
      _toast(
        source == ImageSource.camera
            ? 'The camera could not be opened. Check the app has camera permission.'
            : 'Your photos could not be opened. Check the app has photo access.',
        error: true,
      );
    } catch (_) {
      _toast('Something went wrong choosing the photo. Please try again.', error: true);
    }
  }

  void _showPhotoOptions(bool hasPhoto) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () {
                Navigator.pop(sheetContext);
                _pick(ImageSource.gallery);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              onTap: () {
                Navigator.pop(sheetContext);
                _pick(ImageSource.camera);
              },
            ),
            if (hasPhoto)
              ListTile(
                leading: const Icon(Icons.delete_outline_rounded, color: AppColors.error),
                title: const Text('Remove photo', style: TextStyle(color: AppColors.error)),
                onTap: () {
                  Navigator.pop(sheetContext);
                  setState(() {
                    _pickedPath = null;
                    _removePhoto = true;
                  });
                },
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _save() async {
    // One submission at a time: a second tap while the first is uploading is ignored.
    if (_isLoading) return;
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _progress = null;
    });

    try {
      await ref.read(profileProvider.notifier).updateProfile(
        name: _nameCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        avatarPath: _pickedPath,
        removeAvatar: _removePhoto,
        onProgress: (sent, total) {
          if (mounted) setState(() => _progress = ProfilePhotoRules.progress(sent, total));
        },
      );

      if (mounted) {
        _toast('Profile updated successfully');
        Navigator.pop(context);
      }
    } catch (e) {
      final message = e.toString().contains('Exception')
          ? e.toString().replaceAll('Exception:', '').trim()
          : 'Something went wrong';

      _toast(message, error: true);
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _progress = null;
        });
      }
    }
  }

  Widget _avatar(String name, String? currentUrl) {
    const size = 96.0;
    if (_pickedPath != null) {
      return ClipOval(
        child: Image.file(File(_pickedPath!), width: size, height: size, fit: BoxFit.cover),
      );
    }
    return NetAvatar(url: _removePhoto ? null : currentUrl, name: name, size: size);
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(currentUserProvider);
    final name = user?.name ?? 'U';
    final hasPhoto = _pickedPath != null || (!_removePhoto && (user?.avatar ?? '').isNotEmpty);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Edit Profile'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              /// Photo
              Center(
                child: Column(
                  children: [
                    Semantics(
                      button: true,
                      label: 'Profile photo. Double tap to change it.',
                      // The initials/picture inside are decorative; without this
                      // a screen reader reads "AR" glued onto the label.
                      excludeSemantics: true,
                      onTap: _isLoading ? null : () => _showPhotoOptions(hasPhoto),
                      child: GestureDetector(
                        onTap: _isLoading ? null : () => _showPhotoOptions(hasPhoto),
                        child: _avatar(name, user?.avatar),
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextButton.icon(
                      onPressed: _isLoading ? null : () => _showPhotoOptions(hasPhoto),
                      icon: const Icon(Icons.camera_alt_outlined, size: 18),
                      label: Text(hasPhoto ? 'Change photo' : 'Add photo'),
                    ),
                    if (_removePhoto)
                      const Text(
                        'Your photo will be removed when you save.',
                        style: TextStyle(color: AppColors.textHint, fontSize: 12),
                      ),
                    if (_isLoading && (_pickedPath != null))
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: SizedBox(
                          width: 160,
                          child: LinearProgressIndicator(value: _progress),
                        ),
                      ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              /// Name
              const Text('Full Name', style: AppTextStyles.label),
              const SizedBox(height: 8),
              TextFormField(
                controller: _nameCtrl,
                autofillHints: const [AutofillHints.name],
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  hintText: 'Enter your full name',
                  prefixIcon: Icon(
                    Icons.person_outline_rounded,
                    color: AppColors.textHint,
                  ),
                ),
                validator: (v) =>
                v == null || v.trim().isEmpty ? 'Name is required' : null,
              ),

              const SizedBox(height: 16),

              /// Phone
              const Text('Phone Number', style: AppTextStyles.label),
              const SizedBox(height: 8),
              TextFormField(
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                autofillHints: const [AutofillHints.telephoneNumber],
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _save(),
                decoration: const InputDecoration(
                  hintText: 'Enter your phone number',
                  prefixIcon: Icon(
                    Icons.phone_outlined,
                    color: AppColors.textHint,
                  ),
                ),
                validator: (v) {
                  if (v == null || v.isEmpty) return 'Phone is required';
                  if (v.length < 10) return 'Enter valid phone number';
                  return null;
                },
              ),

              const SizedBox(height: 32),

              /// Button
              AppButton(
                label: 'Save Changes',
                onPressed: _isLoading ? null : _save,
                isLoading: _isLoading,
                icon: Icons.save_rounded,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
