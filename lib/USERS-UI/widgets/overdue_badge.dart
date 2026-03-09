// Stub file for OverdueBadge and OverdueWarningBanner
import 'package:flutter/material.dart';

class OverdueBadge extends StatelessWidget {
  final double amount;
  final String? label;

  const OverdueBadge({
    super.key,
    required this.amount,
    this.label,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.red,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label ?? 'Overdue: ₱${amount.toStringAsFixed(2)}',
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
    );
  }
}

class OverdueWarningBanner extends StatelessWidget {
  final int? bookingId;
  final int? daysOverdue;
  final double? hoursOverdue;
  final double? lateFee;
  final double? lateFeeAmount;
  final VoidCallback? onPayNow;

  const OverdueWarningBanner({
    super.key,
    this.bookingId,
    this.daysOverdue,
    this.hoursOverdue,
    this.lateFee,
    this.lateFeeAmount,
    this.onPayNow,
  });

  @override
  Widget build(BuildContext context) {
    final fee = lateFee ?? lateFeeAmount ?? 0;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.red.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.red.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.warning_amber_rounded, color: Colors.red.shade700),
              const SizedBox(width: 8),
              Text(
                'Booking Overdue',
                style: TextStyle(fontWeight: FontWeight.bold, color: Colors.red.shade900),
              ),
            ],
          ),
          if (daysOverdue != null && daysOverdue! > 0) ...[
            const SizedBox(height: 8),
            Text('Days overdue: $daysOverdue'),
          ],
          if (fee > 0) ...[
            const SizedBox(height: 4),
            Text('Late fee: ₱${fee.toStringAsFixed(2)}'),
          ],
          if (onPayNow != null) ...[
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: onPayNow,
              style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
              child: const Text('Pay Now', style: TextStyle(color: Colors.white)),
            ),
          ],
        ],
      ),
    );
  }
}
