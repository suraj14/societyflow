# SocietyFlow Mobile — Android production readiness

**Verdict: NOT READY** (blockers listed at the end). Last run: 2026-10-03.

## 1. Environment (this machine, Windows 11 Home 25H2)

| Item | Value | How |
|---|---|---|
| Flutter | 3.47.6 stable at `C:\src\flutter` | downloaded from the official release manifest (storage.googleapis.com/flutter_infra_release), user PATH updated |
| Dart | 3.13.5 | bundled |
| Java | Microsoft OpenJDK 17.0.20 (`C:\Program Files\Microsoft\jdk-17.0.20.101-hotspot`), pinned via `flutter config --jdk-dir` | winget `Microsoft.OpenJDK.17`. Android Studio's bundled JBR (Java 25, `G:\android-studio\jbr`) is too new for Gradle 8.14 |
| Android SDK | `G:\tools\android-sdk`: platform-tools 37.0.1, platform android-36, build-tools 36.0.0, emulator 37.2, cmdline-tools 13114758 | official `commandlinetools-win` zip + `sdkmanager`; licenses accepted via `licenses/` files (stdin is closed in this harness) |
| `flutter doctor` | Flutter ✓, Android toolchain ✓ (SDK 36), Chrome ✓, Visual Studio ✗ (irrelevant: desktop only) | |
| Device | AVD `societyflow_pixel` (Pixel 6, Android 15 / API 35, google_apis x86_64, WHPX acceleration) at `G:\tools\avd`, booted headless | `avdmanager` + `emulator -no-window` |
| API URL | `http://10.0.2.2:8000/api/v1` via `--dart-define=API_BASE_URL` against `php artisan serve --host=0.0.0.0 --port=8000` | |

Persisted user environment: `PATH` (+`C:\src\flutter\bin`, platform-tools, cmdline-tools), `ANDROID_HOME`, `JAVA_HOME`, `ANDROID_AVD_HOME`.

## 2. Project facts

- `pubspec.yaml`: Dart `>=3.4.0`, Flutter `>=3.22.0`; Riverpod 2.6, Dio 5.9, mobile_scanner 5.2.3, image_picker 1.2, shared_preferences 2.x.
- Gradle 8.14, AGP 8.11.1, Kotlin 2.2.20, compileSdk/targetSdk from Flutter (36), minSdk from Flutter.
- `applicationId` / namespace `com.example.societyflow` (**P0-7, operator decision**), `version 1.0.0+1`, label "SocietyFlow", launcher icon is still Flutter's default (`@mipmap/ic_launcher`).

## 3. Checks

| Check | Result |
|---|---|
| `flutter pub get` | PASS (5 dependency bumps within constraints) |
| `flutter analyze` | PASS — 4 informational `DropdownButtonFormField value:` deprecations (P3-1, intentional), 0 errors |
| `flutter test` | PASS — 24/24 (`models_test`, `society_config_test`, `widget_test`) |
| Debug APK (`flutter build apk --debug`) | PASS — `app-debug.apk` built, installed and launched on the AVD |
| Release APK | see §6 |
| App bundle | not attempted (no release keystore, see blockers) |

## 4. Configuration strategy (no file edits between environments)

`lib/core/config/api_config.dart`:

- `--dart-define=API_BASE_URL=…` wins over everything (emulator `http://10.0.2.2:8000/api/v1`, LAN phone `http://<PC-IP>:8000/api/v1`, staging).
- No define: debug builds auto-discover the dev server (ADB reverse → 10.0.2.2 → cached → `devFallbackUrl` → subnet scan, each confirmed via `/api/v1/health`).
- Release builds use `prodUrl` (`https://societyflow.joboapps.com/api/v1`) unless `--dart-define=USE_PRODUCTION=false`; they can never carry 127.0.0.1 / localhost / 10.0.2.2.
- Cleartext HTTP is allowed only by the debug manifest (`android/app/src/debug/AndroidManifest.xml`); the main manifest no longer sets `usesCleartextTraffic`.
- Permissions: INTERNET, CAMERA (QR + photos), READ_EXTERNAL_STORAGE `maxSdkVersion=32`. WRITE_EXTERNAL_STORAGE removed.
- Release signing reads `android/key.properties` when present (git-ignored); otherwise the build is debug-signed and prints a warning.

## 5. Backend integration bugs found by this pass (fixed, tested)

- A tenant created from Admin → Tenants had no villa/flat in the mobile profile (`/auth/me`, `/profile` resolved units only via the legacy `residents` row or an owner record). Both formatters now fall back to the active tenancy via `UserFlatResolver` (`EndToEndScenarioTest`).
- `/payments` and `/payments/history` were scoped by the payment's `user_id`, so a rent payment the admin recorded at the office never appeared for the tenant. Now scoped to the user's own payments **or** payments against their units, society-scoped (`EndToEndScenarioTest`).
- Dart's `HttpClient` + `php artisan serve` keep-alive mismatch surfaced as "Cannot reach server" although every request reached the server; the Dio client now sends `Connection: close`.

## 6. Device test log

See the end of this file (filled in from the live run).

## 7. Security checks (code level)

- Token stored in `shared_preferences` (app-private storage, not encrypted at rest). Acceptable for a non-rooted device; `flutter_secure_storage` would be the hardening step.
- 401 → session cleared and user returned to login (`ApiService.onSessionExpired`).
- No passwords/OTP/tokens are logged by the app (`dev.log` prints method + URL only); the backend no longer logs OTPs either.
- The one-time code is returned by the API ONLY when `OTP_EXPOSE_DEV_CODE=true` **and** the environment is `local`/`development`/`testing` (`MobileOtp::exposesDevCode()`); never in `production` or `staging`. A release build of the app ignores a code in the response anyway (`devOtpFrom`). Codes are stored as a keyed hash, one use, 5 minutes, 5 wrong tries, 30 s resend cooldown.
- No debug routes; `debugShowCheckedModeBanner: false`.

## 8. Known limitations / blockers

1. **`applicationId` is `com.example.societyflow`** — Google Play rejects it; permanent once published (P0-7, owner decision).
2. **No release keystore** — release APK is debug-signed until `android/key.properties` + a `.jks` exist (P0-8, owner must own and back it up).
3. **Launcher icon and splash are Flutter defaults** — no SocietyFlow asset was supplied.
4. **No push client** (`firebase_messaging` absent; `AppFeatures.enablePushNotifications = false`).
5. **Windows Developer Mode is off** on this machine — Flutter warns that plugin symlinks need it; the Android build worked regardless, Windows-desktop builds would not.
6. **Admin / Accountant / Staff have no dedicated mobile screens** — they get the generic resident home (the web app is their tool); Guard has the QR/gate screens.

## Device test (2026-10-03, physical phone)

Device: Redmi 2302EPCC4I, Android 13, 720×1600, USB, `adb reverse tcp:8000 tcp:8000`;
APK built with `--dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1`
against `php artisan serve --host=0.0.0.0 --port=8000` on the dev SQLite database.

| Step | Result |
|---|---|
| Install (`adb install -r`) | PASS |
| Launch | PASS (native launch screen → splash → login) |
| Login `admin@societyflow.com` | PASS — only after the `Connection: close` client fix; before it every request reached the server but the app reported "Cannot reach server" |
| Dashboard | PASS — live figures (4 open complaints, 11 notices, recent payment/visitor/ticket activity) |
| Visitors | PASS — guard visitor log with real rows, Pending/Inside counters, Allow / Deny / Check-In actions |
| Bills, Tickets, Profile, QR scan, bookings, services, logout | NOT VERIFIED by this pass on the device — the owner started using the phone directly while the automated taps were running, so driving stopped to avoid colliding input. Every endpoint these screens call returned 200 for the Admin token (16/16, curl), and the same flows are covered end to end by `EndToEndScenarioTest` through the real API controllers. |
| Emulator (Pixel 6 / API 35, WHPX) | boots, app installs and reaches the login screen; login over `10.0.2.2:8000` suffered the same keep-alive problem before the client fix and was not re-run after it (the phone was used instead) |

Release APK: **not built in this pass** (the two debug builds took ~25 min each on this machine; a release build with no `key.properties` would be debug-signed anyway, see blockers 1–2).
