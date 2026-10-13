import 'package:flutter/material.dart';

/// Asks before throwing away what the user has typed. Android Back, the app-bar arrow and a sheet's barrier tap all end
/// in a pop; when [isDirty] says there is unsaved input, the pop is held and "Discard?" is asked first. A clean form
/// closes at once - no dialog for nothing.
class DiscardGuard extends StatelessWidget {
  final bool Function() isDirty;
  final Widget child;
  final String title;
  final String message;

  const DiscardGuard({
    super.key,
    required this.isDirty,
    required this.child,
    this.title = 'Discard what you entered?',
    this.message = 'Going back will lose it.',
  });

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        final navigator = Navigator.of(context);
        if (!isDirty()) {
          navigator.pop();
          return;
        }
        final discard = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text(title),
            content: Text(message),
            actions: [
              TextButton(key: const Key('discard-keep'), onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep editing')),
              TextButton(key: const Key('discard-confirm'), onPressed: () => Navigator.pop(ctx, true), child: const Text('Discard')),
            ],
          ),
        );
        if (discard == true && navigator.mounted) navigator.pop();
      },
      child: child,
    );
  }
}