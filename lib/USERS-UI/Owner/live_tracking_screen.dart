// Stub file for LiveTrackingScreen
import 'package:flutter/material.dart';

class LiveTrackingScreen extends StatelessWidget {
  final String bookingId;
  final String carName;
  final String renterName;

  const LiveTrackingScreen({
    super.key,
    required this.bookingId,
    required this.carName,
    required this.renterName,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Live Tracking')),
      body: Center(child: Text('Tracking: $carName - $renterName')),
    );
  }
}
