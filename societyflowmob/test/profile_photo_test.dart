import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/features/auth/models/user_model.dart';
import 'package:societyflow/features/auth/providers/auth_provider.dart';
import 'package:societyflow/features/profile/models/profile_photo.dart';
import 'package:societyflow/features/profile/screens/edit_profile_screen.dart';

/// Profile photo editing on the phone: the rules a photo must pass before it
/// is uploaded, and the controls the Edit Profile screen offers. (The upload
/// itself, the server limits and the per-role behaviour are covered by
/// tests/Feature/ProfileImageTest.php on the backend.)
void main() {
  group('ProfilePhotoRules.validate', () {
    test('accepts JPG, JPEG, PNG and GIF of ordinary size, any letter case', () {
      for (final name in ['/a/b/photo.jpg', 'C:\\x\\photo.JPEG', 'me.Png', 'anim.gif']) {
        expect(ProfilePhotoRules.validate(path: name, sizeBytes: 500 * 1024), isNull, reason: name);
      }
    });

    test('accepts exactly the 2 MB limit and rejects one byte over it', () {
      expect(ProfilePhotoRules.validate(path: 'a.png', sizeBytes: ProfilePhotoRules.maxBytes), isNull);
      final over = ProfilePhotoRules.validate(path: 'a.png', sizeBytes: ProfilePhotoRules.maxBytes + 1);
      expect(over, isNotNull);
      expect(over, contains('2 MB'));
    });

    test('rejects other file types and files with no extension', () {
      for (final name in ['cv.pdf', 'clip.mp4', 'notes.txt', 'photo.heic', 'photo', 'photo.jpg.exe']) {
        expect(ProfilePhotoRules.validate(path: name, sizeBytes: 1024), contains('JPG, PNG or GIF'), reason: name);
      }
    });

    test('rejects an empty file', () {
      expect(ProfilePhotoRules.validate(path: 'a.png', sizeBytes: 0), contains('could not be read'));
    });

    test('mirrors the server limit', () {
      // App\Support\ProfileImage::MAX_KB is 2048.
      expect(ProfilePhotoRules.maxBytes, 2048 * 1024);
    });
  });

  group('ProfilePhotoRules.progress', () {
    test('is a 0..1 fraction, clamped, and indeterminate until the size is known', () {
      expect(ProfilePhotoRules.progress(0, 100), 0.0);
      expect(ProfilePhotoRules.progress(50, 100), 0.5);
      expect(ProfilePhotoRules.progress(100, 100), 1.0);
      expect(ProfilePhotoRules.progress(150, 100), 1.0);
      expect(ProfilePhotoRules.progress(10, 0), isNull);
      expect(ProfilePhotoRules.progress(10, -1), isNull);
    });
  });

  group('EditProfileScreen photo controls', () {
    const noPhoto = UserModel(
      id: 7,
      name: 'Asha Rao',
      email: 'asha@example.com',
      phone: '9876543210',
      role: 'Tenant',
      status: 'active',
    );

    Widget app(UserModel user) => ProviderScope(
          overrides: [currentUserProvider.overrideWithValue(user)],
          child: const MaterialApp(home: EditProfileScreen()),
        );

    testWidgets('a user with no photo is offered "Add photo" and cannot see a remove option', (tester) async {
      await tester.pumpWidget(app(noPhoto));
      await tester.pumpAndSettle();

      expect(find.text('Add photo'), findsOneWidget);
      expect(find.text('Change photo'), findsNothing);
      // Existing form still prefilled.
      expect(find.widgetWithText(TextFormField, 'Asha Rao'), findsOneWidget);
      expect(find.widgetWithText(TextFormField, '9876543210'), findsOneWidget);

      await tester.tap(find.text('Add photo'));
      await tester.pumpAndSettle();

      expect(find.text('Choose from gallery'), findsOneWidget);
      expect(find.text('Take a photo'), findsOneWidget);
      expect(find.text('Remove photo'), findsNothing);
    });

    testWidgets('the avatar itself is a labelled button for screen readers', (tester) async {
      final handle = tester.ensureSemantics();
      await tester.pumpWidget(app(noPhoto));
      await tester.pumpAndSettle();

      expect(find.bySemanticsLabel('Profile photo. Double tap to change it.'), findsOneWidget);
      handle.dispose();
    });
  });
}
