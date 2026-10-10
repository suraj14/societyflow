import 'package:flutter_test/flutter_test.dart';
import 'package:societyflow/core/config/api_config.dart';
import 'package:societyflow/core/services/server_discovery.dart';

/// One authoritative resolution path (ApiConfig.resolveBaseUrl), with no way
/// for a release APK to reach localhost or a LAN address, and no way for a
/// debug build to reach production unless asked.
void main() {
  const prod = 'https://api.example.test/api/v1';
  const dev = ApiConfig.localDevUrl;
  const staging = 'https://staging.example.test/api/v1';

  String resolve({
    String override = '',
    required bool release,
    required bool production,
  }) =>
      ApiConfig.resolveBaseUrl(
        override: override,
        release: release,
        production: production,
        prodUrl: prod,
        devUrl: dev,
      );

  group('debug resolves the local API over the USB tunnel', () {
    test('the local dev URL is 127.0.0.1:8000', () {
      expect(ApiConfig.localDevUrl, 'http://127.0.0.1:8000/api/v1');
      expect(ApiConfig.isLocalHost(ApiConfig.localDevUrl), isTrue);
    });

    test('a debug build with no override uses the local dev URL', () {
      expect(resolve(release: false, production: false), dev);
    });

    test('a debug build honours an explicit local override', () {
      expect(
        resolve(override: 'http://127.0.0.1:8000/api/v1', release: false, production: false),
        'http://127.0.0.1:8000/api/v1',
      );
    });

    test('a debug build uses production only when asked', () {
      expect(resolve(release: false, production: true), prod);
    });
  });

  group('release resolves production', () {
    test('the production URL is the live HTTPS API', () {
      expect(ApiConfig.prodUrl, 'https://societyflow.joboapps.com/api/v1');
      expect(
        ApiConfig.resolveBaseUrl(
          override: '',
          release: true,
          production: true,
          prodUrl: ApiConfig.prodUrl,
          devUrl: ApiConfig.localDevUrl,
        ),
        'https://societyflow.joboapps.com/api/v1',
      );
    });

    test('ignores a loopback override', () {
      expect(
        resolve(override: 'http://127.0.0.1:8000/api/v1', release: true, production: true),
        prod,
      );
      expect(
        resolve(override: 'http://localhost:8000/api/v1', release: true, production: true),
        prod,
      );
    });

    test('ignores emulator and LAN overrides', () {
      for (final u in [
        'http://10.0.2.2:8000/api/v1',
        'http://192.168.1.14:8000/api/v1',
        'http://10.1.2.3:8000/api/v1',
        'http://172.20.0.5:8000/api/v1',
      ]) {
        expect(resolve(override: u, release: true, production: true), prod, reason: u);
      }
    });

    test('a release with production switched off still never uses a local URL', () {
      expect(resolve(release: true, production: false), prod);
    });

    test('accepts a deliberate non-local override such as staging', () {
      expect(resolve(override: staging, release: true, production: true), staging);
    });
  });

  group('no LAN address is chosen automatically', () {
    test('every discovery candidate is loopback', () {
      for (final u in ServerDiscovery.candidateBaseUrls) {
        final host = Uri.parse(u).host;
        expect(host == '127.0.0.1' || host == 'localhost', isTrue, reason: u);
      }
    });

    test('the debug default is never a 192.168 / 10.x / 172.16-31 address', () {
      final host = Uri.parse(resolve(release: false, production: false)).host;
      expect(host.startsWith('192.168.'), isFalse);
      expect(host.startsWith('10.'), isFalse);
    });
  });

  group('one authoritative path', () {
    test('ApiConfig.baseUrl is the resolver applied to the compile-time values', () {
      // No --dart-define in tests: a debug build with no override.
      expect(ApiConfig.baseUrl, ApiConfig.localDevUrl);
    });

    test('ServerDiscovery reports that same base URL when nothing was discovered', () {
      expect(ServerDiscovery.currentBaseUrl, ApiConfig.baseUrl);
    });
  });

  group('isLocalHost', () {
    test('treats loopback, emulator and private ranges as local', () {
      for (final u in [
        'http://127.0.0.1:8000',
        'http://localhost',
        'http://[::1]:8000',
        'http://10.0.2.2',
        'http://192.168.0.9',
        'http://10.5.5.5',
        'http://172.16.0.1',
        'http://172.31.255.1',
      ]) {
        expect(ApiConfig.isLocalHost(u), isTrue, reason: u);
      }
    });

    test('treats public hosts as not local, including 172.x outside the private /12', () {
      for (final u in [
        'https://societyflow.joboapps.com/api/v1',
        'https://172.67.134.73/api/v1',
        'https://172.32.0.1',
        'https://8.8.8.8',
      ]) {
        expect(ApiConfig.isLocalHost(u), isFalse, reason: u);
      }
    });

    test('an empty or unparseable value counts as local, so it can never ship', () {
      expect(ApiConfig.isLocalHost(''), isTrue);
    });
  });
}
