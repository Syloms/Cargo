// Stub file for RefundRequestScreen
import 'package:flutter/material.dart';

class RefundRequestScreen extends StatelessWidget {
  final int bookingId;
  final String? bookingReference;
  final double? totalAmount;
  final String? cancellationDate;
  final String? paymentMethod;
  final String? paymentReference;

  const RefundRequestScreen({
    super.key,
    required this.bookingId,
    this.bookingReference,
    this.totalAmount,
    this.cancellationDate,
    this.paymentMethod,
    this.paymentReference,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Request Refund')),
      body: const Center(child: Text('Refund Request')),
    );
  }
}
