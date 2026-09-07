import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/network_image_widget.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../providers/notices_provider.dart';
import '../models/notice_model.dart';

class NoticesScreen extends ConsumerStatefulWidget {
  const NoticesScreen({super.key});
  @override
  ConsumerState<NoticesScreen> createState() => _NoticesScreenState();
}

class _NoticesScreenState extends ConsumerState<NoticesScreen> with AutoRefreshMixin {
  @override
  void onAutoRefresh() => ref.read(noticesProvider.notifier).loadNotices();

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(noticesProvider.notifier).loadNotices());
    startAutoRefresh();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(noticesProvider);
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Notice Board')),
      body: RefreshIndicator(
        onRefresh: () => ref.read(noticesProvider.notifier).loadNotices(),
        color: AppColors.primary,
        child: state.isLoading
            ? const ListSkeleton()
            : state.notices.isEmpty
                ? const EmptyState(icon: Icons.campaign_outlined, title: 'No notices', subtitle: 'Society notices will appear here')
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: state.notices.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (_, i) => _NoticeCard(notice: state.notices[i]),
                  ),
      ),
    );
  }
}

class _NoticeCard extends StatelessWidget {
  final NoticeModel notice;
  const _NoticeCard({required this.notice});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      onTap: () => _showDetail(context),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Notice image (if available)
        if (notice.image != null) ...[
          NetImage(
            url: notice.image,
            width: double.infinity,
            height: 160,
            borderRadius: BorderRadius.circular(AppRadius.md),
            fallbackIcon: Icons.image_outlined,
          ),
          const SizedBox(height: 12),
        ],
        Row(children: [
          Container(width: 40, height: 40, decoration: BoxDecoration(color: (notice.isImportant ? AppColors.error : AppColors.primary).withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(notice.isImportant ? Icons.priority_high_rounded : Icons.campaign_rounded, color: notice.isImportant ? AppColors.error : AppColors.primary, size: 18)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(notice.title, style: AppTextStyles.label, maxLines: 2, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 2),
            Text(notice.postedBy ?? 'Admin', style: AppTextStyles.caption),
          ])),
          if (notice.isImportant)
            Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: AppColors.error.withOpacity(0.1), borderRadius: BorderRadius.circular(20)), child: const Text('Important', style: TextStyle(color: AppColors.error, fontSize: 10, fontWeight: FontWeight.w600))),
        ]),
        const SizedBox(height: 10),
        Text(notice.content, style: AppTextStyles.body2, maxLines: 2, overflow: TextOverflow.ellipsis),
        const SizedBox(height: 8),
        Row(children: [
          const Icon(Icons.access_time_rounded, size: 12, color: AppColors.textHint),
          const SizedBox(width: 4),
          Text('${notice.createdAt.day}/${notice.createdAt.month}/${notice.createdAt.year}', style: AppTextStyles.caption),
          const Spacer(),
          const Text('Read more', style: TextStyle(color: AppColors.primary, fontSize: 12, fontWeight: FontWeight.w600)),
        ]),
      ]),
    );
  }

  void _showDetail(BuildContext context) {
    showModalBottomSheet(
      context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
      builder: (_) => DraggableScrollableSheet(
        initialChildSize: 0.7, maxChildSize: 0.95, minChildSize: 0.5,
        builder: (_, ctrl) => Container(
          decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
          child: Column(children: [
            const SizedBox(height: 12),
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
            Expanded(child: ListView(controller: ctrl, padding: const EdgeInsets.all(24), children: [
              if (notice.isImportant)
                Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6), margin: const EdgeInsets.only(bottom: 12), decoration: BoxDecoration(color: AppColors.error.withOpacity(0.1), borderRadius: BorderRadius.circular(8)), child: const Row(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.priority_high_rounded, color: AppColors.error, size: 14), SizedBox(width: 4), Text('Important Notice', style: TextStyle(color: AppColors.error, fontSize: 12, fontWeight: FontWeight.w600))])),
              if (notice.image != null) ...[
                NetImage(url: notice.image, width: double.infinity, height: 200, borderRadius: BorderRadius.circular(AppRadius.lg)),
                const SizedBox(height: 16),
              ],
              Text(notice.title, style: AppTextStyles.h3),
              const SizedBox(height: 8),
              Row(children: [
                const Icon(Icons.person_outline_rounded, size: 14, color: AppColors.textHint),
                const SizedBox(width: 4),
                Text(notice.postedBy ?? 'Admin', style: AppTextStyles.caption),
                const SizedBox(width: 12),
                const Icon(Icons.access_time_rounded, size: 14, color: AppColors.textHint),
                const SizedBox(width: 4),
                Text('${notice.createdAt.day}/${notice.createdAt.month}/${notice.createdAt.year}', style: AppTextStyles.caption),
              ]),
              const SizedBox(height: 16),
              const Divider(),
              const SizedBox(height: 16),
              Text(notice.content, style: AppTextStyles.body1.copyWith(height: 1.6)),
            ])),
          ]),
        ),
      ),
    );
  }
}
