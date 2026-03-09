// Stub file for InsuranceSelectionScreen
import 'package:flutter/material.dart';

class InsuranceSelectionScreen extends StatelessWidget {
  final int bookingId;
  final int userId;
  final double rentalAmount;
  final Function(String coverageType, double premium) onInsuranceSelected;

  const InsuranceSelectionScreen({
    super.key,
    required this.bookingId,
    required this.userId,
    required this.rentalAmount,
    required this.onInsuranceSelected,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Select Insurance')),
      body: const Center(child: Text('Insurance Selection')),
    );
  }
}
