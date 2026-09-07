import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';

class StatusBadge extends StatelessWidget {
  final String status;

  const StatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final color = _color(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(AppRadius.full),
      ),
      child: Text(
        _label(status),
        style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600),
      ),
    );
  }

  String _label(String s) {
    switch (s) {
      case 'paid':       return 'Paid';
      case 'pending':    return 'Pending';
      case 'partial':    return 'Partial';
      case 'overdue':    return 'Overdue';
      case 'open':       return 'Open';
      case 'in_progress':return 'In Progress';
      case 'closed':     return 'Closed';
      case 'resolved':   return 'Resolved';
      case 'allowed':    return 'Allowed';
      case 'denied':     return 'Denied';
      case 'entered':    return 'Entered';
      case 'exited':     return 'Exited';
      case 'success':    return 'Success';
      case 'active':     return 'Active';
      default:           return s.replaceAll('_', ' ').toUpperCase();
    }
  }

  Color _color(String s) {
    switch (s) {
      case 'paid':
      case 'allowed':
      case 'success':
      case 'active':
      case 'resolved':   return AppColors.secondary;
      case 'pending':    return AppColors.warning;
      case 'partial':    return AppColors.info;
      case 'overdue':
      case 'denied':
      case 'closed':     return AppColors.error;
      case 'open':       return AppColors.primary;
      case 'in_progress':return AppColors.info;
      case 'entered':    return AppColors.secondary;
      default:           return AppColors.textHint;
    }
  }
}
