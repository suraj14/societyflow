import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Where the app keeps the signed-in session.
///
/// The bearer token goes through [FlutterSecureStorage] (Android Keystore /
/// iOS Keychain), not SharedPreferences. SharedPreferences on Android is a
/// plaintext XML file in the app's private storage: readable with root, and
/// - before `android:allowBackup="false"` was set - extractable through
/// `adb backup` without it. A copy of a visitor's or guard's session token
/// is exactly what a gate app must not hand over that way.
///
/// Everything else (the cached user profile, shown for the splash screen
/// before the first API round trip) is not a credential, so it stays in
/// SharedPreferences.
class StorageService {
  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'user_data';

  // The package's default options are already Keystore/Keychain-backed
  // AES-GCM with no biometric prompt, which is exactly what a bearer token
  // needs here: encrypted at rest, read without extra user interaction.
  static const FlutterSecureStorage _secure = FlutterSecureStorage();

  static Future<void> saveToken(String token) =>
      _secure.write(key: _tokenKey, value: token);

  static Future<String?> getToken() => _secure.read(key: _tokenKey);

  static Future<void> clearToken() => _secure.delete(key: _tokenKey);

  static Future<void> saveUser(Map<String, dynamic> user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_userKey, jsonEncode(user));
  }

  static Future<Map<String, dynamic>?> getUser() async {
    final prefs = await SharedPreferences.getInstance();
    final data = prefs.getString(_userKey);
    if (data == null) return null;
    return jsonDecode(data) as Map<String, dynamic>;
  }

  /// Full local sign-out: the token, the cached profile, and anything else
  /// SharedPreferences is holding for this install.
  static Future<void> clearAll() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    await _secure.delete(key: _tokenKey);
  }

  static Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }
}
