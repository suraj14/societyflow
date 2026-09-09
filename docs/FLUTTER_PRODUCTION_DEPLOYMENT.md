# Flutter production deployment — SocietyFlow mobile

Covers `societyflowmob/` only. The Laravel side is in
[PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md).

**The mobile app is not deployed to the web server.** It is compiled into an
installable artifact that goes to Google Play (or is offered as a download).
The server only ever needs the *built file*, never the project.

---

## 1. What ships where

| Artifact | Built by | Destination |
|---|---|---|
| `app-release.aab` | `flutter build appbundle --release` | **Google Play Console** — the required upload format |
| `app-release.apk` | `flutter build apk --release` | Direct install / sideload / QA devices only |
| Nothing else | — | — |

Both land under `build/app/outputs/`. Copy the file out; leave the tree behind.

### Never upload to the production server

- The whole `societyflowmob/` directory
- `build/` — ~3 GB of intermediates
- `.dart_tool/`, `android/.gradle/` — local caches
- `lib/`, `test/`, `pubspec.yaml` — source; serving it leaks the codebase
- `local.properties`, `key.properties`, `*.jks`, `*.keystore` — machine paths and **signing secrets**
- `hs_err_pid*.log`, `replay_pid*.log` — JVM crash dumps

If you self-host the APK for sideloading, the only thing in the webroot is
the `.apk` file itself:

```
public/downloads/societyflow-1.0.0.apk     <- the only mobile artifact on the server
```

Serve it with `Content-Type: application/vnd.android.package-archive`. Prefer
Play Store distribution; a self-hosted APK gets no automatic updates and
requires users to enable "install from unknown sources".

---

## 2. Build commands

Run from `societyflowmob/`.

```bash
flutter clean          # drop build/ + .dart_tool/ + platform ephemeral dirs
flutter pub get        # restore packages
flutter analyze        # must be clean (see §5 for the accepted 4)
flutter test           # must be green

flutter build apk --release        # -> build/app/outputs/flutter-apk/app-release.apk
flutter build appbundle --release  # -> build/app/outputs/bundle/release/app-release.aab
```

Smaller per-device APKs (sideload only — Play handles this automatically for
AAB):

```bash
flutter build apk --release --split-per-abi
```

### Cleaning

```bash
flutter clean                 # the standard reset
rm -rf android/.gradle        # project-local Gradle cache; flutter clean does NOT touch it
rm -f hs_err_pid*.log replay_pid*.log
```

`build/` is fully regenerable — deleting it cannot affect source. Verified:
after `flutter clean` removed ~3.1 GB, `pub get` + `analyze` + `test` +
both release builds all succeeded from a source tree of ~1.5 MB.

---

## 3. Blocking issues before Play Store upload

Both are **operator actions** — they involve app identity and a signing
credential, so they are not made automatically.

### 3.1 Application ID is still the Flutter template default

`android/app/build.gradle.kts`:

```kotlin
applicationId = "com.example.societyflow"
```

Google Play **rejects** any `com.example.*` package. Change it to a domain
you control, e.g. `com.joboapps.societyflow`. The application ID is permanent
once published — an app cannot change it later without shipping as a new
listing. Changing it also invalidates any Firebase `google-services.json`
keyed to the old ID.

### 3.2 Release builds are signed with the debug key

```kotlin
buildTypes {
    release {
        signingConfig = signingConfigs.getByName("debug")   // <- template default
    }
}
```

A debug-signed build cannot be uploaded to Play. Create a keystore and wire
it up:

```bash
keytool -genkey -v -keystore ~/societyflow-upload.jks \
  -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

Then `android/key.properties` (**git-ignored — never commit; losing it means
you can never update the app**):

```properties
storeFile=C:/path/to/societyflow-upload.jks
storePassword=...
keyPassword=...
keyAlias=upload
```

…and reference it from a real `signingConfigs.create("release")` block in
`build.gradle.kts`. Back the keystore up somewhere durable, or enrol in Play
App Signing.

### 3.3 The app still points at a LAN server over cleartext HTTP

`lib/core/config/api_config.dart` has `useProduction = false`, and
`AndroidManifest.xml` sets `android:usesCleartextTraffic="true"`. Before
release: set `useProduction = true`, point `prodUrl` at the HTTPS origin, and
remove the cleartext flag. Tracked as **P0-6** in [TODO.md](TODO.md).

---

## 4. Recommended Git exclusions

Already in the repository root `.gitignore`; listed here so the intent is
explicit.

```gitignore
/societyflowmob/.dart_tool/
/societyflowmob/build/
/societyflowmob/.flutter-plugins
/societyflowmob/.flutter-plugins-dependencies
/societyflowmob/.packages
/societyflowmob/.kotlin/
/societyflowmob/local.properties
/societyflowmob/android/.gradle/
/societyflowmob/android/local.properties
/societyflowmob/android/key.properties      # signing secrets
/societyflowmob/ios/Pods/
/societyflowmob/ios/.symlinks/
/societyflowmob/ios/Flutter/ephemeral/
hs_err_pid*.log
replay_pid*.log
*.apk
*.aab
*.jks
*.keystore
```

**Do commit** `pubspec.lock` — this is an application, not a package, so the
lockfile pins the exact dependency set that was tested.

`macos/`, `linux/` and `windows/` ephemeral directories are covered by the
`.gitignore` files Flutter ships inside each platform folder.

---

## 5. Accepted analyzer output

`flutter analyze` reports **4 informational** items, all the same:
`DropdownButtonFormField`'s `value:` is deprecated in favour of
`initialValue:`. They are left in place deliberately — the rename changes how
a `FormField` syncs with a parent-managed value, all four are controlled
dropdowns, and there is no device in the build environment to verify the
behaviour change. Tracked as **P3-1** in [TODO.md](TODO.md). Anything beyond
these four is a regression.

---

## 6. Assets

`assets/images/` and `assets/icons/` each contain a `placeholder.txt`. These
are **load-bearing**: `pubspec.yaml` declares both directories, and Flutter
fails the build if a declared asset directory does not exist. Git does not
track empty directories, so the placeholders are what keep them present after
a clone. Do not delete them until real assets live in those folders.

---

## 7. Build memory

`android/gradle.properties` requests:

```properties
org.gradle.jvmargs=-Xmx8G -XX:MaxMetaspaceSize=4G -XX:ReservedCodeCacheSize=512m
```

That is 12 GB+ of JVM reservation. The repository contained two JVM crash
dumps (`hs_err_pid7652.log`, `replay_pid7652.log`, 2.7 MB) recording exactly
this failure — *"insufficient memory for the Java Runtime Environment to
continue"*. Builds succeed on the current machine, so the setting is left
alone; if builds start dying on a smaller machine or in CI, lower it first:

```properties
org.gradle.jvmargs=-Xmx4G -XX:MaxMetaspaceSize=1G
```

---

## 8. Release checklist

- [ ] `applicationId` changed off `com.example.*` (§3.1)
- [ ] Release keystore created, `key.properties` wired up, keystore backed up (§3.2)
- [ ] `useProduction = true`, HTTPS `prodUrl`, `usesCleartextTraffic` removed (§3.3)
- [ ] `version:` in `pubspec.yaml` bumped (`1.0.0+1` → next build number)
- [ ] `flutter clean && flutter pub get && flutter analyze && flutter test` all pass
- [ ] `flutter build appbundle --release` succeeds
- [ ] AAB installs and reaches the production API from a real device
- [ ] Only the `.aab` leaves the machine — no source, no `build/`
