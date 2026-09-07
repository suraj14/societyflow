import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../providers/payments_provider.dart';
import '../models/payment_model.dart';

class PaymentsScreen extends ConsumerStatefulWidget {
  const PaymentsScreen({super.key});
  @override
  ConsumerState<PaymentsScreen> createState() => _PaymentsScreenState();
}

class _PaymentsScreenState extends ConsumerState<PaymentsScreen>
    with SingleTickerProviderStateMixin, AutoRefreshMixin {
  late TabController _tabController;

  @override
  void onAutoRefresh() => ref.read(paymentsProvider.notifier).loadPayments();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    Future.microtask(() => ref.read(paymentsProvider.notifier).loadPayments());
    startAutoRefresh();
  }

  @override
  void dispose() { _tabController.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(paymentsProvider);
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Payments & Bills'),
        bottom: TabBar(controller: _tabController, labelColor: AppColors.primary, unselectedLabelColor: AppColors.textHint, indicatorColor: AppColors.primary, tabs: const [Tab(text: 'Bills'), Tab(text: 'History')]),
      ),
      body: Column(children: [
        if (!state.isLoading) _summaryCard(state),
        Expanded(child: TabBarView(controller: _tabController, children: [
          _BillsList(bills: state.bills, isLoading: state.isLoading),
          _PaymentHistory(payments: state.payments, isLoading: state.isLoading),
        ])),
      ]),
    );
  }

  Widget _summaryCard(PaymentsState state) {
    final totalDue = state.bills.where((b) => !b.isPaid).fold(0.0, (sum, b) => sum + b.balanceAmount);
    return Padding(
      padding: const EdgeInsets.all(16),
      child: GradientCard(
        child: Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Total Due', style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13)),
            const SizedBox(height: 4),
            Text('₹${totalDue.toStringAsFixed(2)}', style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
            const SizedBox(height: 4),
            Text('${state.bills.where((b) => !b.isPaid).length} pending bills', style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 12)),
          ])),
        ]),
      ),
    );
  }
}

class _BillsList extends ConsumerWidget {
  final List<BillModel> bills;
  final bool isLoading;
  const _BillsList({required this.bills, required this.isLoading});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (isLoading) return const ListSkeleton();
    if (bills.isEmpty) return const EmptyState(icon: Icons.receipt_long_outlined, title: 'No bills found', subtitle: 'Your bills will appear here');
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
      itemCount: bills.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, i) => _BillCard(bill: bills[i]),
    );
  }
}

class _BillCard extends StatelessWidget {
  final BillModel bill;
  const _BillCard({required this.bill});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        Row(children: [
          Container(width: 44, height: 44, decoration: BoxDecoration(color: (bill.isPaid ? AppColors.secondary : AppColors.warning).withOpacity(0.1), borderRadius: BorderRadius.circular(12)), child: Icon(Icons.receipt_long_rounded, color: bill.isPaid ? AppColors.secondary : AppColors.warning, size: 20)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${bill.month} ${bill.year}', style: AppTextStyles.label),
            const SizedBox(height: 2),
            Text(bill.billType.toUpperCase(), style: AppTextStyles.caption.copyWith(letterSpacing: 0.5)),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('₹${bill.totalAmount.toStringAsFixed(0)}', style: AppTextStyles.h3.copyWith(fontSize: 16)),
            const SizedBox(height: 4),
            StatusBadge(status: bill.status),
          ]),
        ]),
        if (!bill.isPaid) ...[
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('Paid: ₹${bill.paidAmount.toStringAsFixed(0)}', style: AppTextStyles.caption),
                Text('Due: ₹${bill.balanceAmount.toStringAsFixed(0)}', style: AppTextStyles.caption.copyWith(color: AppColors.error)),
              ]),
              const SizedBox(height: 6),
              ClipRRect(borderRadius: BorderRadius.circular(4), child: LinearProgressIndicator(value: bill.totalAmount > 0 ? bill.paidAmount / bill.totalAmount : 0, backgroundColor: AppColors.divider, valueColor: const AlwaysStoppedAnimation<Color>(AppColors.secondary), minHeight: 6)),
            ])),
          ]),
        ],
      ]),
    );
  }
}

class _PaymentHistory extends ConsumerWidget {
  final List<PaymentModel> payments;
  final bool isLoading;
  const _PaymentHistory({required this.payments, required this.isLoading});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (isLoading) return const ListSkeleton();
    if (payments.isEmpty) return const EmptyState(icon: Icons.history_rounded, title: 'No payment history', subtitle: 'Your payment history will appear here');
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
      itemCount: payments.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, i) {
        final p = payments[i];
        return AppCard(
          padding: const EdgeInsets.all(14),
          child: Row(children: [
            Container(width: 44, height: 44, decoration: BoxDecoration(color: AppColors.secondary.withOpacity(0.1), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.check_circle_rounded, color: AppColors.secondary, size: 20)),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p.billType, style: AppTextStyles.label),
              const SizedBox(height: 2),
              Text('${p.paymentDate.day}/${p.paymentDate.month}/${p.paymentDate.year} • ${p.paymentMethod.toUpperCase()}', style: AppTextStyles.caption),
            ])),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text('₹${p.amount.toStringAsFixed(0)}', style: AppTextStyles.label.copyWith(color: AppColors.secondary)),
              const SizedBox(height: 4),
              StatusBadge(status: p.status),
            ]),
          ]),
        );
      },
    );
  }
}
