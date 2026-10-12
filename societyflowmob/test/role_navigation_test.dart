import 'dart:convert';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:flutter_secure_storage/test/test_flutter_secure_storage_platform.dart';
import 'package:societyflow/core/navigation/app_destinations.dart';
import 'package:societyflow/core/services/api_service.dart';
import 'package:societyflow/core/services/server_discovery.dart';
import 'package:societyflow/features/admin/providers/admin_provider.dart';
import 'package:societyflow/features/admin/screens/admin_home_screen.dart';
import 'package:societyflow/features/admin/screens/admin_requests_screen.dart';
import 'package:societyflow/features/admin/screens/admin_tickets_screen.dart';
import 'package:societyflow/features/auth/models/user_model.dart';
import 'package:societyflow/features/auth/providers/auth_provider.dart';
import 'package:societyflow/features/facilities/models/facility_model.dart';
import 'package:societyflow/features/facilities/providers/facilities_provider.dart';
import 'package:societyflow/features/facilities/screens/facilities_screen.dart';
import 'package:societyflow/features/home/providers/home_provider.dart';

/// Role-aware mobile navigation. The server tells the app what an account may do (`user.capabilities`,
/// computed from the API's own gates); AppNav turns that into the bottom bar and the shortcuts, so a role is
/// never shown a button its endpoint refuses and no destination appears twice.
///
/// Capability fixtures below mirror App\Support\MobileCapabilities for each role; tests/Feature/MobileCapabilitiesTest.php
/// pins the PHP side against the real endpoints.

// ── fixtures ────────────────────────────────────────────────────────────────

const _resident = {
  'admin_console': false, 'review_bookings': false, 'manage_complaints': false, 'assign_service_requests': false,
  'visitors': true, 'visitor_manage': false, 'visitor_register': false, 'gate_scan': false, 'guard_shift': false,
  'book_facility': true, 'view_facilities': true, 'request_service': true, 'file_complaint': true, 'bills': true,
  'access_passes': true, 'services_directory': true, 'notices': true, 'events': true, 'society_info': true,
};

Map<String, bool> _caps(Map<String, bool> base, [Map<String, bool> over = const {}]) => {...base, ...over};

final _admin = _caps(_resident, {
  'admin_console': true, 'review_bookings': true, 'manage_complaints': true, 'assign_service_requests': true,
  'visitor_manage': true, 'visitor_register': true, 'gate_scan': true, 'book_facility': false, 'bills': false,
});
final _guard = _caps(_resident, {
  'visitor_manage': true, 'visitor_register': true, 'gate_scan': true, 'guard_shift': true,
  'book_facility': false, 'view_facilities': false, 'request_service': false, 'file_complaint': false, 'bills': false, 'access_passes': false,
});
final _staff = _caps(_resident, {'visitor_manage': true, 'visitor_register': true, 'book_facility': false, 'request_service': false, 'bills': false});
final _accountant = _caps(_resident, {'book_facility': false, 'request_service': false, 'bills': false, 'access_passes': false});

UserModel _user(List<String> roles, Map<String, bool> caps, {String name = 'Tara'}) => UserModel.fromJson({
      'id': 1, 'name': name, 'email': 't@x.in', 'role': roles.first, 'roles': roles, 'capabilities': caps, 'society_name': 'Royal Eden',
    });

class _FakeAuth extends AuthNotifier {
  _FakeAuth(UserModel u) {
    state = AuthState(user: u);
  }
}

class _FakeAdminDash extends AdminDashboardNotifier {
  int loads = 0;
  _FakeAdminDash(AdminDashboardState s) {
    state = s;
  }
  @override
  Future<void> load() async => loads++;
}

class _FakeHome extends HomeNotifier {
  _FakeHome({List<Map<String, dynamic>> notices = const []}) {
    state = HomeState(notices: notices);
  }
  @override
  Future<void> loadDashboard() async {}
}

class _FakeFacilities extends FacilitiesNotifier {
  _FakeFacilities() {
    state = const FacilitiesState(facilities: [
      FacilityModel(id: 7, name: 'Clubhouse', charges: 500, status: 'active', openTime: '08:00', closeTime: '20:00', requiresApproval: true),
    ]);
  }
  @override
  Future<void> loadFacilities() async {}
  @override
  Future<void> loadBookings() async {}
}

/// Records every request and answers from a route table: "GET /admin/x" -> (status, body).
class _Routed implements HttpClientAdapter {
  final Map<String, (int, Map<String, dynamic>)> routes;
  final List<(String method, String path, Object? body)> calls = [];
  _Routed(this.routes);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final key = '${options.method} ${options.path}';
    calls.add((options.method, options.path, options.data));
    final r = routes[key] ?? (404, {'message': 'no route in test: $key'});
    return ResponseBody.fromBytes(utf8.encode(jsonEncode(r.$2)), r.$1, headers: {
      Headers.contentTypeHeader: ['application/json'],
    });
  }

  @override
  void close({bool force = false}) {}
}

_Routed _installDio(Map<String, (int, Map<String, dynamic>)> routes) {
  final adapter = _Routed(routes);
  final dio = Dio(BaseOptions(baseUrl: 'http://fake.test/api/v1'));
  dio.httpClientAdapter = adapter;
  ApiService.debugOverrideDio(dio);
  return adapter;
}

Future<void> _phone(WidgetTester tester, {double height = 2400}) async {
  tester.view.physicalSize = Size(1080, height);
  tester.view.devicePixelRatio = 3.0;
  addTearDown(() {
    tester.view.resetPhysicalSize();
    tester.view.resetDevicePixelRatio();
  });
}

Widget _app(Widget home, {List<Override> overrides = const []}) => ProviderScope(overrides: overrides, child: MaterialApp(home: home));

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStoragePlatform.instance = TestFlutterSecureStoragePlatform({});
    ServerDiscovery.overrideBaseUrl = 'http://fake.test/api/v1';
  });
  tearDown(() {
    ServerDiscovery.overrideBaseUrl = null;
    ApiService.debugClearDioOverride();
  });

  // ── the user model ────────────────────────────────────────────────────────
  group('UserModel roles and capabilities', () {
    test('parses every role and the capability map, and keeps the legacy single role', () {
      final u = _user(['Admin', 'Accountant'], _admin);
      expect(u.role, 'Admin');
      expect(u.allRoles, ['Admin', 'Accountant']);
      expect(u.hasRole('Accountant'), isTrue);
      expect(u.can('admin_console'), isTrue);
      expect(u.can('book_facility'), isFalse);
      expect(u.can('something_unknown'), isFalse, reason: 'an unknown capability is never granted');
    });

    test('survives a round trip through the stored session (toJson/fromJson)', () {
      final back = UserModel.fromJson(_user(['Apartment Owner', 'Tenant'], _resident).toJson());
      expect(back.allRoles, ['Apartment Owner', 'Tenant']);
      expect(back.can('book_facility'), isTrue);
    });

    test('an older server (no capabilities) falls back conservatively from the role', () {
      UserModel old(String role) => UserModel.fromJson({'id': 1, 'name': 'x', 'email': 'x@x.in', 'role': role});
      expect(old('Admin').can('admin_console'), isTrue);
      expect(old('Admin').can('book_facility'), isFalse);
      expect(old('Tenant').can('book_facility'), isTrue);
      expect(old('Tenant').can('admin_console'), isFalse);
      expect(old('Guard').can('gate_scan'), isTrue);
      expect(old('Guard').can('file_complaint'), isFalse, reason: 'a guard-only account reaches only the gate surface');
      expect(old('Staff').can('book_facility'), isFalse);
    });
  });

  // ── the tenant's four web Quick Actions, and where each lives in the app ───
  // Web: Pay Rent / Raise Request / View Notices / My Unit. The app has no tile row; each is a destination.
  group('tenant Quick Actions on mobile', () {
    final tenant = UserModel.fromJson({
      'id': 9, 'name': 'Piyus', 'email': 'p@x.in', 'role': 'Tenant', 'roles': ['Tenant'], 'capabilities': _resident, 'society_name': 'Royal Eden',
      'flat_number': '159', 'villa_number': '159', 'property_type': 'villa',
    });

    test('Pay Rent -> the Bills tab (view only: payment claims are made on the web, the app has no write endpoint)', () {
      expect(AppNav.bottomBar(tenant), contains(Dest.bills));
      expect(AppNav.allowed(tenant, Dest.bills), isTrue);
      expect(AppNav.tabIndex[Dest.bills], isNotNull);
    });

    test('Raise Request -> the Tickets tab', () {
      expect(AppNav.bottomBar(tenant), contains(Dest.tickets));
      expect(AppNav.allowed(tenant, Dest.tickets), isTrue);
      expect(AppNav.tabIndex[Dest.tickets], isNotNull);
    });

    test('View Notices -> the Notices destination (a shortcut, or the Notice Board on Home)', () {
      expect(AppNav.allowed(tenant, Dest.notices), isTrue);
      expect(AppNav.shortcuts(tenant), contains(Dest.notices));
      expect(AppNav.tabIndex[Dest.notices], isNotNull);
    });

    test('My Unit -> Profile > Property Details, with the rented unit from the server', () {
      expect(AppNav.bottomBar(tenant), contains(Dest.profile));
      expect(tenant.hasUnit, isTrue);
      expect(tenant.displayUnit, contains('159'));
    });

    test('every destination a tenant can tap resolves to a screen, and a switched-off module takes its action away', () {
      for (final d in [...AppNav.bottomBar(tenant), ...AppNav.shortcuts(tenant)]) {
        expect(AppNav.tabIndex.containsKey(d) || d == Dest.accessPasses, isTrue, reason: '$d has nowhere to go');
      }
      final noBills = UserModel.fromJson({'id': 9, 'name': 'P', 'email': 'p@x.in', 'role': 'Tenant', 'roles': ['Tenant'],
        'capabilities': _caps(_resident, {'bills': false}), 'society_name': 'Royal Eden'});
      expect(AppNav.bottomBar(noBills), isNot(contains(Dest.bills)));
    });
  });

  // ── the destinations registry ─────────────────────────────────────────────
  group('AppNav', () {
    List<Dest> bar(UserModel u) => AppNav.bottomBar(u);

    test('each kind of account gets the right bottom bar', () {
      expect(bar(_user(['Admin'], _admin)), [Dest.home, Dest.visitors, Dest.requests, Dest.tickets, Dest.profile]);
      expect(bar(_user(['Tenant'], _resident)), [Dest.home, Dest.visitors, Dest.bills, Dest.tickets, Dest.profile]);
      expect(bar(_user(['Guard'], _guard)), [Dest.home, Dest.visitors, Dest.services, Dest.notices, Dest.profile]);
      expect(bar(_user(['Staff'], _staff)), [Dest.home, Dest.visitors, Dest.tickets, Dest.notices, Dest.profile]);
      expect(bar(_user(['Accountant'], _accountant)), [Dest.home, Dest.visitors, Dest.tickets, Dest.notices, Dest.profile],
          reason: 'no Bills tab: an accountant has no unit and the app has no accounts screen');
    });

    test('a role without a capability never gets that destination (e.g. no Bills for the office)', () {
      expect(bar(_user(['Admin'], _admin)), isNot(contains(Dest.bills)));
      expect(AppNav.allowed(_user(['Guard'], _guard), Dest.facilities), isFalse);
      expect(AppNav.allowed(_user(['Guard'], _guard), Dest.tickets), isFalse);
    });

    test('multi-role accounts get the union: Admin + Accountant is the office, Guard + Admin is not gate-only', () {
      expect(AppNav.isOffice(_user(['Admin', 'Accountant'], _admin)), isTrue);
      final guardAdmin = _user(['Guard', 'Admin'], _caps(_admin, {'guard_shift': true}));
      expect(AppNav.isGateOnly(guardAdmin), isFalse);
      expect(bar(guardAdmin), contains(Dest.requests));

      final ownerTenant = _user(['Apartment Owner', 'Tenant'], _resident);
      expect(bar(ownerTenant), contains(Dest.bills));
      expect(AppNav.allowed(ownerTenant, Dest.facilities), isTrue);
    });

    test('shortcuts never repeat a bottom-bar tab or each other, for every role', () {
      final accounts = {
        'Admin': _user(['Admin'], _admin),
        'Tenant': _user(['Tenant'], _resident),
        'Guard': _user(['Guard'], _guard),
        'Staff': _user(['Staff'], _staff),
        'Accountant': _user(['Accountant'], _accountant),
        'Owner+Tenant': _user(['Apartment Owner', 'Tenant'], _resident),
      };
      accounts.forEach((name, u) {
        final b = bar(u);
        final s = AppNav.shortcuts(u);
        expect(s.toSet().length, s.length, reason: '$name: a shortcut is listed twice');
        expect(s.toSet().intersection(b.toSet()), isEmpty, reason: '$name: a shortcut repeats a bottom-bar tab');
      });
    });

    test('Notices is not offered as a shortcut while the Notice Board already links to it', () {
      final u = _user(['Tenant'], _resident);
      expect(AppNav.shortcuts(u), contains(Dest.notices));
      expect(AppNav.shortcuts(u, promoted: {Dest.notices}), isNot(contains(Dest.notices)));
    });

    test('a guard sees Notices and Services only on the bar, and Events only when the module is on', () {
      final g = _user(['Guard'], _guard);
      expect(AppNav.shortcuts(g), [Dest.events]);
      final off = _user(['Guard'], _caps(_guard, {'events': false}));
      expect(AppNav.shortcuts(off), isEmpty);
    });

    test('turning a module off removes its destination (Settings -> Features)', () {
      final noFacilities = _user(['Tenant'], _caps(_resident, {'view_facilities': false, 'book_facility': false}));
      expect(AppNav.shortcuts(noFacilities), isNot(contains(Dest.facilities)));
    });
  });

  // ── the Admin home ────────────────────────────────────────────────────────
  group('Admin home', () {
    Future<void> pump(WidgetTester tester, AdminDashboardState dash, {List<Map<String, dynamic>> notices = const []}) async {
      await _phone(tester, height: 9000);
      await tester.pumpWidget(_app(const AdminHomeScreen(), overrides: [
        authProvider.overrideWith((ref) => _FakeAuth(_user(['Admin'], _admin, name: 'Priya'))),
        adminDashboardProvider.overrideWith((ref) => _FakeAdminDash(dash)),
        homeProvider.overrideWith((ref) => _FakeHome(notices: notices)),
      ]));
      await tester.pump();
    }

    testWidgets('shows what needs attention with real counts, and the society at a glance', (tester) async {
      await pump(tester, const AdminDashboardState(loaded: true, societyName: 'Royal Eden', kpis: {
        'units': 94, 'occupied_units': 22, 'vacant_units': 72, 'residents': 31, 'visitors_today': 4, 'open_tickets': 17, 'pending_dues': 67113, 'monthly_revenue': 250000,
      }, attention: [
        AdminAttention(key: 'visitors', label: 'Visitors waiting for approval', count: 11),
        AdminAttention(key: 'complaints', label: 'New complaints', count: 15),
        AdminAttention(key: 'bills', label: 'Overdue bills', count: 1),
      ]));

      expect(find.text('Needs attention'), findsOneWidget);
      expect(find.text('Visitors waiting for approval'), findsOneWidget);
      expect(find.text('11'), findsOneWidget);
      expect(find.text('New complaints'), findsOneWidget);
      expect(find.text('Quick overview'), findsOneWidget);
      expect(find.text('94'), findsOneWidget);
      expect(find.text('₹67,113'), findsOneWidget, reason: 'money is formatted from the real figure');
      expect(find.text('Priya'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('an empty attention list says all clear instead of showing zeros', (tester) async {
      await pump(tester, const AdminDashboardState(loaded: true, kpis: {'units': 10}));
      expect(find.textContaining('All clear'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('a missing figure shows a dash, never an invented zero', (tester) async {
      await pump(tester, const AdminDashboardState(loaded: true, kpis: {'units': 10}));
      expect(find.text('-'), findsWidgets);
      expect(find.text('0'), findsNothing);
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('a failed first load shows the error with Retry and no dummy numbers', (tester) async {
      await pump(tester, const AdminDashboardState(error: 'Cannot reach server.'));
      expect(find.text('Cannot reach server.'), findsOneWidget);
      expect(find.text('Try Again'), findsOneWidget);
      expect(find.text('Quick overview'), findsNothing);
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('quick actions are the allowed shortcuts only, and Visitors/Tickets (bar tabs) are not repeated', (tester) async {
      await pump(tester, const AdminDashboardState(loaded: true, kpis: {'units': 1}));
      for (final k in ['notices', 'facilities', 'services', 'events', 'accessPasses']) {
        expect(find.byKey(Key('quick-$k')), findsOneWidget, reason: k);
      }
      expect(find.byKey(const Key('quick-visitors')), findsNothing);
      expect(find.byKey(const Key('quick-tickets')), findsNothing);
      expect(find.byKey(const Key('quick-requests')), findsNothing);
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('with notices on the board, Notices is the board (View all), not also a quick action', (tester) async {
      await pump(tester, const AdminDashboardState(loaded: true, kpis: {'units': 1}), notices: [
        {'title': 'Water supply off Saturday'},
      ]);
      expect(find.text('Latest notices'), findsOneWidget);
      expect(find.text('Water supply off Saturday'), findsOneWidget);
      expect(find.byKey(const Key('quick-notices')), findsNothing);
      await tester.pumpWidget(const SizedBox());
    });
  });

  // ── the office queues drive the real endpoints ────────────────────────────
  group('Admin queues', () {
    testWidgets('approving a booking POSTs the status, shows the server message, and re-reads the list', (tester) async {
      await _phone(tester);
      final api = _installDio({
        'GET /admin/facility-bookings?status=pending': (200, {'success': true, 'data': [
          {'id': 5, 'facility': 'Clubhouse', 'resident': {'name': 'Suraj', 'phone': '9'}, 'property': '160', 'booking_date': '2026-10-20', 'start_time': '10:00', 'end_time': '12:00', 'purpose': 'Birthday', 'status': 'pending', 'actions': ['approved', 'rejected', 'cancelled']},
        ]}),
        'POST /admin/facility-bookings/5/status': (200, {'success': true, 'message': 'Booking approved.'}),
        'GET /admin/service-requests?status=open': (200, {'success': true, 'data': []}),
      });

      await tester.pumpWidget(_app(const AdminRequestsScreen()));
      await tester.pumpAndSettle();
      expect(find.text('Clubhouse'), findsOneWidget);
      expect(find.byKey(const Key('approve-5')), findsOneWidget);

      await tester.tap(find.byKey(const Key('approve-5')));
      await tester.pumpAndSettle();

      final post = api.calls.firstWhere((c) => c.$1 == 'POST');
      expect(post.$2, '/admin/facility-bookings/5/status');
      expect(post.$3, {'status': 'approved'});
      expect(find.text('Booking approved.'), findsOneWidget);
      expect(api.calls.where((c) => c.$1 == 'GET' && c.$2.contains('facility-bookings')).length, 2, reason: 'the list is re-read after the action');
    });

    testWidgets('a refused action (409 conflict) shows the server reason and changes nothing locally', (tester) async {
      await _phone(tester);
      _installDio({
        'GET /admin/facility-bookings?status=pending': (200, {'success': true, 'data': [
          {'id': 5, 'facility': 'Clubhouse', 'resident': {'name': 'Suraj'}, 'booking_date': '2026-10-20', 'start_time': '10:00', 'end_time': '12:00', 'status': 'pending', 'actions': ['approved']},
        ]}),
        'POST /admin/facility-bookings/5/status': (409, {'success': false, 'message': 'This slot is already booked.'}),
        'GET /admin/service-requests?status=open': (200, {'success': true, 'data': []}),
      });

      await tester.pumpWidget(_app(const AdminRequestsScreen()));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('approve-5')));
      await tester.pumpAndSettle();

      expect(find.textContaining('already booked'), findsOneWidget);
    });

    testWidgets('a non-office token gets a clear permission message, not an empty list', (tester) async {
      await _phone(tester);
      _installDio({
        'GET /admin/facility-bookings?status=pending': (403, {'success': false, 'message': 'Only the society office can do this.'}),
        'GET /admin/service-requests?status=open': (403, {'success': false, 'message': 'Only the society office can do this.'}),
      });

      await tester.pumpWidget(_app(const AdminRequestsScreen()));
      await tester.pumpAndSettle();

      expect(find.textContaining('society office'), findsWidgets);
      expect(find.text('No bookings waiting'), findsNothing);
    });

    testWidgets('resolving a ticket POSTs the status transition', (tester) async {
      await _phone(tester);
      final api = _installDio({
        'GET /admin/complaints?status=unresolved': (200, {'success': true, 'data': [
          {'id': 8, 'complaint_number': 'CMP-8', 'title': 'Lift stuck', 'category': 'Lift', 'priority': 'high', 'status': 'open', 'resident': {'name': 'Piyus'}, 'property': '12'},
        ]}),
        'POST /admin/complaints/8/status': (200, {'success': true, 'message': 'Complaint Resolved.'}),
      });

      await tester.pumpWidget(_app(const AdminTicketsScreen()));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('resolve-8')));
      await tester.pumpAndSettle();

      final post = api.calls.firstWhere((c) => c.$1 == 'POST');
      expect(post.$2, '/admin/complaints/8/status');
      expect(post.$3, {'status': 'resolved'});
      expect(find.text('Complaint Resolved.'), findsOneWidget);
    });

    testWidgets('an unassigned service request offers only the society\'s own providers', (tester) async {
      await _phone(tester);
      final api = _installDio({
        'GET /admin/facility-bookings?status=pending': (200, {'success': true, 'data': []}),
        'GET /admin/service-requests?status=open': (200, {'success': true, 'data': [
          {'id': 3, 'service': 'Plumbing', 'description': 'Tap', 'status': 'pending', 'resident': {'name': 'Meera'}, 'providers': [
            {'id': 21, 'name': 'Ramesh', 'phone': '98'},
          ]},
        ]}),
        'POST /admin/service-requests/3/assign': (200, {'success': true, 'message': 'Service request assigned.'}),
      });

      await tester.pumpWidget(_app(const AdminRequestsScreen(initialTab: 1)));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('assign-3')));
      await tester.pumpAndSettle();
      expect(find.text('Ramesh'), findsOneWidget);
      await tester.tap(find.byKey(const Key('provider-21')));
      await tester.pumpAndSettle();

      final post = api.calls.firstWhere((c) => c.$1 == 'POST');
      expect(post.$2, '/admin/service-requests/3/assign');
      expect(post.$3, {'provider_id': 21});
    });
  });

  // ── the facility booking 403 ──────────────────────────────────────────────
  group('Facility booking permission', () {
    testWidgets('an Admin sees the facility but no Book button, with the reason', (tester) async {
      await _phone(tester);
      await tester.pumpWidget(_app(const FacilitiesScreen(), overrides: [
        authProvider.overrideWith((ref) => _FakeAuth(_user(['Admin'], _admin))),
        facilitiesProvider.overrideWith((ref) => _FakeFacilities()),
      ]));
      await tester.pumpAndSettle();

      expect(find.text('Clubhouse'), findsOneWidget);
      expect(find.byKey(const Key('book-7')), findsNothing);
      expect(find.byKey(const Key('booking-residents-only')), findsOneWidget);
    });

    testWidgets('a resident sees the Book button', (tester) async {
      await _phone(tester);
      await tester.pumpWidget(_app(const FacilitiesScreen(), overrides: [
        authProvider.overrideWith((ref) => _FakeAuth(_user(['Tenant'], _resident))),
        facilitiesProvider.overrideWith((ref) => _FakeFacilities()),
      ]));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('book-7')), findsOneWidget);
      expect(find.byKey(const Key('booking-residents-only')), findsNothing);
    });

    test('a 403 shows the server\'s own reason, not a generic line', () async {
      _installDio({'POST /facility-bookings': (403, {'success': false, 'message': 'Facility booking is for residents.'})});

      final r = await ApiService.instance.post('/facility-bookings', body: {'facility_id': 7});

      expect(r.success, isFalse);
      expect(r.statusCode, 403);
      expect(r.message, startsWith('Facility booking is for residents.'));
      expect(r.message, isNot(startsWith('Access denied.')));
    });

    test('a 403 without a message still says Access denied', () async {
      _installDio({'GET /anything': (403, {})});

      final r = await ApiService.instance.get('/anything');

      expect(r.message, startsWith('Access denied.'));
    });
  });
}
