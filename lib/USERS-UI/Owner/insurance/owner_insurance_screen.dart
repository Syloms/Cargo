// Stub file for OwnerInsuranceScreen
import 'package:flutter/material.dart';

class OwnerInsuranceScreen extends StatelessWidget {
  final int ownerId;

  const OwnerInsuranceScreen({super.key, required this.ownerId});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Insurance Management')),
      body: const Center(child: Text('Insurance Management')),
    );
  }
}
