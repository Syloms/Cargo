// Stub file for VehicleTypeSelectionScreen
import 'package:flutter/material.dart';

class VehicleTypeSelectionScreen extends StatelessWidget {
  final int ownerId;

  const VehicleTypeSelectionScreen({super.key, required this.ownerId});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Select Vehicle Type')),
      body: const Center(child: Text('Vehicle Type Selection')),
    );
  }
}
