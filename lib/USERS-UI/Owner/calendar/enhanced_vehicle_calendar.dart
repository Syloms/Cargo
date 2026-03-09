// Stub file for EnhancedVehicleCalendar
import 'package:flutter/material.dart';

class EnhancedVehicleCalendar extends StatelessWidget {
  final int ownerId;
  final int vehicleId;
  final String vehicleType;
  final String vehicleName;

  const EnhancedVehicleCalendar({
    super.key,
    required this.ownerId,
    required this.vehicleId,
    required this.vehicleType,
    required this.vehicleName,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('$vehicleName Availability')),
      body: const Center(child: Text('Vehicle Calendar')),
    );
  }
}
