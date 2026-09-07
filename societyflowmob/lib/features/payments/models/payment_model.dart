class PaymentModel {
  final int id;
  final double amount;
  final String billType;
  final String paymentMethod;
  final String status;
  final DateTime? paymentDate; // null for pending/failed payments
  final DateTime? dueDate;

  const PaymentModel({
    required this.id, required this.amount, required this.billType,
    required this.paymentMethod, required this.status,
    this.paymentDate, this.dueDate,
  });

  factory PaymentModel.fromJson(Map<String, dynamic> json) => PaymentModel(
    id:            json['id'] as int,
    amount:        double.tryParse(json['amount']?.toString() ?? '') ?? 0,
    billType:      json['bill_type']?.toString() ?? 'Maintenance',
    paymentMethod: json['payment_method']?.toString() ?? 'cash',
    status:        json['status']?.toString() ?? 'pending',
    paymentDate:   DateTime.tryParse(json['payment_date']?.toString() ?? ''),
    dueDate:       DateTime.tryParse(json['due_date']?.toString() ?? ''),
  );
}

class BillModel {
  final int id;
  final String billNumber;
  final double totalAmount;
  final double paidAmount;
  final double balanceAmount;
  final String status;
  final String month;
  final int year;
  final DateTime? dueDate;
  final String billType;

  const BillModel({
    required this.id, required this.billNumber, required this.totalAmount,
    required this.paidAmount, required this.balanceAmount, required this.status,
    required this.month, required this.year, this.dueDate, required this.billType,
  });

  factory BillModel.fromJson(Map<String, dynamic> json) => BillModel(
    id:            json['id'] as int,
    billNumber:    json['bill_number']?.toString() ?? '',
    totalAmount:   double.tryParse(json['total_amount']?.toString() ?? '') ?? 0,
    paidAmount:    double.parse((json['paid_amount'] ?? 0).toString()),
    balanceAmount: double.parse((json['balance_amount'] ?? 0).toString()),
    status:        json['status']?.toString() ?? 'pending',
    month:         json['month']?.toString() ?? '',
    year:          (json['year'] as num?)?.toInt() ?? DateTime.now().year,
    dueDate:       DateTime.tryParse(json['due_date']?.toString() ?? ''),
    billType:      json['bill_type']?.toString() ?? 'maintenance',
  );

  bool get isPaid    => status == 'paid';
  bool get isOverdue => status == 'overdue';
}
