import 'package:flutter/material.dart';
import '../../../core/services/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/error_view.dart';
import '../../../shared/widgets/status_badge.dart';

/// A server-driven work queue for the office: loads a list from an admin endpoint, shows loading / empty /
/// error / permission-denied states honestly, and lets a tile act and then reload from the server (the screen
/// never assumes an action worked - it re-reads).
class AdminQueue extends StatefulWidget {
  final Future<ApiResult> Function() fetch;
  final String emptyTitle;
  final String emptyHint;

  /// Builds one row. [reload] re-fetches the list; [say] shows a SnackBar with a server message.
  final Widget Function(BuildContext context, Map<String, dynamic> item, Future<void> Function() reload, void Function(String, {bool error}) say) tile;

  const AdminQueue({super.key, required this.fetch, required this.emptyTitle, required this.emptyHint, required this.tile});

  @override
  State<AdminQueue> createState() => _AdminQueueState();
}

class _AdminQueueState extends State<AdminQueue> {
  bool _loading = true;
  String? _error;
  bool _denied = false;
  List<Map<String, dynamic>> _items = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(AdminQueue old) {
    super.didUpdateWidget(old);
    if (old.fetch != widget.fetch) _load();
  }

  Future<void> _load() async {
    if (_items.isEmpty) setState(() => _loading = true);
    final r = await widget.fetch();
    if (!mounted) return;
    if (r.success && r.data != null) {
      final raw = r.data!['data'];
      setState(() {
        _items = [for (final i in (raw is List ? raw : const [])) Map<String, dynamic>.from(i as Map)];
        _error = null;
        _denied = false;
        _loading = false;
      });
    } else {
      setState(() {
        _denied = r.statusCode == 403;
        _error = r.message.isNotEmpty ? r.message : 'Could not load this list.';
        _loading = false;
      });
    }
  }

  void _say(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message), backgroundColor: error ? AppColors.error : AppColors.secondary));
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator(color: AppColors.primary));
    if (_denied) {
      return const ErrorView(message: 'Only the society office can use this screen.', icon: Icons.lock_outline_rounded);
    }
    if (_error != null && _items.isEmpty) return ErrorView(message: _error!, onRetry: _load);

    return RefreshIndicator(
      onRefresh: _load,
      color: AppColors.primary,
      child: _items.isEmpty
          ? ListView(children: [
              const SizedBox(height: 80),
              const Icon(Icons.check_circle_outline_rounded, size: 52, color: AppColors.textHint),
              const SizedBox(height: 12),
              Text(widget.emptyTitle, style: AppTextStyles.h3, textAlign: TextAlign.center),
              const SizedBox(height: 6),
              Padding(padding: const EdgeInsets.symmetric(horizontal: 32), child: Text(widget.emptyHint, style: AppTextStyles.body2, textAlign: TextAlign.center)),
            ])
          : ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
              itemCount: _items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (ctx, i) => widget.tile(ctx, _items[i], _load, _say),
            ),
    );
  }
}

/// Card chrome shared by the queue tiles.
class QueueCard extends StatelessWidget {
  final String title;
  final String? subtitle;
  final List<String> lines;
  final String status;
  final List<Widget> actions;

  const QueueCard({super.key, required this.title, this.subtitle, this.lines = const [], required this.status, this.actions = const []});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(child: Text(title, style: AppTextStyles.label, maxLines: 2, overflow: TextOverflow.ellipsis)),
          const SizedBox(width: 8),
          StatusBadge(status: status),
        ]),
        if (subtitle != null && subtitle!.isNotEmpty) ...[
          const SizedBox(height: 2),
          Text(subtitle!, style: AppTextStyles.caption),
        ],
        for (final l in lines.where((l) => l.isNotEmpty)) ...[
          const SizedBox(height: 4),
          Text(l, style: AppTextStyles.body2, maxLines: 3, overflow: TextOverflow.ellipsis),
        ],
        if (actions.isNotEmpty) ...[
          const SizedBox(height: 10),
          Wrap(spacing: 8, runSpacing: 8, children: actions),
        ],
      ]),
    );
  }
}

/// A compact action button for a queue card.
class QueueAction extends StatelessWidget {
  final String label;
  final VoidCallback onTap;
  final Color color;
  final bool filled;

  const QueueAction({super.key, required this.label, required this.onTap, this.color = AppColors.primary, this.filled = false});

  @override
  Widget build(BuildContext context) {
    final style = filled
        ? ElevatedButton.styleFrom(backgroundColor: color, foregroundColor: Colors.white, minimumSize: const Size(0, 40), padding: const EdgeInsets.symmetric(horizontal: 16))
        : OutlinedButton.styleFrom(foregroundColor: color, side: BorderSide(color: color.withValues(alpha: 0.5)), minimumSize: const Size(0, 40), padding: const EdgeInsets.symmetric(horizontal: 16));
    return filled ? ElevatedButton(onPressed: onTap, style: style, child: Text(label)) : OutlinedButton(onPressed: onTap, style: style, child: Text(label));
  }
}
