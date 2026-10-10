import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:flutter_secure_storage/test/test_flutter_secure_storage_platform.dart';
import 'package:societyflow/core/utils/storage_service.dart';

/// The session token goes through secure storage, not SharedPreferences.
/// `secureData` below is the fake Keystore/Keychain backing store: it
/// stands in for the plugin's platform channel, which does not exist under
/// `flutter test`.
void main() {
  late Map<String, String> secureData;

  setUp(() {
    secureData = {};
    FlutterSecureStoragePlatform.instance =
        TestFlutterSecureStoragePlatform(secureData);
    SharedPreferences.setMockInitialValues({});
  });

  test('the token is written to secure storage, not SharedPreferences', () async {
    await StorageService.saveToken('sf-test-token-123');

    expect(secureData['auth_token'], 'sf-test-token-123');

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('auth_token'), isNull);
  });

  test('getToken reads back what was saved', () async {
    await StorageService.saveToken('round-trip-token');
    expect(await StorageService.getToken(), 'round-trip-token');
  });

  test('isLoggedIn reflects whether a token is present', () async {
    expect(await StorageService.isLoggedIn(), isFalse);
    await StorageService.saveToken('a-token');
    expect(await StorageService.isLoggedIn(), isTrue);
  });

  test('the user profile stays in SharedPreferences, not secure storage', () async {
    await StorageService.saveUser({'id': 1, 'name': 'Test Resident'});

    expect(secureData.containsKey('user_data'), isFalse);
    final user = await StorageService.getUser();
    expect(user?['name'], 'Test Resident');
  });

  test('clearAll removes the token and the cached profile', () async {
    await StorageService.saveToken('a-token');
    await StorageService.saveUser({'id': 1});

    await StorageService.clearAll();

    expect(await StorageService.getToken(), isNull);
    expect(await StorageService.getUser(), isNull);
    expect(await StorageService.isLoggedIn(), isFalse);
  });

  test('clearToken removes only the token, not the cached profile', () async {
    await StorageService.saveToken('a-token');
    await StorageService.saveUser({'id': 1, 'name': 'Still Here'});

    await StorageService.clearToken();

    expect(await StorageService.getToken(), isNull);
    expect((await StorageService.getUser())?['name'], 'Still Here');
  });
}
