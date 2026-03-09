// Stub file for PaymentStatusTracker
import 'package:flutter/material.dart';

class PaymentStatusTracker extends StatelessWidget {
  final Map<String, dynamic> paymentData;
  final VoidCallback? onViewReceipt;
  final VoidCallback? onRequestRefund;

  const PaymentStatusTracker({
    super.key,
    required this.paymentData,
    this.onViewReceipt,
    this.onRequestRefund,
  });

  @override
  Widget build(BuildContext context) {
    final status = paymentData['payment_status']?.toString() ?? 'Unknown';
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Payment Status: $status'),
          if (onViewReceipt != null) ...[
            const SizedBox(height: 8),
            TextButton(
              onPressed: onViewReceipt,
              child: const Text('View Receipt'),
            ),
          ],
          if (onRequestRefund != null) ...[
            TextButton(
              onPressed: onRequestRefund,
              child: const Text('Request Refund'),
            ),
          ],
        ],
      ),
    );
  }
}
