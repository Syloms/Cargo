// Stub file for OdometerInputScreen
import 'package:flutter/material.dart';

class OdometerInputScreen extends StatelessWidget {
  final int bookingId;
  final String? vehicleName;
  final String? vehicleImage;
  final bool isStartOdometer;
  final int? startOdometer;
  final int? userId;
  final String? userType;

  const OdometerInputScreen({
    super.key,
    required this.bookingId,
    this.vehicleName,
    this.vehicleImage,
    this.isStartOdometer = true,
    this.startOdometer,
    this.userId,
    this.userType,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Odometer Reading')),
      body: const Center(child: Text('Odometer Input')),
    );
  }
}
