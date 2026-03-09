// Stub file for LateFeePaymentScreen
import 'package:flutter/material.dart';

class LateFeePaymentScreen extends StatelessWidget {
  final int bookingId;
  final double? rentalAmount;
  final double? lateFee;
  final double? hoursOverdue;
  final String? vehicleName;
  final bool? isRentalPaid;
  final double lateFeeAmount;

  const LateFeePaymentScreen({
    super.key,
    required this.bookingId,
    this.rentalAmount,
    this.lateFee,
    this.hoursOverdue,
    this.vehicleName,
    this.isRentalPaid,
    this.lateFeeAmount = 0,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Late Fee Payment')),
      body: const Center(child: Text('Late Fee Payment')),
    );
  }
}
