// Stub file for MotorcycleFilterScreen
import 'package:flutter/material.dart';

class MotorcycleFilterScreen extends StatelessWidget {
  final Map<String, dynamic>? initialFilters;
  final Map<String, dynamic>? currentFilters;
  final ValueChanged<Map<String, dynamic>>? onFiltersApplied;

  const MotorcycleFilterScreen({
    super.key,
    this.initialFilters,
    this.currentFilters,
    this.onFiltersApplied,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Filter Motorcycles')),
      body: const Center(child: Text('Motorcycle Filters')),
    );
  }
}
