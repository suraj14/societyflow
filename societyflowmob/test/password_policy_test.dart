import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/core/security/password_policy.dart';
import 'package:societyflow/shared/widgets/password_strength_meter.dart';

/// The app's password rules must be the server's rules. The vectors below are the ones the backend's PasswordPolicyTest
/// asserts (same passwords, same verdicts), so a rule changed on one side and not the other fails here or there.
void main() {
  // What `GET /api/v1/password-policy` answers for a society that switched everything on (PasswordPolicy::describe()).
  const strictJson = {
    'min': 10,
    'max': 64,
    'uppercase': true,
    'number': true,
    'special': true,
    'requirements': [],
    'common': ['password', 'password123', '12345678'],
  };

  group('baseline (the default policy)', () {
    const p = PasswordPolicy.baseline;

    test('is the server baseline: 8 to 64 characters, nothing else required', () {
      expect([p.min, p.max, p.uppercase, p.number, p.special], [8, 64, false, false, false]);
    });

    // the same table as PasswordPolicyTest::passwords()
    final vectors = <String, bool>{
      'correct horse battery': true,
      'abcdefgh': true,
      'abcdefg': false,
      '': false,
      'password123': false,
      'PassWord': false,
      '12345678': false,
      'pässwörd-ñandú': true,
    };
    vectors.forEach((password, ok) {
      test('"$password" is ${ok ? 'accepted' : 'refused'}', () {
        expect(p.isAcceptable(password), ok);
        expect(p.validate(password) == null, ok);
      });
    });

    test('the ceiling is enforced and the exact ceiling is fine', () {
      expect(p.isAcceptable('a' * 65), isFalse);
      expect(p.isAcceptable('a1' * 32), isTrue);
    });

    test('six characters are no longer enough', () {
      expect(p.validate('abc123'), 'Use at least 8 characters');
    });
  });

  group('a strict society policy', () {
    final p = PasswordPolicy.fromJson(strictJson);

    final vectors = <String, bool>{
      'Sunrise-Villa-159': true,
      'Sun-1ise': false,
      'sunrise-villa-159': false,
      'Sunrise-Villa-One': false,
      'SunriseVilla159': false,
      'SunriseVilla159€': true,
    };
    vectors.forEach((password, ok) {
      test('"$password" is ${ok ? 'accepted' : 'refused'}', () => expect(p.isAcceptable(password), ok));
    });

    test('the first problem is named in a plain sentence', () {
      expect(p.validate('Sunrise-Villa-One'), 'Include a number');
      expect(p.validate('SunriseVilla159'), 'Include a special character such as ! @ # \$ %');
      expect(p.validate('sunrise-villa-159'), 'Include an uppercase letter');
      expect(p.validate('Sun-1ise'), 'Use at least 10 characters');
    });

    test('the checklist lists exactly the active requirements', () {
      expect(p.requirements.map((r) => r.key), ['min', 'uppercase', 'number', 'special']);
      expect(p.requirements.first.label, 'At least 10 characters');
    });
  });

  group('reading the server answer', () {
    test('a minimum below the baseline is lifted to the baseline, never trusted', () {
      expect(PasswordPolicy.fromJson({'min': 6, 'max': 64}).min, 8);
      expect(PasswordPolicy.fromJson({'min': '12'}).min, 12);
    });

    test('a missing or odd answer falls back to safe values', () {
      final p = PasswordPolicy.fromJson({});
      expect([p.min, p.uppercase, p.number, p.special], [8, false, false, false]);
      expect(p.common, isNotEmpty);
    });
  });

  group('strength level', () {
    const p = PasswordPolicy.baseline;

    test('0 empty, 1 too weak, 2 fair, 3 good, 4 strong', () {
      expect(p.level(''), 0);
      expect(p.level('short'), 1);
      expect(p.level('password123'), 1, reason: 'well-known passwords are never better than too weak');
      expect(p.level('abcdefgh'), 2);
      expect(p.level('Abcdefgh12'), 3);
      expect(p.level('Abcdefgh12!xyz9'), 4);
    });
  });

  group('PasswordStrengthMeter', () {
    Widget host(String pw, PasswordPolicy policy) => MaterialApp(home: Scaffold(body: PasswordStrengthMeter(password: pw, policy: policy)));

    testWidgets('shows nothing alarming before anything is typed', (tester) async {
      await tester.pumpWidget(host('', PasswordPolicy.baseline));

      expect(find.text('Too weak'), findsNothing);
      expect(find.byKey(const ValueKey('requirement-min')), findsOneWidget);
      expect(find.text('At least 8 characters'), findsOneWidget);
    });

    testWidgets('says too weak, then fair, as the policy is met', (tester) async {
      await tester.pumpWidget(host('abc', PasswordPolicy.baseline));
      expect(find.text('Too weak'), findsOneWidget);

      await tester.pumpWidget(host('correct horse', PasswordPolicy.baseline));
      expect(find.text('Fair'), findsOneWidget);
      expect(find.text('Too weak'), findsNothing);
    });

    testWidgets('lists every requirement of a strict policy and flags a common password', (tester) async {
      final strict = PasswordPolicy.fromJson(strictJson);
      await tester.pumpWidget(host('password123', strict));

      for (final key in ['min', 'uppercase', 'number', 'special', 'common']) {
        expect(find.byKey(ValueKey('requirement-$key')), findsOneWidget, reason: key);
      }
      expect(find.text('An uppercase letter (A-Z)'), findsOneWidget);
      expect(find.text('Too weak'), findsOneWidget);
    });
  });
}
