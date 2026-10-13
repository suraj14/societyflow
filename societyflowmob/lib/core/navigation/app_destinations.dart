import 'package:flutter/material.dart';
import '../../features/auth/models/user_model.dart';
import '../theme/app_theme.dart';

/// Every place a signed-in user can go from the home screen or the bottom bar.
enum Dest { home, visitors, bills, tickets, profile, notices, facilities, services, events, requests, accessPasses }

class Destination {
  final Dest id;
  final String label;
  final IconData icon;
  final Color color;

  /// Server capability that unlocks it (see UserModel.can / App\Support\MobileCapabilities). Null = everyone.
  /// A destination listed with several is unlocked by ANY of them (multi-role accounts get the union).
  final List<String> anyOf;

  const Destination(this.id, this.label, this.icon, this.color, {this.anyOf = const []});
}

/// The single source of truth for mobile navigation.
///
/// Before this, the bottom bar, the home "More Features" grid, the Guard "Quick Actions" and the Guard "Modules"
/// each carried their own hard-coded role lists, so the same destination appeared two or three times and a role
/// could be shown a shortcut its API refuses. Now: what a user may open comes from [UserModel.can] (the server's
/// answer), the bottom bar is chosen per kind of account here, and every other shortcut is "allowed AND not
/// already on the bar AND not already promoted elsewhere on that screen" - duplicates are impossible by construction.
class AppNav {
  AppNav._();

  static const Map<Dest, Destination> all = {
    Dest.home:         Destination(Dest.home, 'Home', Icons.home_rounded, AppColors.primary),
    Dest.visitors:     Destination(Dest.visitors, 'Visitors', Icons.people_alt_rounded, AppColors.info, anyOf: ['visitors']),
    Dest.bills:        Destination(Dest.bills, 'Bills', Icons.receipt_long_rounded, AppColors.warning, anyOf: ['bills']),
    Dest.tickets:      Destination(Dest.tickets, 'Tickets', Icons.support_agent_rounded, AppColors.error, anyOf: ['file_complaint', 'manage_complaints']),
    Dest.profile:      Destination(Dest.profile, 'Profile', Icons.person_rounded, AppColors.primary),
    Dest.notices:      Destination(Dest.notices, 'Notices', Icons.campaign_rounded, AppColors.primary, anyOf: ['notices']),
    Dest.facilities:   Destination(Dest.facilities, 'Facilities', Icons.meeting_room_rounded, AppColors.info, anyOf: ['view_facilities']),
    Dest.services:     Destination(Dest.services, 'Services', Icons.build_rounded, AppColors.secondary, anyOf: ['services_directory']),
    Dest.events:       Destination(Dest.events, 'Events', Icons.event_rounded, AppColors.warning, anyOf: ['events']),
    Dest.requests:     Destination(Dest.requests, 'Bookings', Icons.event_available_rounded, AppColors.secondary, anyOf: ['review_bookings']),
    Dest.accessPasses: Destination(Dest.accessPasses, 'Access Passes', Icons.badge_rounded, AppColors.primary, anyOf: ['access_passes']),
  };

  /// Tab index inside MainShell's IndexedStack. Destinations without a tab (access passes) are pushed as screens.
  static const Map<Dest, int> tabIndex = {
    Dest.home: 0, Dest.visitors: 1, Dest.bills: 2, Dest.tickets: 3, Dest.profile: 4,
    Dest.notices: 5, Dest.facilities: 6, Dest.services: 7, Dest.events: 8, Dest.requests: 9,
  };

  static bool allowed(UserModel? user, Dest d) {
    if (user == null) return false;
    final need = all[d]!.anyOf;
    return need.isEmpty || need.any(user.can);
  }

  /// Guard-only accounts live on the gate surface; an account that is also Admin is not confined to it.
  static bool isGateOnly(UserModel? u) => u != null && u.hasRole('Guard') && !u.can('admin_console');

  static bool isOffice(UserModel? u) => u != null && u.can('admin_console');

  /// The (at most five) tabs of the bottom bar, in order, for this account.
  static List<Dest> bottomBar(UserModel? user) {
    if (user == null) return const [Dest.home, Dest.profile];
    final List<Dest> wanted;
    if (isGateOnly(user)) {
      wanted = const [Dest.home, Dest.visitors, Dest.services, Dest.notices, Dest.profile];
    } else if (isOffice(user)) {
      wanted = const [Dest.home, Dest.visitors, Dest.requests, Dest.tickets, Dest.profile];
    } else if (user.can('bills')) {
      wanted = const [Dest.home, Dest.visitors, Dest.bills, Dest.tickets, Dest.profile];
    } else {
      wanted = const [Dest.home, Dest.visitors, Dest.tickets, Dest.notices, Dest.profile];   // Staff, Accountant, ...
    }

    return [for (final d in wanted) if (d == Dest.home || d == Dest.profile || allowed(user, d)) d];
  }

  /// Shortcuts for the home screen's "More" area: everything this account may open that is neither on the bottom
  /// bar nor already promoted on that screen (e.g. the Notice Board with its own "View all").
  static List<Dest> shortcuts(UserModel? user, {Set<Dest> promoted = const {}}) {
    if (user == null) return const [];
    final bar = bottomBar(user).toSet();
    const candidates = [Dest.notices, Dest.facilities, Dest.services, Dest.events, Dest.accessPasses];

    return [for (final d in candidates) if (allowed(user, d) && !bar.contains(d) && !promoted.contains(d)) d];
  }
}
