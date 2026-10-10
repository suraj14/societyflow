import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../models/society_document_model.dart';
import '../providers/society_documents_provider.dart';
import '../providers/society_info_provider.dart';
import 'society_document_viewer_screen.dart';
import 'society_info_tabs.dart';

/// Society Info - the former "Society Rules & Documents" screen, now three
/// sections: Society Details, Committee, Documents. Same route
/// ('/society-documents') and same entry points as before, one label.
///
/// The Documents section is the screen this used to be, unchanged: the
/// published, current version of each document the user may read, grouped by
/// category. Guards get only the guard-visible ones; the server decides, not
/// this screen.
class SocietyDocumentsScreen extends ConsumerStatefulWidget {
  /// 0 = Society Details, 1 = Committee, 2 = Documents.
  final int initialTab;
  const SocietyDocumentsScreen({super.key, this.initialTab = 0});

  @override
  ConsumerState<SocietyDocumentsScreen> createState() => _SocietyDocumentsScreenState();
}

class _SocietyDocumentsScreenState extends ConsumerState<SocietyDocumentsScreen> {
  String _query = '';

  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      ref.read(societyDocumentsProvider.notifier).load();
      ref.read(societyInfoProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(societyDocumentsProvider);

    if (state.isLoading && state.documents.isEmpty) {
      return _scaffold(const ListSkeleton());
    }
    if (state.error != null && state.documents.isEmpty) {
      return _scaffold(ErrorView(
        message: state.error!,
        onRetry: () => ref.read(societyDocumentsProvider.notifier).load(),
      ));
    }

    final q = _query.trim().toLowerCase();
    final docs = q.isEmpty
        ? state.documents
        : state.documents
            .where((d) => d.title.toLowerCase().contains(q) || d.description.toLowerCase().contains(q))
            .toList();

    final groups = <String, List<SocietyDocumentModel>>{};
    for (final d in docs) {
      groups.putIfAbsent(d.categoryLabel, () => []).add(d);
    }

    return _scaffold(RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () => ref.read(societyDocumentsProvider.notifier).load(),
      child: state.documents.isEmpty
          ? ListView(children: const [
              SizedBox(height: 80),
              EmptyState(
                icon: Icons.menu_book_rounded,
                title: 'No documents yet',
                subtitle: 'When the society office publishes rules, they will appear here.',
              ),
            ])
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              children: [
                TextField(
                  onChanged: (v) => setState(() => _query = v),
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    hintText: 'Search rules and documents',
                    prefixIcon: const Icon(Icons.search_rounded),
                    filled: true,
                    fillColor: AppColors.surface,
                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
                  ),
                ),
                const SizedBox(height: 4),
                if (docs.isEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 40),
                    child: Text(
                      'Nothing matches "$_query".',
                      textAlign: TextAlign.center,
                      style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary),
                    ),
                  ),
                for (final entry in groups.entries) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
                    child: Text(
                      entry.key.toUpperCase(),
                      style: AppTextStyles.label.copyWith(color: AppColors.textSecondary, letterSpacing: 0.8, fontSize: 12),
                    ),
                  ),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: Column(children: [
                      for (var i = 0; i < entry.value.length; i++) ...[
                        if (i > 0) const Divider(height: 1, indent: 72),
                        _DocumentTile(doc: entry.value[i]),
                      ],
                    ]),
                  ),
                ],
              ],
            ),
    ));
  }

  /// [documentsBody] is the Documents section; the other two sections load
  /// themselves from the Society Info provider.
  Widget _scaffold(Widget documentsBody) => DefaultTabController(
        length: 3,
        initialIndex: widget.initialTab.clamp(0, 2),
        child: Scaffold(
          backgroundColor: AppColors.background,
          appBar: AppBar(
            title: const Text('Society Info'),
            bottom: const TabBar(
              tabs: [
                Tab(text: 'Society Details'),
                Tab(text: 'Committee'),
                Tab(text: 'Documents'),
              ],
            ),
          ),
          body: TabBarView(
            children: [
              const SocietyDetailsTab(),
              const CommitteeTab(),
              documentsBody,
            ],
          ),
        ),
      );
}

class _DocumentTile extends StatelessWidget {
  final SocietyDocumentModel doc;
  const _DocumentTile({required this.doc});

  @override
  Widget build(BuildContext context) {
    final subtitle = [
      doc.version,
      if (doc.effectiveFrom != null) 'from ${formatDocDate(doc.effectiveFrom!)}',
    ].join(' · ');

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      leading: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(gradient: AppColors.primaryGradient, borderRadius: BorderRadius.circular(12)),
        child: const Icon(Icons.description_rounded, color: Colors.white),
      ),
      title: Text(
        doc.title,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: AppTextStyles.body1.copyWith(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(subtitle, style: AppTextStyles.body2.copyWith(color: AppColors.textSecondary)),
      trailing: doc.needsAcknowledgement
          ? Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: AppColors.warning.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                'Please read',
                style: AppTextStyles.body2.copyWith(color: AppColors.warning, fontSize: 11, fontWeight: FontWeight.w600),
              ),
            )
          : const Icon(Icons.chevron_right_rounded, color: AppColors.textHint),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => SocietyDocumentViewerScreen(document: doc)),
      ),
    );
  }
}

String formatDocDate(DateTime d) {
  const m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return '${d.day} ${m[d.month - 1]} ${d.year}';
}
