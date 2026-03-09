// Stub file for RenterAvailabilityCalendar
import 'package:flutter/material.dart';

class RenterAvailabilityCalendar extends StatelessWidget {
  final int vehicleId;
  final String vehicleType;
  final String vehicleName;

  const RenterAvailabilityCalendar({
    super.key,
    required this.vehicleId,
    required this.vehicleType,
    required this.vehicleName,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('$vehicleName Availability')),
      body: const Center(child: Text('Availability Calendar')),
    );
  }
}
