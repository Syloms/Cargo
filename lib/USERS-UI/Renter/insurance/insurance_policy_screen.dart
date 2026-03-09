// Stub file for InsurancePolicyScreen
import 'package:flutter/material.dart';

class InsurancePolicyScreen extends StatelessWidget {
  final int bookingId;
  final int? userId;

  const InsurancePolicyScreen({
    super.key,
    required this.bookingId,
    this.userId,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Insurance Policy')),
      body: const Center(child: Text('Insurance Policy')),
    );
  }
}
