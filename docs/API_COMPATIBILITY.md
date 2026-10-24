# Mobile app <-> Laravel API compatibility (2026-10-24)

Source of truth: `routes/api.php` (prefix `/api/v1`) and the Flutter app in the separate repository `societyflow-mobile` (`lib/core/config/api_config.dart`,
`lib/features/*/providers`). Method: every endpoint the app calls was listed (54 calls) and matched against `php artisan route:list --json --path=api/v1`
for **method and path**. Result: **54 of 54 exist with the method the app uses (PASS)**. This proves the routes line up; it does not prove each response body matches
the app's models - that is covered by the app's parsing tests (`test/models_test.dart`) and the backend's `ApiContentVisibilityTest` / formatter tests.

| Feature | App calls | Auth / who | Backend tests that guard it |
|---|---|---|---|
| Sign-in, register, OTP, password reset | `POST /auth/login`, `/auth/register`, `/auth/check-account`, `/auth/send-otp`, `/auth/verify-otp`, `/auth/select-account`, `/auth/forgot-password`, `/auth/reset-password`; `GET /password-policy` | public (rate-limited; **login now has a failed-attempt lockout, 429 + `retry_after`**) | `LoginLockoutTest`, `Otp*Test`, `PasswordResetEdgeCasesTest`, `AuthSecurityTest` |
| Session | `POST /auth/logout`, `GET /auth/me`, `POST /change-password` | Sanctum bearer; inactive / suspended refused | `AuthSessionRevocationTest`, `InactiveAccountAccessTest` |
| Dashboard, profile, family | `GET /dashboard`, `GET/POST /profile`, `GET /profile/family-members` | Sanctum; scoped to the user's society | `AuthorizationIsolationTest`, `ProfileImageTest` |
| Visitors, access passes | `GET/POST /visitors`, `POST /visitors/lookup`, `/access-passes`, `/access-passes/scan`, `/{id}`, `/{id}/extend` | residents; Guard limited to the gate surface (`restrict.guard.api`) | `VisitorApprovalWorkflowTest`, `AccessPass*Test`, `GuardGateTest` |
| Bills, payments | `GET /bills`, `GET /payments` | residents (own unit) | `FinancePermissionMatrixTest`, finance report suites |
| Complaints | `GET/POST /complaints`, `/complaints/{id}`, `/complaints/categories` | residents; admin queue under `/admin/complaints` | `Complaint*Test` |
| Facilities | `GET /facilities`, `/facilities/{id}/availability`, `GET/POST /facility-bookings`, `POST /facility-bookings/{id}/cancel` | residents (booking), admin queue under `/admin/facility-bookings` | `FacilityBooking*Test`, `AuthorizationIsolationTest` |
| Services | `GET /services`, `/service-providers`, `/service-requests`, `POST /service-requests` | residents | `ServiceRequestNotificationTest` |
| Notices, events, documents, contacts | `GET /notices`, `/events`, `/society/contacts`, `/society/info`, `/society-documents` (+ `/{id}/file`, `/{id}/acknowledge`) | residents / guards per document audience | `NoticeIsolationTest`, `SocietyDocumentTest` |
| In-app notifications | `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all` | each user's own | `InAppNotificationTest` |
| Admin screens | `GET /admin/dashboard`, `/admin/facility-bookings`, `/admin/complaints` (+ `POST .../{id}/status`) | Admin / Super Admin only | `AuthorizationIsolationTest` |
| Guard roster | `GET /guard/shift` | Guard | `GuardShiftAttendanceTest` |

## Client configuration (verified by reading the code)

* Release builds use `https://societyflow.joboapps.com/api/v1`; `ApiConfig` replaces any local / LAN / emulator URL with the production URL in a release build.
* Android: `INTERNET` permission declared; cleartext HTTP is not enabled; no TLS-validation override found.
* 401 clears the session; 403 shows the server's own message; 422 shows field errors; **429 (new login lockout) shows the server's message** ("Too many login attempts. Please try again in N seconds") through the generic branch; 5xx shows a generic line.
* Authorisation is enforced by Laravel (middleware + society scoping); the app's hidden buttons are convenience only.

## Not verified here

Response *bodies* against the live server; any call from a real device against production (the production API was not contacted); iOS.
