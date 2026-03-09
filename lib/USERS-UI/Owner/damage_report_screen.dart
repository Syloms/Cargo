// Stub file for OwnerDamageReportScreen
import 'package:flutter/material.dart';

class OwnerDamageReportScreen extends StatelessWidget {
  final int bookingId;
  final String renterName;
  final String? carName;
  final int? ownerId;

  const OwnerDamageReportScreen({
    super.key,
    required this.bookingId,
    required this.renterName,
    this.carName,
    this.ownerId,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Damage Report')),
      body: Center(child: Text('Damage Report for $renterName')),
    );
  }
}
