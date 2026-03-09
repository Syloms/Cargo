// Stub file for AnalyticsDashboardScreen
import 'package:flutter/material.dart';

class AnalyticsDashboardScreen extends StatelessWidget {
  final int ownerId;
  final String ownerName;

  const AnalyticsDashboardScreen({
    super.key,
    required this.ownerId,
    required this.ownerName,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Analytics Dashboard')),
      body: const Center(child: Text('Analytics Dashboard')),
    );
  }
}
