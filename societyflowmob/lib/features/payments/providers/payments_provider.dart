import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../models/payment_model.dart';

class PaymentsState {
  final bool isLoading;
  final List<BillModel> bills;
  final List<PaymentModel> payments;
  final String? error;

  const PaymentsState({this.isLoading = false, this.bills = const [], this.payments = const [], this.error});

  PaymentsState copyWith({bool? isLoading, List<BillModel>? bills, List<PaymentModel>? payments, String? error}) =>
      PaymentsState(isLoading: isLoading ?? this.isLoading, bills: bills ?? this.bills, payments: payments ?? this.payments, error: error);
}

class PaymentsNotifier extends StateNotifier<PaymentsState> {
  PaymentsNotifier() : super(const PaymentsState());

  Future<void> loadPayments() async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.get(ApiConfig.payments);
    if (result.success && result.data != null) {
      final data = result.data!['data'];
      state = state.copyWith(
        isLoading: false,
        bills:    (data['bills']    as List? ?? []).map((b) => BillModel.fromJson(b    as Map<String, dynamic>)).toList(),
        payments: (data['payments'] as List? ?? []).map((p) => PaymentModel.fromJson(p as Map<String, dynamic>)).toList(),
      );
    } else {
      state = state.copyWith(isLoading: false, error: result.message.isNotEmpty ? result.message : 'Failed to load payments');
    }
  }
}

final paymentsProvider = StateNotifierProvider<PaymentsNotifier, PaymentsState>((ref) => PaymentsNotifier());
